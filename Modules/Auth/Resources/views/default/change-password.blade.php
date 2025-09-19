@extends('default::layouts.master')

@section('header')
@endsection


@section('content')
    <x-default::toolbar title="{{ $title ?? 'Change password' }}"></x-default::toolbar>

    <div>
        <ul class="nav nav-tabs menuTab" role="tablist">
            <li role="presentation" class="active">
                <a href="#home" aria-controls="home" role="tab" data-toggle="tab" aria-expanded="true">Info</a>
            </li>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content">
            <div role="tabpanel" class="tab-pane fade active in" id="home">
                <form action="{{ route('auth.update-password') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-lg-7">

                            <div class="form-group @error('old_password') has-error @enderror">
                                <label for="old_password">Old Password</label>
                                <input type="password" class="form-control" name="old_password" id="old_password"
                                       value="{{ old('old_password') }}" placeholder="Enter old password" required>
                                @error('old_password')
                                <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group @error('password') has-error @enderror">
                                <label for="password">New Password</label>
                                <input type="password" class="form-control" name="password" id="password"
                                       value="{{ old('password') }}" placeholder="Enter new password" required>
                                @error('password')
                                <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group @error('password_confirmation') has-error @enderror">
                                <label for="password_confirmation">Confirm Password</label>
                                <input type="password" class="form-control" name="password_confirmation"
                                       id="password_confirmation"
                                       value="{{ old('password_confirmation') }}" placeholder="Confirm new password"
                                       required>
                                @error('password_confirmation')
                                <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Update Password</button>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <!-- Optional: Add instructions or links here -->
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
