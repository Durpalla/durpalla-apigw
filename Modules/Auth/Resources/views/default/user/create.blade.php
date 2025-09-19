@extends('default::layouts.master')

@section('content')
    <x-default::toolbar title="{{ $title ?? 'Add New Administrator' }}">
    </x-default::toolbar>
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div id="collapse2" class="card-body">
                    <form class="form-horizontal" id="popup-validation" action="{{ route('user.store') }}"
                          method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="control-label col-lg-4">Name</label>
                            <div class="col-lg-4">
                                <input type="text" value="{{ old('name') }}" class="validate[required] form-control"
                                       name="name"
                                       id="req" placeholder="Name">
                                @error('name')
                                <div class="help-block with-errors text-danger"><i
                                        class="fa fa-times"></i> {{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="control-label col-lg-4">Role</label>
                            <div class="col-lg-4">
                                <select name="role" value="{{ old('role') }}" class="validate[required] form-control">
                                    <option value="">Select role</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}"
                                                @if(old('role') == $role->id) selected @endif>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('role')
                                <div class="help-block with-errors text-danger"><i
                                        class="fa fa-times"></i> {{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="control-label col-lg-4">Email</label>
                            <div class="col-lg-4">
                                <input type="email" value="{{ old('email') }}" class="validate[required] form-control"
                                       name="email"
                                       id="req" placeholder="Email">
                                @error('email')
                                <div class="help-block with-errors text-danger"><i
                                        class="fa fa-times"></i> {{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="control-label col-lg-4">Password</label>
                            <div class="col-lg-4">
                                <div
                                    class="input-group auth-pass-inputgroup @error('password') is-invalid @enderror">
                                    <input type="password" value="{{ old('password') }}"
                                           class="validate[required] form-control" name="password"
                                           id="password" placeholder="Password">

                                    <button class="btn btn-light pw-toggle" type="button" id="toggle-password"
                                            data-target="password">
                                        <i class="mdi mdi-eye-outline"></i>
                                    </button>
                                </div>
                                @error('password')
                                <div class="help-block with-errors text-danger"><i
                                        class="fa fa-times"></i> {{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="control-label col-lg-4">Password confirm</label>
                            <div class="col-lg-4">
                                <div
                                    class="input-group auth-pass-inputgroup @error('password_confirmation') is-invalid @enderror">
                                    <input type="password" class="validate[required] form-control"
                                           name="password_confirm"
                                           id="password_confirmation" placeholder="Password confirm">
                                    <button class="btn btn-light pw-toggle" type="button" id="toggle-password2"
                                            data-target="password_confirmation">
                                        <i class="mdi mdi-eye-outline"></i>
                                    </button>
                                </div>
                                @error('password_confirm')
                                <div class="help-block with-errors text-danger"><i
                                        class="fa fa-times"></i> {{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="control-label col-lg-4">Status</label>
                            <div class="col-lg-4">
                                <select name="status" value="{{ old('status') }}"
                                        class="validate[required] form-control">
                                    <option value="">Select status</option>
                                    <option value="1" @if(old('status') == 1) selected @endif>Active</option>
                                    <option value="0" @if(old('status') == 0) selected @endif>Inactive</option>
                                </select>
                                @error('status')
                                <div class="help-block with-errors text-danger"><i
                                        class="fa fa-times"></i> {{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="form-actions no-margin-bottom">
                            <label class="control-label col-lg-4"></label>
                            <div class="col-lg-4">
                                <button type="submit" class="btn btn-primary">CREATE</button>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- /.col-lg-12 -->
    </div>
    <!-- /.row -->
    <!-- /#content -->
@endsection

@section('footer')
    <script>
        (function () {
            bindToggle('password', 'toggle-password');
            bindToggle('password_confirmation', 'toggle-password2');
        })();
    </script>
@endsection
