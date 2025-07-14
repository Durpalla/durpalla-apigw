@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card" style="background-color: none;">
                <div class="card-header">
                    <h3 class="card-title">{{ $title ?? '' }}</h3>
                    <div class="card-tools">
                        <div class="btn-group" role="group" aria-label="Basic example">
                            @can('role-list')
                            <a href="{{ route('dashboard.role.index') }}" class="btn btn-xs btn-default"><i class="fa fa-tags"></i> Roles</a>
                            @endcan
                            @can('user-list')
                            <a href="{{ route('dashboard.user.index') }}" class="btn btn-xs btn-default"><i class="fa fa-user-secret"></i> Users</a>
                            @endcan
                        </div>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                        <div class="box" style="padding:15px 0;">
                          <div class="row">
                          @foreach( $permissions as $key => $lists )
                          <div class="col-lg-3 col-md-3 col-sm-3 col-xs-12 box-item">
                            <div class="card border-primary mb-3">
                              <div class="card-header"><i class="fa fa-wrench"></i> {{ ucfirst( $key ) }}</div>
                              <div class="card-body text-primary permissionBody">
                                @foreach( $lists as $list )
                                  <p class="card-text">
                                      <span class="label-text">{{ $list->name }}
                                      </span>
                                  </p>
                                @endforeach
                              </div>
                            </div>
                          </div>
                          @endforeach
                        </div>
                        </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('header')
<style type="text/css">
    /*search box css start here*/
    .permissionBody {
        height: 220px;
        overflow: auto;
    }
    .list-search-btn {
        font-size: 23px;
    }
    .table-avatar {
        max-width: 50px;
    }
    .search-sec{
        padding: 2rem;
    }
    .search-slt{
        display: block;
        width: 100%;
        font-size: 1.5rem;
        line-height: 1.5;
        color: #55595c;
        background-color: #fff;
        background-image: none;
        border: 1px solid #ccc;
        height: calc(3rem + 2px) !important;
        border-radius:0;
    }
    .wrn-btn{
        width: 100%;
        font-size: 16px;
        font-weight: 400;
        text-transform: capitalize;
        height: calc(3rem + 2px) !important;
        border-radius:0;
    }
    @media (min-width: 992px){
        .search-sec{
            position: relative;
            top: -114px;
            background: rgba(26, 70, 104, 0.51);
        }
    }

    @media (max-width: 992px){
        .search-sec{
            background: #1A4668;
        }
    }
</style>
@endsection

@section('footer')
<script>

</script>
@endsection