@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-3xl font-bold mb-6">Sửa Khóa học: {{ $course->title }}</h1>
    @include('admin.courses._form', ['route' => route('admin.courses.update', $course), 'method' => 'PATCH', 'course' => $course])
</div>
@endsection