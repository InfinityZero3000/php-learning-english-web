@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Thêm Quiz mới</h1>
    @include('admin.quizzes._form', ['route' => route('admin.quizzes.store'), 'method' => 'POST', 'quiz' => null, 'lessons' => $lessons])
</div>
@endsection