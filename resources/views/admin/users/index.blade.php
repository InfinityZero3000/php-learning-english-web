@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Quản lý Người dùng</h1>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    <div class="mb-4 flex gap-2">
        <form method="GET" class="flex gap-2 flex-1">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Tìm tên, email..." class="border rounded px-3 py-2 w-64">
            <select name="role_id" class="border rounded px-3 py-2">
                <option value="">-- Vai trò --</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ request('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vai trò</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Khóa</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thao tác</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($users as $user)
                <tr>
                    <td class="px-6 py-4">{{ $user->id }}</td>
                    <td class="px-6 py-4 font-medium">{{ $user->name }}</td>
                    <td class="px-6 py-4">{{ $user->email }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-xs {{ $user->role?->slug === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                            {{ $user->role?->name ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @if($user->locked_at)
                            <span class="text-red-600 font-bold">Đã khóa</span>
                        @else
                            <span class="text-green-600">Hoạt động</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 space-x-2">
                        <a href="{{ route('admin.users.show', $user) }}" class="text-blue-600 hover:text-blue-900">Chi tiết</a>
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Xóa người dùng này?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Xóa</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Không tìm thấy người dùng nào.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-6 py-4">{{ $users->links() }}</div>
    </div>
</div>
@endsection