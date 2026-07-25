@extends('layouts.app')

@section('title', 'Dashboard')
@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Dashboard</h1>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
            <h3 class="text-gray-500 text-sm">Khóa học đã học</h3>
            <p class="text-3xl font-bold text-blue-600">{{ $stats['completed_lessons'] ?? 0 }}</p>
            <p class="text-xs text-gray-400 mt-1">Bài học đã hoàn thành</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
            <h3 class="text-gray-500 text-sm">Điểm Quiz TB</h3>
            <p class="text-3xl font-bold text-green-600">{{ number_format($stats['avg_quiz_score'] ?? 0, 1) }}%</p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['passed_quizzes'] ?? 0 }} bài đạt / {{ $stats['total_attempts'] ?? 0 }} bài</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
            <h3 class="text-gray-500 text-sm">Điểm cao nhất</h3>
            <p class="text-3xl font-bold text-purple-600">{{ number_format($stats['highest_score'] ?? 0, 1) }}%</p>
            <p class="text-xs text-gray-400 mt-1">Best score</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
            <h3 class="text-gray-500 text-sm">Từ vựng đã lưu</h3>
            <p class="text-3xl font-bold text-orange-600">{{ $stats['bookmarks_count'] ?? 0 }}</p>
            <p class="text-xs text-gray-400 mt-1">Bookmarks</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Activity --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">🎯 Hoạt động gần đây</h2>
            @if(!empty($stats['recent_activity']) && count($stats['recent_activity']) > 0)
                <div class="space-y-3">
                    @foreach($stats['recent_activity'] as $activity)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800">{{ $activity['quiz_title'] ?? 'Quiz' }}</p>
                                <p class="text-xs text-gray-500">{{ $activity['completed_at'] ?? '' }}</p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm font-semibold {{ ($activity['passed'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $activity['score'] ?? 0 }}%
                                {{ ($activity['passed'] ?? false) ? '✓' : '✗' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <p class="text-gray-400 text-lg">📭 Chưa có hoạt động nào.</p>
                    <p class="text-gray-400 text-sm mt-2">Hãy bắt đầu học và làm quiz!</p>
                    <a href="{{ route('courses.index') }}" class="inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Khám phá khóa học
                    </a>
                </div>
            @endif
        </div>

        {{-- Quick Links --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">⚡ Truy cập nhanh</h2>
            <div class="space-y-3">
                <a href="{{ route('courses.index') }}" class="flex items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-lg text-blue-700 transition">
                    <span class="text-2xl mr-3">📚</span>
                    <div>
                        <p class="font-medium">Khóa học</p>
                        <p class="text-xs text-blue-500">Xem danh sách khóa học</p>
                    </div>
                </a>
                <a href="{{ route('vocabularies.index') }}" class="flex items-center p-3 bg-green-50 hover:bg-green-100 rounded-lg text-green-700 transition">
                    <span class="text-2xl mr-3">📝</span>
                    <div>
                        <p class="font-medium">Từ vựng</p>
                        <p class="text-xs text-green-500">Tra cứu từ vựng</p>
                    </div>
                </a>
                <a href="{{ route('profile') }}" class="flex items-center p-3 bg-purple-50 hover:bg-purple-100 rounded-lg text-purple-700 transition">
                    <span class="text-2xl mr-3">👤</span>
                    <div>
                        <p class="font-medium">Hồ sơ</p>
                        <p class="text-xs text-purple-500">Cập nhật thông tin cá nhân</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection