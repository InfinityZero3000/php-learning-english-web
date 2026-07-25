@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Quản lý Từ vựng</h1>
        <a href="{{ route('admin.vocabularies.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">+ Thêm Từ vựng</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    <div class="mb-4">
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Tìm từ, nghĩa..." class="border rounded px-3 py-2 w-64">
            <select name="topic_id" class="border rounded px-3 py-2">
                <option value="">-- Chủ đề --</option>
                @foreach(\App\Models\Topic::orderBy('name')->get() as $topic)
                    <option value="{{ $topic->id }}" {{ request('topic_id') == $topic->id ? 'selected' : '' }}>{{ $topic->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-gray-500 text-white px-4 py-2 rounded">Lọc</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Từ</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nghĩa</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phiên âm</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Chủ đề</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thao tác</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($vocabularies as $vocab)
                <tr>
                    <td class="px-6 py-4">{{ $vocab->id }}</td>
                    <td class="px-6 py-4 font-medium">{{ $vocab->word }}</td>
                    <td class="px-6 py-4">{{ $vocab->meaning }}</td>
                    <td class="px-6 py-4">{{ $vocab->pronunciation }}</td>
                    <td class="px-6 py-4">{{ $vocab->topic?->name }}</td>
                    <td class="px-6 py-4 space-x-2">
                        <a href="{{ route('admin.vocabularies.edit', $vocab) }}" class="text-blue-600 hover:text-blue-900">Sửa</a>
                        <form action="{{ route('admin.vocabularies.destroy', $vocab) }}" method="POST" class="inline" onsubmit="return confirm('Xóa từ vựng này?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Xóa</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Chưa có từ vựng nào.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4">{{ $vocabularies->links() }}</div>
    </div>
</div>
@endsection