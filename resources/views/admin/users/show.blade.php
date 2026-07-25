@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Chi tiết Người dùng: {{ $user->name }}</h1>
        <a href="{{ route('admin.users.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">&larr; Danh sách</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-3 gap-6">
        <!-- User Info -->
        <div class="col-span-2 bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Thông tin</h2>
            <div class="grid grid-cols-2 gap-4">
                <div><span class="text-gray-500">ID:</span> <span class="font-medium">{{ $user->id }}</span></div>
                <div><span class="text-gray-500">Tên:</span> <span class="font-medium">{{ $user->name }}</span></div>
                <div><span class="text-gray-500">Email:</span> <span class="font-medium">{{ $user->email }}</span></div>
                <div><span class="text-gray-500">Vai trò:</span>
                    <span class="px-2 py-1 rounded text-xs {{ $user->role?->slug === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                        {{ $user->role?->name ?? 'N/A' }}
                    </span>
                </div>
                <div><span class="text-gray-500">Trạng thái:</span>
                    @if($user->locked_at)
                        <span class="text-red-600 font-bold">Đã khóa ({{ $user->locked_at->format('d/m/Y H:i') }})</span>
                    @else
                        <span class="text-green-600 font-bold">Hoạt động</span>
                    @endif
                </div>
                <div><span class="text-gray-500">Đăng ký:</span> <span class="font-medium">{{ $user->created_at->format('d/m/Y H:i') }}</span></div>
            </div>
        </div>

        <!-- Stats -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Thống kê</h2>
            <div class="space-y-3">
                <div class="flex justify-between"><span>Tổng khóa học:</span><span class="font-bold">{{ $stats['courses_count'] }}</span></div>
                <div class="flex justify-between"><span>Từ vựng đã lưu:</span><span class="font-bold">{{ $stats['bookmarks'] }}</span></div>
                <div class="flex justify-between"><span>Số lần làm quiz:</span><span class="font-bold">{{ $stats['quiz_attempts'] }}</span></div>
                <div class="flex justify-between"><span>Điểm TB quiz:</span><span class="font-bold">{{ $stats['avg_score'] }}%</span></div>
                <div class="flex justify-between"><span>Tiến độ từ vựng:</span><span class="font-bold">{{ $stats['progress_count'] }}</span></div>
            </div>
        </div>

        <!-- Assign Role -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Phân quyền</h2>
            <form action="{{ route('admin.users.role', $user) }}" method="POST">
                @csrf
                <div class="flex gap-2">
                    <select name="role_id" class="flex-1 border rounded px-3 py-2">
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ $user->role_id == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Cập nhật</button>
                </div>
            </form>
        </div>

        <!-- Lock/Unlock -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Khóa/Mở khóa</h2>
            @if($user->locked_at)
                <form action="{{ route('admin.users.unlock', $user) }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Mở khóa tài khoản</button>
                </form>
            @else
                <form action="{{ route('admin.users.lock', $user) }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700" onclick="return confirm('Khóa tài khoản này?')">Khóa tài khoản</button>
                </form>
            @endif
        </div>

        <!-- Reset Password -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Đặt lại mật khẩu</h2>
            <form action="{{ route('admin.users.password', $user) }}" method="POST">
                @csrf
                <div class="space-y-3">
                    <input type="password" name="new_password" placeholder="Mật khẩu mới (tối thiểu 8 ký tự)" required minlength="8" class="w-full border rounded px-3 py-2">
                    <input type="password" name="new_password_confirmation" placeholder="Xác nhận mật khẩu" required minlength="8" class="w-full border rounded px-3 py-2">
                    <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700">Đặt lại mật khẩu</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection