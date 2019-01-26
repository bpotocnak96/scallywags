@extends('layouts/main')

@section('css')
	<link rel='stylesheet' href="{{ asset('css/forum.css') }}">
@endsection

@section('content')
	<div class="container">
		<h2 class='text-center'>Add a new discussion...</h2>
		<form action='/forum' method='POST'>
			@csrf
			<div class="form-group">
				<input type="text" class="form-control" name='title' id="title" placeholder="Title..." required>
			</div>
			<div class="form-group">
				<textarea  class="form-control" name='body' id="body" placeholder="..." rows="10" required></textarea>
			</div>
			<div class='form-group'>
				<button type='submit' class="btn btn-primary d-inline m-auto">Submit</button>
				<button class="btn btn-danger d-inline m-auto" onClick='window.location="/forum"'>Cancel</button>
			</div>
		</form>
	</div>
@endsection

@section('javascript')
	<script src='{{ asset("js/forum.js") }}'></script>
@endsection
