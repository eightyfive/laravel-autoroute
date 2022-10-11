<?php
namespace Tests;

use Eyf\Autoroute\Autoroute;
use Eyf\Autoroute\AutorouteServiceProvider;
use Orchestra\Testbench\TestCase as Test;

class TestCase extends Test
{
    protected function setUp(): void
    {
        parent::setUp();
    }

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

        $autoroute = $app->make(Autoroute::class);

        // Test "app path" is:
        // vendor/orchestra/testbench-core/laravel/
        $autoroute->createGroup("../../../../../public/api.yaml");
    }
}
