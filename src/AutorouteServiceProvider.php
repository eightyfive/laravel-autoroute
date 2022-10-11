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

        $this->app->bind(
            AutorouteResolverInterface::class,
            AutorouteResolver::class
        );

        $this->app->singleton(Autoroute::class, function ($app) {
            $config = $app["config"]->get("autoroute");

            return new Autoroute(
                $app->make(Router::class),
                $app->make(AutorouteResolverInterface::class),
                $app->basePath($config["dir"])
            );
        });
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
