@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content">
  <ul class="nav nav-tabs" id="myTab" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Booking history</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Activities</a>
    </li>
  </ul>
  <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
    <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
      <div class="row">
          <div class="col-12 col-md-12 col-lg-8 order-2 order-md-1">
            <div id="advancedFilter">
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
                            <select class="form-control select2"  id="filterMerchant" id="items" data-placeholder="Select merchant" data-dropdown-css-class="select2-purple" style="width: 100%;">
                                <option value="">Select merchant</option>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <select class="form-control" id="status">
                                <option value="">Select status</option>
                                <option value="0">Pending</option>
                                <option value="1" selected="selected">Active</option>
                                <option value="2">Cancelled</option>
                            </select>
                        </div>
                    </div>
            </div>
            <table class="table table-striped projects" id="dataTableHistory">
                <thead>
                    <tr>
                        <th>Booking date</th>
                        <th style="width:5%">Items</th>
                        <th>Cancelled</th>
                        <th>Subtotal</th>
                        <th>Discount</th>
                        <th>VAT</th>
                        <th>Service Charge</th>
                        <th>Bank Charge</th>
                        <th>Total</th>
                        <th style="width: 8%" class="text-center">Status </th>
                        <th style="width: 60px"><i class="fas fa-cog"></i></th>
                    </tr>
                </thead>
                <tbody>
              </tbody>
            </table>
          </div>
          <div class="col-12 col-md-12 col-lg-4 order-1 order-md-2">
              <h3>Statistics</h3>
              <table class="table">
                  <tr>
                      <th>Total Bookings</th>
                      <td>{{ $stat['total_bookings'] }}</td>
                  </tr>
                  <tr>
                      <th>Total Amount</th>
                      <td>{{ $stat['total_booking_amount'] }}</td>
                  </tr>
                  <tr>
                      <th>Total Vat (Customer)</th>
                      <td>{{ $stat['total_vat'] }} ({{ $stat['total_customer_vat'] }}}</td>
                  </tr>
                  <tr>
                      <th>Total Charge</th>
                      <td>{{ $stat['total_charge'] }}</td>
                  </tr>
                  <tr>
                      <th>Total Cancelled</th>
                      <td>{{ $stat['total_cancelled'] }}</td>
                  </tr>
                  <tr>
                      <th>Cancelled amount</th>
                      <td>{{ $stat['total_cancelled_amount'] }}</td>
                  </tr>
                  <tr>
                      <th>Total Discount</th>
                      <td>{{ $stat['total_discounts'] }}</td>
                  </tr>
                  <tr>
                      <th>Total Refunded</th>
                      <td>{{ $stat['total_refunded'] }}</td>
                  </tr>
              </table>
              <h3>Most visited</h3>
              <table class="table">
                  <tr>
                      <th>Route</th>
                      <th>Visits</th>
                  </tr>
                  @if($mostVisited)
                      @foreach( $mostVisited as $visit)
                  <tr>
                      <td>{{ $visit['route_name'] }}</td>
                      <td>{{ count($visit['total']) }}</td>
                  </tr>
                      @endforeach
                  @endif
              </table>
            <h3 class="text-secondary"> {{ $customer->name }} @can('customer-edit') <a href="{{ route('dashboard.customer.edit', $customer->id ) }}"><i class="fa fa-edit"></i></a>@endcan</h3><hr>
            <h5 class="mt-5 text-muted">Contact info</h5>
            <ul class="list-unstyled">
              <li>
                <a href="" class="btn-link text-secondary"><i class="fas fa-mobile"></i> {{ $customer->mobile }}</a>
              </li>
              <li>
                <a href="" class="btn-link text-secondary"><i class="fas fa-envelope"></i> {{ $customer->email }}</a>
              </li>
              <li>
                <a href="" class="btn-link text-secondary"><i class="fas fa-printer"></i> {{ $customer->fax }}</a>
              </li>
              <!-- <li>
                <a href="" class="btn-link text-secondary"><i class="far fa-fw fa-file-word"></i> Contract-10_12_2014.docx</a>
              </li> -->
            </ul>
            <!-- <div class="text-center mt-5 mb-3">
              <a href="#" class="btn btn-sm btn-primary">Add files</a>
              <a href="#" class="btn btn-sm btn-warning">Report contact</a>
            </div> -->
          </div>
        </div>
      </div>
      <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
          <table class="table table-bordered table-striped">
            <tr>
              <th>Time</th>
              <th>Subject</th>
              <th>URL</th>
              <th>Method</th>
              <th>Ip</th>
              <th width="300px">User Agent</th>
              <th>Action</th>
            </tr>
            @if($customer->logs->count())
              @foreach($customer->logs as $key => $log)
              <tr>
                <td>{{ date('d/m/Y h:i a', strtotime($log->created_at)) }}</td>
                <td>{{ $log->subject }}</td>
                <td class="text-success">{{ $log->url }}</td>
                <td><label class="label label-info">{{ $log->method }}</label></td>
                <td class="text-warning">{{ $log->ip }}</td>
                <td class="text-danger">{{ $log->agent }}</td>
                <td><button class="btn btn-danger btn-sm">Delete</button></td>
              </tr>
              @endforeach
            @else
              <tr>
                <td colspan="7">No activities found.</td>
              </tr>
            @endif
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
<script type="text/javascript">
jQuery(function($){
    var customFilter = $('#advancedFilter');
    var merchant = $(customFilter).find('select#filterMerchant');
    var date_from = $(customFilter).find('input#date_from');
    var date_to = $(customFilter).find('input#date_to');
    var status = $(customFilter).find('select#status');
    var table = $('#dataTableHistory').DataTable( {
        "processing": true,
        "serverSide": true,
        "deferRender": true,
        "bAutoWidth": false,
        "sPageButtonActive": "active",
        "ajax": {
           'url': "{{ route('dashboard.customer.booking', $customer->id) }}",
           pages: 5, // number of pages to cache
           'data': function(data){
              data.merchant = $(merchant).val();
              data.date_from = $(date_from).val();
              data.date_to = $(date_to).val();
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
            { "data": "created_at" },
            { "mRender": function(data, type, row)
                {
                    return '<a href="/admin/booking/show/'+ row['id'] +'" class="btn btn-secondary">' + row['booking_items'] + '</a>'
                }
            },
            { "mRender": function(data, type, row)
                {
                    return '<a href="/admin/booking/show/'+ row['id'] +'" class="btn btn-outline-danger">' + row['cancelled_items'] + '</a>'
                }
            },
            { "data": "subtotal" },
            { "data": "discount" },
            { "data": "vat_total" },
            { "data": "charge_total" },
            { "data": "bank_charge" },
            { "data": "total" },
            { "mRender": function(data, type, row)
                {
                    return row['status'];
                }
            },
            {"mRender": function ( data, type, row )
                {
                    var str =  "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'>";

                    str += "<a href='/admin/booking/show/" + row['id'] + "' class='dropdown-item' data-booking-id='" + row['id'] + "'><i class='fa fa-eye'></i> View</a>";
                    str += "</div> </div>";
                    return str;
                }
            }
      ],
      "columnDefs": [
      {"targets": [1,2,3,4,5,6,8], "searchable": false, "orderable": false, "visible": true}
      ],
      "order": [[0, 'desc']],
      buttons: [
           'copy', 'excel', 'pdf', 'print'
        ]
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
  });
</script>
  @endsection
