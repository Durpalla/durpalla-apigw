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
                    <a href="{{ route('dashboard.blogcatagory.create') }}" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i> Add new</a>
                </div>
              </div>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <table class="table table-striped projects" id="dataTable">
                <thead>
                    <tr>
                        <th style="width: 20%"> Id </th> 
                        <th style="width: 20%"> Title </th> 
                        <th style="width: 20%"> Content </th> 
                        <th style="width: 20%"> Action </th> 
                    </tr>
                </thead>
                <tbody>
                  @foreach( $catagorys as $catagory )
                    <tr>
                      <td>{{$catagory->id}}</td>
                      <td>{{$catagory->title}}</td>
                      <td>{{$catagory->description}}</td>
                      <td>
                        <a href="{{route('dashboard.blogcatagory.edit',$catagory->id)}}"><i class="fa fa-edit"></i></a>
                        <a href="#"><i class="fa fa-trash-alt"></i></a>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
                <tbody>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection

@section('footer')
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
@endsection