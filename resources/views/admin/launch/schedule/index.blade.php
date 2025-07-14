@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content">
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link clickableTab active" data-type="active" id="active-tab" data-toggle="tab" href="#active" role="tab" aria-controls="active" aria-selected="true">Active</a>
        </li>
        <li class="nav-item">
            <a class="nav-link clickableTab" data-type="current" id="active-tab" data-toggle="tab" href="#active" role="tab" aria-controls="active" aria-selected="true">Current</a>
        </li>
        <li class="nav-item">
            <a class="nav-link clickableTab" data-type="past" id="active-tab" data-toggle="tab" href="#active" role="tab" aria-controls="active" aria-selected="true">Completed</a>
        </li>
        <li class="nav-item">
            <a class="nav-link clickableTab" data-type="inactive" id="active-tab" data-toggle="tab" href="#active" role="tab" aria-controls="active" aria-selected="false">Inactive</a>
        </li>
        <li class="nav-item">
            <a class="nav-link clickableTab" data-type="pause" id="active-tab" data-toggle="tab" href="#active" role="tab" aria-controls="active" aria-selected="false">Paused</a>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
        <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
            <div style="position: relative;">
                <div id="advancedFilter">
                    <div class="row pt-2 mb-3">
                        <div class="col-sm-1" id="scheduleAction">
                            <button class="btn btn-danger" id="schedulePauseBtn"><i class="fa fa-pause"></i> Pause</button>
                        </div>
                        <div class="col-sm-2 mb-3">
                            <div class="form-group">
                                <input type="text" id="date_from" class="form-control datepicker" placeholder="Trip date">
                            </div>
                        </div>
{{--                        <div class="col-sm-2 mb-3">--}}
{{--                            <div class="form-group">--}}
{{--                                <div class="input-group date" id="timepicker" data-target-input="nearest">--}}
{{--                                    <input type="text" name="schedule_time" id="scheduleTime"--}}
{{--                                           class="form-control disabled datetimepicker-input" data-format="H:i"--}}
{{--                                           data-target="#timepicker"/>--}}
{{--                                    <div class="input-group-append" data-target="#timepicker"--}}
{{--                                         data-toggle="datetimepicker">--}}
{{--                                        <div class="input-group-text"><i class="far fa-clock"></i></div>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                                <!-- /.input group -->--}}
{{--                            </div>--}}
{{--                        </div>--}}
                        <div class="col-sm-2 mb-3">
                            <select class="form-control" id="filterMerchant">
                                <option value="">Select merchant</option>
                            </select>
                        </div>
                        <div class="col-sm-2 mb-3">
                            <select class="form-control" id="filtervehicle">
                                <option value="">Select vehicle</option>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <select id="filterRoutes" class="form-control">
                                <option value="">Select route</option>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <select id="filterService" class="form-control">
                                <option value="">Service type</option>
                                @foreach($service_list as $key => $value)
                                <option value="{{ $key }}">{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-1">
                            <button type="reset" id="resetFilter" class="btn btn-warning"><i class="fa fa-times"></i></button>
                            <button type="button" id="filterBtn" class="btn btn-success"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>
              <table class="table table-striped projects display responsive nowrap" id="dataTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="form-control" id="checkedAll"></th>
                        <th style="width: 10%"> Trip Date </th>
                        <th> Time </th>
                        <th> vehicle </th>
                        <th> Route </th>
                        <th> Type </th>
                        <th> Cabin(b/c) </th>
                        <th> Seat(b/c) </th>
                        <th> Deck(b/c) </th>
                        <th> Discounts </th>
                        <th> Discount list </th>
                        <th> Created by </th>
                        <th> Create at </th>
                        <th style="width: 8%" class="text-center"> Status </th>
                        <th> Report </th>
                        <th><i class="fa fa-cog"></i></th>
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
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.css') }}">
    <!-- iCheck for checkboxes and radio inputs -->
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
  <!-- Select2 -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">


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
<script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.responsive.min.js') }}"></script>
<!-- <script src="{{ asset('assets/plugins/dataTable/Select-1.3.0/js/select.bootstrap4.js') }}"></script> -->
<script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
<script
    src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>    <!-- Tempusdominus Bootstrap 4 -->
<script
    src="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
<script>
    let can_edit = true, can_active = true, can_inactive = true, can_delete = false;
    let merchant_id = '';
    @can('schedule-edit')
        can_edit = true;
    @endcan
        @can('schedule-active')
        can_active = true;
    @endcan
        @can('schedule-inactive')
        can_inactive = true;
    @endcan
        @can('schedule-delete')
        can_delete = true;
    @endcan
var url = "{{ route('dashboard.schedule.index') }}";
//
// Pipelining function for DataTables. To be used to the `ajax` option of DataTables
//
$.fn.dataTable.ext.classes.sPageButton = 'page-item';
$.fn.dataTable.pipeline = function ( opts ) {
    // Configuration options
    var conf = $.extend( {
        pages: 5,     // number of pages to cache
        url: "{{ route('dashboard.schedule.index') }}",      // script url
        data: null,   // function or object with parameters to send to the server
                      // matching how `ajax.data` works in DataTables
        method: 'GET' // Ajax HTTP method
    }, opts );

    // Private variables for storing the cache
    var cacheLower = -1;
    var cacheUpper = null;
    var cacheLastRequest = null;
    var cacheLastJson = null;

    return function ( request, drawCallback, settings ) {
        var ajax          = true;
        var requestStart  = request.start;
        var drawStart     = request.start;
        var requestLength = request.length;
        var requestEnd    = requestStart + requestLength;

        if ( settings.clearCache ) {
            // API requested that the cache be cleared
            ajax = true;
            settings.clearCache = false;
        }
        else if ( cacheLower < 0 || requestStart < cacheLower || requestEnd > cacheUpper ) {
            // outside cached data - need to make a request
            ajax = true;
        }
        else if ( JSON.stringify( request.order )   !== JSON.stringify( cacheLastRequest.order ) ||
          JSON.stringify( request.columns ) !== JSON.stringify( cacheLastRequest.columns ) ||
          JSON.stringify( request.search )  !== JSON.stringify( cacheLastRequest.search )
          ) {
            // properties changed (ordering, columns, searching)
        ajax = true;
    }

        // Store the request for checking next time around
        cacheLastRequest = $.extend( true, {}, request );

        if ( ajax ) {
            // Need data from the server
            if ( requestStart < cacheLower ) {
                requestStart = requestStart - (requestLength*(conf.pages-1));

                if ( requestStart < 0 ) {
                    requestStart = 0;
                }
            }

            cacheLower = requestStart;
            cacheUpper = requestStart + (requestLength * conf.pages);

            request.start = requestStart;
            request.length = requestLength*conf.pages;

            // Provide the same `data` options as DataTables.
            if ( typeof conf.data === 'function' ) {
                // As a function it is executed with the data object as an arg
                // for manipulation. If an object is returned, it is used as the
                // data object to submit
                var d = conf.data( request );
                if ( d ) {
                    $.extend( request, d );
                }
            }
            else if ( $.isPlainObject( conf.data ) ) {
                // As an object, the data given extends the default
                $.extend( request, conf.data );
            }

            settings.jqXHR = $.ajax( {
                "type":     conf.method,
                "url":      conf.url,
                "data":     request,
                "dataType": "json",
                "cache":    true,
                "success":  function ( json ) {
                    cacheLastJson = $.extend(true, {}, json);

                    if ( cacheLower != drawStart ) {
                        json.data.splice( 0, drawStart-cacheLower );
                    }
                    if ( requestLength >= -1 ) {
                        json.data.splice( requestLength, json.data.length );
                    }

                    drawCallback( json );
                }
            } );
        }
        else {
            json = $.extend( true, {}, cacheLastJson );
            json.draw = request.draw; // Update the echo for each response
            json.data.splice( 0, requestStart-cacheLower );
            json.data.splice( requestLength, json.data.length );

            drawCallback(json);
        }
    }
};

// Register an API method that will empty the pipelined data, forcing an Ajax
// fetch on the next draw (i.e. `table.clearPipeline().draw()`)
$.fn.dataTable.Api.register( 'clearPipeline()', function () {
    return this.iterator( 'table', function ( settings ) {
        settings.clearCache = true;
    } );
} );

$(function(){
    let type = 'active';
    $('#advancedFilterBtn').click(function(e) {
        e.defaultPrevented;
        $(this).toggleClass('active');
        $('#advancedFilter').toggleClass('d-none');
    });
    var customFilter = $('#advancedFilter');
    var keyword = $('input#keywords');
    var route = $(customFilter).find('select#filterRoutes');
    var merchant = $(customFilter).find('select#filterMerchant');
    var vehicle = $(customFilter).find('select#filtervehicle');
    var status = $(customFilter).find('select#filterStatus');
    var date_from = $(customFilter).find('input#date_from');
    var scheduleTime = $(customFilter).find('input#scheduleTime');
    var search = $(customFilter).find('button#filterBtn');
    let service = $(customFilter).find('select#filterService');
    var table = $('#dataTable').DataTable( {
        "processing": true,
        "serverSide": true,
        "deferRender": true,
        "bAutoWidth": false,
        "sPageButtonActive": "active",
        "ajax": {
           'url': url,
           pages: 5, // number of pages to cache
           'data': function(data){
              // Read values
              data.keyword = $(keyword).val();
              data.date = $(date_from).val();
              data.time = $(scheduleTime).val();
              data.route = $(route).val();
               data.merchant = $(merchant).val();
               data.vehicle = $(vehicle).val();
              data.status = type;
              data.service_type = service.val();
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
                "mRender": function(data, type, row) {
                    return '<input type="checkbox" data-id="' + row['id'] + '" name="id[]" class="item" valule="' + row['id'] + '">';
                }
            },
            { "mRender": function(data, type, row)
                {
                    return '<a href="/admin/vehicle/schedule/show/'+ row['id'] +'" class="table-avatar">' + row['schedule_date'] + '</a>';
                }
            },
            { "data": "leaving_at" },
            { "data": "vehicle_name" },
            { "data": "route_name" },
            { "data": "schedule_type" },
            {
                "mRender": function(data, type, row) {
                    return row['cabin_booking'] + '/' + row['total_cabin'];
                }
            },
            {
                "mRender": function(data, type, row) {
                    return row['seat_booking'] + '/' + row['total_seat'];
                }
            },
            {
                "mRender": function(data, type, row) {
                    return row['deck_booking'] + '/' + row['total_deck'];
                }
            },
            {
                "mRender": function(data, type, row) {
                    return '<a href="/admin/vehicle/schedule/show/' + row['id'] + '?tab=discount" target="ext">' + row['discounts'] + '</a>';
                }
            },
            { "data": "discount_list" },
            { "data": "created_by" },
            { "data": "created_at" },
            { "data": "status" },
            { "mRender": function(data, type, row)
                {
                    return "<a href='/admin/vehicle/schedule/download/" + row['id'] + "' class='btn btn-default' target='_blank'><i class='fa fa-chart-bar'></i></a>";
                }
            },
            {"mRender": function ( data, type, row )
                {
                    var str = '';
                    if(row['action_btn_visible']) {
                        str += "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'>";

                        str += "<a href='/admin/vehicle/schedule/show/" + row['id'] + "' class='dropdown-item' data-schedule-id='" + row['id'] + "'><i class='fa fa-eye'></i> View</a>";
                        if (row['status'] == null) {
                            // if( can_cancel ) {
                            str += "<a href='/admin/vehicle/schedule/cancel/" + row['id'] + "/" + row['vehicle_id'] + "' class='dropdown-item'><i class='fa fa-ban'></i> Cancel</a>";
                            str += "<a href='/admin/vehicle/schedule/reschedule/" + row['id'] + "/" + row['vehicle_id'] + "' class='dropdown-item'><i class='fa fa-exchange'></i> Re-schedule</a>";
                            // }
                        } else if (row['status'] == 'PAUSE') {
                            if (can_active) {
                                str += "<a href='#' class='dropdown-item schedule-action' data-action='active' data-schedule-id='" + row['id'] + "'><i class='fa fa-check'></i> Active</a>";
                            }
                        } else if (row['status'] == 'ACTIVE') {
                            if (can_active) {
                                str += "<a href='#' class='dropdown-item schedule-action' data-action='pause' data-schedule-id='" + row['id'] + "'><i class='fa fa-pause'></i> Pause</a>";
                            }
                        }
                        str += "</div> </div>";
                    }
                    return str;
                }
            }
      ],
      "columnDefs": [
      {"targets": [0,1,2,4,5,6,8,9], "searchable": false, "orderable": false, "visible": true}
      ],
      "order": [[0, 'desc']],
      buttons: [
           'copy', 'excel', 'pdf', 'print'
        ]
  } );


    $('.clickableTab').click(function() {
        type = $(this).data('type');
        if( ['inactive', 'past', 'pause'].includes(type)) {
            $('#scheduleAction').addClass('d-none');
        } else {
            $('#scheduleAction').removeClass('d-none');
        }
        table.draw();
    });

    //Click on Search Button
    $(search).click( function(e) {
        table.draw();
    });

    $(scheduleTime).change(function(e) {
        table.draw();
    });

    $(service).change(function(e) {
        table.draw();
    });

    $(status).change(function(e) {
        type = $(this).val();
        table.draw();
    })

    //Custom Filters ( title search )
    $(keyword).keyup( function(event) {
        var keycode = (event.keyCode ? event.keyCode : event.which);
        // if(keycode == '13'){
            table.draw();
        // }
    } );

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

    $(vehicle).select2({
        placeholder: "Select vehicle",
        theme: 'bootstrap4',
        allowClear: true,
        cache: false,
        ajax: {
            url: "{{ route('dashboard.vehicle.suggest') }}",
            dataType: 'json',
            type: "GET",
            quietMillis: 50,
            data: function (term) {
                return {
                    term: term.term,
                    merchant_id: $(merchant).val()
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

    //Custom Filters ( Author search )
    $(merchant).on('select2:select', function (e) {
        e.defaultPrevented;
        table.draw();
        merchant_id = e.value;
        $(vehicle).val("").trigger('change');
    });

    //Custom Filters ( Author search )
    $(merchant).on('select2:clear', function (e) {
        e.defaultPrevented;
        table.draw();
        $(vehicle).val("").trigger('change');
    });
    //Custom Filters ( Author search )
    $(vehicle).on('select2:select', function (e) {
        e.defaultPrevented;
        table.draw();
    });

    //Custom Filters ( Author search )
    $(vehicle).on('select2:clear', function (e) {
        e.defaultPrevented;
        table.draw();
    });

    $('#resetFilter').click(function(e) {
        e.defaultPrevented;
        $('#advancedFilter').find('input').val('');
        $('#advancedFilter').find('select').val('');
        $(route).val("").trigger('change');
        $(route).select2("val", "");
        $(merchant).select2("val", "");
        $(merchant).val("").trigger('change');
        $(vehicle).select2("val", "");
        $(vehicle).val("").trigger('change');
        table.draw();
    });

    //Datemask dd/mm/yyyy
    $('#datemask').inputmask('dd/mm/yyyy', { 'placeholder': 'dd/mm/yyyy' })
    //Money Euro
    $('[data-mask]').inputmask()
    $('.datepicker').click(function(e) {
        $(this).val("");
    });

    $('.datepicker').datepicker({
      format: 'dd/mm/yyyy',
      todayHighlight:'TRUE',
      autoclose: true,
      // startDate: "-0d",
      // endDate: "+360d"
      endDate: "+30d"
    }).on('changeDate', function (ev) {
        $(this).datepicker('hide');
        table.draw();
    });

    //Timepicker
    // $('#timepicker').datepicker({
    //     format: 'LT',
    //     autoclose: true
    // }).on('hide', function(ev){
    //     alert('ok');
    // });

    $(scheduleTime).change(function(e) {
        table.draw();
    });

    //Custom Filters ( Author search )
    $(status).change( function() {
        table.draw();
    } );

    // $('#myModal').modal('show');
    $('table').on('click', '.schedule-action', function() {
        console.log( this );
        var url = "{{ route('dashboard.schedule.action') }}";
        var action = $(this).data('action');
        var id = $(this).data('schedule-id');
        if( action ) {
            var data = {action: action, id: id};
            Swal.fire({
                title: 'Are you sure?',
                text: "You are going to " + action + " this vehicle schedule.",
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
                            response = JSON.parse( response );
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
        //check all item
    $('#checkedAll').on("click", function( e ) {
      e.defaultPrevented;
      var parent = $(this).parents('table');
      if( $(this).is(":checked") ) {
        $(parent).find(".item").each(function(){
          $(this).prop('checked', true);
        });
      } else {
        $(parent).find(".item").each(function(){
          $(this).prop('checked', false);
        });;
      }
    });

    $('#schedulePauseBtn').click(function() {
        let items = $('#dataTable input.item:checked');
        if( $(items).length > 0 ) {
            let ids = [];
            $(items).each(function(e) {
                ids.push($(this).data('id'));
            });

            if( ids.length > 0 ) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    // dataType: "json",
                    type: "POST",
                    url: "{{ route('dashboard.schedule.pause') }}",
                    data: {ids: ids.join()},
                    success: function (response, textStatus, xhr) {
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
        }else {
            Toast.fire({
                icon: "error",
                title: "Sorry! no items selected"
            });
        }
    });
});
</script>
@endsection
