@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <div class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link clickableTab active" data-type="1" id="active-tab" data-toggle="tab" href="#active"
                   role="tab" aria-controls="active" aria-selected="true">Active</a>
            </li>
            <li class="nav-item">
                <a class="nav-link clickableTab" data-type="2" id="inactive-tab" data-toggle="tab" href="#inactive"
                   role="tab" aria-controls="inactive" aria-selected="false">Inactive</a>
            </li>
            <li class="nav-item">
                <a class="nav-link clickableTab" data-type="9" id="deleted-tab" data-toggle="tab" href="#deleted"
                   role="tab" aria-controls="deleted" aria-selected="false">Deleted</a>
            </li>
            @if(auth()->user()->hasRole('admin') || auth()->user()->hasAnyPermission(['vehicle-create', 'vehicle-add']))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('dashboard.vehicle.create', ['type' => $type]) }}"><i
                            class="fa fa-plus"></i> Add new {{$type}}</a>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <div id="advancedFilter">
                    <div class="row pt-2">
                        <div class="col-sm-4">
                            <input type="text" class="form-control" id="keywords" placeholder="search">
                        </div>
                        <div class="col-sm-4">
                            <select class="form-control select2" id="filterMerchant" id="items"
                                    data-placeholder="Select merchant" data-dropdown-css-class="select2-purple"
                                    style="width: 100%;">
                                <option value="">Select merchant</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <select class="form-control select2" id="filterRoutes" id="items"
                                    data-placeholder="Select route" data-dropdown-css-class="select2-purple"
                                    style="width: 100%;">
                                <option value="">Select route</option>
                            </select>
                        </div>
                    </div>
                </div>
                <table class="table table-striped projects display responsive nowrap" id="vehicleTables">
                    <thead>
                    <tr>
                        <th> Name</th>
                        <th> Default route</th>
                        <th> Reg. No</th>
                        <th> Reg. Exp date</th>
                        <th>Total Capacity</th>
                        <th>Merchant</th>
                        <th> Joining Date</th>
                        <th style="width: 8%" class="text-center"> Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
        </section>
    @endsection

    @section('header')
        <!-- Select2 -->
            <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
            <link rel="stylesheet"
                  href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">

            <link rel="stylesheet" type="text/css"
                  href="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/css/responsive.dataTables.min.css') }}"/>
            <style type="text/css">
                #advancedFilter, #advancedFilter2 {
                    background: #f2f2f2;
                    z-index: 1;
                    padding: 10px;
                    border: 1px solid #d9d5d5;
                    border-left: 0;
                    border-right: 0;
                    top: auto;
                    left: 0;
                    right: 0;
                    margin-top: 10px;
                }
            </style>
        @endsection

        @section('footer')
            <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
            <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
            <script
                src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
            <script
                src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.responsive.min.js') }}"></script>
            <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/dataTables.buttons.min.js') }}"></script>
            <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.bootstrap4.min.js') }}"></script>
            <script src="{{ asset('assets/plugins/dataTable/pdfmake-0.1.36/pdfmake.min.js') }}"></script>
            <script src="{{ asset('assets/plugins/dataTable/pdfmake-0.1.36/vfs_fonts.js') }}"></script>
            <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.flash.js') }}"></script>
            <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.html5.min.js') }}"></script>
            <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.print.min.js') }}"></script>
            <script>
                let can_edit = false, can_active = false, can_inactive = false, can_delete = false,
                    vehicle_type = 'launch';
                @can('vehicle-edit')
                    can_edit = true;
                @endcan
                    @can('vehicle-action')
                    can_active = true;
                @endcan
                    @can('vehicle-action')
                    can_inactive = true;
                @endcan
                    @can('vehicle-delete')
                    can_delete = true;
                @endcan
                var url = "{{ route('dashboard.vehicle.index') }}";
                vehicle_type = "{{$type}}";
                $.fn.dataTable.ext.classes.sPageButton = 'page-item';

                $(function () {
                    var customFilter = $('#advancedFilter');
                    var keyword = $(customFilter).find('input#keywords');
                    var route = $(customFilter).find('select#filterRoutes');
                    var merchant = $(customFilter).find('select#filterMerchant');
                    var search = $(customFilter).find('button#search');
                    let status = 1;
                    var table = $('#vehicleTables').DataTable({
                        "processing": true,
                        "serverSide": true,
                        "deferRender": true,
                        "bAutoWidth": false,
                        "dom": "lBfrtip",
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
                                data.type = vehicle_type;
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
                            {
                                "mRender": function (data, type, row) {
                                    return '<a href="/admin/vehicle/show/' + row['id'] + '" class="table-avatar">' + row['name'] + '</a>';
                                }
                            },
                            {"data": "route_id"},
                            {"data": "registration_no"},
                            {
                                "mRender": function (data, type, row) {
                                    let date = new Date(row['registration_expiry_date']);
                                    let month = date.getMonth() + 1;
                                    return date.getDate() + '/' + month + '/' + date.getFullYear();
                                }
                            },
                            {"data": "capacity"},
                            {
                                "mRender": function (data, type, row) {
                                    return '<a href="/admin/merchant/show/' + row['merchant_id'] + '" class="table-avatar">' + row['merchant_name'] + '</a>';
                                }
                            },
                            {
                                "mRender": function (data, type, row) {
                                    let date = new Date(row['joining_date']);
                                    let month = date.getMonth() + 1;
                                    return date.getDate() + '/' + month + '/' + date.getFullYear();
                                }
                            },
                            {
                                "mRender": function (data, type, row) {
                                    var str = "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'>";

                                    str += "<a href='/admin/vehicle/show/" + row['id'] + "' class='dropdown-item' data-vehicle-id='" + row['id'] + "'><i class='fa fa-eye'></i> View</a>";
                                    if (can_edit) {
                                        str += "<a href='/admin/vehicle/edit/" + row['id'] + "' class='dropdown-item'><i class='fa fa-edit'></i> Edit</a>";
                                    }
                                    if (row['deleted_at'] == true && can_delete) {
                                        str += "<a href='#' class='dropdown-item vehicle-action' data-action='restore' data-vehicle-id='" + row['id'] + "'><i class='fa fa-check'></i> Restore</a>";
                                    } else {
                                        if (row['status'] == 1 && can_inactive) {
                                            str += "<a href='#' class='dropdown-item vehicle-action' data-action='inactive' data-vehicle-id='" + row['id'] + "'><i class='fa fa-ban'></i> Inactive</a>";
                                            if (can_delete) {
                                                str += "<a href='#' class='dropdown-item vehicle-action' data-action='delete' data-vehicle-id='" + row['id'] + "'><i class='fa fa-times'></i> Delete</a>";

                                            }
                                        }
                                        if (row['status'] == 2 && can_active && row['deleted_at'] == false) {
                                            str += "<a href='#' class='dropdown-item vehicle-action' data-action='active' data-vehicle-id='" + row['id'] + "'><i class='fa fa-check'></i> Active</a>";
                                        }
                                    }
                                    str += "</div> </div>";
                                    return str;
                                }
                            }
                        ],
                        "columnDefs": [
                            {"targets": [0, 1, 5], "searchable": false, "orderable": false, "visible": true}
                        ],
                        "order": [[2, 'asc']],
                        buttons: [
                            {
                                extend: 'copy',
                                text: '<i class="fas fa-copy"></i> ',
                                titleAttr: 'Copy to clipboard',
                                className: 'btn btn-default',
                                messageTop: 'vehicle list',
                                footer: true,
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5, 6]
                                }
                            },
                            {
                                extend: 'print',
                                text: '<i class="fa fa-print"></i> ',
                                titleAttr: 'Print bookings',
                                className: 'btn btn-info',
                                messageTop: 'vehicle list',
                                footer: true,
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5, 6]
                                }
                            },
                            {
                                extend: 'csv',
                                text: '<i class="fa fa-file-excel"></i> ',
                                titleAttr: 'Export bookings',
                                className: 'btn btn-success',
                                messageTop: 'vehicle list',
                                footer: true,
                                exportOptions: {
                                    columns: [0, 1, 2, 3, 4, 5, 6]
                                }
                            }
                        ]
                    });

                    //Status Filters
                    $('.clickableTab').click(function (e) {
                        e.defaultPrevented;
                        status = $(this).data('type');
                        table.draw();
                    });

                    //Custom Filters ( title search )
                    $(keyword).keyup(function (event) {
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

                    // $('#myModal').modal('show');
                    $('table').on('click', '.vehicle-action', function () {
                        var url = "{{ route('dashboard.vehicle.action') }}";
                        var action = $(this).data('action');
                        var id = $(this).data('vehicle-id');
                        if (action) {
                            var data = {action: action, id: id};
                            Swal.fire({
                                title: 'Are you sure?',
                                text: "You are going to " + action + " this vehicle account.",
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
                                            table.draw();
                                            if (response.status == true) {
                                                Toast.fire({
                                                    icon: response.label,
                                                    title: response.content
                                                });
                                            }
                                        }
                                    });

                                }
                            });
                        }
                        return false;
                    });
                    let hash = window.location.hash.substr(1);

                    if (hash == 'active') {
                        status = 1;
                        table.draw();
                    } else if (hash == 'inactive') {
                        status = 2;
                        table.draw();
                    } else if (hash == 'deleted') {
                        status = 9;
                        table.draw();
                    }
                });
            </script>
@endsection
