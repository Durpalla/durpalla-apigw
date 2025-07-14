@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            @foreach($service_list as $key => $value)
            <li class="nav-item">
                <a class="nav-link clickableTab {{ ($key == 'launch') ? 'active' : '' }}" data-type="{{ $key }}" id="active-tab" data-toggle="tab"
                   href="#{{ $key }}" role="tab" aria-controls="active" aria-selected="true">{{ $value }}</a>
            </li>
            @endforeach
            @if(auth()->user()->hasAnypermission(['route-create']) || auth()->user()->hasRole('admin'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('dashboard.routes.create') }}"><i class="fa fa-plus"></i> Add new
                        route</a>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <table class="table table-striped projects" id="dataTable">
                    <thead>
                    <tr>
                        <th style="width: 1%"> #</th>
                        <th> Name</th>
                        <th style="width: 8%"> Type</th>
                        <th style="width: 15%"> Starting point</th>
                        <th style="width: 15%"> Ending point</th>
                        <th style="width: 30%"> Via</th>
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
    <script>
        let can_edit = false, can_active = false, can_inactive = false, can_delete = false;
        @can('route-edit')
            can_edit = true;
        @endcan
            @can('route-create')
            can_create = true;
        @endcan
            @can('route-delete')
            can_delete = true;
        @endcan
        var url = "{{ route('dashboard.routes.index') }}";
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        $.fn.dataTable.pipeline = function (opts) {
            // Configuration options
            var conf = $.extend({
                pages: 5,     // number of pages to cache
                url: "{{ route('dashboard.routes.index') }}",      // script url
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
            // $(mediaModal).modal('show');
            // $(confirmModal).on('hide.bs.modal', function (event) {
            //     $(this).find('.modal-footer .btn').each(function(e){
            //         if( $(this).hasClass('btn-primary') ) {
            //             alert('You clicked Yes');
            //         } else {
            //             alert('You clicked No');
            //         }
            //     });
            //     alert('modal closing');
            //   var button = $(event.relatedTarget) // Button that triggered the modal
            //   var recipient = button.data('whatever') // Extract info from data-* attributes
            //   // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            //   // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            //   var modal = $(this)
            //   modal.find('.modal-title').text('New message to ' + recipient)
            //   modal.find('.modal-body input').val(recipient)
            // });
            var customFilter = $('#customFilters');
            var keyword = $(customFilter).find('input#keywords');
            var area = $(customFilter).find('select#area');
            var package = $(customFilter).find('select#package');
            var status = $(customFilter).find('select#status');
            var search = $(customFilter).find('button#search');
            let service = 'launch';
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
                        data.area = $(area).val();
                        data.package = $(package).val();
                        data.status = $(status).val();
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
                    {"data": "route_name"},
                    {"data": "route_type"},
                    {"data": "starting_point.ghat.name"},
                    {"data": "ending_point.ghat.name"},
                    {
                        "mRender": function (data, type, row) {
                            var str = "";
                            if (row.hasOwnProperty('boarding_vias') && row['boarding_vias'].length > 0) {
                                for (var i = 0; i < row['boarding_vias'].length; i++) {
                                    console.log(row['boarding_vias'][i].ghat.length);
                                    if (row['boarding_vias'][i].hasOwnProperty('ghat') && row['boarding_vias'][i].ghat != null) {
                                        str += "<span class='badge badge-info'>" + row['boarding_vias'][i].ghat.name.charAt(0).toUpperCase() + row['boarding_vias'][i].ghat.name.slice(1) + "</span>";
                                    }
                                }
                            }
                            return str;
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            let str = '';
                            if (can_edit) {
                                str += '<a href="/admin/vehicle/route/edit/' + row['id'] + '" class="btn btn-default"><i class="fa fa-edit"></i> Edit</a>';
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
                                type: "POST",
                                url: url,
                                data: data,
                                success: function (response, textStatus, xhr) {
                                    response = JSON.parse(response);
                                    if (response.status === true) {
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
                }
                return false;
            });

        });
    </script>
@endsection
