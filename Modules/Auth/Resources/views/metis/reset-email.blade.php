@extends('metis::layouts.auth')

@section('content')
    <div class="form-signin">
        <div class="text-center">
            <img src="/images/logo.png" class="auth-logo" alt="Kartat Logo">
        </div>
        <hr style="margin-bottom: 0">
        <div class="tab-content">
            <div id="login" class="tab-pane active">
                <form action="{{route('auth.reset-otp')}}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label>Your Email</label>
                        <input type="text" class="validate[required] form-control" name="email"
                               id="req" value="{{old('email')}}" placeholder="Enter Registered Email"
                               required>

                        @error('email')
                        <div class="alert alert-danger">{{ $message }}</div>
                        @enderror
                    </div>


                    <button class="btn btn-lg btn-primary btn-block" type="submit">SUBMIT</button>
                    <div class="form-group text-center">
                        <a href="{{route('auth.login')}}" class="btn btn-link">
                            Back to Login
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </div>
@endsection

@section('footer')
    <script src="https://www.google.com/recaptcha/api.js?render={{ config('auth.recaptcha.site_key') }}"></script>
    <script>
        $(document).ready(function () {
            grecaptcha.ready(function () {
                grecaptcha.execute('{{ config('auth.recaptcha.site_key') }}', {action: 'submit'}).then(function (token) {
                    document.getElementById('recaptcha_token').value = token;
                });
            });
        });
    </script>
@endsection
