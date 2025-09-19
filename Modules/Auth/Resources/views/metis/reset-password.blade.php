

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
            <img src="/images/logo.png" class="auth-logo" alt="Metis Logo">
        </div>
        <hr style="margin-bottom: 0">
        <div class="tab-content">
            <div id="login" class="tab-pane active">
                <form action="{{route('auth.reset-password')}}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="form-group">
                        <label>Reset Password</label>
                        <div class="password-container">
                        <input type="password" class="validate[required] form-control password-input" name="password"
                               id="password" value="{{old('password')}}" placeholder="new password"
                               required>
                        <i class="fa fa-eye eye-icon" id="toggle-password"></i>
                        </div>
                        @error('password')
                        <div class="alert alert-danger">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label>Confirm Reset Password</label>
                        <div class="password-container">
                        <input type="password" class="validate[required] form-control password-input"
                               name="password_confirm"
                               id="password-confirm" value="{{old('password_confirm')}}"
                               placeholder="confirm your password" required>
                        <i class="fa fa-eye eye-icon" id="toggle-password-confirm"></i>
                        </div>
                        @error('password_confirm')
                        <div class="alert alert-danger">{{ $message }}</div> @enderror
                    </div>



                    <button class="btn btn-lg btn-primary btn-block" type="submit">SUBMIT</button>
                </form>
            </div>

        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#toggle-password').click(function() {
                const passwordField = $('#password');
                const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', type);

                // Toggle the eye icon (open/closed)
                $(this).toggleClass('fa-eye fa-eye-slash');
            });
            $('#toggle-password-confirm').click(function() {
                const passwordField = $('#password-confirm');
                const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', type);

                // Toggle the eye icon (open/closed)
                $(this).toggleClass('fa-eye fa-eye-slash');
            });
        });
    </script>

@endsection

