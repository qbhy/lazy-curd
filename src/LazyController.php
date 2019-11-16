<?php

namespace Qbhy\LazyCurd;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

abstract class LazyController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function ok($data = null, $code = 0, string $msg = 'ok')
    {
        return new JsonResponse([
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
        ]);
    }

    /**
     * @return Builder|Model
     */
    abstract public function model();

    abstract public function rules(): array;

    public function filterData(Request $request, array $rules = null)
    {
        $rules = $rules ?? $this->rules();
        $this->validate($request, $rules);
        return $request->only(array_keys($rules));
    }

    public function create(Request $request)
    {
        return $this->model()->create($this->filterData($request));
    }

    public function destroy($id)
    {
        return $this->model()->findOrFail($id)->delete();
    }

    public function update(Request $request, $id)
    {
        $model = $this->model()->findOrFail($id);
        $model->update($this->filterData($request));

        return $model;
    }

    public function index(Request $request)
    {
        return $this->filterable($request, $this->model())->simplePaginate($request->get('per_page', 16))->items();
    }

    /**
     * @param Request    $request
     * @param null       $builder
     * @param array|null $rules
     * @return Builder|Model
     * @throws \Illuminate\Validation\ValidationException
     */
    public function filterable(Request $request, $builder = null, array $rules = null)
    {
        $rules   = $rules ?? json_decode(str_replace('required', 'nullable', json_encode($this->rules())), true);
        $builder = $builder ?? $this->model();

        return self::filter($request, $builder, $rules);
    }

    public static function filterableRules(array $rules): array
    {
        $condition = [];

        foreach ($rules as $column => $item) {
            $condition[$column] = ['nullable'];
        }

        return $condition;
    }

    /**
     * @param Request $request
     * @param Builder $builder
     * @param array   $rules
     * @return Builder
     * @throws ValidationException
     */
    public static function filter(Request $request, $builder, array $rules)
    {
        validator($request->all(), self::filterableRules($rules) + [
                'id'           => ['nullable'],
                'keyword'      => ['string', 'max:255', 'nullable'],
                'search_range' => ['string', 'max:512', 'required_with:keyword'],
            ])->validate();

        $condition = $request->only(array_keys($rules));
        return $builder
            ->where(function (Builder $builder) use ($condition) {
                foreach ($condition as $column => $value) {
                    $rules = explode(',', $value);
                    if (count($rules) === 1) {
                        $builder->where($column, $rules[0]);
                    } else {
                        switch ($rules[0]) {
                            case '<>':
                            case '>':
                            case '>=':
                            case '<':
                            case '<=':
                                $builder->where($column, $rules[0], $rules[1]);
                                break;
                            case 'in':
                                $builder->whereIn($column, Arr::except($rules, [0]));
                                break;
                            case 'not-in':
                                $builder->whereNotIn($column, Arr::except($rules, [0]));
                                break;
                            case 'is-null':
                                $builder->whereNull($column, $rules[1]);
                                break;
                            case 'not-null':
                                $builder->whereNotNull($column, $rules[1]);
                                break;
                        }
                    }
                }
            })
            ->when($request->get('keyword'), function (Builder $builder, $keyword) use ($request, $condition, $rules) {
                $range = explode(',', $request->get('search_range'));
                $builder->where(function (Builder $builder) use ($keyword, $range, $condition, $rules) {
                    foreach ($range as $item) {
                        if (!array_key_exists($item, $condition) && isset($rules[$item])) {
                            $builder->orWhere($item, 'like', "%{$keyword}%");
                        }
                    }
                    return $builder;
                });
            });
    }
}
