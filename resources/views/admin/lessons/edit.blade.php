@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Sửa Bài học: {{ $lesson->title }}</h1>
    @include('admin.lessons._form', ['route' => route('admin.lessons.update', $lesson), 'method' => 'PUT', 'lesson' => $lesson, 'courses' => $courses, 'levels' => $levels])
</div>
@endsection