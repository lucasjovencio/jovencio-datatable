<?php

namespace Jovencio\DataTable;

use Illuminate\Support\ServiceProvider;

class SeuPacoteServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(JovencioDataTableResponse::class, function ($app) {
            return new JovencioDataTableResponse($app['request']);
        });
    }

    public function boot()
    {
    }
}