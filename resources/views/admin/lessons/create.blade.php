@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Thêm Bài học mới</h1>
    @include('admin.lessons._form', ['route' => route('admin.lessons.store'), 'method' => 'POST', 'lesson' => null, 'courses' => $courses, 'levels' => $levels])
</div>
@endsection