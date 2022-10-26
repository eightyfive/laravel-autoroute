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
    public function get_models_namespace(): void
    {
        $this->assertEquals(
            $this->resolver->getModelsNamespace(),
            "App\\Models"
        );
    }
}
