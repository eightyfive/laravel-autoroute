<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Events\Dispatcher;

use Tests\TestCase;
use Tests\Autoroute;
use App\Models\User;

class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected Autoroute $autoroute;

    protected User $alice;
    protected User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->autoroute = new Autoroute(
            new Router(new Dispatcher()),
            __DIR__ . "/../../routes"
        );

        $this->autoroute->createGroup("api.yaml");

        // Alice
        if (!isset($this->alice)) {
            $this->alice = User::create([
                "name" => "Alice",
                "email" => "alice@example.org",
                "password" => "password",
            ]);
        }

        // Bob
        if (!isset($this->bob)) {
            $this->bob = User::create([
                "name" => "Bob",
                "email" => "bob@example.org",
                "password" => "password",
            ]);
        }
    }
}
