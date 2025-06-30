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
                                <a href="{{ route('broadcast.create') }}" class="btn btn-primary"><i
                                        class="fa fa-plus"></i> Add new</a>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <table class="table table-striped table-bordered projects display responsive nowrap" id="dataTable">
                            <thead>
                            <tr>
                                <th style="width: 10%">Title</th>
                                <th style="width: 20%"> Message</th>
                                <th style="width: 10%">Type</th>
                                <th style="width: 10%">Group</th>
                                <th>Created by</th>
                                <th>Scheduled at</th>
                                <th><i class="fa fa-wrench"></i></th>
                            </tr>
                            </thead>
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
    <script>
        let can_edit = false, can_active = false, can_inactive = false, can_delete = false;
        @can('vehicle-edit')
            can_edit = true;
        @endcan
            @can('vehicle-active')
            can_active = true;
        @endcan
            @can('vehicle-inactive')
            can_inactive = true;
        @endcan
            @can('vehicle-delete')
            can_delete = true;
        @endcan
        var url = "{{ route('broadcast.index') }}";
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        $(function () {
            var customFilter = $('#advancedFilter');
            var route = $(customFilter).find('select#filterRoutes');
            var merchant = $(customFilter).find('select#filterMerchant');
            var keyword = $(customFilter).find('input#keywords');
            var status = 1;
            var search = $(customFilter).find('button#search');
            var table = $('#dataTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": false,
                "autoWidth": false,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': url,
                    pages: 5, // number of pages to cache
                    'data': function (data) {
                        // Read values
                        data.keyword = $(keyword).val();
                        data.merchant = $(merchant).val();
                        data.route = $(route).val();
                        data.status = status;
                    }
                },
                dom: 'lBfrtip',
                "lengthChange": false,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "oLanguage": {
                    "sLengthMenu": "Show _MENU_ ",
                },
                "pageLength": 25,
                "bFilter": false,
                "bInfo": true,
                "searching": false,
                "columns": [
                    {"data": "title"},
                    {"data": "message"},
                    {"data": "type"},
                    {"data": "group"},
                    {"data": "user"},
                    {"data": "scheduled_at"},
                    {
                        "mRender": function (data, type, row) {
                            let str = "<a href='/admin/setting/broadcast/" + row['id'] + "/edit' class='btn btn-secondary btn-sm'><i class='fa fa-edit'></i></a>";
                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0, 1, 2, 3], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[2, 'asc']]
            });

            //Click on Search Button
            $(search).click(function (e) {
                table.draw();
            });

            //Custom Filters ( title search )
            $(keyword).keyup(function (event) {
                var keycode = (event.keyCode ? event.keyCode : event.which);
                // if(keycode == '13'){
                table.draw();
                // }
            });
            //Status Filters
            $('.clickableTab').click(function (e) {
                e.defaultPrevented;
                status = $(this).data('type');
                table.draw();
            });


            $(route).on('select2:select', function (e) {
                e.defaultPrevented;
                table.draw();
            });

            $(route).on('select2:clear', function (e) {
                e.defaultPrevented;
                table.draw();
            });

            $(route).select2({
                placeholder: "Select route",
                theme: 'bootstrap4',
                allowClear: true,
                cache: false,
                ajax: {
                    url: "{{ route('dashboard.routes.suggest') }}",
                    dataType: 'json',
                    type: "GET",
                    quietMillis: 50,
                    data: function (term) {
                        return {
                            term: term.term
                        };
                    },
                    processResults: function (data) {
                        var myResults = [];
                        $.each(data.results, function (index, item) {
                            myResults.push({
                                'id': item.id,
                                'text': item.name
                            });
                        });
                        return {
                            results: myResults
                        };
                    }
                }
            });

            $(merchant).on('select2:select', function (e) {
                e.defaultPrevented;
                table.draw();
            });

            $(merchant).on('select2:clear', function (e) {
                e.defaultPrevented;
                table.draw();
            });

            $(merchant).select2({
                placeholder: "Select merchant",
                theme: 'bootstrap4',
                allowClear: true,
                cache: false,
                ajax: {
                    url: "{{ route('dashboard.merchant.suggest') }}",
                    dataType: 'json',
                    type: "GET",
                    quietMillis: 50,
                    data: function (term) {
                        return {
                            term: term.term
                        };
                    },
                    processResults: function (data) {
                        var myResults = [];
                        $.each(data.results, function (index, item) {
                            myResults.push({
                                'id': item.id,
                                'text': item.name
                            });
                        });
                        return {
                            results: myResults
                        };
                    }
                }
            });
        });
    </script>
@endsection
