@extends('metis::layouts.auth')

@section('header')
@endsection

@section('content')
    <div class="form-signin">
        <div class="text-center">
            <img src="/images/logo.png" class="auth-logo" alt="Metis Logo">
        </div>
        <hr>
        <div class="tab-content">
            <div id="forgot" class="tab-pane active">
                <form action="{{ route('auth.verify-reset-otp') }}" method="POST">
                    @csrf
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <input type="hidden" name="g-recaptcha-response" id="recaptcha_token">
                    <input type="hidden" name="token" value="{{ $token }}">
                    <p class="text-muted text-center">Enter your OTP code to verify</p>
                    <input name="code" value="{{ old('code') }}" type="text" placeholder="Enter OTP" class="form-control is-invalid">
                    <br>
                    <button class="btn btn-lg btn-primary btn-block" type="submit">Verify</button>
                </form>
            </div>
        </div>
    </div>
@endsection


@section('footer')
    <script src="https://www.google.com/recaptcha/api.js?render={{ config('auth.recaptcha.site_key') }}"></script>
    <script>
        $(document).ready(function() {
            grecaptcha.ready(function () {
                grecaptcha.execute('{{ config('auth.recaptcha.site_key') }}', {action: 'submit'}).then(function (token) {
                    document.getElementById('recaptcha_token').value = token;
                });
            });
        });
    </script>
@endsection
