@extends('layouts.master')

@section('content')
<!-- Info boxes -->
<div class="row">
    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box">
        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-ship"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">vehicle</span>
          <span class="info-box-number">
            {{ $stats['total_vehicles'] }}
            <!-- <small>%</small> -->
          </span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->
    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box mb-3">
        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-bed"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Cabins</span>
          <span class="info-box-number">{{ $stats['total_cabins']}}</span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->

    <!-- fix for small devices only -->
    <div class="clearfix hidden-md-up"></div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box mb-3">
        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-chair"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Seats</span>
          <span class="info-box-number">{{ $stats['total_seats']}}</span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->
    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box mb-3">
        <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-ticket-alt"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Decks</span>
          <span class="info-box-number">{{ $stats['total_decks']}}</span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->
    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box">
        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-shopping-cart"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Bookings</span>
          <span class="info-box-number">
            {{ $stats['total_bookings'] }}
            <!-- <small>%</small> -->
          </span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->
    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box mb-3">
        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-bed"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Cabin bookings</span>
          <span class="info-box-number">{{ $stats['total_cabin_bookings']}}</span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->

    <!-- fix for small devices only -->
    <div class="clearfix hidden-md-up"></div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box mb-3">
        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-chair"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Seat bookings</span>
          <span class="info-box-number">{{ $stats['total_seat_bookings']}}</span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->
    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box mb-3">
        <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-ticket-alt"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Deck bookings</span>
          <span class="info-box-number">{{ $stats['total_deck_bookings']}}</span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->
  </div>
  <!-- /.row -->

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title">Monthly bookings</h5>

          <div class="card-tools">
            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
              <i class="fas fa-minus"></i>
            </button>
            <div class="btn-group">
              <button type="button" class="btn btn-tool dropdown-toggle" data-toggle="dropdown">
                <i class="fas fa-wrench"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-right" role="menu">
                <a href="#" class="dropdown-item">Action</a>
                <a href="#" class="dropdown-item">Another action</a>
                <a href="#" class="dropdown-item">Something else here</a>
                <a class="dropdown-divider"></a>
                <a href="#" class="dropdown-item">Separated link</a>
              </div>
            </div>
            <button type="button" class="btn btn-tool" data-card-widget="remove">
              <i class="fas fa-times"></i>
            </button> -->
          </div>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
          <div class="row">
            <div class="col-md-12">
              <p class="text-center">
                <strong>Sales: 1 Jan, 2020 - 30 Jan, 2020</strong>
              </p>

              <div class="chart">
                <!-- Sales Chart Canvas -->
                <canvas id="salesChart" height="180" style="height: 180px;"></canvas>
              </div>
              <!-- /.chart-responsive -->
            </div>
            <!-- /.col -->
            <!-- <div class="col-md-4">
              <p class="text-center">
                <strong>Goal Completion</strong>
              </p>

              <div class="progress-group">
                Add Products to Cart
                <span class="float-right"><b>160</b>/200</span>
                <div class="progress progress-sm">
                  <div class="progress-bar bg-primary" style="width: 80%"></div>
                </div>
              </div>

              <div class="progress-group">
                Complete Purchase
                <span class="float-right"><b>310</b>/400</span>
                <div class="progress progress-sm">
                  <div class="progress-bar bg-danger" style="width: 75%"></div>
                </div>
              </div>

              <div class="progress-group">
                <span class="progress-text">Visit Premium Page</span>
                <span class="float-right"><b>480</b>/800</span>
                <div class="progress progress-sm">
                  <div class="progress-bar bg-success" style="width: 60%"></div>
                </div>
              </div>

              <div class="progress-group">
                Send Inquiries
                <span class="float-right"><b>250</b>/500</span>
                <div class="progress progress-sm">
                  <div class="progress-bar bg-warning" style="width: 50%"></div>
                </div>
              </div>
            </div> -->
            <!-- /.col -->
          </div>
          <!-- /.row -->
        </div>
        <!-- ./card-body -->
        <!-- <div class="card-footer">
          <div class="row">
            <div class="col-sm-3 col-6">
              <div class="description-block border-right">
                <span class="description-percentage text-success"><i class="fas fa-caret-up"></i> 17%</span>
                <h5 class="description-header">$35,210.43</h5>
                <span class="description-text">TOTAL REVENUE</span>
              </div>
            </div>
            <div class="col-sm-3 col-6">
              <div class="description-block border-right">
                <span class="description-percentage text-warning"><i class="fas fa-caret-left"></i> 0%</span>
                <h5 class="description-header">$10,390.90</h5>
                <span class="description-text">TOTAL COST</span>
              </div>
            </div>
            <div class="col-sm-3 col-6">
              <div class="description-block border-right">
                <span class="description-percentage text-success"><i class="fas fa-caret-up"></i> 20%</span>
                <h5 class="description-header">$24,813.53</h5>
                <span class="description-text">TOTAL PROFIT</span>
              </div>
            </div>
            <div class="col-sm-3 col-6">
              <div class="description-block">
                <span class="description-percentage text-danger"><i class="fas fa-caret-down"></i> 18%</span>
                <h5 class="description-header">1200</h5>
                <span class="description-text">GOAL COMPLETIONS</span>
              </div>
            </div>
          </div>
        </div> -->
        <!-- /.card-footer -->
      </div>
      <!-- /.card -->
    </div>
    <!-- /.col -->
  </div>
  <!-- /.row -->

  <!-- Main row -->
  <div class="row">
    <!-- Left col -->
    <div class="col-md-8">
      <!-- MAP & BOX PANE -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Upcoming schedules</h3>

          <div class="card-tools">
            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
              <i class="fas fa-minus"></i>
            </button>
            <button type="button" class="btn btn-tool" data-card-widget="remove">
              <i class="fas fa-times"></i>
            </button> -->
          </div>
        </div>
        <!-- /.card-header -->
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table m-0">
              <thead>
              <tr>
                <th>Trip date</th>
                <th>vehicle</th>
                <th>Route</th>
                <th>Type</th>
                <th><i class="fa fa-wrench"></i></th>
              </tr>
              </thead>
              <tbody>
              </tbody>
            </table>
          </div>
        </div>
        <!-- /.card-body -->
        <div class="card-footer clearfix">
          <a href="javascript:void(0)" class="btn btn-sm btn-secondary float-right">View All schedule</a>
        </div>
      </div>
      <!-- /.card -->


      <!-- TABLE: LATEST ORDERS -->
      <div class="card">
        <div class="card-header border-transparent">
          <h3 class="card-title">Recent bookings</h3>

          <div class="card-tools">
            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
              <i class="fas fa-minus"></i>
            </button>
            <button type="button" class="btn btn-tool" data-card-widget="remove">
              <i class="fas fa-times"></i>
            </button> -->
          </div>
        </div>
        <!-- /.card-header -->
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table m-0" id="bookingsTable">
              <thead>
              <tr>
                <th>Booking Date</th>
                <th>Customer</th>
                <th>Subtotal</th>
                <th>Vat</th>
                <th>Charge</th>
                <th>Discount</th>
                <th>Payment status</th>
                <th>Total</th>
              </tr>
              </thead>
              <tbody>
              </tbody>
            </table>
          </div>
          <!-- /.table-responsive -->
        </div>
        <!-- /.card-body -->
        <div class="card-footer clearfix">
          <a href="javascript:void(0)" class="btn btn-sm btn-secondary float-right">View all</a>
        </div>
        <!-- /.card-footer -->
      </div>
      <!-- /.card -->
    </div>
    <!-- /.col -->

    <div class="col-md-4">
      <!-- Info Boxes Style 2 -->
      <!-- <div class="info-box mb-3 bg-warning">
        <span class="info-box-icon"><i class="fas fa-tag"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Inventory</span>
          <span class="info-box-number">5,200</span>
        </div>
      </div>
      <div class="info-box mb-3 bg-success">
        <span class="info-box-icon"><i class="far fa-heart"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Mentions</span>
          <span class="info-box-number">92,050</span>
        </div>
      </div>
      <div class="info-box mb-3 bg-danger">
        <span class="info-box-icon"><i class="fas fa-cloud-download-alt"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Downloads</span>
          <span class="info-box-number">114,381</span>
        </div>
      </div>
      <div class="info-box mb-3 bg-info">
        <span class="info-box-icon"><i class="far fa-comment"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Direct Messages</span>
          <span class="info-box-number">163,921</span>
        </div>
      </div> -->
      <!-- /.info-box -->

      <!-- <div class="card">
        <div class="card-header">
          <h3 class="card-title">Browser Usage</h3>

          <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i>
            </button>
            <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-times"></i>
            </button>
          </div>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-8">
              <div class="chart-responsive">
                <canvas id="pieChart" height="150"></canvas>
              </div>
            </div>
            <div class="col-md-4">
              <ul class="chart-legend clearfix">
                <li><i class="far fa-circle text-danger"></i> Chrome</li>
                <li><i class="far fa-circle text-success"></i> IE</li>
                <li><i class="far fa-circle text-warning"></i> FireFox</li>
                <li><i class="far fa-circle text-info"></i> Safari</li>
                <li><i class="far fa-circle text-primary"></i> Opera</li>
                <li><i class="far fa-circle text-secondary"></i> Navigator</li>
              </ul>
            </div>
          </div>
        </div>
        <div class="card-footer bg-white p-0">
          <ul class="nav nav-pills flex-column">
            <li class="nav-item">
              <a href="#" class="nav-link">
                United States of America
                <span class="float-right text-danger">
                  <i class="fas fa-arrow-down text-sm"></i>
                  12%</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link">
                India
                <span class="float-right text-success">
                  <i class="fas fa-arrow-up text-sm"></i> 4%
                </span>
              </a>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link">
                China
                <span class="float-right text-warning">
                  <i class="fas fa-arrow-left text-sm"></i> 0%
                </span>
              </a>
            </li>
          </ul>
        </div>
      </div> -->
      <!-- /.card -->
          <!-- USERS LIST -->
          <!-- <div class="card">
            <div class="card-header">
              <h3 class="card-title">Latest Members</h3>

              <div class="card-tools">
                <span class="badge badge-danger">8 New Members</span>
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i>
                </button>
                <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-times"></i>
                </button>
              </div>
            </div>
            <div class="card-body p-0">
              <ul class="users-list clearfix">
                <li>
                  <img src="{{ asset('assets/plugins/AdminLte/dist/img/user1-128x128.jpg') }}" alt="User Image">
                  <a class="users-list-name" href="#">Alexander Pierce</a>
                  <span class="users-list-date">Today</span>
                </li>
                <li>
                  <img src="{{ asset('assets/plugins/AdminLte/dist/img/user8-128x128.jpg') }}" alt="User Image">
                  <a class="users-list-name" href="#">Norman</a>
                  <span class="users-list-date">Yesterday</span>
                </li>
                <li>
                  <img src="{{ asset('assets/plugins/AdminLte/dist/img/user7-128x128.jpg') }}" alt="User Image">
                  <a class="users-list-name" href="#">Jane</a>
                  <span class="users-list-date">12 Jan</span>
                </li>
                <li>
                  <img src="{{ asset('assets/plugins/AdminLte/dist/img/user6-128x128.jpg') }}" alt="User Image">
                  <a class="users-list-name" href="#">John</a>
                  <span class="users-list-date">12 Jan</span>
                </li>
                <li>
                  <img src="{{ asset('assets/plugins/AdminLte/dist/img/user2-160x160.jpg') }}" alt="User Image">
                  <a class="users-list-name" href="#">Alexander</a>
                  <span class="users-list-date">13 Jan</span>
                </li>
                <li>
                  <img src="{{ asset('assets/plugins/AdminLte/dist/img/user5-128x128.jpg') }}" alt="User Image">
                  <a class="users-list-name" href="#">Sarah</a>
                  <span class="users-list-date">14 Jan</span>
                </li>
                <li>
                  <img src="{{ asset('assets/plugins/AdminLte/dist/img/user4-128x128.jpg') }}" alt="User Image">
                  <a class="users-list-name" href="#">Nora</a>
                  <span class="users-list-date">15 Jan</span>
                </li>
                <li>
                  <img src="{{ asset('assets/plugins/AdminLte/dist/img/user3-128x128.jpg') }}" alt="User Image">
                  <a class="users-list-name" href="#">Nadia</a>
                  <span class="users-list-date">15 Jan</span>
                </li>
              </ul>
            </div>
            <div class="card-footer text-center">
              <a href="javascript::">View All Users</a>
            </div>
          </div> -->
          <!--/.card -->
    </div>
    <!-- /.col -->
  </div>
  <!-- /.row -->
