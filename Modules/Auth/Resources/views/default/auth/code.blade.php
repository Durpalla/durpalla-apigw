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
                            <div class="p-2 mt-4 text-center">

                                <h4>Verify your email</h4>
                                <p class="mb-5">Please enter the 6 digit code sent to <span
                                        class="fw-semibold">{{ \App\Helpers\CommonHelper::maskEmail($email) }}</span>
                                </p>

                                <form method="POST" action="{{ route('auth.2fa.post') }}">
                                    @csrf
                                    <input type="hidden" name="code" id="otp">
                                    <input type="hidden" name="token" value="{{ $token }}">
                                    <input type="hidden" name="g-recaptcha-response" id="recaptcha_token">
                                    <div class="row">
                                        <div class="col-2">
                                            <div class="mb-3">
                                                <label for="digit1" class="visually-hidden">Digit 1</label>
                                                <input type="text"
                                                       class="form-control form-control-lg text-center two-step"
                                                       id="digit1-input" maxLength="1">
                                            </div>
                                        </div>

                                        <div class="col-2">
                                            <div class="mb-3">
                                                <label for="digit2" class="visually-hidden">Digit 2</label>
                                                <input type="text"
                                                       class="form-control form-control-lg text-center two-step"
                                                       id="digit2-input" maxLength="1">
                                            </div>
                                        </div>

                                        <div class="col-2">
                                            <div class="mb-3">
                                                <label for="digit3" class="visually-hidden">Digit 3</label>
                                                <input type="text"
                                                       class="form-control form-control-lg text-center two-step"
                                                       id="digit3-input" maxLength="1">
                                            </div>
                                        </div>

                                        <div class="col-2">
                                            <div class="mb-3">
                                                <label for="digit4" class="visually-hidden">Digit 4</label>
                                                <input type="text"
                                                       class="form-control form-control-lg text-center two-step"
                                                       id="digit4-input" maxLength="1">
                                            </div>
                                        </div>

                                        <div class="col-2">
                                            <div class="mb-3">
                                                <label for="digit4" class="visually-hidden">Digit 5</label>
                                                <input type="text"
                                                       class="form-control form-control-lg text-center two-step"
                                                       id="digit4-input" maxLength="1">
                                            </div>
                                        </div>

                                        <div class="col-2">
                                            <div class="mb-3">
                                                <label for="digit4" class="visually-hidden">Digit 6</label>
                                                <input type="text"
                                                       class="form-control form-control-lg text-center two-step"
                                                       id="digit4-input" maxLength="1">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" id="otpSubmit" class="btn btn-success w-md" disabled>
                                            Confirm
                                        </button>
                                        <p class="pt-3">
                                            <a href="{{ route('auth.login') }}" class="mt-4">
                                                <i class="mdi mdi-lock me-1"></i> Back to login
                                            </a>
                                        </p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-5 text-center">
                <p>© {{ date('Y') }} All rights reserved
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

            const $inputs = $('.two-step');
            const $submit = $('#otpSubmit');
            const $otpHidden = $('#otp');

            function digitsOnly(s) {
                return (s || '').replace(/\D/g, '');
            }

            function updateOtpAndButton() {
                const code = $inputs.map(function () {
                    return $(this).val();
                }).get().join('');
                $otpHidden.val(code);
                $submit.prop('disabled', code.length !== 6);
            }

            // Auto-advance on input; keep only 1 digit
            $inputs.on('input', function (e) {
                let v = digitsOnly($(this).val());
                if (v.length > 1) v = v[0];
                $(this).val(v);

                if (v) {
                    // focus next empty input
                    const $next = $(this).closest('.col-2').nextAll().find('.two-step').first();
                    if ($next.length) $next.focus().select();
                }
                updateOtpAndButton();
            });

            // Keyboard navigation/backspace
            // Keyboard navigation/backspace (continuous clear left)
            // Keyboard navigation/backspace (continuous clear left)
            // Backspace: clear current and jump left (one-by-one)
            $inputs.on('keydown', function (e) {
                if (e.key !== 'Backspace') return;

                e.preventDefault(); // we control behavior completely

                const i = $inputs.index(this);
                const $cur = $(this);

                if ($cur.val()) {
                    // Clear current, then jump left
                    $cur.val('');
                    updateOtpAndButton();

                    if (i > 0) {
                        const $prev = $inputs.eq(i - 1);
                        $prev.focus().select();
                    }
                    return;
                }

                // Current already empty -> clear previous and stay there
                if (i > 0) {
                    const $prev = $inputs.eq(i - 1);
                    if ($prev.val()) {
                        $prev.val('');
                        updateOtpAndButton();
                    }
                    $prev.focus().select();
                }
            });

            // Paste full code into any box
            $inputs.on('paste', function (e) {
                const text = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
                const code = digitsOnly(text).slice(0, 6);
                if (!code) return;

                // fill sequentially from the first input
                $inputs.each(function (i) {
                    $(this).val(code[i] || '');
                });

                // focus last filled or next
                const lastIndex = code.length;
                const $focus = $inputs.filter('[data-index="' + Math.min(lastIndex + 1, 6) + '"]');
                ($focus.length ? $focus : $inputs.last()).focus().select();

                updateOtpAndButton();
                e.preventDefault();
            });

            // Focus first box on load
            $(function () {
                $inputs.first().focus();
            });
        })();
    </script>
@endsection
