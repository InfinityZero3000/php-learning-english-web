<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'LexiLingo')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@700;800;900&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --green: #12b76a;
            --green-d: #039855;
            --green-dd: #027a48;
            --amber: #f79009;
            --amber-d: #dc6803;
            --ink: #0c1f16;
            --muted: #5c6b63;
            --bg: #f2f7f3;
            --card: #ffffff;
            --line: #e2ede7;
            --soft: #eaf5ef;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Be Vietnam Pro', system-ui, sans-serif;
            color: var(--ink);
            background: var(--card);
        }

        a { text-decoration: none; }

        .topbar {
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 22px;
            border-bottom: 2px solid #f0f0f0;
        }

        .topbar-close {
            border: none;
            background: transparent;
            cursor: pointer;
            color: #afafaf;
            font-size: 26px;
            font-weight: 700;
            line-height: 1;
            padding: 6px;
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .topbar-logo {
            width: 30px;
            height: 30px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--green), var(--green-d));
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .topbar-logo span {
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            color: #fff;
            font-size: 17px;
        }

        .topbar-brand strong {
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            font-size: 20px;
            color: var(--green-dd);
        }

        .topbar-switch {
            border: 2px solid #e5e5e5;
            background: #fff;
            cursor: pointer;
            color: var(--green-dd);
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: .4px;
            text-transform: uppercase;
            padding: 9px 16px;
            border-radius: 14px;
            box-shadow: 0 3px 0 #e5e5e5;
        }

        .wrap {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 56px 24px;
        }

        .panel {
            width: 100%;
            max-width: 380px;
        }

        h1 {
            font-family: 'Nunito', sans-serif;
            font-weight: 800;
            font-size: 30px;
            text-align: center;
            margin: 0 0 26px;
        }

        .subtitle {
            color: var(--muted);
            text-align: center;
            margin: 0 0 24px;
        }

        .flash-success {
            background: var(--soft);
            color: var(--green-dd);
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            padding: 12px 16px;
            border-radius: 12px;
            margin: 0 0 20px;
        }

        .flash-notice {
            background: #fff5e6;
            color: var(--amber-d);
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            padding: 12px 16px;
            border-radius: 12px;
            margin: 0 0 20px;
        }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .field-group input {
            width: 100%;
            border: 2px solid #e5e5e5;
            border-radius: 14px;
            background: #f7f7f7;
            padding: 15px 16px;
            font-family: 'Be Vietnam Pro', sans-serif;
            font-size: 16px;
            outline: none;
            color: var(--ink);
        }

        .field-group input:focus {
            background: #fff;
            border-color: #b7d9c8;
        }

        .field-error {
            color: #f04438;
            font-size: 13px;
            margin-top: 8px;
        }

        .btn-submit {
            display: block;
            width: 100%;
            margin-top: 18px;
            border: none;
            cursor: pointer;
            background: var(--green);
            color: #fff;
            font-family: 'Fredoka', sans-serif;
            font-weight: 700;
            font-size: 17px;
            letter-spacing: .5px;
            text-transform: uppercase;
            text-align: center;
            padding: 15px;
            border-radius: 16px;
            box-shadow: 0 4px 0 var(--green-dd);
        }

        .btn-submit:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 var(--green-dd);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0;
        }

        .divider .line {
            flex: 1;
            height: 2px;
            background: #f0f0f0;
        }

        .divider span {
            color: #afafaf;
            font-weight: 700;
            font-size: 13px;
        }

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


        border: none;


        font-family: 'Fredoka', sans-serif;

        font-weight: 700;

        font-size: 15px;


        padding: 14px;


        border-radius: 16px;


        cursor: pointer;


        text-decoration: none;


        opacity: 1;


        transition: all .25s ease;

    }



    /* FACEBOOK */

    .btn-social.facebook {

        background: #1877F2;


        color: white;


        box-shadow:

        0 4px 0 #0d5dcc;

    }



    .btn-social.facebook:hover {

        background: #2d88ff;


        transform: translateY(-2px);


        box-shadow:

        0 6px 0 #0d5dcc;

    }





    /* GOOGLE */

    .btn-social.google {


        background: #EA4335;


        color: white;


        box-shadow:

        0 4px 0 #b3261e;


    }



    .btn-social.google:hover {


        background: #ff5a4f;


        transform: translateY(-2px);


        box-shadow:

        0 6px 0 #b3261e;

    }
        .terms {
            text-align: center;
            color: #afafaf;
            font-size: 12px;
            line-height: 1.6;
            margin: 24px 0 0;
        }

        .terms b { color: #777; }

        .footer-link {
            text-align: center;
            color: #afafaf;
            font-size: 13px;
            margin: 24px 0 0;
        }

        .footer-link a {
            color: #1cb0f6;
            font-weight: 700;
        }

        .back-link {
            text-align: center;
            margin-top: 18px;
        }

        .back-link a {
            color: #1cb0f6;
            font-weight: 700;
            font-size: 14px;
        }

        @yield('styles')
    </style>
</head>
<body>

    <header class="topbar">
        <a class="topbar-close" href="{{ url('/') }}">&times;</a>
        @yield('topbar-switch')
    </header>

    <div class="wrap">
        <div class="panel">
            @yield('content')
        </div>
    </div>

</body>
</html>
