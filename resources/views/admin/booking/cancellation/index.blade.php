@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content">
  <ul class="nav nav-tabs" id="myTab" role="tablist">
    <li class="nav-item">
      <a class="nav-link clickableTab active" id="pending-tab" data-type="0" data-toggle="tab" href="#pending" role="tab" aria-controls="pending" aria-selected="true">Pending</a>
    </li>
    <li class="nav-item">
      <a class="nav-link clickableTab" id="approved-tab" data-type="1" data-toggle="tab" href="#approved" role="tab" aria-controls="approved" aria-selected="false">Approved</a>
    </li>
    <li class="nav-item">
      <a class="nav-link clickableTab" id="processing-tab" data-type="2" data-toggle="tab" href="#processing" role="tab" aria-controls="processing" aria-selected="false">Processing</a>
    </li>
    <li class="nav-item">
      <a class="nav-link clickableTab" id="refunded-tab" data-type="3" data-toggle="tab" href="#refunded" role="tab" aria-controls="refunded" aria-selected="false">Refunded</a>
    </li>
    <li class="nav-item">
      <a class="nav-link clickableTab" id="declined-tab" data-type="9" data-toggle="tab" href="#declined" role="tab" aria-controls="declined" aria-selected="false">Declined</a>
    </li>
  </ul>
  <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
      <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
        <div id="advancedFilterPending">
            <div class="row pt-2">
                <div class="col-sm-6">
                    <div class="input-group">
                      <input type="text" id="date_from" class="form-control datepicker" placeholder="Booking date">
                      <span class="input-group-addon m-2">
                          To
                      </span>
                      <input type="text" id="date_to" class="form-control datepicker" placeholder="Booking date">
                    </div>
                </div>
                <div class="col-sm-3">
                    <select class="form-control" id="filterMerchantPending">
                        <option value="">Select merchant</option>
                    </select>
                </div>
                <div class="col-sm-3">
                </div>
            </div>
        </div>
        <table class="table table-striped projects display responsive nowrap" id="dataTablePending">
                <thead>
                    <tr>
                        <th style="width: 1%">PNR#</th>
                        <th>Request time</th>
                        <th style="width: 20%"> Customer Info </th>
                        <th style="width:5%">Items</th>
                        <th>Booking date</th>
                        <th>Total</th>
                        <th>Refundable</th>
                        <th style="width: 8%" class="text-center">Status </th>
                        <th style="width: 60px"><i class="fas fa-cog"></i></th>
                    </tr>
                </thead>
                <tbody>
              </tbody>
        </table>
      </div>
    </div>
  </section>
  <!-- /.content -->
  @endsection

  @section('header')
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
  <!-- Select2 -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">

<link rel="stylesheet" type="text/css"
      href="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/css/responsive.dataTables.min.css') }}"/>
<style type="text/css">
#advancedFilter {
    position: absolute;
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
    .highlightApproved {
        /*background: #f93535;*/
    }
    .highlightPending {
        /*background: #f93535;*/
    }
    .highlightRefunded {
        /*background: #f93535;*/
    }
    .highlightDeclined {
        /*background: #f93535;*/
    }
</style>
  @endsection

  @section('footer')

<script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.responsive.min.js') }}"></script>
<script>
      let can_edit = false, can_active = false, can_inactive = false, can_delete = false;
    @can('order-edit')
        can_edit = true;
    @endcan
        @can('order-active')
        can_active = true;
    @endcan
        @can('order-inactive')
        can_inactive = true;
    @endcan
        @can('order-delete')
        can_delete = true;
    @endcan
