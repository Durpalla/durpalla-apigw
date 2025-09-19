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
                                        <h5 class="text-primary"> Reset Password?</h5>
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
                                <form class="form-horizontal" action="{{ route('auth.reset-password') }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="token" value="{{ $token }}">
                                    <input type="hidden" name="g-recaptcha-response" id="recaptcha_token">

                                    <div class="mb-3">
                                        <label class="form-label" for="password">Password</label>
                                        <div
                                            class="input-group auth-pass-inputgroup @error('password') is-invalid @enderror">
                                            <input type="password" name="password"
                                                   class="form-control @error('password') is-invalid @enderror"
                                                   id="password" placeholder="Enter password"
                                                   aria-label="Password" aria-describedby="toggle-password">
                                            <button class="btn btn-light pw-toggle" type="button" id="toggle-password"
                                                    data-target="password">
                                                <i class="mdi mdi-eye-outline"></i>
                                            </button>
                                            @error('password')
                                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>

                                        {{-- Live checklist --}}
                                        <ul class="list-unstyled mt-2" id="pw-rules">
                                            <li data-rule="length" class="d-flex align-items-center">
                                                <i class="mdi mdi-close-circle-outline me-2"></i> More than 8 characters
                                            </li>
                                            <li data-rule="upper" class="d-flex align-items-center">
                                                <i class="mdi mdi-close-circle-outline me-2"></i> An uppercase character
                                            </li>
                                            <li data-rule="lower" class="d-flex align-items-center">
                                                <i class="mdi mdi-close-circle-outline me-2"></i> A lowercase character
                                            </li>
                                            <li data-rule="number" class="d-flex align-items-center">
                                                <i class="mdi mdi-close-circle-outline me-2"></i> A number
                                            </li>
                                            <li data-rule="special" class="d-flex align-items-center">
                                                <i class="mdi mdi-close-circle-outline me-2"></i> A special character
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirm Password</label>
                                        <div
                                            class="input-group auth-pass-inputgroup @error('password_confirmation') is-invalid @enderror">
                                            <input type="password" name="password_confirmation"
                                                   class="form-control @error('password_confirmation') is-invalid @enderror"
                                                   id="password_confirmation" placeholder="Enter password"
                                                   aria-label="Password" aria-describedby="password_confirmation">
                                            <button class="btn btn-light pw-toggle" type="button" id="toggle-password2"
                                                    data-target="password_confirmation">
                                                <i class="mdi mdi-eye-outline"></i>
                                            </button>
                                            @error('password_confirmation')
                                            <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <button class="btn btn-primary w-md waves-effect waves-light"
                                                type="submit">Reset
                                        </button>
                                    </div>

                                </form>
                            </div>

                        </div>
                    </div>
                    <div class="mt-5 text-center">
                        <p>Remember It ? <a href="{{ route('auth.login') }}" class="fw-medium text-primary"> Sign In
                                here</a></p>
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
        (function () {
            const pwd = document.getElementById('password');
            const pwd2 = document.getElementById('password_confirmation');
            const rulesList = document.getElementById('pw-rules');
            const submitBtn = document.getElementById('submit-btn');
            const confirmHint = document.getElementById('confirm-hint');

            // Toggle eye buttons
            const bindEye = (inputId, btnId) => {
                const input = document.getElementById(inputId);
                const btn = document.getElementById(btnId);
                btn?.addEventListener('click', () => {
                    const t = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', t);
                    const icon = btn.querySelector('i');
                    if (icon) icon.className = t === 'password' ? 'mdi mdi-eye-outline' : 'mdi mdi-eye-off-outline';
                });
            };
            bindEye('password', 'password-addon');
            bindEye('password_confirmation', 'password2-addon');

            // Regex rules
            const tests = {
                length: v => v.length > 8,
                upper: v => /[A-Z]/.test(v),
                lower: v => /[a-z]/.test(v),
                number: v => /\d/.test(v),
                special: v => /[^A-Za-z0-9]/.test(v),
            };

            // Update checklist UI
            function updateRules(v) {
                let passedAll = true;
                rulesList.querySelectorAll('li').forEach(li => {
                    const rule = li.getAttribute('data-rule');
                    const ok = tests[rule](v);
                    const icon = li.querySelector('i');

                    li.classList.toggle('text-success', ok);
                    li.classList.toggle('text-danger', !ok);

                    if (icon) {
                        icon.className = ok
                            ? 'mdi mdi-check-circle-outline me-2'
                            : 'mdi mdi-close-circle-outline me-2';
                    }
                    if (!ok) passedAll = false;
                });
                return passedAll;
            }

            // Confirm match UI
            function updateConfirm() {
                if (!pwd2.value.length) {
                    confirmHint.textContent = '';
                    confirmHint.className = 'form-text';
                    return;
                }
                if (pwd.value === pwd2.value) {
                    confirmHint.textContent = 'Passwords match.';
                    confirmHint.className = 'form-text text-success';
                } else {
                    confirmHint.textContent = 'Passwords do not match.';
                    confirmHint.className = 'form-text text-danger';
                }
            }

            // Wire inputs
            const maybeDisableSubmit = () => {
                const strong = updateRules(pwd.value);
                const match = pwd.value.length && pwd.value === pwd2.value;
                submitBtn.disabled = !(strong && match);
            };

            pwd.addEventListener('input', () => {
                updateRules(pwd.value);
                updateConfirm();
                maybeDisableSubmit();
            });
            pwd2.addEventListener('input', () => {
                updateConfirm();
                maybeDisableSubmit();
            });

            grecaptcha.ready(function () {
                grecaptcha.execute('{{ config('auth.recaptcha.site_key') }}', {action: 'submit'}).then(function (token) {
                    document.getElementById('recaptcha_token').value = token;
                });
            });

            bindToggle('password', 'toggle-password');
            bindToggle('password_confirmation', 'toggle-password2');

            // Initial
            updateRules('');
            maybeDisableSubmit();
        })();
    </script>
@endsection
