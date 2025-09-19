@extends('metis::layouts.master')

@section('header')
@endsection

@section('content')
    <div id="content">
        <div class="outer">
            <div class="inner bg-light lter">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="box">
                            <header>
                                <div class="icons"><i class="fa fa-table"></i></div>
                                <h5>{{ $title ?? 'Administrators' }}</h5>
                                <div class="toolbar">
                                    <nav style="padding: 8px;">
                                        <a href="{{ route('user.create') }}" class="btn btn-success btn-sm">
                                            <i class="fa fa-plus-circle"></i> Add new administer
                                        </a>
                                    </nav>
                                </div>
                                <!-- /.toolbar -->
                            </header>
                            <div id="collapse4" class="body">
                                <table id="dataTable" class="table table-bordered table-condensed table-hover table-striped">
                                    <thead>
                                    <tr>
                                        <th class="table-ids">ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
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
                <!-- /.row -->
                <!--End Datatables-->
            </div>
            <!-- /.inner -->
        </div>
        <!-- /.outer -->
    </div>
    <!-- /#content -->
@endsection

@section('footer')
    <script>
        $(function () {
            let table = $('#dataTable').DataTable({
                serverSide: true,
                processing: true,
                "ajax":  {
                    url: "{{ route('user.index') }}",
                    data: function (data) {
                        data.status = $('#filterPanel #status').val();
                        data.keyword = $('#filterPanel #keywords').val();
                    }
                },
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                dom: 'lr<"toolbar">tip',
                stateSave: true,
                "stateDuration": 60 * 60 * 24 * 7,
                deferRender: true,
                "lengthChange": true,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "pageLength": 50,
                "bFilter": true,
                "bInfo": true,
                "searching": true,
                "order": [[0, "DESC"]],
                columns: [
                    {"data": 'id', order: true},
                    {"data": 'name'},
                    {"data": 'email'},
                    {"data": 'role', searching: false, sortable: false},
                    {"data": 'status'},
                    {"data": 'actions', searching: false, sortable: false}
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

