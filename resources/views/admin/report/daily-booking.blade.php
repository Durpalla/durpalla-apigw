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
                            <a href="{{route('dashboard.report.index')}}" type="button" class="btn btn-default"><i class="fa fa-arrow-alt-circle-left"></i> Back</a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div id="advancedFilter">
                            <div class="row pt-2">
                                <div class="col-sm-3">
                                    <input type="text" class="form-control datepicker" id="booking_date" placeholder="search">
                                </div>
                                <div class="col-sm-5">
                                    <div class="input-group">
                                        <select class="form-control" id="filterStatus">
                                            <option value="">All</option>
                                            <option value="COMPLETE">Completed</option>
                                            <option value="ADVANCE">Advance</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <table class="table table-bordered table-striped" id="dailyBookings">
                            <thead>
                            <tr>
                                <th rowspan="2">INV#</th>
                                <th rowspan="2">Customer info</th>
                                <th rowspan="2">Booking date</th>
                                <th rowspan="2">Journey Date</th>
                                <th rowspan="2">Route name</th>
                                <th rowspan="2">vehicle name</th>
                                <th colspan="3" class="text-center">Items</th>
                                <th rowspan="2">Total Amount</th>
                                <th rowspan="2">Paid amount</th>
                                <th rowspan="2">Dues</th>
                                <th colspan="2" class="text-center">Sercie charges</th>
                                <th rowspan="2">Other passenger info</th>
                                <th rowspan="2">Party</th>
                                <th rowspan="2">Platform</th>
                                <th rowspan="2">Status</th>
                            </tr>
                            <tr>
                                <th>Cabin</th>
                                <th>Seat</th>
                                <th>Deck</th>
                                <th>Jolzan</th>
                                <th>SSLCommerz</th>
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

@section('header')
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">

    <link rel="stylesheet" type="text/css"
          href="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/css/responsive.dataTables.min.css') }}"/>
    <style type="text/css">
        #advancedFilter {
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
    </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/pdfmake-0.1.36/pdfmake.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/pdfmake-0.1.36/vfs_fonts.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.flash.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.print.min.js') }}"></script>
    <script type="text/javascript">
        let url = "{{ route('dashboard.report.booking.daily') }}";
        let visibility = true;
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        $.fn.dataTable.pipeline = function (opts) {
            // Configuration options
            var conf = $.extend({
                pages: 5,     // number of pages to cache
                url: url,      // script url
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
            var customFilter = $('#advancedFilter');
            var keyword = $('input#keywords');
            var status = $(customFilter).find('select#filterStatus');
            var booking_date = $(customFilter).find('input#booking_date');
            var table = $('#dailyBookings').DataTable({
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
                        data.booking_date = $(booking_date).val();
                        data.status = $(status).val();
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
                "dom": "lBfrtip",
                "columns": [
                    {"data": "invoice"},
                    {"data": "customer_info"},
                    {"data": "booking_date"},
                    {"data": "journey_dates"},
                    {"data": "routes"},
                    {"data": "vehicles"},
                    {"data": "cabins"},
                    {"data": "seats"},
                    {"data": "decks"},
                    {"data": "total_amount"},
                    {"data": "paid_amount"},
                    {"data": "due_amount"},
                    {"mRender": function(data, type, row)
                        {
                            return (row['service_charge'] - row['gateway_charge']).toFixed(2)
                        }
                    },
                    {"data": "gateway_charge"},
                    {"data": "other_passenger"},
                    {"data": "party"},
                    {"data": "platform"},
                    {"data": "status"}
                ],
                "columnDefs": [
                    {"targets": [0, 1, 5], "searchable": false, "orderable": false, "visible": true},
                    {"visible": visibility, "targets": 8}
                ],
                "order": [[4, 'desc']]
            });

            $(status).change(function(e) {
                table.draw();
            });

            $('.datepicker').click(function (e) {
                $(this).val("");
            });

            $('.datepicker').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
                // startDate: "-0d",
                // endDate: "+360d"
                endDate: "0d"
            }).on('changeDate', function (ev) {
                $(this).datepicker('hide');
                table.draw();
            });
        });
    </script>
@endsection
