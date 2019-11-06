<?php

namespace Qbhy\LazyCurd;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LazyMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lazy:make {model}
        {--namespace=}
        {--excludes=}
        ';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '懒人模块生成器';

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws
     */
    public function handle()
    {
        /** @var Model $model */
        $model     = $this->laravel->make(str_replace('/', '\\', $this->argument('model')));
        $namespace = str_replace('/', '\\', $this->option('namespace') ?: 'App\\Http\\Controllers');
        $excludes  = $this->option('excludes') ?: 'id,created_at,updated_at';

        $rules          = $this->buildPhpScriptStr($this->buildRules($model, explode(',', $excludes)));
        $modelName      = get_class($model);
        $controllerName = Arr::last(explode('\\', $modelName)) . 'Controller';
        $controllerPath = base_path(Str::camel(Str::snake(str_replace("\\", '/', $namespace . '/' . $controllerName . '.php'))));

        if (is_file($controllerPath)) {
            $this->error("{$controllerName} exists !");
            return;
        }

        app('files')->put($controllerPath, $this->buildController($rules, $modelName, $namespace));
        $this->info("{$controllerName} 控制器生成成功！");
    }

    /**
     * @param $rules
     * @param $model
     * @param $namespace
     * @return string
     */
    private function buildController($rules, $model, $namespace)
    {
        $modelName = Arr::last(explode('\\', $model));
        $snakeName = Str::snake($modelName);
        return str_replace(
            ['{rules}', '{model}', '{modelName}', '{snakeName}', '{namespace}'],
            [$rules, $model, $modelName, $snakeName, $namespace],
            file_get_contents(__DIR__ . '/../stubs/controller.stub')
        );
    }

    private function buildPhpScriptStr(array $rules)
    {
        $str = '[';

        foreach ($rules as $key => $rule) {
            $str .= PHP_EOL . "            '{$key}' => [";
            foreach ($rule as $item) {
                $str .= "'{$item}',";
            }
            $str .= '],';
        }

        return $str . PHP_EOL . '        ]';
    }

    /**
     * @param Model $model
     * @param array $excludes
     * @return array
     * @throws
     */
    public function buildRules($model, array $excludes)
    {
        $rules  = [];
        $table  = $model->getConnection()->getTablePrefix() . $model->getTable();
        $schema = $model->getConnection()->getDoctrineSchemaManager($table);

        $database = null;
        if (strpos($table, '.')) {
            list($database, $table) = explode('.', $table);
        }

        $columns = $schema->listTableColumns($table, $database);

        if ($columns) {
            foreach ($columns as $column) {
                $name = $column->getName();
                if (in_array($name, $excludes)) {
                    continue;
                }
                if ($model->isFillable($name)) {
                    $rule = [];
                    if (in_array($name, $model->getDates())) {
                        $rule[] = 'date_format:Y-m-d H:i:s';
                    } else {
                        $type = $column->getType()->getName();
                        switch ($type) {
                            case 'string':
                            case 'text':
                            case 'guid':
                                $rule[] = 'string';
                                $rule[] = 'max:' . $column->getLength();
                                break;
                            case 'datetimetz':
                            case 'datetime':
                                $rule[] = 'date_format:Y-m-d H:i:s';
                                break;
                            case 'date':
                                $rule[] = 'date_format:Y-m-d';
                                break;
                            case 'time':
                                $rule[] = 'date_format:H:i:s';
                                break;
                            case 'integer':
                            case 'bigint':
                            case 'smallint':
                                $rule[] = 'integer';
                                $column->getUnsigned() ? ($rule[] = Str::contains($name, '_id') ? 'min:1' : 'min:0') : null;
                                break;
                            case 'boolean':
                                $rule[] = 'boolean';
                                break;
                            case 'decimal':
                            case 'float':
                                $rule[] = 'float';
                                $column->getUnsigned() ? $rule[] = 'min:0' : null;
                                break;
                        }
                    }

                    $rule[]       = $column->getNotnull() ? 'required' : 'nullable';
                    $rules[$name] = $rule;
                }
            }

        }

        return $rules;
    }
}
