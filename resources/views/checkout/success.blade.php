<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Success - Jolzan</title>
    <link rel="icon" href="{{ asset('icons/favicon.ico')}}">
    <link rel="apple-touch-icon" sizes="57x57" href="{{ asset('icons/apple-icon-57x57.png')}}">
    <link rel="apple-touch-icon" sizes="60x60" href="{{ asset('icons/apple-icon-60x60.png')}}">
    <link rel="apple-touch-icon" sizes="72x72" href="{{ asset('icons/apple-icon-72x72.png')}}">
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('icons/apple-icon-76x76.png')}}">
    <link rel="apple-touch-icon" sizes="114x114" href="{{ asset('icons/apple-icon-114x114.png')}}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('icons/apple-icon-120x120.png')}}">
    <link rel="apple-touch-icon" sizes="144x144" href="{{ asset('icons/apple-icon-144x144.png')}}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('icons/apple-icon-152x152.png')}}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-icon-180x180.png')}}">
    <link rel="icon" type="image/png" sizes="192x192"  href="{{ asset('icons/android-icon-192x192.png')}}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32x32.png')}}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('icons/favicon-96x96.png')}}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/favicon-16x16.png')}}">
    <link href="https://fonts.googleapis.com/css?family=Roboto|Varela+Round" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <style>
        body {
            font-family: 'Varela Round', sans-serif;
        }

        .modal-confirm {
            color: #636363;
            width: 325px;
            margin: 30px auto;
        }

        .modal-confirm .modal-content {
            padding: 20px;
            border-radius: 5px;
            border: none;
        }

        .modal-confirm .modal-header {
            border-bottom: none;
            position: relative;
        }

        .modal-confirm h4 {
            text-align: center;
            font-size: 26px;
            margin: 30px 0 -15px;
        }

        .modal-confirm .form-control, .modal-confirm .btn {
            border-radius: 3px;
        }

        .modal-confirm .close {
            position: absolute;
            top: -5px;
            right: -5px;
        }

        .modal-confirm .modal-footer {
            border: none;
            text-align: center;
            border-radius: 5px;
            font-size: 13px;
        }

        .modal-confirm .icon-box {
            color: #fff;
            position: absolute;
            margin: 0 auto;
            left: 0;
            right: 0;
            top: -70px;
            width: 95px;
            height: 95px;
            border-radius: 50%;
            z-index: 9;
            background: #82ce34;
            padding: 15px;
            text-align: center;
            box-shadow: 0px 2px 2px rgba(0, 0, 0, 0.1);
        }

        .modal-confirm .icon-box i {
            font-size: 58px;
            position: relative;
            top: 3px;
        }

        .modal-confirm.modal-dialog {
            margin-top: 80px;
        }

        .modal-confirm .btn {
            color: #fff;
            border-radius: 4px;
            background: #82ce34;
            text-decoration: none;
            transition: all 0.4s;
            line-height: normal;
            border: none;
        }

        .modal-confirm .btn:hover, .modal-confirm .btn:focus {
            background: #6fb32b;
            outline: none;
        }

        .trigger-btn {
            display: inline-block;
            margin: 100px auto;
        }
    </style>
</head>
<body>
<!-- Modal HTML -->
<div style="display: block;">
    <div class="modal-dialog modal-confirm">
        <div class="modal-content">
            <div class="modal-header">
                <div class="icon-box">
                    <i class="material-icons">&#xE876;</i>
                </div>
                <h4 class="modal-title">Success!</h4>
            </div>
            <div class="modal-body">
                <p class="text-center">Your booking has been confirmed. Check your email for detials.</p>
            </div>
                        <div class="modal-footer">
                            <a href="{{ $url }}" class="btn btn-success btn-block">View details</a>
                        </div>
        </div>
    </div>
</div>
<script type="text/javascript">

    $(document).ready(function () {

        window.history.pushState(null, "", window.location.href);

        window.onpopstate = function () {

            window.history.pushState(null, "", window.location.href);

        };

    });

</script>
</body>
</html>