var url = "{{ route('dashboard.cancellation.index') }}";
//
// Pipelining function for DataTables. To be used to the `ajax` option of DataTables
//
$.fn.dataTable.ext.classes.sPageButton = 'page-item';
$.fn.dataTable.pipeline = function ( opts ) {
    // Configuration options
    var conf = $.extend( {
        pages: 5,     // number of pages to cache
        url: "{{ route('dashboard.cancellation.index') }}",      // script url
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
    $('#advancedFilterBtn').click(function(e) {
        e.defaultPrevented;
        $(this).toggleClass('active');
        $('#advancedFilter').toggleClass('d-none');
    });
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
    let status = 0;
    let type = 0;
    var customFilter = $('#advancedFilterPending');
    var merchant = $(customFilter).find('select#filterMerchantPending');
    var date_from = $(customFilter).find('input#date_from');
    var date_to = $(customFilter).find('input#date_to');
    var search = $(customFilter).find('button#search');
    let service = "{{ $service_type }}";
    var table = $('#dataTablePending').DataTable( {
        "processing": true,
        "serverSide": true,
        "deferRender": true,
        "bAutoWidth": false,
        "sPageButtonActive": "active",
        "ajax": {
           'url': url,
           pages: 5, // number of pages to cache
           'data': function(data){
              data.date_from = $(date_from).val();
              data.date_to = $(date_to).val();
              data.merchant = $(merchant).val();
              data.service_type = service;
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
            { "data": "booking_id" },
            { "data": "created_at" },
            { "mRender": function(data, type, row)
                {
                    return '<a href="/admin/customer/show/'+ row['customer_id'] +'" class="table-avatar">' + row['customer_name'] + '</a>' +
                    '<p class="mb-0">' + row['customer_email'] + '</p>'+
                    '<p>' + row['customer_mobile'] + '</p>';
                }
            },
            { "mRender": function(data, type, row)
                {
                    return '<a href="/admin/booking/cancellation/show/'+ row['id'] +'" class="btn btn-secondary">' + row['items'] + '</a>'
                }
            },
            { "data": "booking_date" },
            { "data": "total" },
            { "data": "cancelled_amount" },
            { "mRender": function(data, type, row)
                {
                    let status;
                    switch(row['status']) {
                        case 1:
                            status = '<span class="badge badge-primary">Approved</span>';
                        break;
                        case 2:
                            status = '<span class="badge badge-warning">Processing</span>';
                        break;
                        case 3:
                            status = '<span class="badge badge-success">Refunded</span>';
                        break;
                        case 9:
                            status = '<span class="badge badge-danger">Declined</span>';
                        break;
                        default:
                            status = '<span class="badge badge-info">Pending</span>';
                        break;
                    }
                    return status;
                }
            },
            {"mRender": function ( data, type, row )
                {
                    var str =  "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'>";

                    str += "<a href='/admin/booking/cancellation/show/" + row['id'] + "' class='dropdown-item' data-booking-id='" + row['id'] + "'><i class='fa fa-eye'></i> Re-view</a>";
                    str += "</div> </div>";
                    return str;
                }
            }
      ],
      "columnDefs": [
      {"targets": [0,1, 5], "searchable": false, "orderable": false, "visible": true}
      ],
      "order": [[1, 'desc']],
      buttons: [
           'copy', 'excel', 'pdf', 'print'
        ]
  } );


    $('.clickableTab').click(function() {
        type = $(this).data('type');
        status = type;
        table.draw();
    });

    let hash = window.location.hash.substr(1);

    if(hash == 'pending') {
        status = 0;
        table.draw();
    } else if(hash == 'approved'){
        status = 1;
        table.draw();
    } else if(hash == 'processing'){
        status = 2;
        table.draw();
    } else if(hash == 'refunded'){
        status = 3;
        table.draw();
    } else if(hash == 'declined'){
        status = 9;
        table.draw();
    }

    //Click on Search Button
    $(search).click( function(e) {
        table.draw();
    });

    //Custom Filters ( Author search )
    $(merchant).on('select2:select', function (e) {
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


    //Datemask dd/mm/yyyy
    $('#datemask').inputmask('dd/mm/yyyy', { 'placeholder': 'dd/mm/yyyy' })
    //Money Euro
    $('[data-mask]').inputmask()
    $('.datepicker').click(function(e) {
        $(this).val("");
    });

    $(date_from).datepicker({
      format: 'dd/mm/yyyy',
      todayHighlight:'TRUE',
      autoclose: true,
      // startDate: "-0d",
      // endDate: "+360d"
      endDate: "0d"
    }).on('changeDate', function (ev) {
        $(this).datepicker('hide');
        table.draw();
    });


    $(date_to).datepicker({
      format: 'dd/mm/yyyy',
      todayHighlight:'TRUE',
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
