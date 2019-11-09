<?php

namespace Qbhy\LazyCurd;

use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application as LumenApplication;

class LaravelServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            LazyMakeCommand::class,
        ]);
    }

}
