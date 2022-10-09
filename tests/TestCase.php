<?php
namespace Tests;

use Eyf\Autoroute\AutorouteServiceProvider;
use Orchestra\Testbench\TestCase as Test;

class TestCase extends Test
{
    public function setUp(): void
    {
        parent::setUp();
        // additional setup
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
    }
}
