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
                                <a href="{{ route('services.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Add new</a>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <table class="table table-striped projects" id="dataTable">
                            <thead>
                            <tr>
                                <th style="width: 10%"> ID </th>
                                <th style="width: 20%"> Name </th>
                                <th style="width: 20%"> Slug </th>
                                <th> Description </th>
                                <th> Status </th>
                                <th><i class="fa fa-wrench"></i></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($services as $service)
                                <tr>
                                    <td>{{ $service->id }}</td>
                                    <td>{{ $service->name }}</td>
                                    <td>{{ $service->slug }}</td>
                                    <td>{{ $service->description }}</td>
                                    <td>{{ ($service->status === 1) ? 'Enable' : 'Disable' }}</td>
                                    <td>
                                        <a href="{{ route('services.edit', $service->id) }}" style="float: left" class="btn btn-secondary">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                            @if(!$services->count())
                                <tr>
                                    <td colspan="5">No services found</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                        {!! $services->links() !!}
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
