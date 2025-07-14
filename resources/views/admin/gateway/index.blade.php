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
                                <a href="{{ route('gateway.create') }}" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i> Add new</a>
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
                                <th style="width: 20%"> Description </th>
                                <th style="width: 20%"> Status </th>
                                <th><i class="fa fa-wrench"></i></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($gateways as $gateway)
                                <tr>
                                    <td>{{ $gateway->id }}</td>
                                    <td>{{ $gateway->name }}</td>
                                    <td>{{ $gateway->description }}</td>
                                    <td>{{ ($gateway->status == 1) ? 'Active' : 'Inactive' }}</td>
                                    <td>
                                        <a href="{{ route('gateway.edit', $gateway->id) }}" style="float: left" class="btn btn-secondary">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
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
