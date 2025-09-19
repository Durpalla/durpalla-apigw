@extends('default::layouts.master')

@section('content')
    <x-default::toolbar title="{{ $title ?? 'Update administer' }}">
    </x-default::toolbar>
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div id="collapse2" class="card-body">
                    <form class="form-horizontal" id="popup-validation" action="{{ route('user.update', $user->id) }}"
                          method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="control-label col-lg-4">Name</label>
                            <div class="col-lg-4">
                                <input type="text" value="{{ old('name', $user->name) }}" class="validate[required] form-control"
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
                                                @if(old('role', $user->roles->first()->id) == $role->id) selected @endif>{{ $role->name }}</option>
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
                                <input type="email" value="{{ old('email', $user->email) }}" class="validate[required] form-control"
                                       name="email"
                                       id="req" placeholder="Email">
                                @error('email')
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
                                    <option value="1" @if(old('status', $user->status) == 1) selected @endif>Active</option>
                                    <option value="0" @if(old('status', $user->status) == 0) selected @endif>Inactive</option>
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

@endsection
