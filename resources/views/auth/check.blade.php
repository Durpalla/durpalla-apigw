@extends('layouts.modal')

@section('content')
<!-- Automatic element centering -->
<div class="lockscreen-wrapper">
  <div class="lockscreen-logo">
    <!-- <a href="/"><b>Jol</b>Zatra</a> -->
    <a href="/"><img src="{{ asset('default/logo2.png')}}" style="position: relative; width: 70%; height: auto; vertical-align: top;"></a>
  </div>
  <!-- User name -->
  <div class="lockscreen-name text-center">{{ $user->name }}</div>

  <!-- START LOCK SCREEN ITEM -->
  <div class="lockscreen-item">
    <!-- lockscreen image -->
    <div class="lockscreen-image">
      <img src="{{ ($user->profile_pic) ? asset($user->profile_pic) : asset('default/avatar.png')}} ">
    </div>
    <!-- /.lockscreen-image -->

    <!-- lockscreen credentials (contains the form) -->
    <form action="{{ route('auth.verify') }}" class="lockscreen-credentials" method="POST">
        @csrf
        <input type="hidden" name="email" value="{{ $user->email }}">
      <div class="input-group">
        <input type="password" name="otp_code" class="form-control" placeholder="OTP code" required>

        <div class="input-group-append">
          <button type="submit" class="btn"><i class="fas fa-arrow-right text-muted"></i></button>
        </div>
      </div>
    </form>
    <!-- /.lockscreen credentials -->

  </div>
  <!-- /.lockscreen-item -->
  <div class="help-block text-center">
    <span class="alert" style="background: #e9ffe8">We have sent an otp code to your email.</span>
  </div>
</div>
<!-- /.center -->
@endsection
@section('header')
<style type="text/css">
    .lockscreen-wrapper {
        margin-top: -10% !important;
    }

    .lockscreen {
        position: relative;
    }
    .lockscreen img {
        position: relative;
        width: 70%;
        height: auto;
        vertical-align: top;
    }
</style>
@endsection
