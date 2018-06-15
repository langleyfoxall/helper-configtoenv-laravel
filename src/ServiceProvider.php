<?php

namespace LangleyFoxall\ConfigToEnv;

use LangleyFoxall\ConfigToEnv\Commands\ConfigToEnvCommand;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ConfigToEnvCommand::class
            ]);
        }
    }
}