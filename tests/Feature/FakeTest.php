<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\ResetPassword;
use App\User;
use App\Category;
use App\ThreadReply;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class FakeTest extends TestCase
{
  //  use RefreshDatabase;
    /**
     *
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $this->assertTrue(true);
    }

    public function test_create_user_and_thread_and_reply()
    {
        $user = factory(\App\User::class)->create();
        $this->assertTrue($user->id != null);
        $category = Category::get()->first();
        $this->assertTrue($category != null);

        $thread =  factory('App\Thread')->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->assertTrue($thread->id != null);

        $threadreply = factory(\App\ThreadReply::class)->create([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
        ]);

        $this->assertTrue($threadreply!=null);

    }
}
