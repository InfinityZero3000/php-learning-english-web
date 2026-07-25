@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-3xl font-bold mb-6">Thêm Khóa học</h1>
    @include('admin.courses._form', ['route' => route('admin.courses.store'), 'method' => 'POST', 'course' => null])
</div>
@endsection