@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            @if(auth()->user()->hasAnyPermission(['merchant-statistics', 'merchants-statistics']) || auth()->user()->hasAnyRole(['admin', 'merchant']))
                <li class="nav-item">
                    <a class="nav-link active"
                       id="contact-tab" data-toggle="tab" href="#contact" role="tab" aria-controls="contact"
                       aria-selected="false">Statistics</a>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">

            <div
                class="tab-pane fade active show"
                id="contact" role="tabpanel" aria-labelledby="contact-tab">
                <form action="" id="reportFilterForm" method="GET">
                    <div class="row mt-3">
                        <div class="col-sm-3">
                            <select class="form-control" name="route_id" id="filterRoutes" id="items"
                                    data-placeholder="Select route" data-dropdown-css-class="select2-purple"
                                    style="width: 100%;"></select>
                        </div>
                        <div class="col-sm-3">
                            <select class="form-control" name="type" id="filterStatus">
                                <option value="" selected="selected">All party</option>
                                <option value="merchant">Merchant</option>
                                <option value="jolzan">Jolzan</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <div class="input-group">
                                <input type="text" name="date_from" id="date_from" class="form-control datepicker"
                                       placeholder="Schedule date" value="{{ date('01/m/Y') }}" required>
                                <span class="input-group-addon m-2">To</span>
                                <input type="text" name="date_to" id="date_to" class="form-control datepicker"
                                       placeholder="Schedule date" value="{{ date('t/m/Y') }}" required>
                                <div class="input-group-btn">
                                    <button class="btn btn-success"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </form>
                <div class="row mt-3">
                    <div id="vehicleStatistics" class="p-3" style="width:100%">
                        <h2>Account: All ({{ date('01/m/Y') }} to {{ date('t/m/Y') }})</h2>
                        <table class="table table-striped table-bordered">
                            <thead>
                            <tr>
                                <th></th>
                                <th>vehicle</th>
                                <th>No of Routes</th>
                                <th>No of Passengers</th>
                                <th>Total Ticket sell amount</th>
                                <th>Total Vat amount</th>
                                <th>Waiver</th>
                                <th>No of discount applied</th>
                                <th>Discount amount</th>
                                <th>No of coupon applied</th>
                                <th>Coupon amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    <div id="routeStatistics" class="p-3 d-none" style="width:100%"></div>
                    <div id="scheduleStatistics" class="p-3 d-none" style="width:100%"></div>
                </div>
            </div>
        </div>
    </section>
    <!-- /.content -->
@endsection

