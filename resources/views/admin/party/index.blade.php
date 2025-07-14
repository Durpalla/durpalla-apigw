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
                                <a href="{{ route('parties.create') }}" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i> Add new</a>
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
                                <th style="width: 20%"> Address </th>
                                <th><i class="fa fa-wrench"></i></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($parties as $party)
                                <tr>
                                    <td>{{ $party->id }}</td>
                                    <td>{{ $party->name }}</td>
                                    <td>{{ $party->slug }}</td>
                                    <td>{{ $party->address }}</td>
                                    <td>
                                        <a href="{{ route('parties.edit', $party->id) }}" style="float: left" class="btn btn-secondary">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        {!! $parties->links() !!}
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
