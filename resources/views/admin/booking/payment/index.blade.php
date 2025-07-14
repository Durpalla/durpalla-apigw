@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content">
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item">
          <a class="nav-link active clickableTab" data-type="pending" id="pending-tab" data-toggle="tab" href="#pending" role="tab" aria-controls="pending" aria-selected="true">Pending</a>
        </li>
        <li class="nav-item">
            <a class="nav-link clickableTab" data-type="due" id="dues-tab" data-toggle="tab" href="#dues" role="tab" aria-controls="dues" aria-selected="false">Dues</a>
        </li>
        <li class="nav-item">
            <a class="nav-link clickableTab" data-type="success" id="success-tab" data-toggle="tab" href="#success" role="tab" aria-controls="success" aria-selected="false">Completed</a>
        </li>
        <li class="nav-item">
          <a class="nav-link clickableTab" data-type="canceled" id="failed-tab" data-toggle="tab" href="#failed" role="tab" aria-controls="failed" aria-selected="false">Failed</a>
        </li>
      </ul>
    <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
        <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                <div id="advancedFilter">
                    <div class="row pt-2">
                        <div class="col-sm-4">
                            <div class="input-group">
                                <input type="text" id="date_from" class="form-control datepicker" placeholder="Booking date">
                                <span class="input-group-addon m-2">
                                    To
                                </span>
                                <input type="text" id="date_to" class="form-control datepicker" placeholder="Booking date">
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <input type="text" class="form-control" id="invoiceID" placeholder="Invoice no.">
                        </div>
                        <div class="col-sm-2">
                            <input type="text" class="form-control" id="transactionID" placeholder="Transaction ID">
                        </div>
                        <div class="col-sm-1">
                            <input type="text" class="form-control" id="bankTrxID" placeholder="Bank Trx ID">
                        </div>
                        <div class="col-sm-2">
                            <select class="form-control" id="paymentGateway">
                                <option value="">Select gateway</option>
                                <option value="bkash">Bkash</option>
                                <option value="nagad">Nagad</option>
                                <option value="sslcommerz">SslCommerz</option>
                            </select>
                        </div>
                        <div class="col-sm-1">
                            <button class="btn btn-success" id="searchBtn"><i class="fa fa-search"></i></button>
                            <button class="btn btn-warning" id="exportPayment"><i class="fa fa-file-excel"></i></button>
                        </div>
                    </div>
                </div>
              <table class="table table-striped projects display responsive nowrap" id="dataTable">
                <thead>
                    <tr>
                        <th style="width: 1%"> Invoice#</th>
                        <th>Paid at</th>
                        <th style="width: 20%"> Customer Info </th>
                        <th>Payment method</th>
                        <th>Transaction ID</th>
                        <th>Bank Trx ID</th>
                        <th style="width:5%">Total Payable</th>
                        <th>Total Paid</th>
                        <th>Dues</th>
                        <th>Bank charge</th>
                        <th>Store amount</th>
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
var url = "{{ route('dashboard.payment.index') }}";
//
// Pipelining function for DataTables. To be used to the `ajax` option of DataTables
//
$.fn.dataTable.ext.classes.sPageButton = 'page-item';
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
    let type = 'pending';
    let status = 'pending';
    var customFilter = $('#advancedFilter');
    var keyword = $('input#keywords');
    var merchant = $(customFilter).find('select#filterMerchant');
    var date_from = $(customFilter).find('input#date_from');
    var date_to = $(customFilter).find('input#date_to');
    var search = $(customFilter).find('button#searchBtn');
    var invoice = $(customFilter).find('input#invoiceID');
    var transaction_id = $(customFilter).find('input#transactionID');
    var bank_trx = $(customFilter).find('input#bankTrxID');
    let service = "{{ $service_type }}";
    let gateway = $(customFilter).find('select#paymentGateway');
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
              data.invoice_id = $(invoice).val();
              data.date_from = $(date_from).val();
              data.date_to = $(date_to).val();
              data.transaction_id = $(transaction_id).val();
              data.bank_trx = $(bank_trx).val();
              data.service_type = service;
              data.status = status;
              data.gateway = $(gateway).val();
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
                    if( row['customer'] ) {
                        return '<a href="/admin/customer/show/'+ row['customer_id'] +'" class="table-avatar">' + row['customer']['name'] + '</a>' +
                        '<p class="mb-0">' + row['customer']['email'] + '</p>'+
                        '<p>' + row['customer']['mobile'] + '</p>';
                    } else {
                        return '';
                    }
                }
            },
            { "data": "gateway" },
            { "data": "transaction_id" },
            { "data": "bank_tran_id" },
            { "data": "booking.total_payable" },
            { "data": "paid_amount" },
            { "data": "dues" },
            { "mRender": function(data, type, row)
                {
                    let charge = (row['paid_amount'] - row['store_amount']);
                    return charge.toFixed(2);
                }
            },
            { "data": "store_amount" },
            { "mRender": function(data, type, row)
                {
                    let badge = 'info';
                    switch(row['status']) {
                        case 'success':
                            badge = 'success';
                        break;
                        case 'fail':
                            badge = 'danger';
                        break;
                        case 'canceled':
                            badge = 'warning';
                        break;
                    }
                    return '<span class="badge badge-' + badge + '">' + row['status'] + '</span>';
                }
            },
            {"mRender": function ( data, type, row )
                {
                    var str =  "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'>";

                    str += "<a href='/admin/booking/show/" + row['booking_id'] + "' class='dropdown-item' data-booking-id='" + row['booking_id'] + "'><i class='fa fa-eye'></i> Re-view</a>";
                    if( can_active ) {
                        str += "<a href='/admin/booking/edit/" + row['id'] + "' class='dropdown-item'><i class='fa fa-edit'></i> Edit</a>";
                    }
                    if( parseInt(row['status']) == 1 ) {
                        if( can_inactive ) {
                            str += "<a href='#' class='dropdown-item booking-action' data-action='inactive' data-booking-id='" + row['id'] + "'><i class='fa fa-ban'></i> Inactive</a>";
                        }
                    } else if(parseInt(row['status']) == 0) {
                        if( can_active ) {
                            str += "<a href='#' class='dropdown-item booking-action' data-action='active' data-booking-id='" + row['id'] + "'><i class='fa fa-check'></i> Active</a>";
                        }
                    } else {
                        if( can_active ) {
                            str += "<a href='#' class='dropdown-item booking-action' data-action='reactive' data-booking-id='" + row['id'] + "'><i class='fa fa-check'></i> Re-active</a>";
                        }
                    }
                    if( row['deleted_at'] == '' ) {
                        if( can_delete ) {
                            str += "<a href='#' class='dropdown-item booking-action' data-action='softdelete' data-booking-id='" + row['id'] + "'><i class='fa fa-times'></i> Delete</a>";
                        }
                    } else {
                        if( can_delete ) {
                            str += "<a href='#' class='dropdown-item booking-action' data-action='delete' data-booking-id='" + row['id'] + "'><i class='fa fa-times'></i> Delete</a>";
                        }
                    }
                    str += "</div> </div>";
                    return str;
                }
            }
      ],
      "columnDefs": [
      {"targets": [0, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11], "searchable": false, "orderable": false, "visible": true}
      ],
      "order": [[1, 'desc']],
      buttons: [
           'copy', 'excel', 'pdf', 'print'
        ]
    });

    $('.clickableTab').click(function() {
        type = $(this).data('type');
        status = type;
        table.draw();
    });

    let hash = window.location.hash.substr(1);

    if(hash == 'pending') {
        status = 'pending';
        table.draw();
    } else if(hash == 'dues'){
        status = 'due';
        table.draw();
    } else if(hash == 'success'){
        status = 'success';
        table.draw();
    } else if(hash == 'failed'){
        status = 'canceled';
        table.draw();
    }

    //Click on Search Button
    $(search).click( function(e) {
        table.draw();
    });

    //Custom Filters ( title search )
    $(invoice).keyup( function(event) {
        table.draw();
    });

    $(transaction_id).keyup( function(event) {
        table.draw();
    });

    $(bank_trx).keyup( function(event) {
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

    //Custom Filters ( Author search )
    $(gateway).change( function() {
        table.draw();
    } );

    $('#exportPayment').on("click", function(e) {
       let url = "{{ route('payment.export') }}?date_from=" + $(date_from).val() + "&service=" + service + "&date_to=" + $(date_to).val() + "&status=" + status + "&merchant=" + $(merchant).val() + "&gateway=" + $(gateway).val() + "&bank_trx=" + $(bank_trx).val() + "&transaction_id=" + $(transaction_id).val()
        window.location.href = url
    });

});
</script>
@endsection
