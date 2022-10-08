<?php
namespace Eyf\Autoroute;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Routing\Router;
use Illuminate\Contracts\Auth\Access\Gate;

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
        $this->app->singleton(Autoroute::class, function ($app) {
            $router = $app->make(Router::class);
            $gate = $app->make(Gate::class);
            $namer = $app->make(RouteNamerInterface::class);

            return new Autoroute(
                $router,
                $gate,
                $namer,
                $app->basePath() . "/routes"
            );
        });

        $this->app->bind(RouteNamerInterface::class, RouteNamer::class);
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
