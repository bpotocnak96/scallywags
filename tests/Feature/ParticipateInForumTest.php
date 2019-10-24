<?php

namespace Tests\Feature;

use Tests\TestCase;
Use App\Rules\Recaptcha;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ParticipateInForumTest extends TestCase
{
	use DatabaseMigrations;


	/** @test */
	public function an_authenticated_user_can_reply_to_a_thread()
	{
		// given we have an authenticated user,
		$this->signIn();

		// and we have a thread they can read
		$thread = factory('App\Thread')->create();

		// when the user replies to the thread
		$reply = factory('App\ThreadReply')->make();
		$this->post($thread->getPath().'/reply',$reply->toArray());

		$this->assertDatabaseHas('thread_replies',['body'=>$reply->body]);
	}

	/** @test */
	public function an_unauthenticated_user_can_not_reply_to_a_thread()
	{
		$this->expectException('Illuminate\Auth\AuthenticationException');
		$this->post('/forum/foobar/1/reply',[]);
	}

	/** @test*/
	public function an_authenticated_user_can_create_a_thread() {
		// given we have a user who is signed in
		$this->signIn();

		$thread = factory('App\Thread')->make();
		$thread= $thread->toArray();
		$thread['g-recaptcha-response'] = 'test';
		$this->withExceptionHandling()->post('/forum/',$thread);

		// the reply should be visible on the thread page
		$response = $this->get('/forum/');
		$response->assertSee($thread['title']);
		$response->assertSee($thread['body']);
	}

	/** @test */
	public function a_thread_requires_a_title() {
		$this->publishThread(['title'=>null])->assertSessionHasErrors('title');
	}
	/** @test */
	public function a_thread_requires_a_body() {
		$this->publishThread(['body'=>null])->assertSessionHasErrors('body');
	}
	/** @test */
	public function a_thread_requires_a_valid_category() {
		factory('App\Category',5)->create();
		$this->publishThread(['category_id'=>null])->assertSessionHasErrors('category_id');
		$this->publishThread(['category_id'=>999999])->assertSessionHasErrors('category_id');
	}

	/** @test */
	public function a_thread_must_have_a_unique_slug() {
		$this->publishThread(['title'=>'my title']);
		$this->publishThread(['title'=>'my title']);
		$this->assertCount(2,\App\Thread::all());
		$this->assertCount(1,\App\Thread::where(['slug'=>'my-title'])->get());
	}

	/** @test */
	public function a_user_can_edit_their_thread() {
		$this->signIn();
		$thread = factory('App\Thread')->create(['user_id'=>auth()->id()]);
		$this->json('patch',$thread->getPath(),[
			'title'=>'foobar',
			'body'=>'foobar'
		]);
		$this->assertEquals($thread->fresh()->title,'foobar');
	}

	/** @test */
	public function an_unauth_user_cannot_edit_a_thread() {
		$this->signIn();
		$thread = factory('App\Thread')->create();
		$this->withExceptionHandling()->json('patch',$thread->getPath())->assertStatus(403);
		$this->assertNotEquals($thread->fresh()->title,'foobar');
	}

	/** @test */
	public function a_guest_cannot_edit_a_thread() {
		$thread = factory('App\Thread')->create();
		$this->withExceptionHandling()->json('patch',$thread->getPath(),['title'=>'foobar'])->assertStatus(401);
	}

	/** @test */
	public function a_locked_thread_cannot_be_updated() {
		$this->signIn();
		$thread = factory('App\Thread')->create(['user_id'=>auth()->id()]);
		$thread->lock();
		$this->withExceptionHandling()->json('patch',$thread->getPath())->assertStatus(403);
		$this->assertNotEquals($thread->fresh()->title,'foobar');
	}

	/** @test */
	public function an_admin_can_update_any_thread() {
		$user = factory('App\User')->create(['is_admin'=>1]);
		$this->signIn($user);
		auth()->user()->update(['is_admin'=>1]);
		$thread = factory('App\Thread')->create();
		$thread->lock();
		$this->json('patch',$thread->getPath(),[
			'title'=>'foobar',
			'body'=>'foobar'
		]);
		$this->assertEquals($thread->fresh()->title,'foobar');
	}

	/** @test */
	public function a_reply_requires_a_body() {
		$this->publishReply(['body'=>null])->assertStatus(422);
	}

	/** @test */
	public function a_thread_requires_a_recaptcha() {

		unset(app()[Recaptcha::class]);

		$this->signIn();
		$thread = factory('App\Thread')->make();
		$thread= $thread->toArray();
		$this->withExceptionHandling()->post('/forum/',$thread)->assertSessionHasErrors('g-recaptcha-response');
	}

	/** @test */
	public function a_user_can_delete_their_thread() {
		$this->signIn();
		$thread = factory('App\Thread')->create(['user_id'=>auth()->id()]);
		$reply = factory('App\ThreadReply')->create(['thread_id'=>$thread->id]);
		$this->json('DELETE',$thread->getPath());
		$this->assertDatabaseMissing('threads',['id'=>$thread->id]);
		$this->assertDatabaseMissing('thread_replies',['id'=>$reply->id]);
		$this->assertDatabaseMissing('forum_activities',[
			'subject_id'=>$thread->id,
			'subject_type'=>get_class($thread)
		]);
		$this->assertDatabaseMissing('forum_activities',[
			'subject_id'=>$reply->id,
			'subject_type'=>get_class($reply)
		]);
	}
	/** @test */
	public function a_user_can_delete_their_reply() {
		$this->signIn();
		$reply = factory('App\ThreadReply')->create(['user_id'=>auth()->id()]);
		// dd($reply);
		$this->json('DELETE','/forum/reply/'.$reply->id);
		$this->assertDatabaseMissing('thread_replies',['id'=>$reply->id]);
	}

	/** @test */
	public function a_guest_can_not_delete_a_thread() {
		$this->withExceptionHandling()->json('DELETE','/forum/foobar/1')->assertStatus(401);
	}
	/** @test */
	public function a_guest_can_not_delete_a_reply() {
		$this->withExceptionHandling()->json('DELETE','/forum/reply/1')->assertStatus(401);
	}

	/** @test */
	public function a_user_can_not_delete_another_users_thread() {
		$this->signIn();
		$thread = factory('App\Thread')->create();
		$this->withExceptionHandling()->json('DELETE',$thread->getPath())->assertStatus(403);
		$this->assertDatabasehas('threads',['id'=>$thread->id]);
	}

	/** @test */
	public function a_user_can_not_delete_another_users_reply() {
		$this->signIn();
		$reply = factory('App\ThreadReply')->create();
		$this->withExceptionHandling()->json('DELETE','/forum/reply/'.$reply->id);
		$this->assertDatabaseMissing('thread_replies',['id'=>$reply->id,'body'=>'Reply deleted']);
	}

	/** @test */
	public function a_user_can_edit_their_reply() {
		$this->signIn();
		$reply = factory('App\ThreadReply')->create(['user_id'=>auth()->id()]);
		$response = $this->patch('/forum/reply/'.$reply->id,['body'=>'foobar']);
		$this->assertDatabaseHas('thread_replies',['id'=>$reply->id,'body'=>'foobar']);
	}

	/** @test */
	public function a_user_can_not_edit_someone_elses_reply() {
		$this->signIn();
		$reply = factory('App\ThreadReply')->create(['user_id'=>auth()->id()+1]);
		$response = $this->withExceptionHandling()->patch('/forum/reply/'.$reply->id,['body'=>'foobar']);
		$this->assertDatabaseMissing('thread_replies',['id'=>$reply->id,'body'=>'foobar']);

	}

	/** @test */
	public function a_user_can_not_add_a_reply_if_spam_is_detected() {

		// given we have an authenticated user,
		$this->signIn();

		// and we have a thread they can read
		$thread = factory('App\Thread')->create();

		// when the user replies to the thread
		$reply = factory('App\ThreadReply')->make([
			'body'=>'Yahoo Customer Support'
		]);
		// $this->expectException();
		$this->withExceptionHandling()->post($thread->getPath().'/reply',$reply->toArray());

		$this->assertDatabaseMissing('thread_replies',['body'=>$reply->body]);
	}

	/** @test */
	public function user_can_only_reply_once_per_30_seconds() {
		$this->signIn();
		$thread = factory('App\Thread')->create();
		$reply1 = factory('App\ThreadReply')->make();
		$reply2 = factory('App\ThreadReply')->make();
		$this->post($thread->getPath().'/reply',$reply1->toArray());
		$response = $this->post($thread->getPath().'/reply',$reply2->toArray());
		$this->assertDatabaseHas('thread_replies',['body'=>$reply1->body]);
		$this->assertDatabaseMissing('thread_replies',['body'=>$reply2->body]);
	}

	/** @test */
	public function a_user_must_confirm_their_email_before_creating_a_thread() {
		$this->signIn();
		auth()->user()->update(['email_verified_at'=>null]);
		$this->withExceptionHandling()->get('/forum/new')
		->assertRedirect('/email/verify');

		$thread = factory('App\Thread')->make();
		$this->withExceptionHandling()->post('/forum/',$thread->toArray())
		->assertRedirect('/email/verify');

	}

	/** @test */
	public function a_user_can_mark_the_best_reply_for_their_thread() {
		$this->signIn();
		$thread = factory('App\Thread')->create(['user_id'=>auth()->id()]);
		$reply = factory('App\ThreadReply')->create(['thread_id'=>$thread->id]);
		$this->post('/forum/reply/'.$reply->id.'/best');
		$this->assertTrue($reply->refresh()->isBest());
	}

	/** @test */
	public function a_user_can_not_mark_the_best_reply_for_someone_elses_thread() {
		$this->signIn();
		$thread = factory('App\Thread')->create();
		$reply = factory('App\ThreadReply')->create(['thread_id'=>$thread->id]);
		$this->withExceptionHandling()->post('/forum/reply/'.$reply->id.'/best');
		$this->assertFalse($reply->refresh()->isBest());
	}

	/** @test */
	public function only_one_reply_can_be_best() {
		$this->signIn();
		$thread = factory('App\Thread')->create(['user_id'=>auth()->id()]);
		$replies = factory('App\ThreadReply',2)->create(['thread_id'=>$thread->id]);
		$this->post('/forum/reply/'.$replies[0]->id.'/best');
		$this->post('/forum/reply/'.$replies[1]->id.'/best');
		$this->assertTrue($replies[0]->isBest());
		$this->assertFalse($replies[1]->isBest());
	}

	/** @test */
	public function if_a_best_reply_is_deleted_the_thread_is_updated() {
		$this->signIn();
		$thread = factory('App\Thread')->create();
		$reply = factory('App\ThreadReply')->create(['thread_id'=>$thread->id]);
		$reply->markAsBest();
		$reply->delete();
		$this->assertEquals(null,$thread->refresh()->best_reply_id);
	}

	/** @test */
	public function a_thread_body_is_automatically_sanatized() {
		$this->signIn();
		$thread = factory('App\Thread')->create([
			'body'=>'<h3>HELLO <a href="#" onClick="foobar">click me</a><script>foobar</script></h3>'
		]);
		$response = $this->get($thread->getPath());
		$response->assertSee('<h3>HELLO <a href="#">click me</a></h3>');
	}

	/** @test */
	public function a_reply_body_is_automatically_sanatized() {
		$this->signIn();
		$thread = factory('App\Thread')->create();
		$reply = factory('App\ThreadReply')->create([
			'body'=>'<h3>HELLO <a href="#" onClick="foobar">click me</a><script>foobar</script></h3>',
			'thread_id'=>$thread->id
		]);
		$response = $this->get($thread->getPath()."/replies");
		$response->assertSee('<h3>HELLO');
	}








	public function publishThread($overrides=[]) {
		$this->signIn();
		$thread = factory('App\Thread')->make($overrides);
		$thread= $thread->toArray();
		$thread['g-recaptcha-response'] = 'test';
		return $this->withExceptionHandling()->post('/forum/',$thread);
	}
	public function publishReply($overrides=[]) {
		$this->signIn();
		$thread = factory('App\Thread')->create();
		$reply = factory('App\ThreadReply')->make($overrides);
		return $this->withExceptionHandling()->post($thread->getPath().'/reply/',$reply->toArray());
	}


}
