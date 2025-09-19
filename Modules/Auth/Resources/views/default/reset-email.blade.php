@extends('default::layouts.auth')

@section('content')
    <div class="account-pages min-vh-100 d-flex justify-content-center align-items-center py-0">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card overflow-hidden">
                        <div class="bg-primary bg-soft">
                            <div class="row">
                                <div class="col-7">
                                    <div class="text-primary p-4">
                                        <h5 class="text-primary"> Forgot Password?</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="auth-logo text-center">
                                <a href="/" class="auth-logo-light d-inline-block">
                                    <div class="avatar-md profile-user-wid mb-4">
                                        <span class="avatar-title rounded-circle bg-light">
                                            <img src="{{ asset('images/logo-sm.png') }}" alt=""
                                                 class="rounded-circle" height="34">
                                        </span>
                                    </div>
                                </a>
                            </div>

                            <div class="p-2">
                                <form class="form-horizontal" action="{{ route('auth.reset-otp') }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label for="useremail" class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" id="useremail"
                                               placeholder="Enter email" required>
                                        @error('email')
                                        <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>

                                    <div class="text-center">
                                        <button class="btn btn-primary w-md waves-effect waves-light"
                                                type="submit">Reset
                                        </button>
                                        <p class="mt-4">
                                            Remember It ?
                                            <a href="{{ route('auth.login') }}" class="fw-medium text-primary"> Sign
                                                In</a>
                                        </p>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                    <div class="mt-5 text-center">

                        <p>© {{ date('Y') }} All rights reserved.
                        </p>
                    </div>

                </div>
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
