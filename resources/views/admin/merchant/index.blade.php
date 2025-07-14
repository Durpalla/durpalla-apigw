@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link clickableTab active" data-type="active" id="active-tab" data-toggle="tab"
                   href="#active" role="tab" aria-controls="active" aria-selected="true">Active</a>
            </li>
            <li class="nav-item">
                <a class="nav-link clickableTab" data-type="inactive" id="inactive-tab" data-toggle="tab"
                   href="#inactive" role="tab" aria-controls="inactive" aria-selected="false">Inactive</a>
            </li>
            @if(auth()->user()->hasAnypermission(['merchant-create']) || auth()->user()->hasRole('admin'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('dashboard.merchant.create') }}"><i class="fa fa-plus"></i> Add new
                        merchant</a>
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
                        <div class="col-sm-6">
                            <div class="input-group date">
                                <input type="text" id="date_from" class="form-control datepicker"
                                       placeholder="Joining date">
                                <span class="input-group-addon m-2">
                                    To
                                </span>
                                <input type="text" id="date_to" class="form-control datepicker"
                                       placeholder="Joining date">
                            </div>
                        </div>
                    </div>
                </div>
                <table class="table table-striped table-bordered projects display responsive nowrap" id="dataTable">
                    <thead>
                    <tr>
                        <th style="width: 3%"><div><i class="fa fa-image"></i></div></th>
                        <th style="width: 20%"><div>Name</div></th>
                        <th><div>Total vehicle</div></th>
                        <th><div>Mobile</div></th>
                        <th><div>Email</div></th>
                        <th><div>Reg. No.</div></th>
                        <th><div>Expiry date</div></th>
                        <th><div>Vat to</div></th>
                        <th><div>Honorium</div></th>
                        <th><div>Joining Date</div></th>
                        <th style="width: 8%" class="text-center"><div>Status</div></th>
                        <th style="width: 45px" class="text-center"><div><i class="fas fa-cog"></i></div></th>
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
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
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

        #advancedFilterBtn.active {
            color: #219876;
            background: #eaeaea;
        }

        .datepicker {
            width: fit-content;
        }
    </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.responsive.min.js') }}"></script>
    <script>
        let can_edit = false, can_active = false, can_inactive = false, can_delete = false, can_shadow = false;
        @can('merchant-edit')
            can_edit = true;
        @endcan
            @can('merchant-active')
            can_active = true;
        @endcan
            @can('merchant-inactive')
            can_inactive = true;
        @endcan
            @can('merchant-delete')
            can_delete = true;
        @endcan
        @if(auth()->user()->hasRole('admin'))
            can_shadow = true;
        @endif
        var url = "{{ route('dashboard.merchant.index') }}";
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        $.fn.dataTable.pipeline = function (opts) {
            // Configuration options
            var conf = $.extend({
                pages: 5,     // number of pages to cache
                url: "{{ route('dashboard.merchant.index') }}",      // script url
                data: null,   // function or object with parameters to send to the server
                              // matching how `ajax.data` works in DataTables
                method: 'GET' // Ajax HTTP method
            }, opts);

            // Private variables for storing the cache
            var cacheLower = -1;
            var cacheUpper = null;
            var cacheLastRequest = null;
            var cacheLastJson = null;

            return function (request, drawCallback, settings) {
                var ajax = true;
                var requestStart = request.start;
                var drawStart = request.start;
                var requestLength = request.length;
                var requestEnd = requestStart + requestLength;

                if (settings.clearCache) {
                    // API requested that the cache be cleared
                    ajax = true;
                    settings.clearCache = false;
                } else if (cacheLower < 0 || requestStart < cacheLower || requestEnd > cacheUpper) {
                    // outside cached data - need to make a request
                    ajax = true;
                } else if (JSON.stringify(request.order) !== JSON.stringify(cacheLastRequest.order) ||
                    JSON.stringify(request.columns) !== JSON.stringify(cacheLastRequest.columns) ||
                    JSON.stringify(request.search) !== JSON.stringify(cacheLastRequest.search)
                ) {
                    // properties changed (ordering, columns, searching)
                    ajax = true;
                }

                // Store the request for checking next time around
                cacheLastRequest = $.extend(true, {}, request);

                if (ajax) {
                    // Need data from the server
                    if (requestStart < cacheLower) {
                        requestStart = requestStart - (requestLength * (conf.pages - 1));

                        if (requestStart < 0) {
                            requestStart = 0;
                        }
                    }

                    cacheLower = requestStart;
                    cacheUpper = requestStart + (requestLength * conf.pages);

                    request.start = requestStart;
                    request.length = requestLength * conf.pages;

                    // Provide the same `data` options as DataTables.
                    if (typeof conf.data === 'function') {
                        // As a function it is executed with the data object as an arg
                        // for manipulation. If an object is returned, it is used as the
                        // data object to submit
                        var d = conf.data(request);
                        if (d) {
                            $.extend(request, d);
                        }
                    } else if ($.isPlainObject(conf.data)) {
                        // As an object, the data given extends the default
                        $.extend(request, conf.data);
                    }

                    settings.jqXHR = $.ajax({
                        "type": conf.method,
                        "url": conf.url,
                        "data": request,
                        "dataType": "json",
                        "cache": true,
                        "success": function (json) {
                            cacheLastJson = $.extend(true, {}, json);

                            if (cacheLower != drawStart) {
                                json.data.splice(0, drawStart - cacheLower);
                            }
                            if (requestLength >= -1) {
                                json.data.splice(requestLength, json.data.length);
                            }

                            drawCallback(json);
                        }
                    });
                } else {
                    json = $.extend(true, {}, cacheLastJson);
                    json.draw = request.draw; // Update the echo for each response
                    json.data.splice(0, requestStart - cacheLower);
                    json.data.splice(requestLength, json.data.length);

                    drawCallback(json);
                }
            }
        };

        // Register an API method that will empty the pipelined data, forcing an Ajax
        // fetch on the next draw (i.e. `table.clearPipeline().draw()`)
        $.fn.dataTable.Api.register('clearPipeline()', function () {
            return this.iterator('table', function (settings) {
                settings.clearCache = true;
            });
        });

        $(function () {
            $('#advancedFilterBtn').click(function (e) {
                e.defaultPrevented;
                $(this).toggleClass('active');
                $('#advancedFilter').toggleClass('d-none');
            });
            var customFilter = $('#advancedFilter');
            var keyword = $('input#keywords');
            var route = $(customFilter).find('select#filterRoutes');
            var date_from = $(customFilter).find('input#date_from');
            var date_to = $(customFilter).find('input#date_to');
            var status = 'active';
            var search = $(customFilter).find('button#search');
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
                        data.keyword = $(keyword).val();
                        data.route = $(route).val();
                        data.date_from = $(date_from).val();
                        data.date_to = $(date_to).val();
                        data.status = status;
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
                            return '<img src="' + row['photo'] + '" class="table-avatar">';
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            return '<a href="/admin/merchant/show/' + row['id'] + '" class="table-avatar">' + row['merchant_name'] + '</a>';
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            return '<a href="/admin/merchant/show/' + row['id'] + '?tab=vehicle" class="btn btn-default">' + row['vehicle_count'] + '</a>';
                        }
                    },
                    {"data": "merchant_mobile"},
                    {"data": "merchant_email"},
                    {"data": "merchant_reg_no"},
                    {"data": "merchant_reg_expiry_date"},
                    {"data": "vat_applicable_to"},
                    {"data": "honorium_service_charge"},
                    {"data": "created_at"},
                    {
                        "mRender": function (data, type, row) {
                            return (parseInt(row['status']) == 1) ? 'Active' : ((parseInt(row['status']) == 0) ? 'Pending' : 'Inactive');
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            var str = "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'> <a href='/admin/merchant/show/" + row['id'] + "' class='dropdown-item' data-merchant-id='" + row['id'] + "'><i class='fa fa-eye'></i> View</a> <a href='/admin/merchant/edit/" + row['id'] + "' class='dropdown-item'><i class='fa fa-edit'></i> Edit</a>";
                            if (row['deleted_at'] != null) {
                                if (can_delete) {
                                    str += "<a href='#' class='dropdown-item merchant-action' data-action='restore' data-merchant-id='" + row['id'] + "'><i class='fa fa-check'></i> Restore</a>";
                                }
                            } else if (parseInt(row['status']) != 1) {
                                if (can_active) {
                                    str += "<a href='#' class='dropdown-item merchant-action' data-action='active' data-merchant-id='" + row['id'] + "'><i class='fa fa-check'></i> Active</a>";
                                }
                            } else {
                                if (can_delete) {
                                    str += "<a href='#' class='dropdown-item merchant-action' data-action='delete' data-merchant-id='" + row['id'] + "'><i class='fa fa-times'></i> Delete</a>";
                                }
                            }
                            if(can_shadow) {
                                str += "<a href='/admin/shadow_sessions/" + row['user_id'] + "' class='dropdown-item'><i class='fa fa-user-astronaut'></i> Shadow login</a>";
                            }
                            str += "</div> </div>";
                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0, 1, 5], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[5, 'desc']],
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
            $(route).on('select2:select', function (e) {
                e.defaultPrevented;
                table.draw();
            });

            $(route).on('select2:clear', function (e) {
                e.defaultPrevented;
                table.draw();
            });

            $(route).select2({
                placeholder: "Pick some items",
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

            $('.clickableTab').click(function(e) {
                e.defaultPrevented;
                status = $(this).data('type');
                table.draw();
            });

            //Datemask dd/mm/yyyy
            $('#datemask').inputmask('dd/mm/yyyy', {'placeholder': 'dd/mm/yyyy'})
            //Money Euro
            $('[data-mask]').inputmask()
            $('.datepicker').click(function (e) {
                $(this).val("");
            });

            $('.datepicker').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
                endDate: "+30d"
            }).on('changeDate', function (ev) {
                $(this).datepicker('hide');
                table.draw();
            });

            //Custom Filters ( Author search )
            $(status).change(function () {
                table.draw();
            });

            // $('#myModal').modal('show');
            $('table').on('click', '.merchant-action', function () {
                console.log(this);
                var url = "{{ route('dashboard.merchant.action') }}";
                var action = $(this).data('action');
                var id = $(this).data('merchant-id');
                if (action == 'request') {

                } else {
                    var data = {action: action, id: id};
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You are going to " + action + " this merchant account.",
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
                                        Toast.fire({
                                            icon: response.label,
                                            title: response.content
                                        });
                                        // Swal.fire(
                                        //     'Deleted!',
                                        //     'Your file has been deleted.',
                                        //     'success'
                                        // );
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
