<?php
namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;
use Tests\AutorouteResolver;
use App\Models\Post;

final class AutorouteResolverTest extends TestCase
{
    use RefreshDatabase;

    protected AutorouteResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new AutorouteResolver();
    }

    /** @test */
    public function create_by_route(): void
    {
        $model = $this->resolver->createByRoute(
            "/users/{user}/posts",
            ["user" => "123"],
            ["title" => "Create", "user_id" => 123]
        );

        $this->assertInstanceOf(Post::class, $model);
        $this->assertEquals("Create", $model->title);
    }

    /** @test */
    public function ability_read(): void
    {
        $this->assertEquals(
            "read",
            $this->resolver->getAbilityName("/users/{user}", "read")
        );
    }

    /** @test */
    public function ability_list(): void
    {
        $this->assertEquals(
            "list",
            $this->resolver->getAbilityName("/user", "list")
        );
    }

    /** @test */
    public function ability_list_deep(): void
    {
        $this->assertEquals(
            "listUser",
            $this->resolver->getAbilityName("/users/{user}/posts", "list")
        );
    }

    /** @test */
    public function ability_list_deeper(): void
    {
        $this->assertEquals(
            "listPost",
            $this->resolver->getAbilityName(
                "/users/{user}/posts/{post}/comments",
                "list"
            )
        );
    }

    /** @test */
    public function list_plural_to_base_name(): void
    {
        $this->assertEquals(
            ["User"],
            $this->resolver->getModelBaseNames("/users")
        );
    }

    /** @test */
    public function list_singular_to_base_name(): void
    {
        $this->assertEquals(
            ["User"],
            $this->resolver->getModelBaseNames("/user")
        );
    }

    /** @test */
    public function read_plural_to_base_name(): void
    {
        $this->assertEquals(
            ["Post"],
            $this->resolver->getModelBaseNames("/posts/{id}")
        );
    }

    /** @test */
    public function read_singular_to_base_name(): void
    {
        $this->assertEquals(
            ["Post"],
            $this->resolver->getModelBaseNames("/post/{id}")
        );
    }

    /** @test */
    public function list_deep_plural_to_base_name(): void
    {
        $this->assertEquals(
            ["User", "Post"],
            $this->resolver->getModelBaseNames("/users/{id}/posts")
        );
    }

    /** @test */
    public function list_deep_mixed_to_base_name(): void
    {
        $this->assertEquals(
            ["User", "Post"],
            $this->resolver->getModelBaseNames("/user/{user}/posts/{post}")
        );
    }

    /** @test */
    public function singular_segment_to_base_name(): void
    {
        $this->assertEquals($this->resolver->getModelBaseName("user"), "User");
    }

    /** @test */
    public function plural_segment_to_base_name(): void
    {
        $this->assertEquals(
            $this->resolver->getModelBaseName("comments"),
            "Comment"
        );
    }

    /** @test */
    public function get_model_names(): void
    {
        $this->assertEquals(
            $this->resolver->getModelNames("users/{id}/comments"),
            ["App\\Models\\User", "App\\Models\\Comment"]
        );
    }

    /** @test */
    public function get_models_namespace(): void
    {
        $this->assertEquals(
            $this->resolver->getModelsNamespace(),
            "App\\Models"
        );
    }
}
