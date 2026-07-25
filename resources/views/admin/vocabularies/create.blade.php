@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Thêm Từ vựng mới</h1>
    @include('admin.vocabularies._form', ['route' => route('admin.vocabularies.store'), 'method' => 'POST', 'vocabulary' => null])
</div>
@endsection