<?php
namespace Eyf;


use Illuminate\Support\ServiceProvider;

class AutorouteServiceProvider extends ServiceProvider
{

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/autoroute.php',
            'autoroute'
        );

        $this->app->singleton([Autoroute::class => 'autoroute'], function ($app) {
            $config = $app['config']->get('autoroute');
            return new Autoroute(
                $app['router'],
                $config['routes'],
                $config['constraints'],
                $config['options']
            );
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/autoroute.php' => config_path('autoroute.php')
        ]);
    }

    /**
     * @return string[]
     */
    public function provides()
    {
        return ['autoroute'];
    }
}
