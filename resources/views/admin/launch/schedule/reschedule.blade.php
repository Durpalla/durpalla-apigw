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
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-8">
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
                            <div class="col-4">
                                @canany(['schedule-assign', 'schedule-create', 'route-mapping'])
                                <h3 class="text-secondary"><i class="fas fa-plus"></i> Re-schedule date</h3><hr>
                                <form action="{{ route('dashboard.schedule.rescheduleConfirm', $schedule->id) }}" method="POST">
                                  @csrf
                                  <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                                  <input type="hidden" name="tab" value="schedule">
                                  <div class="form-group">
                                    <label>Date</label>

                                    <div class="input-group">
                                        <input type="text" name="schedule_date" value="{{ old('schedule_date') }}" class="form-control datepicker" data-inputmask-alias="datetime" data-inputmask-inputformat="dd/mm/yyyy" data-mask required>
                                        <div class="input-group-addon">
                                            <span class="glyphicon glyphicon-th"></span>
                                        </div>
                                    </div>

                                    @error('leaving_at')
                                    <span class="invalid-feedback" role="alert">
                                      <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                  </div>
                                  <div class="form-group">
                                    <label>Time:</label>
                                    <div class="input-group date" id="timepicker" data-target-input="nearest">
                                      <input type="text" name="schedule_time" value="{{ old('schedule_time') }}" class="form-control disabled datetimepicker-input" data-format="H:i" data-target="#timepicker"/>
                                      <div class="input-group-append" data-target="#timepicker" data-toggle="datetimepicker">
                                        <div class="input-group-text"><i class="far fa-clock"></i></div>
                                      </div>
                                    </div>
                                    <!-- /.input group -->
                                  </div>
                                  <div class="form-group">
                                    <button class="btn btn-block btn-primary" type="submit">Save</button>
                                  </div>
                                </form>
                                @endcanany
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
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script src="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>

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
        $('.datepicker').datepicker({
            format: 'dd/mm/yyyy',
            todayHighlight:'TRUE',
            autoclose: true,
            startDate: "-0d",
            endDate: "+30d"
        }).on('changeDate', function (ev) {
            $(this).datepicker('hide');
        });
    });

        //Timepicker
        $('#timepicker').datetimepicker({
            format: 'LT',
            autoclose: true
        });
    </script>
@endsection
