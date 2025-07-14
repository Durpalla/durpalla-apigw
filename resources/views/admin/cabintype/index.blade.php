@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            @foreach($service_list as $key => $value)
                <li class="nav-item">
                    <a class="nav-link clickableTab {{ ($key == 'launch') ? 'active' : '' }}" data-type="{{ $key }}"
                       id="active-tab" data-toggle="tab"
                       href="#{{ $key }}" role="tab" aria-controls="active" aria-selected="true">{{ $value }}</a>
                </li>
            @endforeach
            @if(auth()->user()->hasAnypermission(['route-create']) || auth()->user()->hasRole('admin'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('dashboard.cabintype.create') }}"><i class="fa fa-plus"></i> Add
                        new
                        type</a>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <table class="table table-striped projects" id="cabinTypeTable">
                    <thead>
                    <tr>
                        <th style="width: 1%"> #</th>
                        <th> Name</th>
                        <th>Type</th>
                        <th style="width: 8%"> Letter</th>
                        <th style="width: 15%"> Capacity</th>
                        <th style="width: 15%"> AC</th>
                        <th style="width: 15%"> Service</th>
                        <th style="width: 8%" class="text-center"><i class="fas fa-cog"></i></th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script type="text/javascript">
        let can_edit = false, can_active = false, can_inactive = false, can_delete = false;
        @can('type-edit')
            can_edit = true;
        @endcan
            @can('type-create')
            can_create = true;
        @endcan
            @can('type-delete')
            can_delete = true;
        @endcan
        var url = "{{ route('dashboard.cabintype.index') }}";
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        $(function () {
            var customFilter = $('#customFilters');
            var keyword = $(customFilter).find('input#keywords');
            var area = $(customFilter).find('select#area');
            var package = $(customFilter).find('select#package');
            var status = $(customFilter).find('select#status');
            var search = $(customFilter).find('button#search');
            let service = 'launch';
            var table = $('#cabinTypeTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": true,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': url,
                    pages: 5, // number of pages to cache
                    'data': function (data) {
                        // Read values
                        data.keyword = $(keyword).val();
                        data.service_type = service;
                    }
                },
                "lengthChange": true,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "oLanguage": {
                    "sLengthMenu": "Show _MENU_ ",
                },
                "pageLength": 25,
                "bFilter": true,
                "bInfo": true,
                "searching": false,
                "columns": [
                    {"data": "id"},
                    {"data": "name"},
                    {"data": "type"},
                    {"data": "letter"},
                    {"data": "capacity"},
                    {
                        "mRender": function(data, type, row) {
                            return (row['is_ac']) ? 'AC': 'NonAc';
                        }
                    },
                    {"data": "service_type"},
                    {
                        "mRender": function (data, type, row) {
                            let str = '';
                            if (can_edit) {
                                str += '<a href="/admin/vehicle/cabin/type/edit/' + row['id'] + '" class="btn btn-default"><i class="fa fa-edit"></i> Edit</a>';
                            }
                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0, 1, 5], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[2, 'asc']],
                buttons: [
                    'copy', 'excel', 'pdf', 'print'
                ]
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
                service = $(this).data('type');
                table.draw();
            });

            //Custom Filters ( Author search )
            $(area).change(function () {
                // var keycode = (event.keyCode ? event.keyCode : event.which);
                table.draw();
            });

            //Custom Filters ( Author search )
            $(package).change(function () {
                // var keycode = (event.keyCode ? event.keyCode : event.which);
                table.draw();
            });

            //Custom Filters ( Author search )
            $(status).change(function () {
                table.draw();
            });

            // $('#myModal').modal('show');
            $('table').on('click', '.route-action', function () {
                console.log(this);
                var url = "{{ route('dashboard.route.action') }}";
                var action = $(this).data('action');
                var id = $(this).data('route-id');
                if (action) {
                    var data = {action: action, id: id};
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You are going to " + action + " this route.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes'
                    }).then((result) => {
                        if (result.value) {

                            $.ajaxSetup({
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                            $.ajax({
                                // dataType: "json",
                                type: "POST",
                                url: url,
                                data: data,
                                success: function (response, textStatus, xhr) {
                                    response = JSON.parse(response);
                                    if (response.status == true) {
                                        table.draw();
                                        Toast.fire({
                                            icon: response.label,
                                            title: response.content
                                        });
                                    }
                                }
                            });

                        }
                    });
                    // if (confirmed) {
                    // }
                }
                return false;
            });

        });
    </script>
@endsection
