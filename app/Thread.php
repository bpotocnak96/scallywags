<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Events\ThreadReplyCreated;

class Thread extends Model
{

	use RecordsActivity;
	use Favourable;

	protected $guarded = ['g-recaptcha-response'];
	protected $appends = array('is_subscribed','is_favourited');

	protected static function boot() {
		parent::boot();
		static::addGlobalScope('replies_count', function ($builder) {
			$builder->withCount('replies');
		});
		static::addGlobalScope('category', function ($builder) {
			$builder->with('category');
		});
		static::addGlobalScope('user', function ($builder) {
			$builder->with('user');
		});
		static::deleting(function($thread) {
			$thread->replies->each->delete();
			$thread->user->unaward('deleted_thread');
		});
		static::created(function($thread) {
			$thread->user->award('created_thread');
		});

	}

	public function getRouteKeyName() {
		return "slug";
	}

	public function replies() {
		return $this->hasMany(ThreadReply::class);
	}
	public function user() {
		return $this->belongsTo(User::class);
	}
	public function category() {
		return $this->belongsTo(Category::class);
	}
	public function subscriptions() {
		return $this->hasMany(ThreadSubscription::class);
	}

	public function addReply($reply) {
		$reply = $this->replies()->create($reply);
		event(new ThreadReplyCreated($reply));
		$this->user->award('received_reply');
		$reply->user->award('created_reply');
		return $reply;
	}
	public function subscribe() {
		$attributes = ['user_id' => auth()->id()];
		if (! $this->subscriptions()->where($attributes)->exists()) {
			return $this->subscriptions()->create($attributes);
		}
	}
	public function unsubscribe() {
		return $this->subscriptions()->where(['user_id' => auth()->id()])->delete();
	}

	public function getPath() {
		return "/forum/".$this->category->slug."/".$this->slug;
	}

	public function scopeFilter($query, $filters) {
		return $filters->apply($query);
	}


	/**
	* Determine if the current reply has been favourited.
	*
	* @return boolean
	*/

	public function isSubscribed()
	{
		return ! ! $this->subscriptions->where('user_id', auth()->id())->count();
	}

	public function getIsSubscribedAttribute()
	{
		return $this->isSubscribed();
	}

	public function hasBeenUpdated() {
		if (!auth()->check()) return false;
		$key = auth()->user()->visitedThreadCachedKey($this->id);
		return cache($key) < $this->updated_at;
	}

	public function getBodyAttribute($body) {
		return \Purify::clean($body);
	}

	public function setSlugAttribute($value) {
		$slug = str_slug($value);
		$count = Thread::where(['slug'=>$slug])->count();
		if ($count == 1 ) {
			$found_slug = false;
			while (!$found_slug) {
				$new_slug = $slug."-".str_slug(substr(bcrypt($this->id),0,12));
				if (!Thread::where(['slug'=>$new_slug])->exists()) {
					$found_slug = true;
				}
			}
		} else {
			$new_slug = $slug;
		}
		$this->attributes['slug'] = $new_slug;
	}

	public function bestReply() {
		return ThreadReply::find($this->best_reply_id);
	}

	public function lock() {
		$this->update(['is_locked'=>1]);
	}

	public function unlock() {
		$this->update(['is_locked'=>0]);
	}
}
