<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Eyf\Autoroute\RouteNamer;

final class RouteNamerTest extends TestCase
{
    public function testName(): void
    {
        $namer = new RouteNamer();
        $name = $namer->getRouteName('FooBarController@getUsers');

        $this->assertEquals($name, 'foo_bar.get_users');
    }

    public function testNamespace(): void
    {
        $namer = new RouteNamer();
        $name = $namer->getRouteName(
            'UserController@find',
            'App\\Http\\Controllers\\Admin\\Api'
        );

        $this->assertEquals($name, 'admin.api.user.find');
    }

    public function testSubNamespace(): void
    {
        $namer = new RouteNamer();
        $name = $namer->getRouteName(
            'Api\\UserController@find',
            'App\\Http\\Controllers\\Admin'
        );

        $this->assertEquals($name, 'admin.api.user.find');
    }

    public function testUses(): void
    {
        $namer = new RouteNamer();
        $uses = $namer->getUses('api.foo.bar.user.find');

        $this->assertEquals($uses, 'Api\\Foo\\Bar\\UserController@find');
    }
}
