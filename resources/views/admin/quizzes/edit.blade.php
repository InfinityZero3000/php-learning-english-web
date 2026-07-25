@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Sửa Quiz: {{ $quiz->title }}</h1>
    @include('admin.quizzes._form', ['route' => route('admin.quizzes.update', $quiz), 'method' => 'PUT', 'quiz' => $quiz, 'lessons' => $lessons])
</div>
@endsection