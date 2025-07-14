@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">
          <div class="card" style="background-color: none;">
            <div class="card-header" style="padding: .65rem 1.25rem;">
              <h3 class="card-title">{{ $title ?? '' }}</h3>
                <div class="card-tools pt-2">
                    <div class="btn-group" role="group" aria-label="Basic example">
                        <div class="input-group input-group-sm" role="group" aria-label="Basic example">
                            <input type="text" class="form-control" id="keywords" placeholder="search">
                            <button class="btn btn-warning btn-sm"><i class="fa fa-search"></i></button>
                            <button class="btn btn-sm ml-2" id="advancedFilterBtn"><i class="fa fa-sliders-h"></i></button>
                        </div>
                    </div>
                </div>
                <div class="clearfix"></div>
                <div id="advancedFilter" class="d-none">
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
                            <select class="form-control" id="filterMerchant">
                                <option value="">Select merchant</option>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <select class="form-control" id="status">
                                <option value="">Select status</option>
                                <option value="1">Active</option>
                                <option value="9">Deleted</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <table class="table table-striped projects" id="dataTable">
                <thead>
                    <tr>
                        <th style="width: 1%"> <i class="fas fa-image"></i> </th>
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
      </div>
    </div>
  </section>
@endsection

@section('header')
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
  <!-- Select2 -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
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
    var customFilter = $('#advancedFilter');
    var keyword = $('input#keywords');
    var merchant = $(customFilter).find('select#filterMerchant');
    var date_from = $(customFilter).find('input#date_from');
    var date_to = $(customFilter).find('input#date_to');
    var status = $(customFilter).find('select#status');
    var search = $(customFilter).find('button#search');
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
              data.date_from = $(date_from).val();
              data.date_to = $(date_to).val();
              data.merchant = $(merchant).val();
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
        "columns": [            
            { "data": "id" },
            { "data": "created_at" },
            { "mRender": function(data, type, row)
                {
                    return '<a href="/admin/customer/show/'+ row['id'] +'" class="table-avatar">' + row['customer_name'] + '</a>' +
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
        ],
        "createdRow": function ( row, data, index ) {
            console.log(data.status);
            if ( data.status == 1 ){
                $(row).addClass('highlightApproved');
            } else if(data.status == 0 ) {
                $(row).addClass('highlightPending');
            } else if(data.status == 2 ) {
                $(row).addClass('highlightProcessing');
            } else if(data.status == 3 ) {
                $(row).addClass('highlightRefunded');
            } else if(data.status == 9 ) {
                $(row).addClass('highlightDeclined');
            }
        }
  } );

    //Click on Search Button
    $(search).click( function(e) {
        table.draw();
    });

    //Custom Filters ( title search )
    $(keyword).keyup( function(event) {
        var keycode = (event.keyCode ? event.keyCode : event.which);
        // if(keycode == '13'){
            table.draw();
        // }
    } );

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
      endDate: "0d"
    }).on('changeDate', function (ev) {
        $(this).datepicker('hide');
        table.draw();
    });


    //Custom Filters ( Author search )
    $(status).change( function() {
        table.draw();
    } );

    // $('#myModal').modal('show');
    $('table').on('click', '.customer-action', function() {
        console.log( this );
        var url = "{{ route('dashboard.customer.index') }}";
        var action = $(this).data('action');
        var id = $(this).data('customer-id');
        if( action == 'request' ) {

        } else {
            var data = {action: action, id: id};
            Swal.fire({
                title: 'Are you sure?',
                text: "You are going to " + action + " this customer account.",
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

    $('table').on('click', '.customer-request', function() {
        var id = $(this).data('customer-id');
        var name = $(this).data('customer-name');
        var url = "{{ route('dashboard.customer.store') }}";
        var modalContent = $(confirmModal).find('.modal-body');
        $(confirmModal).find('form').attr('action', url);
        $(modalContent).html("");
        $(confirmModal).find('.modal-title').text('Make request').css('text-transform', 'capitalize');
        $(modalContent).append('\n' +
            '<div class="form-group">\n' +
            '<label class="control-label">Customer : </label>\n' +
            '<span><strong>' + name + '</strong></span>\n' +
            '<input type="hidden" name="customer_id" value="' + id + '">\n' +
            '</div>');
        $(modalContent).append('\n' +
            '<div class="form-group">\n' +
            '<label class="control-label">Request for</label>\n' +
            '<select name="type" class="form-control" id="customerRequestType">\n' +
            '<option value="">Select type</option>\n' +
            '<option value="Package">Change Package</option>\n' +
            '<option value="Email">Change Email</option>\n' +
            '<option value="Username">Change Username</option>\n' +
            '<option value="Password">Change Password</option>\n' +
            '<option value="CustomerID">Change Customer ID</option>\n' +
            '<option value="Primary contact">Change Primary mobile</option>\n' +
            '<option value="Secondary contact">Change Secondary mobile</option>\n' +
            '</select>\n' +
            '<input type="hidden" name="customer_id" value="' + id + '">\n' +
            '</div>');
        $(modalContent).find('#customerRequestType').change(function(e) {
            var type = $(this).val();
            if( ['Package'].includes(type) ) {
                var url = "{{ route('dashboard.customer.index') }}";
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    type: "POST",
                    url: url,
                    data: {type: type, customer_id: id},
                    success: function (response, textStatus, xhr) {
                        // response = JSON.parse( response );
                        if (response.status == true) {
                            $(modalContent).append('\n' +
                                '<div class="form-group">\n' +
                                '<label class="control-label">New ' + type + '</label>\n' +
                                '<select name="new_property" id="newPackageSelect" class="form-control">\n' +
                                '<option value="">Choose package</option>\n' +
                                '</select>\n' +
                                '</div>');
                            console.log( response.data );
                            $.each(response.data, function (key, value) {
                                console.log( key + ' - ' + value );
                                $(modalContent).find('#newPackageSelect')
                                    .append($("<option></option>")
                                        .attr("value", key)
                                        .text(value));
                            });

                            $(confirmModal).modal('show');
                        } else {
                            Swal.fire(
                                'Warning!',
                                'Action not succeded',
                                'error'
                            );
                        }
                    }
                });
            } else {
                $(modalContent).append('\n' +
                    '<div class="form-group">\n' +
                    '<label class="control-label">New ' + type + '</label>\n' +
                    '<input type="text" class="form-control" name="new_property" placeholder="New '+ type + '" required>\n' +
                    '</div>');
                $(confirmModal).modal('show');
            }
        });
        $(confirmModal).modal('show');
        return false;
    });

});
</script>
@endsection