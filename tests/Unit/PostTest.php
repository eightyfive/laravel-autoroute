<?php
namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;
use App\Models\User;
use App\Models\Post;

class PostTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    function creates_post()
    {
        $user = User::factory()->create();

        $post = Post::create([
            "title" => "Lorem ipsum",
            "user_id" => $user->id,
        ]);

        $this->assertEquals($post->title, "Lorem ipsum");
        $this->assertEquals($post->user_id, $user->id);
    }
}
