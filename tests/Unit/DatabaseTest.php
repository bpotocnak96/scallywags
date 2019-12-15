<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $this->assertTrue(true);
    }

    /** @test */
    public function is_there_any_admin()
    {

        $this->assertDatabaseHas('users', [
            'is_admin' => '1'
        ]);
    }
    /** @test */
    public function is_there_any_user()
    {

        $this->assertDatabaseHas('users', [
        ]);
    }
    /** @test */
    public function is_there_any_user_no_admin()
    {
        $this->assertDatabaseHas('users', [
            'is_admin' => '1'
        ]);
    }
    /** @test */
    public function is_there_main_admin()
    {
        $this->assertDatabaseHas('users', [
            'email' => 'bpotocnak@btect.sk',
            'is_admin' => '1'
        ]);
    }
    /** @test */

    public function is_there_any_category()
    {
        $this->assertDatabaseHas('categories', [

            ]);
    }


}
