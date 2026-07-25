@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Quản lý Quiz</h1>
        <a href="{{ route('admin.quizzes.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">+ Thêm Quiz</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tiêu đề</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bài học</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Khóa học</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Số câu</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Điểm đạt</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thao tác</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($quizzes as $quiz)
                <tr>
                    <td class="px-6 py-4">{{ $quiz->id }}</td>
                    <td class="px-6 py-4 font-medium">{{ $quiz->title }}</td>
                    <td class="px-6 py-4">{{ $quiz->lesson?->title }}</td>
                    <td class="px-6 py-4">{{ $quiz->lesson?->course?->name }}</td>
                    <td class="px-6 py-4">{{ $quiz->questions_count }}</td>
                    <td class="px-6 py-4">{{ $quiz->passing_score }}%</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-xs {{ $quiz->status === 'published' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ $quiz->status === 'published' ? 'Đã xuất bản' : 'Nháp' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 space-x-2">
                        <a href="{{ route('admin.quizzes.questions', $quiz) }}" class="text-indigo-600 hover:text-indigo-900">Câu hỏi</a>
                        <a href="{{ route('admin.quizzes.edit', $quiz) }}" class="text-blue-600 hover:text-blue-900">Sửa</a>
                        <form action="{{ route('admin.quizzes.destroy', $quiz) }}" method="POST" class="inline" onsubmit="return confirm('Xóa quiz này?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Xóa</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">Chưa có quiz nào.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4">{{ $quizzes->links() }}</div>
    </div>
</div>
@endsection