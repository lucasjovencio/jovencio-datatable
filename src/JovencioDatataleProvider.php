<?php

namespace Jovencio\DataTable;

use Illuminate\Support\ServiceProvider;

class JovencioDatataleProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(DataTableQueryFactory::class, function ($app) {
            return new DataTableQueryFactory($app['request']);
        });
    }

    public function boot()
    {
    }
}