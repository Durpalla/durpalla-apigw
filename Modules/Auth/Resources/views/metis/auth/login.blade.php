@extends('metis::layouts.auth')

@section('content')
    <style>
        .password-container {
            position: relative;
            width: 290px;
        }
        .password-input {
            width: 100%;
            padding: 10px;
            font-size: 16px;
        }
        .eye-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
    <div class="form-signin">
        <div class="text-center">
            <img src="/images/logo.png" class="logo" alt="Kartat Logo">
        </div>
        <hr style="margin-bottom: 0">
        <div class="tab-content">
            <div id="login" class="tab-pane active">
                <form action="{{ route('auth.login.post') }}" method="POST">
                    @csrf
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <p class="text-muted text-center">
                            Enter your credentials
                        </p>
                    @endif
                    <input type="hidden" name="g-recaptcha-response" id="recaptcha_token">
                    <input name="email" value="{{ old('email') }}" type="text" placeholder="Email" class="form-control top">
                    <div class="password-container">
                    <input name="password" value="{{ old('password') }}" type="password" placeholder="Password" class="form-control bottom password-input" id="password">
                    <i class="fa fa-eye eye-icon" id="toggle-password"></i>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input name="rememberme" type="checkbox"> Remember Me
                        </label>
                    </div>
                    <div class="form-group text-center">
                        <a href="{{route('auth.forgot-password')}}" class="btn btn-link">
                            Forgot Your Password?
                        </a>
                    </div>
                    <button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://www.google.com/recaptcha/api.js?render={{ config('auth.recaptcha.site_key') }}"></script>
    <script>
        $(document).ready(function() {
            $('#toggle-password').click(function() {
                const passwordField = $('#password');
                const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', type);

                // Toggle the eye icon (open/closed)
                $(this).toggleClass('fa-eye fa-eye-slash');
            });
            grecaptcha.ready(function () {
                grecaptcha.execute('{{ config('auth.recaptcha.site_key') }}', { action: 'submit' }).then(function (token) {
                    document.getElementById('recaptcha_token').value = token;
                });
            });
        });
    </script>
@endsection