@endsection

@section('header')
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endsection

@section('footer')
<script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/chart.js/Chart.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
<script src="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
<script type="text/javascript">
jQuery(function($) {
  let month = "{{$month}}";
  //   var table = $('#bookingsTable').DataTable( {
  //       "processing": true,
  //       "serverSide": true,
  //       "deferRender": true,
  //       "bAutoWidth": false,
  //       "sPageButtonActive": "active",
  //       "ajax": {
  //         'url': "{{ route('dashboard.merchant.bookings', Auth::user()->id)}}",
  //         pages: 5,
  //         data: function(data) {
  //           data.month = month;
  //         }
  //       },
  //       "lengthChange": true,
  //       lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
  //       "oLanguage": {
  //         "sLengthMenu": "Show _MENU_ ",
  //       },
  //       "pageLength": 25,
  //       "bFilter": true,
  //       "bInfo": true,
  //       "searching": false,
  //       "columns": [
  //           { "data": "created_at" },
  //           { "mRender": function(data, type, row)
  //               {
  //                   return '<a href="/admin/customer/show/'+ row['id'] +'" class="table-avatar">' + row['customer_name'] + '</a>' +
  //                   '<p class="mb-0">' + row['customer_email'] + '</p>'+
  //                   '<p>' + row['customer_mobile'] + '</p>';
  //               }
  //           },
  //           { "data": "subtotal" },
  //           { "data": "vat_total" },
  //           { "data": "charge_total" },
  //           { "data": "discount" },
  //           { "data": "payment_status" },
  //           { "data": "total" }
  //     ],
  //     "columnDefs": [
  //     {"targets": [0,1, 5], "searchable": false, "orderable": false, "visible": true}
  //     ],
  //     "order": [[0, 'desc']],
  //     buttons: [
  //          'copy', 'excel', 'pdf', 'print'
  //       ]
  // });

  //-----------------------
  //- MONTHLY SALES CHART -
  //-----------------------

  // Get context with jQuery - using jQuery's .get() method.
  var salesChartCanvas = $('#salesChart').get(0).getContext('2d')

  var chartData = {
    "cabin": {
      items: [
        <?php
        foreach( $stats['bookingGraphs'] as $key => $graph ) :
          echo '"' . $graph['cabin']['count'] . '",';
        endforeach;
        ?>
      ],
      amount: [
        <?php
        foreach( $stats['bookingGraphs'] as $key => $graph ) :
          echo '"' . $graph['cabin']['amount'] . '",';
        endforeach;
        ?>
      ]
    },
    "seat": {
      items: [
        <?php
        foreach( $stats['bookingGraphs'] as $key => $graph ) :
          echo '"' . $graph['seat']['count'] . '",';
        endforeach;
        ?>
      ],
      amount: [
        <?php
        foreach( $stats['bookingGraphs'] as $key => $graph ) :
          echo '"' . $graph['seat']['amount'] . '",';
        endforeach;
        ?>
      ]
    },
    "deck": {
      items: [
        <?php
        foreach( $stats['bookingGraphs'] as $key => $graph ) :
          echo $graph['deck']['count'] . ',';
        endforeach;
        ?>
      ],
      amount: [
        <?php
        foreach( $stats['bookingGraphs'] as $key => $graph ) :
          echo $graph['deck']['amount'] . ',';
        endforeach;
        ?>
      ]
    }
  };

  var salesChartData = {
    labels  : [
      <?php
      foreach( $stats['bookingGraphs'] as $key => $graph ) :
      echo '"' . $key . '",';
    endforeach; ?>
    ],
    datasets: [
      {
        label               : 'Cabin',
        backgroundColor     : 'rgba(60,141,188,0.9)',
        borderColor         : 'rgba(60,141,188,0.8)',
        pointRadius          : false,
        pointColor          : '#3b8bba',
        pointStrokeColor    : 'rgba(60,141,188,1)',
        pointHighlightFill  : '#fff',
        pointHighlightStroke: 'rgba(60,141,188,1)',
        data                : chartData.cabin.amount,
        fill: false
      },
      {
        label               : 'Seat',
        backgroundColor     : 'rgba(210, 214, 222, 1)',
        borderColor         : 'rgba(210, 214, 222, 1)',
        pointRadius         : false,
        pointColor          : 'rgba(210, 214, 222, 1)',
        pointStrokeColor    : '#c1c7d1',
        pointHighlightFill  : '#fff',
        pointHighlightStroke: 'rgba(220,220,220,1)',
        data                : chartData.seat.amount,
        fill: false
      },
      {
        label               : 'Deck',
        backgroundColor     : 'rgba(210, 214, 222, 1)',
        borderColor         : '#ffc107',
        pointRadius         : false,
        pointColor          : 'rgba(210, 214, 222, 1)',
        pointStrokeColor    : '#c1c7d1',
        pointHighlightFill  : '#fff',
        pointHighlightStroke: 'rgba(220,220,220,1)',
        data                : chartData.deck.amount,
        fill: false
      }
    ]
  }

  var salesChartOptions = {
    maintainAspectRatio : false,
    responsive : true,
    legend: {
      display: false
    },
    scales: {
      xAxes: [{
        gridLines : {
          display : false,
        }
      }],
      yAxes: [{
        beginAtZero: true,
        min: 0,
        step: 10,
        gridLines : {
          display : false,
        }
      }]
    }
  }


  // This will get the first returned node in the jQuery collection.
  var salesChart = new Chart(salesChartCanvas, {
      type: 'line',
      data: salesChartData,
      options: salesChartOptions
    }
  );

  $('#priceQtyFiltering').find('input.item').change(function(e) {
    e.defaultPrevented;
    let val = $(this).val();
    if( val == 'price' ) {
      salesChart.data.datasets[0].data = chartData.cabin.amount;
      salesChart.data.datasets[1].data = chartData.seat.amount;
      salesChart.data.datasets[2].data = chartData.deck.amount;
      salesChart.update();

      $('#goalPrice').removeClass('d-none');
      $('#goalQuantity').addClass('d-none');
    } else {
      salesChart.data.datasets[0].data = chartData.cabin.items;
      salesChart.data.datasets[1].data = chartData.seat.items;
      salesChart.data.datasets[2].data = chartData.deck.items;
      salesChart.update();

      $('#goalPrice').addClass('d-none');
      $('#goalQuantity').removeClass('d-none');
    }
  });

  //---------------------------
  //- END MONTHLY SALES CHART -
  //---------------------------
  $('#datepicker').datepicker({
      changeMonth: true,
      changeYear: true,
      format: 'MM-yyyy',
      todayHighlight:'TRUE',
      autoclose: true,
      endDate: "+30d"
  }).on('changeDate', function (e) {
    var d = new Date( e.date );
    var month = ("0" + (d.getMonth() + 1)).slice(-2);
    var day = ("0" + (d.getDate())).slice(-2);

    month = d.getFullYear() + '-' + month;
    $(this).datepicker('hide');
    window.location.href = "/admin?month=" + month;
  });
});
</script>
@endsection
