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
                                <h5>{{ $title ?? 'Gateways' }}</h5>
                                <div class="toolbar">
                                    <nav style="padding: 8px;">
                                        @if(\App\Helpers\CommonHelper::hasPermission(['gateway-create']))
                                        <a href="{{ route('gateway.create') }}" class="btn btn-success btn-sm">
                                            <i class="fa fa-plus-circle"></i> Add new gateway
                                        </a>
                                        @endif
                                    </nav>
                                </div>
                                <!-- /.toolbar -->
                            </header>
                            <div id="collapse4" class="body">
                                <table id="dataTable" class="table table-bordered table-condensed table-hover table-striped">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Namespace</th>
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
    <script src="{{ asset('lib/DataTables/datatables.js') }}"></script>
    <script>
        $(function () {
            $('#dataTable').DataTable({
                "sAjaxSource": "{{ route('gateway.index') }}",
                "bAutoWidth": true,
                responsive: true,
                "sPageButtonActive": "active",
                dom: 'lBfrtip',
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
                    {"data": 'class_name', searching: false, sortable: false},
                    {"data": 'status'},
                    {"data": 'actions', searching: false, sortable: false}
                ]
            });
        });
    </script>
@endsection

