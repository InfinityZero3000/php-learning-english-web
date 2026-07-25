@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 bg-white rounded-lg shadow p-6">
            <h1 class="text-3xl font-bold mb-2">{{ $lesson->title }}</h1>
            <div class="text-sm text-gray-500 mb-4">
                <a href="{{ route('courses.show', $lesson->course) }}" class="text-blue-600 hover:underline">{{ $lesson->course->name }}</a>
            </div>

            @if($lesson->video_url)
            <div class="mb-6">
                <iframe src="{{ $lesson->video_url }}" class="w-full aspect-video rounded" frameborder="0" allowfullscreen></iframe>
            </div>
            @endif

            @if($lesson->content)
            <div class="prose max-w-none mb-6">
                {!! nl2br(e($lesson->content)) !!}
            </div>
            @endif

            <!-- Navigation -->
            <div class="flex justify-between mt-8 pt-4 border-t">
                @if(isset($prevLesson))
                    <a href="{{ route('lessons.show', $prevLesson) }}" class="text-blue-600 hover:underline">&larr; Bài trước: {{ $prevLesson->title }}</a>
                @else
                    <span></span>
                @endif
                @if(isset($nextLesson))
                    <a href="{{ route('lessons.show', $nextLesson) }}" class="text-blue-600 hover:underline">Bài sau: {{ $nextLesson->title }} &rarr;</a>
                @endif
            </div>

            @auth
            <form action="{{ route('progress.complete', $lesson) }}" method="POST" class="mt-4">
                @csrf
                @if(isset($completed) && $completed)
                    <span class="text-green-600 font-bold">&#10003; Đã hoàn thành</span>
                @else
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">Đánh dấu hoàn thành</button>
                @endif
            </form>
            @endauth
        </div>

        <!-- Sidebar -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Bài học trong khóa</h2>
            <div class="space-y-2">
                @foreach($lesson->course->lessons->sortBy('sort_order') as $l)
                    <a href="{{ route('lessons.show', $l) }}" class="block p-2 rounded {{ $l->id === $lesson->id ? 'bg-blue-100 text-blue-800 font-bold' : 'hover:bg-gray-100' }}">
                        {{ $l->sort_order }}. {{ $l->title }}
                    </a>
                @endforeach
            </div>

            @if(isset($vocabularies) && $vocabularies->count() > 0)
            <h2 class="text-xl font-bold mt-6 mb-4">Từ vựng</h2>
            <div class="space-y-2">
                @foreach($vocabularies as $vocab)
                <div class="p-2 border rounded">
                    <p class="font-bold">{{ $vocab->word }}</p>
                    <p class="text-sm text-gray-600">{{ $vocab->meaning }}</p>
                </div>
                @endforeach
            </div>
            @endif

            @if(isset($quizzes) && $quizzes->count() > 0)
            <h2 class="text-xl font-bold mt-6 mb-4">Quiz</h2>
            <div class="space-y-2">
                @foreach($quizzes as $quiz)
                    <a href="{{ route('quizzes.show', $quiz) }}" class="block p-3 border rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-800 font-medium">
                        {{ $quiz->title }}
                    </a>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endsection