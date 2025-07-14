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
                    @can('merchant-create')
                    <a href="{{ route('dashboard.blog.create') }}" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i> Add new</a>
                    @endcan
                </div>
              </div>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
                @foreach( $blogs as $blog )
             <div class="row row-blog">
                 <div class="card card-blog">
                     <div class="card-header">
                         <h3 class="card-title">{{ $blog->title }}</h3>
                         <div class="btn-group float-right" role="group" aria-label="Basic example">
                            <span class="badge badge-info catagory">{{$blog->blogcatagory['title']}}</span>
                            <a href="{{route('dashboard.blog.edit',$blog->id)}}" class="btn btn-xs btn-primary edit"><i class="fa fa-edit"></i></a>
                            <a href="#" class="btn btn-xs btn-warning"><i class="fa fa-trash-alt"></i></a>
                        </div>
                     </div>
                     <div class="card-body">
                         <p>
                             {{ $blog->body }}
                         </p>
                     </div>
                </div>
             </div>
             @endforeach
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
@section('header')
<style type="text/css">
    .card-blog{
        width:100%;
    }
    .catagory{
        margin-right: 50px;
    }
    .edit{
        margin-right: 10px;
    }
</style>
@endsection

@section('footer')
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>

@endsection