@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Quản lý Level</h1>
        <a href="{{ route('admin.levels.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">+ Thêm Level</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Khóa học</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thứ tự</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thao tác</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($levels as $level)
                <tr>
                    <td class="px-6 py-4">{{ $level->id }}</td>
                    <td class="px-6 py-4">{{ $level->name }}</td>
                    <td class="px-6 py-4">{{ $level->slug }}</td>
                    <td class="px-6 py-4">{{ $level->courses_count }}</td>
                    <td class="px-6 py-4">{{ $level->sort_order }}</td>
                    <td class="px-6 py-4 space-x-2">
                        <a href="{{ route('admin.levels.edit', $level) }}" class="text-blue-600 hover:text-blue-900">Sửa</a>
                        <form action="{{ route('admin.levels.destroy', $level) }}" method="POST" class="inline" onsubmit="return confirm('Xóa level này?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Xóa</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Chưa có level nào.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4">{{ $levels->links() }}</div>
    </div>
</div>
@endsection