<?php
namespace Tests;
use Orchestra\Testbench\TestCase as Test;
use Laravel\Sanctum\SanctumServiceProvider;
use Illuminate\Support\Facades\Route;

use Eyf\Autoroute\Autoroute;
use Eyf\Autoroute\AutorouteServiceProvider;

use App\Models\Post;
use App\Models\User;

class TestCase extends Test
{
    protected function getPackageProviders($app)
    {
        return [SanctumServiceProvider::class, AutorouteServiceProvider::class];
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

        Route::model("user_id", User::class);
        Route::model("post_id", Post::class);

        // vendor/orchestra/testbench-core/laravel/public/../../../../../public/api.yaml
        $autoroute->createGroup("../../../../../public/api.yaml", [
            "prefix" => "api",
            "namespace" => "App\Http\Controllers\Api",
            "middleware" => ["api"],
        ]);
    }
}
