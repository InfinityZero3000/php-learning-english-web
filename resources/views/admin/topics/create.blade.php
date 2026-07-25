@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Thêm Topic mới</h1>
    @include('admin.topics._form', ['route' => route('admin.topics.store'), 'method' => 'POST', 'topic' => null])
</div>
@endsection