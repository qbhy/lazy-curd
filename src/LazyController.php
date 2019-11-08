<?php

namespace Qbhy\LazyCurd;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        return $this->filterable($request, $this->model())->simplePaginate()->items();
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

    /**
     * @param Request $request
     * @param Builder $builder
     * @param array   $rules
     * @return mixed
     * @throws ValidationException
     */
    public static function filter(Request $request, $builder, array $rules)
    {
        /** @var \Illuminate\Validation\Factory $validator */
        $validator = app(Factory::class);
        $validator->make($request->all(), $rules + [
                'id'           => ['integer', 'min:1', 'nullable'],
                'keyword'      => ['string', 'max:255', 'nullable'],
                'search_range' => ['string', 'max:512', 'required_with:keyword'],
            ])->validate();

        $condition = $request->only(array_keys($rules));

        return $builder->where($condition)->when($request->get('keyword'), function (Builder $builder, $keyword) use ($request, $condition, $rules) {
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