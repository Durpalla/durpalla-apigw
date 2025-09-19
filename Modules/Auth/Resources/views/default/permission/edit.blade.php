@extends('default::layouts.master')

@section('content')
    <x-default::toolbar title="{{ $title ?? 'Add new permission' }}"></x-default::toolbar>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form class="form-horizontal" id="popup-validation"
                          action="{{ route('permission.update', $permission->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label class="control-label col-lg-4">Permission name</label>
                            <div class="col-lg-4">
                                <input type="text" value="{{ old('name', $permission->name) }}"
                                       class="validate[required] form-control" name="name"
                                       id="req" placeholder="Permission name">
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
    </div>
@endsection
