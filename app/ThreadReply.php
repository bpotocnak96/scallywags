<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ThreadReply extends Model
{
	use RecordsActivity;
	use Favourable;

	protected $fillable = ['thread_id','user_id','body'];
	protected $appends = array('is_favourited');
	protected $touches = ['Thread'];

	protected static function boot() {
		parent::boot();
		static::addGlobalScope('user', function ($builder) {
			$builder->with('user');
		});
		static::addGlobalScope('thread', function ($builder) {
			$builder->with('thread');
		});
		static::deleting(function($reply) {
			$reply->thread->user()->decrement('reputation',1);
			$reply->user()->decrement('reputation',1);
		});
		// static::saving(function($thread) {
		// 	auth()->user()->read($thread->id);
		// });
		// static::updating(function($thread) {
		// 	auth()->user()->read($thread->id);
		// });
	}

	public function user() {
		return $this->belongsTo(User::class);
	}
	public function thread() {
		return $this->belongsTo(Thread::class);
	}

	public function wasJustPublished() {
		return $this->created_at->gt(\Carbon\Carbon::now()->subSeconds(30));
	}

	public function setBodyAttribute($body) {
		$reg = '/@([\w\-]+)/';
		preg_match_all($reg,$body,$matches);
		$names = $matches[1];
		foreach($names as $n) {
			$user = User::where(['name'=>$n])->first();
			if ($user) {
				$body = preg_replace($reg,'<a href="'.$user->getPath().'">$0</a>',$body);
			}
		}
		$this->attributes['body'] = $body;
	}

	public function markAsBest() {
		$thread = $this->thread;
		if (!$thread->best_reply_id) {
			$thread->update(['best_reply_id'=>$this->id]);
			if ($this->user_id != auth()->id()) {
				$this->user()->increment('reputation',10);
			}
			return true;
		}
		return false;
	}

	public function isBest() {
		return $this->thread->best_reply_id == $this->id;
	}

	public function getBodyAttribute($body) {
		return \Purify::clean($body);
	}

}
