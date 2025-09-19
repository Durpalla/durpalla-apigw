@extends('default::layouts.master')

@section('header')
@endsection

@section('content')

    <x-default::toolbar title="{{ $title ?? 'Roles'}}">
        <a href="{{ route('role.create') }}" class="btn btn-success btn-sm">
            <i class="fa fa-plus-circle"></i> Add new role
        </a>
    </x-default::toolbar>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="dataTable" class="table table-bordered table-condensed table-hover table-striped">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Permissions</th>
                            <th class="table-actions">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <script>
        $(function () {
            let table = $('#dataTable').DataTable({
                serverSide: true,
                processing: true,
                "ajax": {
                    url: "{{ route('role.index') }}",
                    data: function (data) {
                        data.status = $('#filterPanel #status').val();
                        data.keyword = $('#filterPanel #keywords').val();
                    }
                },
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                dom: 'lr<"toolbar">tip',
                "lengthChange": true,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "pageLength": 25,
                "bFilter": true,
                "bInfo": true,
                "searching": true,
                "order": [[0, "desc"]],
                columns: [
                    {"data": 'id'},
                    {"data": 'name'},
                    {"data": 'permissions', searching: false, sorting: false},
                    {"data": 'actions', searching: false, sorting: false}
                ],
                "createdRow": function (row, data, index) {
                    // if ( data[6] == 'Disable' ){
                    //     $(row).addClass('highlightError');
                    // }
                }
            });
        });
    </script>
@endsection

