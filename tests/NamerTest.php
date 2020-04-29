<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Eyf\Autoroute\Namer;

final class NamerTest extends TestCase
{
    public function testGeneratesName(): void
    {
        $namer = new Namer();
        $name = $namer->getRouteName('FooBarController@getUsers');

        $this->assertEquals($name, 'foo_bar.get_users');
    }

    public function testGeneratesNameWithNamespace(): void
    {
        $namer = new Namer();
        $name = $namer->getRouteName('UserController@find', 'App\\Http\\Controllers\\Admin\\Api');

        $this->assertEquals($name, 'admin.api.user.find');
    }

    public function testGeneratesNameWithSubNamespace(): void
    {
        $namer = new Namer();
        $name = $namer->getRouteName('Api\\UserController@find', 'App\\Http\\Controllers\\Admin');

        $this->assertEquals($name, 'admin.api.user.find');
    }
}