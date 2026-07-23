<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập</title>

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
            background:#28a745;
            color:white;
            border:none;
            border-radius:5px;
            cursor:pointer;
        }

        button:hover{
            background:#218838;
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
            margin-bottom:15px;
        }

        .links{
            display:flex;
            justify-content:space-between;
            margin-top:15px;
        }

        a{
            text-decoration:none;
            color:#007bff;
        }

        a:hover{
            text-decoration:underline;
        }
    </style>

</head>
<body>

<div class="container">

    <h2>Đăng nhập</h2>

    @if(session('success'))
        <p class="success">{{ session('success') }}</p>
    @endif

    <form action="{{ route('login.store') }}" method="POST">

        @csrf

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

        <button type="submit">
            Đăng nhập
        </button>

    </form>

    <div class="links">
        <a href="{{ route('register') }}">Đăng ký</a>

        {{-- Thêm route này khi làm chức năng quên mật khẩu --}}
        <a href="#">Quên mật khẩu?</a>
    </div>

</div>

</body>
</html>