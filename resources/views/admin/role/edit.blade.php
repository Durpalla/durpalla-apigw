@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content content-overlap">
    <div class="row">
        <div class="col-12">
            <!-- /.card-header -->
            <div class="col-md-7">
                <div class="card-body">
                    <form action="{{ route('dashboard.role.update', $role->id) }}" method="POST">
                        @csrf
                        {{ method_field('PUT') }}
                        <div class="form-group">
                            <label>Role name</label>
                            <div class="input-group">
                                <input type="text" name="name" value="{{ $role->name }}" class="form-control">
                                <div class="input-group-btn">
                                    <button class="btn btn-success pull-right" type="submit">Update</button>
                                </div>
                            </div>
                        </div>
                        <div class="box" style="padding:15px 0;">
                            <div class="row">
                                @foreach( $permissions as $key => $lists )
                                <div class="col-lg-3 col-md-3 col-sm-3 col-xs-12 box-item" id="permissionParent">

                                    <div class="box-part">
                                        <div class="title">
                                          <h4><input type="checkbox" class="checkedAll" id="{{ $key }}"> {{ ucfirst( $key ) }}</h4>
                                        </div>
                                        <div class="text">
                                            @foreach( $lists as $list )
                                            <div class="form-check">
                                              <label>
                                                    <input type="checkbox" class="checkedItem {{ $key }}" name="permission[]" value="{{ $list->id }}" @if( in_array($list->id, $rolePermissions) ) checked @endif> <span class="label-text">{{ ucwords( str_replace('-', ' ', $list->name ) ) }}</span>
                                                </label>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                            
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@section('header')
<style type="text/css">

</style>
@endsection

@section('footer')
<script>

</script>
@endsection