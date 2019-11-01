<?php

namespace App\Http\Controllers;

use App\Thread;
use App\Trending;
use App\Category;
use App\Rules\Recaptcha;
use App\Filters\ThreadFilter;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ThreadsController extends Controller
{
	public function __construct() {
		$this->middleware('auth')->except(['index','show']);
	}
	/**
	* Display a listing of the resource.
	*
	* @return \Illuminate\Http\Response
	*/
	public function index(Category $category, ThreadFilter $filters)
	{
		if ($category->exists) {
			$threads = $category->threads();
			$threads = $threads->filter($filters)->latest()->paginate(10);
		} else {
			$threads = Thread::filter($filters)->paginate(10);
		}

		// $categories = Category::all();

		$trending_threads = Trending::get();
		// get pinned threads from filtered threads
        $pinned_threads = $threads->where('is_pinned', 1);

		if (request()->wantsJson()) {
			return $threads;
		}
		// echo json_encode($threads);
		// return null;
		return view('forum/index',compact('threads','trending_threads', 'pinned_threads'));
	}

	/**
	* Show the form for creating a new resource.
	*
	* @return \Illuminate\Http\Response
	*/
	public function create()
	{
		return view('forum/new');
	}

	/**
	* Store a newly created resource in storage.
	*
	* @param  \Illuminate\Http\Request  $request
	* @return \Illuminate\Http\Response
	*/
	public function store(Request $request, Recaptcha $recaptcha)
	{
		$data = request()->validate([
			'title'=>'required|spamfree',
			'body'=>'required|spamfree',
			'category_id'=>'required|exists:categories,id',
			'g-recaptcha-response' => ['required',$recaptcha]
		]);
		$data['user_id'] = auth()->user()->id;
		$data['slug'] = request('title');
		$thread = Thread::create($data);
		$thread->subscribe();
		return redirect($thread->getPath());
	}

	/**
	* Display the specified resource.
	*
	* @param  \App\Thread  $thread
	* @return \Illuminate\Http\Response
	*/
	public function show(Request $request, $category_id, Thread $thread)
	{
		if (auth()->check()) {
			auth()->user()->read($thread->id);
		}

		Trending::push($thread);
		$thread->increment('visits');

		$trending_threads = Trending::get();
		$page = $request->page?$request->page:1;
		return view('forum/show',compact('thread','page','trending_threads'));
	}

	/**
	* Show the form for editing the specified resource.
	*
	* @param  \App\Thread  $thread
	* @return \Illuminate\Http\Response
	*/
	public function edit(Thread $thread)
	{
		//
	}

	/**
	* Update the specified resource in storage.
	*
	* @param  \Illuminate\Http\Request  $request
	* @param  \App\Thread  $thread
	* @return \Illuminate\Http\Response
	*/
	public function update(Request $request, $category, Thread $thread)
	{
		$this->authorize('update',$thread);
		$data = request()->validate([
			'title'=>'required|spamfree',
			'body'=>'required|spamfree',
		]);
		$thread->update($data);
	}

	/**
	* Remove the specified resource from storage.
	*
	* @param  \App\Thread  $thread
	* @return \Illuminate\Http\Response
	*/
	public function destroy($category, Thread $thread)
	{
		$this->authorize('update',$thread);
		$thread->delete();
		return response()->json(array('status'=>1,'fb'=>'Thread Deleted'));
	}

	/**
	* Lock the thread
	*
	* @param  \App\Thread  $thread
	*/
	public function lock($category, Thread $thread) {
		$thread->lock();
		return back();
	}

	/**
	* Unlock the thread
	*
	* @param  \App\Thread  $thread
	*/
	public function unlock($category, Thread $thread) {
		$thread->unlock();
		return back();
    }

	/**
	* Pin the thread
	*
	* @param  \App\Thread  $thread
	*/
	public function pin($category, Thread $thread) {
		$thread->pin();
		return back();
	}

	/**
	* Unpin the thread
	*
	* @param  \App\Thread  $thread
	*/
	public function unpin($category, Thread $thread) {
		$thread->unpin();
		return back();
	}
}
