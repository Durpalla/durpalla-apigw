@extends('metis::layouts.master')

@section('header')
@endsection


@section('content')
    <div id="content">
        <div class="outer">
            <div class="inner bg-light no-padding">
                <h3><i class="fa fa-plus"></i> {{ $title ?? 'Reset Your Password'}}</h3>
                <div>
                    <ul class="nav nav-tabs menuTab" role="tablist">
                        <li role="presentation" class="active">
                            <a href="#home" aria-controls="home" role="tab" data-toggle="tab"
                               aria-expanded="true">Info</a>
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

                                        <div class="form-group">
                                            <label>Old Password</label>
                                            <input type="text" class="validate[required] form-control"
                                                   name="old_password"
                                                   id="req" value="{{old('old_password')}}" placeholder="old password"
                                                   required>
                                            @error('old_password')
                                            <div class="alert alert-danger">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>New Password</label>
                                            <input type="text" class="validate[required] form-control" name="password"
                                                   id="req" value="{{old('password')}}" placeholder="new password"
                                                   required>
                                            @error('password')
                                            <div class="alert alert-danger">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Confirm Password</label>
                                            <input type="text" class="validate[required] form-control"
                                                   name="password_confirm"
                                                   id="req" value="{{old('password_confirm')}}"
                                                   placeholder="confirm your password" required>
                                            @error('password_confirm')
                                            <div class="alert alert-danger">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary">Submit</button>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.inner -->
        </div>
        <!-- /.outer -->
    </div>

@endsection
