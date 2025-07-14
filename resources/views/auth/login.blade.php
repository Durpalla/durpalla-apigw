@extends('layouts.modal')

@section('content')
<div class="login-box">
  <div class="login-logo">
    <!-- <a href="/"><b>Jol</b>Zatra</a> -->
    <a href="/"><img src="{{ asset('default/logo2.png')}}"></a>
</div>
<!-- /.login-logo -->
<div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">Log in</p>

      <form action="{{ route('auth.login') }}" method="post">
        @csrf
        <div class="input-group mb-3">
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="Email address" required autocomplete="email" autofocus>
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-envelope"></span>
                </div>
            </div>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>
        <!-- <div class="input-group mb-3">
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" value="{{ old('password') }}" placeholder="Password"  required autocomplete="current-password">
            <div class="input-group-append">
                <div class="input-group-text">
                  <span class="fas fa-lock"></span>
              </div>
            </div>
            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div> -->
        <div class="row">
            <!-- /.col -->
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-block">Send code</button>
            </div>
            <!-- /.col -->
        </div>
    </form>

      <!-- <div class="social-auth-links text-center mb-3">
        <p>- OR -</p>
        <a href="#" class="btn btn-block btn-primary">
          <i class="fab fa-facebook mr-2"></i> Sign in using Facebook
        </a>
        <a href="#" class="btn btn-block btn-danger">
          <i class="fab fa-google-plus mr-2"></i> Sign in using Google+
        </a>
    </div> -->
    <!-- /.social-auth-links -->

    <!-- <p class="mb-1">
        <a href="{{ route('login') }}">I forgot my password</a>
    </p> -->
      <!-- <p class="mb-0">
        <a href="register.html" class="text-center">Register a new membership</a>
    </p> -->
</div>
<!-- /.login-card-body -->
</div>
</div>
<!-- /.login-box -->
@endsection
@section('header')
<style type="text/css">
    .login-logo {
        position: relative;
    }
    .login-logo img {
        position: relative;
        width: 70%;
        height: auto;
        vertical-align: top;
    }
</style>
@endsection
