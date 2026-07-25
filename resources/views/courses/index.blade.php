@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Khóa học</h1>
    <div class="mb-4 flex gap-2">
        <form method="GET" class="flex gap-2 flex-1">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Tìm khóa học..." class="border rounded px-3 py-2 w-64">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Tìm</button>
        </form>
    </div>
    @if(isset($levels))
    <div class="mb-4 flex gap-2">
        @foreach($levels as $level)
            <a href="?level_id={{ $level->id }}" class="px-3 py-1 rounded border {{ request('level_id') == $level->id ? 'bg-blue-600 text-white' : 'hover:bg-gray-100' }}">{{ $level->name }}</a>
        @endforeach
    </div>
    @endif
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @forelse($courses as $course)
            <div class="bg-white rounded-lg shadow overflow-hidden hover:shadow-lg transition">
                <div class="p-6">
                    <h2 class="text-xl font-bold mb-2">{{ $course->name }}</h2>
                    <p class="text-gray-600 text-sm mb-4">{{ Str::limit($course->description, 100) }}</p>
                    <div class="flex items-center justify-between text-sm text-gray-500">
                        <span>{{ $course->level?->name ?? 'N/A' }}</span>
                        <span>{{ $course->lessons?->count() ?? 0 }} bài học</span>
                    </div>
                    <a href="{{ route('courses.show', $course) }}" class="block mt-4 text-center bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Xem chi tiết</a>
                </div>
            </div>
        @empty
            <p class="text-gray-500 col-span-3 text-center py-8">Chưa có khóa học nào.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $courses->links() }}</div>
</div>
@endsection