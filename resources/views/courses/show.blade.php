@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 md:p-8">
            <h1 class="text-3xl font-bold mb-2">{{ $course->name }}</h1>
            <div class="flex items-center gap-4 text-sm text-gray-500 mb-6">
                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">{{ $course->level?->name ?? 'N/A' }}</span>
                <span>{{ $course->lessons->count() }} bài học</span>
                @if(isset($progress))
                <span class="text-green-600">Hoàn thành: {{ $progress }}%</span>
                @endif
            </div>

            @if($course->description)
            <div class="prose max-w-none mb-8">
                <p>{{ $course->description }}</p>
            </div>
            @endif

            <!-- Progress Bar -->
            @if(isset($progress))
            <div class="w-full bg-gray-200 rounded-full h-3 mb-8">
                <div class="bg-green-500 h-3 rounded-full" style="width: {{ $progress }}%"></div>
            </div>
            @endif

            <!-- Lessons -->
            <h2 class="text-2xl font-bold mb-4">Danh sách bài học</h2>
            <div class="space-y-3">
                @forelse($course->lessons->sortBy('sort_order') as $lesson)
                    <a href="{{ route('lessons.show', $lesson) }}" class="flex items-center justify-between p-4 border rounded hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">{{ $lesson->sort_order ?? '#' }}</span>
                            <div>
                                <p class="font-medium">{{ $lesson->title }}</p>
                                @if($lesson->duration)
                                <p class="text-sm text-gray-500">{{ $lesson->duration }} phút</p>
                                @endif
                            </div>
                        </div>
                        <span class="text-blue-600">&rarr;</span>
                    </a>
                @empty
                    <p class="text-gray-500">Chưa có bài học nào.</p>
                @endforelse
            </div>

            <!-- Vocabulary Section -->
            @if(isset($vocabularies) && $vocabularies->count() > 0)
            <h2 class="text-2xl font-bold mt-8 mb-4">Từ vựng</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($vocabularies as $vocab)
                <div class="p-4 border rounded">
                    <p class="font-bold text-lg">{{ $vocab->word }}</p>
                    <p class="text-gray-600">{{ $vocab->meaning }}</p>
                    @if($vocab->pronunciation)
                    <p class="text-gray-400 text-sm">{{ $vocab->pronunciation }}</p>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endsection