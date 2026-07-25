@extends('layouts.app')

@section('title', 'Khu vực quản trị')

@section('content')
    <div class="alert alert-warning">
        <h1 class="h3">Khu vực quản trị</h1>
        <p class="mb-0">Khu vực này yêu cầu tài khoản quản trị viên.</p>
    </div>
    @isset($users)
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <div class="card">
            <div class="card-header">Quản lý người dùng</div>
            <div class="card-body">
                @foreach ($users as $user)
                    <form method="POST" action="{{ route('admin.users.updateRole', $user) }}" class="row g-2 align-items-center mb-2">
                        @csrf
                        @method('PATCH')
                        <div class="col">{{ $user->name }} ({{ $user->email }})</div>
                        <div class="col-auto">
                            <select name="role_id" class="form-select">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto"><button class="btn btn-primary">Lưu</button></div>
                    </form>
                @endforeach
                {{ $users->links() }}
            </div>
        </div>
    @endisset
@endsection
