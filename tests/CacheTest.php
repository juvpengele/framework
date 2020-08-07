<?php

namespace Bow\Test;

use \Bow\Cache\Cache;

class CacheTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        parent::setUp();

        Cache::confirgure(__DIR__.'/data/cache/bow');
    }



    public function testCreateCache()
    {
        $r = Cache::add('name', 'Dakia');

        $this->assertEquals($r, true);
    }

    public function testGetCache()
    {
        $this->assertEquals(Cache::get('name'), 'Dakia');
    }

    public function testAddWithCallbackCache()
    {
        $r = Cache::add('lastname', function () {
            return 'Franck';
        });

        $r = $r && Cache::add('age', function () {
                return 25;
        }, 20000);

        $this->assertEquals($r, true);
    }

    public function testGetCallbackCache()
    {
        $this->assertEquals(Cache::get('lastname'), 'Franck');

        $this->assertEquals(Cache::get('age'), 25);
    }

    public function testAddArrayCache()
    {
        $r = Cache::add('address', [
            'tel' => "49929598",
            'city' => "Abidjan",
            'country' => "Cote d'ivoire"
        ]);

        $this->assertEquals($r, true);
    }

    public function testGetArrayCache()
    {
        $r = Cache::get('address');

        $this->assertEquals(true, is_array($r));

        $this->assertEquals(count($r), 3);

        $this->assertArrayHasKey('tel', $r);

        $this->assertArrayHasKey('city', $r);

        $this->assertArrayHasKey('country', $r);
    }

    public function testHas()
    {
        $r1 = Cache::has('name');

        $r2 = Cache::has('jobs');

        $this->assertEquals(true, $r1);

        $this->assertEquals(false, $r2);
    }

    public function testForget()
    {
        Cache::forget('address');

        $r1 = Cache::forget('name');

        $this->assertEquals(true, $r1);

        $this->assertEquals(Cache::get('name', false), false);
    }

    public function testForgetEmpty()
    {
        $r1 = Cache::forget('name');

        $this->assertEquals(false, $r1);
    }

    public function testTimeOfEmpty()
    {
        $r1 = Cache::timeOf('lastname');

        $this->assertEquals('+', $r1);
    }

    public function testTimeOf2Empty()
    {
        $r1 = Cache::timeOf('address');

        $this->assertEquals(false, $r1);
    }

    public function testTimeOf3Empty()
    {
        $r1 = Cache::timeOf('age');

        $this->assertEquals(is_int($r1), true);
    }

    public function testCanAddManyDataAtTheSameTimeInTheCache()
    {
        $passes = Cache::addMany(['name' => 'Doe', 'first_name' => 'John']);

        $this->assertEquals($passes, true);
    }

    public function testCanRetrieveMultipleCacheStored()
    {
        Cache::addMany(['name' => 'Doe', 'first_name' => 'John']);

        $this->assertEquals(Cache::get('name'), 'Doe');
        $this->assertEquals(Cache::get('first_name'), 'John');
    }

    public function testClearCache()
    {
        Cache::addMany(['name' => 'Doe', 'first_name' => 'John']);

        $this->assertEquals(Cache::get('first_name'), 'John');
        $this->assertEquals(Cache::get('name'), 'Doe');

        Cache::clear();

        $this->assertNull(Cache::get('name'));
        $this->assertNull(Cache::get('first_name'));
    }
}