@section('header')
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
    <link href="{{ asset('assets/plugins/fullcalendar/assets/css/fullcalendar.css') }}" rel='stylesheet'/>
    <link href="{{ asset('assets/plugins/fullcalendar/assets/css/fullcalendar.print.css') }}" rel='stylesheet'
          media='print'/>
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <style type="text/css">
        /***
      User Profile Sidebar by @keenthemes
      A component of Metronic Theme - #1 Selling Bootstrap 3 Admin Theme in Themeforest: https://j.mp/metronictheme
      Licensed under MIT
      ***/

        body {
            background: #F1F3FA;
        }

        .nav-tabs .nav-item {
            margin-right: 8px;
        }

        .nav-tabs .nav-link {
            border-top-left-radius: .25rem;
            border-top-right-radius: .25rem;
            border: 1px solid #eee;
            background: #e4e2e2;
            color: #000;
        }

        /* Profile container */
        .profile-userpic {
            height: auto;
            overflow: none;
        }

        .profile-userpic img {
            margin: 0 auto;
            width: auto;
            height: auto;
            max-height: 220px;
            background: #fbfbfb;
            border: 1px solid #eee;
            padding: 10px;
        }

        #external-events {
            float: left;
            width: 150px;
            padding: 0 10px;
            text-align: left;
        }

        #external-events h4 {
            font-size: 16px;
            margin-top: 0;
            padding-top: 1em;
        }

        .external-event { /* try to mimick the look of a real event */
            margin: 10px 0;
            padding: 2px 4px;
            background: #3366CC;
            color: #fff;
            font-size: .85em;
            cursor: pointer;
        }

        #external-events p {
            margin: 1.5em 0;
            font-size: 11px;
            color: #666;
        }

        #external-events p input {
            margin: 0;
            vertical-align: middle;
        }

        #calendar {
            margin: 0 auto;
            width: 100%;
            background-color: #FFFFFF;
            border-radius: 6px;
            border: 1px solid #eee;
            /*box-shadow: 0 1px 2px #C3C3C3;
            -webkit-box-shadow: 0px 0px 21px 2px rgba(0,0,0,0.18);
            -moz-box-shadow: 0px 0px 21px 2px rgba(0,0,0,0.18);
            box-shadow: 0px 0px 21px 2px rgba(0,0,0,0.18);*/
        }


    </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/fullcalendar/assets/js/fullcalendar.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/plugins/printjs/print.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/0.9.0rc1/jspdf.min.js"></script>
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
        let pdf = new jsPDF();
        let specialElementHandlers = {
            '#editor': function (element, renderer) {
                return true;
            }
        };

        function tableToExcel(table, name, filename) {
            let uri = 'data:application/vnd.ms-excel;base64,',
                template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><title></title><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--><meta http-equiv="content-type" content="text/plain; charset=UTF-8"/></head><body><table>{table}</table></body></html>',
                base64 = function (s) {
                    return window.btoa(decodeURIComponent(encodeURIComponent(s)))
                }, format = function (s, c) {
                    return s.replace(/{(\w+)}/g, function (m, p) {
                        return c[p];
                    })
                }

            if (!table.nodeType) table = document.getElementById(table)
            var ctx = {worksheet: name || 'Worksheet', table: table.innerHTML}

            var link = document.createElement('a');
            link.download = filename;
            link.href = uri + base64(format(template, ctx));
            link.click();
        }

        function makePdf(elem) {
            pdf.fromHTML($(elem).html(), 15, 15, {
                'width': 170,
                'elementHandlers': specialElementHandlers
            });
            pdf.save('vehicle_statistics.pdf');
        }

        var url = "{{ route('dashboard.merchant.vehicles', $merchant->user_id) }}";
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        $.fn.dataTable.pipeline = function (opts) {
            // Configuration options
            var conf = $.extend({
                pages: 5,     // number of pages to cache
                url: "{{ route('dashboard.merchant.vehicles', $merchant->user_id) }}",      // script url
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

        jQuery(function ($) {
            $('#filterRoutes').select2({
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

            $('#reportFilterForm').submit(function (e) {
                e.defaultPrevented;

                let data = $(this).serialize();

                vehicleStatistics(data);
                $('#routeStatistics').addClass('d-none');
                $('#scheduleStatistics').addClass('d-none');

                return false;
            });

            //vehicle statistics table
            function vehicleStatistics($params) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': '*'
                    }
                });
                $.ajax({
                    url: "{{ route('merchant.dashboard.vehicleStat', $merchant->user_id)}}",
                    type: 'post',
                    data: $params,
                    success: function (response) {
                        var html = $.parseHTML(response);
                        $('#vehicleStatistics').html(response);
                    }
                });
            }

            vehicleStatistics(null);
            $('.datepicker').click(function (e) {
                $(this).val("");
            });

            $('.datepicker').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
            }).on('changeDate', function (ev) {
                $(this).datepicker('hide');
            });
        });

        function openStatToModal(_this) {
            let container = $('#routeStatistics');
            let vehicle_id = $(_this).data('id');
            let date_from = $(_this).data('date-from');
            let date_to = $(_this).data('date-to');
            let type = $(_this).data('type');
            let route_id = $(_this).data('route-id');
            routeStats({
                vehicle_id: vehicle_id,
                date_from: date_from,
                date_to: date_to,
                type: type,
                route_id: route_id
            }, container);
            $('#scheduleStatistics').addClass('d-none');
            return false;
        }

        function scheduleStatModal(_this) {
            let container = $('#scheduleStatistics');
            let vehicle_id = $(_this).data('id');
            let date_from = $(_this).data('date-from');
            let date_to = $(_this).data('date-to');
            let type = $(_this).data('type');
            let route_id = $(_this).data('route-id');
            scheduleStats({
                vehicle_id: vehicle_id,
                date_from: date_from,
                date_to: date_to,
                type: type,
                route_id: route_id
            }, container);
            return false;
        }

        //vehicle statistics table
        function routeStats($params, container) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': '*'
                }
            });
            $.ajax({
                url: "{{ route('merchant.dashboard.routeStat') }}",
                type: 'post',
                data: $params,
                success: function (response) {
                    var html = $.parseHTML(response);
                    $(container).html(response).removeClass('d-none');
                }
            });
        }

        //vehicle statistics table
        function scheduleStats($params, container) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': '*'
                }
            });
            $.ajax({
                url: "{{ route('merchant.dashboard.scheduleStat') }}",
                type: 'post',
                data: $params,
                success: function (response) {
                    var html = $.parseHTML(response);
                    $(container).html(response).removeClass('d-none');
                }
            });
        }

        function toggleRow(_this) {
            let parent = $(_this).parents('table');
            let id = $(_this).data('id');
            let icon = $(_this).find('i.fa');
            if (icon.hasClass('fa-plus')) {
                $(icon).addClass('fa-minus').removeClass('fa-plus');
            } else {
                $(icon).addClass('fa-plus').removeClass('fa-minus');
            }
            $(parent).find('.collapse-' + id).each(function (e) {
                $(this).toggleClass('d-none');
            });
        }

        /*Modal Route create script*/
        let Url = "{{ route('dashboard.ghat.suggest') }}";

        //Initialize Select2 Elements
        $(".select2").each(function () {
            initializeSelect2(this);
        });

        function initializeSelect2(select2) {
            $(select2).select2({
                placeholder: "Select ghat",
                allowClear: true,
                cache: false,
                theme: 'bootstrap4',
                ajax: {
                    url: Url,
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
        };

        $('select#starting, select#ending').on("select2:select", function (e) {
            let starting = $('select#starting').val();
            let ending = $('select#ending').val();
            $.ajax({
                url: "{{ route('dashboard.route.name') }}",
                dataType: "json",
                data: {starting: starting, ending: ending},
                success: function (data) {
                    if (data.status == true) {
                        $('input#routeNameVal').val(data.route_name);
                    }
                }
            });
        });

        var intId = 2;
        $("#addProperty").click(function () {
            var lastField = $("#buildyourform div:last");
            intId = parseFloat(intId) + 1;
            console.log(intId);
            var fieldWrapper = $("<div class='row fieldwrapper' id='field" + intId + "'/>");
            fieldWrapper.data("idx", intId);

            var field_name_wrapper = $('<div class="col-md-6 form-group">' +
                '<div class="select2-purple">' +
                '</div></div>');
            var select2 = $('<select name="property_name[]" class="select2" data-placeholder="Select ghat" data-dropdown-css-class="select2-purple" style="width: 100%;">' +
                '</select>');

            var field_paginate = $("<div class='col-md-5 form-group'>" +
                "<select class='fieldname form-control' name='property_type[]'>" +
                "<option value='via'>Via</option>" +
                "</select>" +
                "</div>");

            var removeButton = $("<div class='col-md-1'>" +
                "<button type='button' class='btn btn-danger remove'>" +
                "<i class='fa fa-trash-o'></i>" +
                "X</button>" +
                "</div> </div>");

            removeButton.click(function (e) {
                removeField(this);
            });
            fieldWrapper.append(field_name_wrapper);
            field_name_wrapper.append(select2);
            fieldWrapper.append(field_paginate);
            fieldWrapper.append(removeButton);
            $("#buildyourform").append(fieldWrapper);
            initializeSelect2($(select2));
        });

        function removeField(_this) {
            var id = _this.getAttribute('data-id');
            if (id) {

                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    url: '',
                    type: 'post',
                    data: {field_id: id},
                    success: function (data) {
                        if (data == 'true') {
                            var msg = '<div class="alert alert-success alert-dismissible">' +
                                '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>' +
                                '<strong>Successfully deleted field</strong>.' +
                                '</div>';
                            $('#msg').html(msg);
                        }
                        //console.log(data);

                    }
                });
            }
            var parent = $(_this).parents('.fieldwrapper');
            $(parent).remove();
        }

        $('#routeForm').submit(function (event) {
            event.defaultPrevented;
            var data = $(this).serialize();
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $.ajax({
                url: "{{ route('dashboard.routes.store') }}",
                type: "POST",
                data: data,
                success: function (response) {
                    if (response.status == true) {
                        var $option = $("<option/>", {
                            value: response.route.id,
                            text: response.route.name
                        });
                        $('#route_id').append($option);
                        $('#route_id').val(response.route.id);
                        $('#routeModal').modal('hide');
                    }
                    Toast.fire({
                        icon: response.label,
                        title: response.content
                    });
                },
            });
            return false;
        });
    </script>
@endsection
