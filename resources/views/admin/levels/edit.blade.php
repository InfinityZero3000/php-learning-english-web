@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-xl">
    <h1 class="text-3xl font-bold mb-6">Sửa Level: {{ $level->name }}</h1>
    @include('admin.levels._form', ['route' => route('admin.levels.update', $level), 'method' => 'PATCH', 'level' => $level])
</div>
@endsection