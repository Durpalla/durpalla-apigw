@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            @foreach($service_list as $key => $value)
                <li class="nav-item">
                    <a class="nav-link clickableTab @if($key == 'launch') active @endif" data-type="{{ $key }}" id="{{$key}}-tab" data-toggle="tab"
                       href="#{{ $key }}" role="tab" aria-controls="active" aria-selected="true">{{ $value }}</a>
                </li>
            @endforeach
            @if(auth()->user()->hasAnypermission(['ghat-create']) || auth()->user()->hasRole('admin'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('dashboard.ghat.create') }}"><i class="fa fa-plus"></i> Add new
                        stoppage</a>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <div class="float-right">
                    <select class="form-control" id="filterStatus">
                        <option value="1">Active</option>
                        <option value="9">Deleted</option>
                    </select>
                </div>
                <table class="table table-striped projects" id="dataTable">
                    <thead>
                    <tr>
                        <th style="width: 1%"> #</th>
                        <th> Name</th>
                        <th>Service type</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Altitude</th>
                        <th style="width: 15%" class="text-center"><i class="fas fa-cog"></i></th>
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
    <script>
        let can_edit = false, can_active = false, can_inactive = false, can_delete = false;
        @can('ghat-edit')
            can_edit = true;
        @endcan
            @can('ghat-create')
            can_create = true;
        @endcan
            @can('ghat-delete')
            can_delete = true;
        @endcan
        var url = "{{ route('dashboard.ghat.index') }}";
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        $(function () {
            let status = $('select#filterStatus');
            var type = 'launch';
            var table = $('#dataTable').DataTable({
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
                        data.status = $(status).val();
                        data.service_type = type;
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
                    {"data": "service_type"},
                    {"data": "latitude"},
                    {"data": "longitude"},
                    {"data": "altitude"},
                    {
                        "mRender": function (data, type, row) {
                            let str = '';
                            if (can_edit) {
                                str += "<a href='/admin/vehicle/ghat/edit/" + row['id'] + "' class='btn btn-secondary btn-xs'><i class='fa fa-edit'></i> Edit</a>";
                            }

                            if (can_delete && row['deleted_at'] == null) {
                                str += "<a href='#' class='btn btn-danger btn-xs ghatAction' data-action='delete' data-ghat-id='" + row['id'] + "'><i class='fa fa-times'></i> Delete</a>";
                            }

                            if (can_delete && row['deleted_at'] != null) {
                                str += "<a href='#' class='btn btn-success btn-xs ghatAction' data-action='restore' data-ghat-id='" + row['id'] + "'><i class='fa fa-check'></i> Restore</a>";
                            }
                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [2], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[0, 'desc']],
                buttons: [
                    'copy', 'excel', 'pdf', 'print'
                ]
            });

            //type Filters
            $('.clickableTab').click(function (e) {
                e.defaultPrevented;
                type = $(this).data('type');
                table.draw();
            });

            //Custom Filters ( Author search )
            $(status).change(function () {
                table.draw();
            });

            let hash = window.location.hash.substr(1);

            if(hash) {
                type = hash;
                table.draw();
            }

            // $('#myModal').modal('show');
            $('table').on('click', '.ghatAction', function () {
                var url = "{{ route('dashboard.ghat.action') }}";
                var action = $(this).data('action');
                var id = $(this).data('ghat-id');
                if (action) {
                    var data = {action: action, id: id};
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You are going to " + action + " this stoppage.",
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
                                dataType: "json",
                                type: "POST",
                                url: url,
                                data: data,
                                success: function (response, textStatus, xhr) {
                                    if (response.status == true) {
                                        table.draw();
                                    }
                                    Toast.fire({
                                        icon: response.label,
                                        title: response.content
                                    });
                                }
                            });
                        }
                    });
                }
                return false;
            });
        });
    </script>
@endsection
