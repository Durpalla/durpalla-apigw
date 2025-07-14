@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="card" style="background-color: none;">
                    <div class="card-header">
                        <h3 class="card-title">Booking of this schedule</em></h3>
                        <div class="card-tools">
                            <div class="btn-group">
                                <a href="{{route('dashboard.vehicle.show', $schedule->vehicle_id)}}" class="btn btn-success">Back to schedule</a>
                                <a href="{{route('dashboard.schedule.cancelConfirm', $schedule->id )}}" class="btn btn-danger" id="cancelConfirm">Confirm cancel</a>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <table class="table table-striped" id="scheduleBookings">
                                    <thead>
                                        <tr>
                                          <th>Invoice</th>
                                          <th>Type</th>
                                          <th>tickets</th>
                                          <th>Passenger</th>
                                          <th>Booking date</th>
                                          <th>Schedule date</th>
                                          <th>Payment method</th>
                                          <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection

@section('header')
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.css') }}">
    <!-- iCheck for checkboxes and radio inputs -->
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
    <!-- Select2 -->
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <style>
        .height {
            min-height: 200px;
        }

        .icon {
            font-size: 47px;
            color: #5CB85C;
        }

        .iconbig {
            font-size: 77px;
            color: #5CB85C;
        }

        .table > tbody > tr > .emptyrow {
            border-top: none;
        }

        .table > thead > tr > .emptyrow {
            border-bottom: none;
        }

        .table > tbody > tr > .highrow {
            border-top: 3px solid;
        }
    </style>
@endsection

@section('footer')
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
<script type="text/javascript">
jQuery(function($) {
    let url = "{{ route('dashboard.schedule.bookings', $schedule->id) }}";
    let table = $('#scheduleBookings').DataTable({
        "processing": true,
        "serverSide": true,
        "deferRender": true,
        "bAutoWidth": false,
        "sPageButtonActive": "active",
        "ajax": {
           'url': url,
           pages: 5, // number of pages to cache
           'data': function(data){
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
            "mRender": function(data, type, row ) {
              var str =  '<a href="/admin/booking/show/' + row['booking_id'] + '" class="cabin-action2">#' + row['booking_id'] + '</a>';
              return str;
            }
          },
          { "data": "booking_type" },
          {
            "mRender": function( data, type, row ) {
              let passenger = JSON.parse(row['passenger']);
              let str = '';
              if( row['booking_type'] == 'cabin' ) {
                str = '<span class="badge badge-info"><i class="fa fa-bed"></i> ' + row['item']['cabin_no'] + '</span>';
              } else if( row['booking_type'] == 'seat' ) {
                str = '<span class="badge badge-info"><i class="fa fa-chair"></i> ' + row['item']['cabin_no'] + '</span>';
              } else {
                str = '<span class="badge badge-info"><i class="fa fa-ticket-alt"></i> x ' + passenger.person + '</span>';
              }

               return '<a href="/admin/booking/show/' + row['booking_id'] + '">' + str + '</a>';
            }
          },
          {
            "mRender": function( data, type, row ) {
              let passenger = JSON.parse(row['passenger']);
              return passenger.name + ' - ' + passenger.mobile;
            }
          },
          {
            "mRender": function(data, type, row) {
              let date = new Date(row['booking_date']);
              let month = date.getMonth() + 1;
              return date.getDate() + '/' + month + '/' + date.getFullYear();
            }
          },
          {
            "mRender": function(data, type, row) {
              let date = new Date(row['trip_date']);
              let month = date.getMonth() + 1;
              return date.getDate() + '/' + month + '/' + date.getFullYear();
            }
          },
          { "data": "booking.payment.payment_method" },
          {"mRender": function ( data, type, row )
            {
              let str = '';
              switch(row['status']) {
                case 0 :
                  str = '<span class="badge badge-info">Pending</span>';
                  break;
                case 1 :
                  str = '<span class="badge badge-success">Success</span>';
                  break;
                default:
                  str = '<span class="badge badge-danger">Cancelled</span>';
                break;
              }

              return str;
            }
          }
      ],
      "columnDefs": [
      {"targets": [0,1, 5], "searchable": false, "orderable": false, "visible": true}
      ],
      "order": [[3, 'desc']],
      buttons: [
           'copy', 'excel', 'pdf', 'print'
        ]
    });

    $('#cancelConfirm').click(function(e) {
        let confirmed = confirm('Are you sure to cancel this schedule? All booking of this schedule will be cancelled.');

        if( confirmed ) {
            return true;
        }
        return false;
    });
});
</script>
@endsection
