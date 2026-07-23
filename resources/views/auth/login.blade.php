@extends('layouts.auth')

@section('title', 'Đăng nhập · LexiLingo')


@section('topbar-switch')
    <a class="topbar-switch" href="{{ route('register') }}">
        Đăng ký
    </a>
@endsection



@section('styles')

    .password-field {
        position: relative;
    }


    .password-field input {
        padding-right: 70px;
    }



    .inline-forgot {

        position: absolute;

        right: 16px;

        top: 50%;

        transform: translateY(-50%);


        color: #999;

        font-weight: 700;

        font-size: 13px;

        letter-spacing: .4px;

        cursor: pointer;

    }




    .forgot-link {

        text-align: center;

        margin-top: 16px;

    }



    .forgot-link a {

        color: #1cb0f6;

        font-weight: 700;

        font-size: 14px;

        text-transform: uppercase;

        letter-spacing: .3px;

    }




    /* SOCIAL BUTTON */

    .socials {

        display: flex;

        gap: 12px;

        margin-top: 16px;

    }



    .btn-social {

        flex: 1;

        display: flex;

        justify-content: center;

        align-items: center;


        padding: 13px;


        border-radius: 12px;


        text-decoration: none;


        font-weight: 700;


        font-size: 15px;


        transition: .25s;


        border: none;


        cursor: pointer;

    }




    /* FACEBOOK */

    .btn-social.facebook {

        background: #1877F2;

        color: white;


        box-shadow:

        0 5px 15px rgba(24,119,242,.35);

    }




    .btn-social.facebook:hover {


        background: #2d88ff;


        transform: translateY(-3px);


        box-shadow:

        0 8px 20px rgba(24,119,242,.45);

    }





    /* GOOGLE */


    .btn-social.google {


        background: #EA4335;


        color: white;


        box-shadow:

        0 5px 15px rgba(234,67,53,.35);

    }




    .btn-social.google:hover {


        background: #ff5a4f;


        transform: translateY(-3px);


        box-shadow:

        0 8px 20px rgba(234,67,53,.45);

    }




    .terms {


        margin-top: 20px;


        text-align: center;


        font-size: 13px;


        color: #777;


        line-height: 1.6;

    }


@endsection





@section('content')


<h1>Đăng nhập</h1>




@if(session('success'))

    <div class="flash-success">

        {{ session('success') }}

    </div>

@endif





<form action="{{ route('login.store') }}" method="POST">


    @csrf



    <div class="field-group">



        <input

            type="email"

            name="email"

            value="{{ old('email') }}"

            placeholder="Email hoặc tên đăng nhập">





        <div class="password-field">



            <input

                type="password"

                name="password"

                placeholder="Mật khẩu">





            <a

                class="inline-forgot"

                href="{{ route('password.request') }}">

                QUÊN?

            </a>



        </div>



    </div>





    @error('email')

        <div class="field-error">

            {{ $message }}

        </div>

    @enderror





    @error('password')

        <div class="field-error">

            {{ $message }}

        </div>

    @enderror





    <button type="submit" class="btn-submit">

        Đăng nhập

    </button>






    <div class="forgot-link">


        <a href="{{ route('password.request') }}">

            Quên mật khẩu?

        </a>


    </div>






    <div class="divider">


        <div class="line"></div>


        <span>HOẶC</span>


        <div class="line"></div>


    </div>







    <div class="socials">



        <a

            href="{{ route('facebook.login') }}"

            class="btn-social facebook">


            Facebook


        </a>






        <a

            href="{{ route('google.login') }}"

            class="btn-social google">


            Google


        </a>



    </div>







    <p class="terms">


        Khi đăng nhập, bạn đồng ý với


        <b>Điều khoản</b>


        và


        <b>Chính sách bảo mật</b>


        của LexiLingo.


    </p>





</form>






<p class="footer-link">


    Chưa có tài khoản?


    <a href="{{ route('register') }}">

        Đăng ký

    </a>


</p>



@endsection