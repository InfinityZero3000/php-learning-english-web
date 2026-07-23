<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quên mật khẩu</title>
</head>
<body>

<h2>Quên mật khẩu</h2>

@if(session('success'))
    <p style="color:green">{{ session('success') }}</p>
@endif

<form action="{{ route('password.email') }}" method="POST">

    @csrf

    <label>Email</label>

    <input
        type="email"
        name="email"
        value="{{ old('email') }}"
    >

    @error('email')
        <p style="color:red">{{ $message }}</p>
    @enderror

    <button type="submit">
        Gửi liên kết đặt lại mật khẩu
    </button>

</form>

<p>
    <a href="{{ route('login') }}">Quay lại đăng nhập</a>
</p>

</body>
</html>