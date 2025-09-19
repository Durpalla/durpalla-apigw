@extends('metis::layouts.master')

@section('content')
    <div id="content">
        <div class="outer">
            <div class="inner bg-light lter">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="box">
                            <header class="dark">
                                <div class="icons"><i class="fa fa-check"></i></div>
                                <h5>{{ $title ?? 'Update administer' }}</h5>
                                <!-- .toolbar -->
                                <div class="toolbar">
                                    <nav style="padding: 8px;">
                                        <a href="{{ route('user.index') }}" class="btn btn-default btn-sm">
                                            <i class="fa fa-angle-left"></i> back
                                        </a>
                                    </nav>
                                </div>
                                <!-- /.toolbar -->
                            </header>
                            <div id="collapse2" class="body">
                                <form class="form-horizontal" id="popup-validation"
                                      action="{{ route('user.update', $user->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-group">
                                        <label class="control-label col-lg-4">Name</label>
                                        <div class="col-lg-4">
                                            <input type="text" value="{{ old('name', $user->name) }}"
                                                   class="validate[required] form-control" name="name"
                                                   id="req" placeholder="Name">
                                            @error('name')
                                            <div class="help-block with-errors text-danger"><i
                                                    class="fa fa-times"></i> {{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="control-label col-lg-4">Role</label>
                                        <div class="col-lg-4">
                                            <select name="role" value="{{ old('role', $user->roles()->first()->id ?? '') }}"
                                                    class="validate[required] form-control">
                                                <option value="">Select role</option>
                                                @foreach($roles as $role)
                                                    <option value="{{ $role->id }}"
                                                            @if(old('role', $user->roles()->first()->id ?? '') == $role->id) selected @endif>{{ $role->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('role')
                                            <div class="help-block with-errors text-danger"><i
                                                    class="fa fa-times"></i> {{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="control-label col-lg-4">Email</label>
                                        <div class="col-lg-4">
                                            <input type="email" value="{{ old('email', $user->email) }}"
                                                   class="validate[required] form-control" name="email"
                                                   id="req" placeholder="Email">
                                            @error('email')
                                            <div class="help-block with-errors text-danger"><i
                                                    class="fa fa-times"></i> {{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="control-label col-lg-4">Password</label>
                                        <div class="col-lg-4">
                                            <input type="password" class="validate[required] form-control"
                                                   name="password"
                                                   id="req" placeholder="Password" autocomplete="new-password">

                                            @error('password')
                                            <div class="help-block with-errors text-danger"><i
                                                    class="fa fa-times"></i> {{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="control-label col-lg-4">Password confirm</label>
                                        <div class="col-lg-4">
                                            <input type="password" class="validate[required] form-control"
                                                   name="password_confirm"
                                                   id="req" placeholder="Password confirm">
                                            @error('password_confirm')
                                            <div class="help-block with-errors text-danger"><i
                                                    class="fa fa-times"></i> {{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    @if(\App\Helpers\CommonHelper::hasPermission(['user-action']))
                                        <div class="form-group">
                                            <label class="control-label col-lg-4">Status</label>
                                            <div class="col-lg-4">
                                                <select name="status" value="{{ old('status') }}"
                                                        class="validate[required] form-control">
                                                    <option value="">Select status</option>
                                                    <option value="1" @if(old('status', 1) == 1) selected @endif>
                                                        Active
                                                    </option>
                                                    <option value="0" @if(old('status') == 0) selected @endif>Inactive
                                                    </option>
                                                </select>
                                                @error('status')
                                                <div class="help-block with-errors text-danger"><i
                                                        class="fa fa-times"></i> {{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    @endif

                                    <div class="form-actions no-margin-bottom">
                                        <label class="control-label col-lg-4"></label>
                                        <div class="col-lg-4">
                                            <button type="submit" class="btn btn-primary">UPDATE</button>
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
            </div>
            <!-- /.inner -->
        </div>
        <!-- /.outer -->
    </div>
    <!-- /#content -->
@endsection
