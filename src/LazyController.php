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
        $builder = $builder ?? $this->model();
        $rules   = json_decode(str_replace('required', 'nullable', json_encode($rules ?? $this->rules())), true);

        $this->validate($request, $rules + [
                'keyword'      => ['string', 'max:255', 'nullable'],
                'search_range' => ['string', 'max:512', 'required_with:keyword'],
            ]);

        $condition = $request->only(array_keys($rules));

        return $builder->where($condition)->when($request->get('keyword'), function (Builder $builder, $keyword) use ($request, $condition) {
            $range = explode(',', $request->get('search_range'));
            $builder->where(function (Builder $builder) use ($keyword, $range, $condition) {
                foreach ($range as $item) {
                    if (!array_key_exists($item, $condition)) {
                        $builder->orWhere($item, 'like', "%{$keyword}%");
                    }
                }
                return $builder;
            });
        });
    }
}