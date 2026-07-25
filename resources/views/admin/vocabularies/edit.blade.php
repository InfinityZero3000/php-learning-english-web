@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Sửa Từ vựng: {{ $vocabulary->word }}</h1>
    @include('admin.vocabularies._form', ['route' => route('admin.vocabularies.update', $vocabulary), 'method' => 'PUT', 'vocabulary' => $vocabulary])
</div>
@endsection