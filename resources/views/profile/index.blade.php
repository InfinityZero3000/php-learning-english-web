@extends('layouts.app')

@section('title', 'Hồ sơ · LexiLingo')
@section('top', 'Hồ sơ')
@section('subtitle', 'Cập nhật thông tin tài khoản')

@section('content')
    <div class="card" style="max-width:700px;margin:0 auto;padding:28px">
        <h2 style="margin-top:0">Thông tin cá nhân</h2>
        <p class="metric">Cập nhật hồ sơ của bạn và đổi mật khẩu để bảo vệ tài khoản.</p>

        <form action="{{ route('profile.update') }}" method="POST">
            @csrf
            @method('PUT')

            <label style="display:block;font-weight:800;margin-bottom:8px">Tên</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" style="margin-bottom:14px">
            @error('name')
                <div class="field-error">{{ $message }}</div>
            @enderror

            <label style="display:block;font-weight:800;margin-bottom:8px">Email</label>
            <input type="email" value="{{ $user->email }}" readonly style="margin-bottom:14px;background:#f8fbfd">

            <label style="display:block;font-weight:800;margin-bottom:8px">Mật khẩu hiện tại</label>
            <input type="password" name="current_password" style="margin-bottom:14px">
            @error('current_password')
                <div class="field-error">{{ $message }}</div>
            @enderror

            <label style="display:block;font-weight:800;margin-bottom:8px">Mật khẩu mới</label>
            <input type="password" name="new_password" style="margin-bottom:14px">
            @error('new_password')
                <div class="field-error">{{ $message }}</div>
            @enderror

            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:12px">
                <button type="submit" class="btn">Lưu thay đổi</button>
                @if(session('success'))
                    <span style="color:var(--brand-dark);font-weight:700">✓ {{ session('success') }}</span>
                @endif
            </div>
        </form>

        <form action="{{ route('profile.destroy') }}" method="POST" style="margin-top:24px"
              onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản? Hành động này không thể hoàn tác.');">
            @csrf
            @method('DELETE')
            <button type="submit" style="background:none;border:none;color:#b91c1c;font-weight:800;padding:0;cursor:pointer">Xóa tài khoản của tôi</button>
        </form>
    </div>
@endsection
