@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Sửa Topic: {{ $topic->name }}</h1>
    @include('admin.topics._form', ['route' => route('admin.topics.update', $topic), 'method' => 'PUT', 'topic' => $topic])
</div>
@endsection