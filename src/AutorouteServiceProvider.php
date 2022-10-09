<?php
namespace Eyf\Autoroute;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Routing\Router;

class AutorouteServiceProvider extends ServiceProvider implements
    DeferrableProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . "/../config/autoroute.php",
            "autoroute"
        );

        $this->app->singleton(Autoroute::class, function ($app) {
            $router = $app->make(Router::class);
            $namer = $app->make(RouteNamerInterface::class);

            return new Autoroute($router, $namer, $app->basePath() . "/routes");
        });

        $this->app->bind(RouteNamerInterface::class, RouteNamer::class);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__ . "/../config/autoroute.php" => config_path(
                        "autoroute.php"
                    ),
                ],
                "config"
            );
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return [Autoroute::class];
    }
}
