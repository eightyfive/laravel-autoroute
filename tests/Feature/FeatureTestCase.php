<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;
use App\Models\User;

class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $alice;
    protected User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        // Alice
        $this->alice = User::create([
            "name" => "Alice",
            "email" => "alice@example.org",
            "password" => "password",
        ]);

        // Bob
        $this->bob = User::create([
            "name" => "Bob",
            "email" => "bob@example.org",
            "password" => "password",
        ]);
    }
}
