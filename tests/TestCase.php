<?php
namespace Tests;

use Orchestra\Testbench\TestCase as Test;

use Eyf\Autoroute\Autoroute;
use Eyf\Autoroute\AutorouteServiceProvider;

class TestCase extends Test
{
    protected function getPackageProviders($app)
    {
        return [AutorouteServiceProvider::class];
    }

    public function getEnvironmentSetUp($app)
    {
        include_once __DIR__ . "/../database/migrations/create_users_table.php";
        include_once __DIR__ . "/../database/migrations/create_posts_table.php";

        (new \CreateUsersTable())->up();
        (new \CreatePostsTable())->up();

        // Register routes for Testbench app
        $autoroute = $app->make(Autoroute::class);

        // Testbench app "base path" is:
        // vendor/orchestra/testbench-core/laravel

        // Default Autoroute `dir` is:
        // `base_path('public')`

        // vendor/orchestra/testbench-core/laravel/public/../../../../../public/api.yaml
        $autoroute->createGroup("../../../../../public/api.yaml");
    }
}
