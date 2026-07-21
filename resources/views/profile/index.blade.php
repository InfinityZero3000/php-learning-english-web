<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ người học</title>

    <style>
        *{
            box-sizing:border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body{
            margin:0;
            background:linear-gradient(135deg,#e0f2fe,#f8fafc);
        }

        .container{
            width:900px;
            max-width:95%;
            margin:50px auto;
        }

        .profile-card{
            background:white;
            border-radius:20px;
            padding:35px;
            box-shadow:0 10px 30px rgba(0,0,0,0.1);
        }

        .header{
            display:flex;
            align-items:center;
            gap:25px;
            border-bottom:1px solid #eee;
            padding-bottom:25px;
        }

        .avatar{
            width:100px;
            height:100px;
            border-radius:50%;
            background:#2563eb;
            color:white;
            display:flex;
            justify-content:center;
            align-items:center;
            font-size:40px;
            font-weight:bold;
        }

        .header h2{
            margin:0;
            color:#1e293b;
        }

        .header p{
            color:#64748b;
            margin-top:8px;
        }


        .form-group{
            margin-top:25px;
        }

        label{
            display:block;
            font-weight:600;
            margin-bottom:8px;
            color:#334155;
        }

        input{
            width:100%;
            padding:13px;
            border:1px solid #cbd5e1;
            border-radius:10px;
            font-size:15px;
            outline:none;
        }

        input:focus{
            border-color:#2563eb;
        }


        .btn{
            margin-top:30px;
            padding:13px 25px;
            border:none;
            border-radius:10px;
            cursor:pointer;
            font-size:15px;
        }

        .btn-update{
            background:#2563eb;
            color:white;
        }

        .btn-update:hover{
            background:#1d4ed8;
        }


        .btn-logout{
            background:#ef4444;
            color:white;
            margin-left:10px;
        }

        .btn-logout:hover{
            background:#dc2626;
        }


        .success{
            background:#dcfce7;
            color:#166534;
            padding:12px;
            border-radius:10px;
            margin-bottom:20px;
        }


        .error{
            color:#dc2626;
            font-size:14px;
            margin-top:5px;
        }


        .info{
            display:flex;
            gap:20px;
            margin-top:25px;
        }

        .box{
            flex:1;
            background:#f8fafc;
            padding:20px;
            border-radius:15px;
            text-align:center;
        }

        .box h3{
            margin:0;
            color:#2563eb;
        }

        .box p{
            color:#64748b;
        }

    </style>

</head>

<body>


<div class="container">

    <div class="profile-card">


        @if(session('success'))
            <div class="success">
                {{ session('success') }}
            </div>
        @endif


        <div class="header">

            <div class="avatar">
                {{ strtoupper(substr($user->name,0,1)) }}
            </div>

            <div>
                <h2>{{ $user->name }}</h2>

                <p>
                    {{ $user->email }}
                </p>

                <p>
                    Thành viên học tiếng Anh
                </p>
            </div>

        </div>



        <div class="info">

            <div class="box">
                <h3>0</h3>
                <p>Bài học hoàn thành</p>
            </div>


            <div class="box">
                <h3>0</h3>
                <p>Từ vựng đã học</p>
            </div>


            <div class="box">
                <h3>0</h3>
                <p>Bài kiểm tra</p>
            </div>

        </div>




        <form action="{{ route('profile.update') }}" method="POST">

            @csrf
            @method('PUT')


            <div class="form-group">

                <label>Họ và tên</label>

                <input 
                    type="text"
                    name="name"
                    value="{{ old('name',$user->name) }}"
                >

                @error('name')
                    <div class="error">
                        {{ $message }}
                    </div>
                @enderror

            </div>



            <div class="form-group">

                <label>Email</label>

                <input 
                    type="email"
                    name="email"
                    value="{{ old('email',$user->email) }}"
                >

                @error('email')
                    <div class="error">
                        {{ $message }}
                    </div>
                @enderror

            </div>



            <button class="btn btn-update">
                Lưu thay đổi
            </button>


        </form>



        <form action="{{ route('logout') }}" method="POST">

            @csrf

            <button class="btn btn-logout">
                Đăng xuất
            </button>

        </form>


    </div>

</div>


</body>
</html>