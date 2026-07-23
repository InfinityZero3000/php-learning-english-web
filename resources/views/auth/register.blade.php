<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký</title>

    <style>
        body{
            font-family: Arial, sans-serif;
            background:#f5f5f5;
        }

        .container{
            width:400px;
            margin:60px auto;
            background:#fff;
            padding:25px;
            border-radius:10px;
            box-shadow:0 0 10px rgba(0,0,0,.15);
        }

        h2{
            text-align:center;
            margin-bottom:20px;
        }

        input{
            width:100%;
            padding:10px;
            margin-top:5px;
            margin-bottom:15px;
            border:1px solid #ccc;
            border-radius:5px;
            box-sizing:border-box;
        }

        button{
            width:100%;
            padding:10px;
            background:#007bff;
            color:white;
            border:none;
            border-radius:5px;
            cursor:pointer;
        }

        button:hover{
            background:#0056b3;
        }

        .error{
            color:red;
            font-size:14px;
            margin-top:-10px;
            margin-bottom:10px;
        }

        .success{
            color:green;
            text-align:center;
        }

        a{
            text-decoration:none;
        }

        .login-link{
            text-align:center;
            margin-top:15px;
        }
    </style>

</head>
<body>

<div class="container">

    <h2>Đăng ký tài khoản</h2>

    @if(session('success'))
        <p class="success">{{ session('success') }}</p>
    @endif

    <form action="{{ route('register.store') }}" method="POST">

        @csrf

        <label>Họ và tên</label>

        <input
            type="text"
            name="name"
            value="{{ old('name') }}"
        >

        @error('name')
            <div class="error">{{ $message }}</div>
        @enderror

        <label>Email</label>

        <input
            type="email"
            name="email"
            value="{{ old('email') }}"
        >

        @error('email')
            <div class="error">{{ $message }}</div>
        @enderror

        <label>Mật khẩu</label>

        <input
            type="password"
            name="password"
        >

        @error('password')
            <div class="error">{{ $message }}</div>
        @enderror

        <label>Xác nhận mật khẩu</label>

        <input
            type="password"
            name="password_confirmation"
        >

        <button type="submit">
            Đăng ký
        </button>

    </form>

    <div class="login-link">
        Đã có tài khoản?
        <a href="{{ route('login') }}">
            Đăng nhập
        </a>
    </div>

</div>

</body>
</html>


