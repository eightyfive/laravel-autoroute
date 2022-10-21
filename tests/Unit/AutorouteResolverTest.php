<?php
namespace Tests\Unit;

use Tests\TestCase;
use Tests\AutorouteResolver;

final class AutorouteResolverTest extends TestCase
{
    protected AutorouteResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new AutorouteResolver();
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
            $this->resolver->getModelBaseNames("/posts/{post_id}")
        );
    }

    /** @test */
    public function read_singular_to_base_name(): void
    {
        $this->assertEquals(
            ["Post"],
            $this->resolver->getModelBaseNames("/post/{post_id}")
        );
    }

    /** @test */
    public function list_deep_plural_to_base_name(): void
    {
        $this->assertEquals(
            ["User", "Post"],
            $this->resolver->getModelBaseNames("/users/{user_id}/posts")
        );
    }

    /** @test */
    public function list_deep_mixed_to_base_name(): void
    {
        $this->assertEquals(
            ["User", "Post"],
            $this->resolver->getModelBaseNames(
                "/user/{user_id}/posts/{post_id}"
            )
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
        $this->assertEquals($this->resolver->getModelBaseName("posts"), "Post");
    }

    /** @test */
    public function get_model_names(): void
    {
        $this->assertEquals(
            $this->resolver->getModelNames("users/{user_id}/posts"),
            ["App\\Models\\User", "App\\Models\\Post"]
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

    /** @test */
    public function parameter_names_0(): void
    {
        $this->assertEquals([], $this->resolver->getParameterNames("/users"));

        $this->assertEquals(
            null,
            $this->resolver->getParentParameterName("/users")
        );
    }

    /** @test */
    public function parameter_names_1_1(): void
    {
        $uri = "/users/{user_id}";

        $this->assertEquals(
            ["user_id"],
            $this->resolver->getParameterNames($uri)
        );

        $this->assertEquals(
            null,
            $this->resolver->getParentParameterName($uri)
        );
    }

    /** @test */
    public function parameter_names_1_2(): void
    {
        $uri = "/users/{user_id}/posts";

        $this->assertEquals(
            ["user_id"],
            $this->resolver->getParameterNames($uri)
        );

        $this->assertEquals(
            "user_id",
            $this->resolver->getParentParameterName($uri)
        );
    }

    /** @test */
    public function parameter_names_2_2(): void
    {
        $uri = "/users/{user_id}/posts/{post_id}";

        $this->assertEquals(
            ["user_id", "post_id"],
            $this->resolver->getParameterNames($uri)
        );

        $this->assertEquals(
            null,
            $this->resolver->getParentParameterName($uri)
        );
    }

    /** @test */
    public function parameter_names_2_3(): void
    {
        $uri = "/users/{user_id}/posts/{post_id}/posts";

        $this->assertEquals(
            ["user_id", "post_id"],
            $this->resolver->getParameterNames($uri)
        );

        $this->assertEquals(
            "post_id",
            $this->resolver->getParentParameterName($uri, [
                "user_id" => "123",
                "post_id" => "456",
            ])
        );
    }
}
