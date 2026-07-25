@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Admin Dashboard</h1>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm">Tổng người dùng</h3>
            <p class="text-3xl font-bold text-blue-600">{{ $stats['total_users'] ?? 0 }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['new_users_today'] ?? 0 }} mới hôm nay</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm">Tổng khóa học</h3>
            <p class="text-3xl font-bold text-green-600">{{ $stats['total_courses'] ?? 0 }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['total_lessons'] ?? 0 }} bài học</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm">Tổng từ vựng</h3>
            <p class="text-3xl font-bold text-purple-600">{{ $stats['total_vocabularies'] ?? 0 }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['total_quizzes'] ?? 0 }} quiz</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm">Điểm Quiz TB</h3>
            <p class="text-3xl font-bold text-orange-600">{{ $stats['avg_quiz_score'] ?? 0 }}%</p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['total_attempts'] ?? 0 }} lượt làm</p>
        </div>
    </div>

    <!-- Stats Row 2 -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm">Người dùng hoạt động tuần này</h3>
            <p class="text-3xl font-bold text-teal-600">{{ $stats['active_users_week'] ?? 0 }}</p>
        </div>
        <!-- Admin Dashboard -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm">Điểm Quiz TB</h3>
            <p class="text-3xl font-bold text-indigo-600">{{ $stats['avg_quiz_score'] ?? 0 }}%</p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['total_attempts'] ?? 0 }} lượt làm</p>
        </div>
        <!-- Admin Dashboard -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm">Tổng từ vựng</h3>
            <p class="text-3xl font-bold text-rose-600">{{ $stats['total_vocabularies'] ?? 0 }}</p>
        </div>
    </div>

    <!-- Charts & Quick Links -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Chart: Users per Day -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Người dùng mới (30 ngày)</h2>
            @php
                $userChartData = $chartData['users_per_day'] ?? [];
                $labels = array_column($userChartData, 'date');
                $counts = array_column($userChartData, 'count');
            @endphp
            @if (!empty($userChartData))
            <div class="relative" style="height: 250px;">
                <canvas id="usersChart"></canvas>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const ctx = document.getElementById('usersChart')?.getContext('2d');
                    if (ctx) {
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: @json($labels),
                                datasets: [{
                                    label: 'Người dùng mới',
                                    data: @json($counts),
                                    borderColor: '#3b82f6',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    fill: true,
                                    tension: 0.3
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } }
                            }
                        });
                    }
                });
            </script>
            @else
            <p class="text-gray-400">Chưa có dữ liệu</p>
            @endif
        </div>

        <!-- Chart: Quiz Score Distribution -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Phân phối điểm Quiz</h2>
            @php $dist = $chartData['quiz_scores_distribution'] ?? []; @endphp
            @if (!empty($dist))
            <div class="relative" style="height: 250px;">
                <canvas id="scoreDistChart"></canvas>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const ctx = document.getElementById('scoreDistChart')?.getContext('2d');
                    if (ctx) {
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: @json(array_keys($dist)),
                                datasets: [{
                                    label: 'Số lượt',
                                    data: @json(array_values($dist)),
                                    backgroundColor: ['#ef4444','#f97316','#eab308','#22c55e','#3b82f6']
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } }
                            }
                        });
                    }
                });
            </script>
            @else
            <p class="text-gray-400">Chưa có dữ liệu</p>
            @endif
        </div>

        <!-- Top Courses -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Top khóa học</h2>
            @php $topCourses = $chartData['top_courses'] ?? []; @endphp
            @if (!empty($topCourses))
            <ul class="space-y-2">
                @foreach ($topCourses as $c)
                <li class="flex justify-between items-center p-2 bg-gray-50 rounded">
                    <span>{{ $c['title'] }}</span>
                    <span class="text-sm text-gray-500">{{ $c['lessons_count'] }} bài</span>
                </li>
                @endforeach
            </ul>
            @else
            <p class="text-gray-400">Chưa có dữ liệu</p>
            @endif
        </div>

        <!-- Quick Links -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Quản lý nhanh</h2>
            <div class="space-y-3">
                <a href="{{ route('admin.courses.index') }}" class="block p-3 bg-blue-50 hover:bg-blue-100 rounded-lg text-blue-700">
                    📚 Quản lý Khóa học
                </a>
                <a href="{{ route('admin.levels.index') }}" class="block p-3 bg-green-50 hover:bg-green-100 rounded-lg text-green-700">
                    📊 Quản lý Level
                </a>
                <a href="{{ route('admin.topics.index') }}" class="block p-3 bg-yellow-50 hover:bg-yellow-100 rounded-lg text-yellow-700">
                    🏷️ Quản lý Topic
                </a>
                <a href="{{ route('admin.lessons.index') }}" class="block p-3 bg-indigo-50 hover:bg-indigo-100 rounded-lg text-indigo-700">
                    📖 Quản lý Bài học
                </a>
                <a href="{{ route('admin.vocabularies.index') }}" class="block p-3 bg-purple-50 hover:bg-purple-100 rounded-lg text-purple-700">
                    📝 Quản lý Từ vựng
                </a>
                <a href="{{ route('admin.quizzes.index') }}" class="block p-3 bg-pink-50 hover:bg-pink-100 rounded-lg text-pink-700">
                    ❓ Quản lý Quiz
                </a>
                <a href="{{ route('admin.users.index') }}" class="block p-3 bg-gray-50 hover:bg-gray-100 rounded-lg text-gray-700">
                    👥 Quản lý Người dùng
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
@endpush
@endsection