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
                    <a href="{{ route('dashboard.page.create') }}" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i> Add new</a>
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
                  @foreach( $pages as $page )
                    <tr>
                      <td>{{$page->id}}</td>
                      <td>{{$page->title}}</td>
                      <td>{{ \Illuminate\Support\Str::limit(strip_tags($page->content), 50) }}</td>
                      <td>
                        <a href="{{route('dashboard.page.edit',$page->id)}}"><i class="fa fa-edit"></i></a>
                        @if(!$page->readonly)
                          <form action="{{ route('dashboard.page.delete', $page->id) }}" class="form-inline" method="POST">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="id" value="{{ $page->id }}">
                            <button class="btn btn-xs btn-danger" type="submit"><i class="fa fa-trash-alt"></i></button>
                          </form>
                        @endif
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
