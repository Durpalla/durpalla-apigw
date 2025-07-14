@extends('layouts.master')

@section('content')
<!-- Info boxes -->
<div class="row pt-2">
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
      <div class="info-box mb-3 clickable" data-type="merchant">
        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-bed"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Merchant passengers</span>
          <span class="info-box-number">{{ $stats['passengers']['merchant']}}</span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->

    <!-- fix for small devices only -->
    <div class="clearfix hidden-md-up"></div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box mb-3 clickable" data-type="jolzan">
        <span class="info-box-icon bg-success elevation-1">
          <img src="{{ asset('default/logo-icon.png') }}" style="max-height: 64px;width:100%;" />
        </span>

        <div class="info-box-content">
          <span class="info-box-text">Jolzan passengers</span>
          <span class="info-box-number">{{ $stats['passengers']['jolzan']}}</span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->
    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box mb-3 clickable" data-type="other">
        <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-user"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Others</span>
          <span class="info-box-number">{{ $stats['passengers']['other']}}</span>
        </div>
        <!-- /.info-box-content -->
      </div>
      <!-- /.info-box -->
    </div>
    <!-- /.col -->
    <!-- <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box">
        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-shopping-cart"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Bookings</span>
          <span class="info-box-number">
            {{ $stats['total_bookings'] }}
            <small>%</small>
          </span>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box mb-3">
        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-bed"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Cabin bookings</span>
          <span class="info-box-number">{{ $stats['total_cabin_bookings']}}</span>
        </div>
      </div>
    </div>

    fix for small devices only
    <div class="clearfix hidden-md-up"></div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box mb-3">
        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-chair"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Seat bookings</span>
          <span class="info-box-number">{{ $stats['total_seat_bookings']}}</span>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3">
      <div class="info-box mb-3">
        <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-ticket-alt"></i></span>

        <div class="info-box-content">
          <span class="info-box-text">Deck bookings</span>
          <span class="info-box-number">{{ $stats['total_deck_bookings']}}</span>
        </div>
      </div>
    </div> -->
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
            <div class="col-md-9">
              <p class="text-center">
                <strong>Period: <span id="chartPeriod">{{ date('01 M Y', strtotime($month)) }} - {{ date('t M Y', strtotime($month)) }}</span></strong>
              </p>

              <div class="chart">
                <!-- Sales Chart Canvas -->
                <canvas id="salesChart" height="180" style="height: 180px;"></canvas>
              </div>
              <!-- /.chart-responsive -->
            </div>
            <!-- /.col -->
            <div class="col-md-3">
              <div id="priceQtyFiltering">
                <div class="form-check form-check-inline">
                  <input class="form-check-input item" type="radio" name="priceQty" id="price" value="price" checked>
                  <label class="form-check-label" for="price">Price</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input item" type="radio" name="priceQty" id="quantity" value="quantity">
                  <label class="form-check-label" for="quantity">Quantity</label>
                </div>
              </div><hr>
              <div id="goalPrice">
                <div class="progress-group" id="cabinGoal">
                  Cabin
                  <span class="float-right cabin"><b>{{ $stats['total_cabin_booking_amount']}}</b>/{{ $stats['total_booking_amount'] }}</span>
                  <div class="progress progress-sm">
                    <div class="progress-bar bg-primary" style="width: {{ ($stats['total_bookings'] > 0) ? round(($stats['total_cabin_booking_amount']*100)/$stats['total_booking_amount']) : 0 }}%"></div>
                  </div>
                </div>

                <div class="progress-group" id="seatGoal">
                  Seat
                  <span class="float-right" id="seat"><b>{{ $stats['total_seat_booking_amount']}}</b>/{{ $stats['total_booking_amount'] }}</span>
                  <div class="progress progress-sm">
                    <div class="progress-bar bg-danger" style="width: {{ ($stats['total_bookings'] > 0) ? round(($stats['total_seat_booking_amount']*100)/$stats['total_booking_amount']) : 0 }}%"></div>
                  </div>
                </div>

                <div class="progress-group" id="deckGoal">
                  <span class="progress-text">Deck</span>
                  <span class="float-right" id="deck"><b>{{ $stats['total_deck_booking_amount']}}</b>/{{ $stats['total_booking_amount'] }}</span>
                  <div class="progress progress-sm">
                    <div class="progress-bar bg-success" style="width: {{ ($stats['total_bookings'] > 0) ? round(($stats['total_deck_booking_amount']*100)/$stats['total_booking_amount']) : 0 }}%"></div>
                  </div>
                </div>
              </div>

              <div id="goalQuantity" class="d-none">
                <div class="progress-group" id="cabinGoal">
                  Cabin
                  <span class="float-right cabin"><b>{{ $stats['total_cabin_bookings']}}</b>/{{ $stats['total_bookings'] }}</span>
                  <div class="progress progress-sm">
                    <div class="progress-bar bg-primary" style="width: {{ ($stats['total_bookings'] > 0) ? round(($stats['total_cabin_bookings']*100)/$stats['total_bookings']) : 0 }}%"></div>
                  </div>
                </div>

                <div class="progress-group" id="seatGoal">
                  Seat
                  <span class="float-right" id="seat"><b>{{ $stats['total_seat_bookings']}}</b>/{{ $stats['total_bookings'] }}</span>
                  <div class="progress progress-sm">
                    <div class="progress-bar bg-danger" style="width: {{ ($stats['total_bookings'] > 0) ? round(($stats['total_seat_bookings']*100)/$stats['total_bookings']) : 0 }}%"></div>
                  </div>
                </div>

                <div class="progress-group" id="deckGoal">
                  <span class="progress-text">Deck</span>
                  <span class="float-right" id="deck"><b>{{ $stats['total_deck_bookings']}}</b>/{{ $stats['total_bookings'] }}</span>
                  <div class="progress progress-sm">
                    <div class="progress-bar bg-success" style="width: {{ ($stats['total_bookings'] > 0) ? round(($stats['total_deck_bookings']*100)/$stats['total_bookings']) : 0 }}%"></div>
                  </div>
                </div>
              </div>
            </div>
            <!-- /.col -->
          </div>
          <!-- /.row -->
        </div>
        <!-- ./card-body -->
        <div class="card-footer">
          <div class="row">
            <div class="col-sm-3 col-6">
              <div class="description-block border-right">
                <!-- <span class="description-percentage text-success"><i class="fas fa-caret-up"></i> 17%</span> -->
                <h5 class="description-header">{{ $stats['total_bookings'] }}</h5>
                <span class="description-text">TOTAL TICKET SELL</span>
              </div>
            </div>
            <div class="col-sm-3 col-6">
              <div class="description-block border-right">
                <!-- <span class="description-percentage text-warning"><i class="fas fa-caret-left"></i> 0%</span> -->
                <h5 class="description-header">{{ abs( $stats['total_booking_amount']) }} Tk.</h5>
                <span class="description-text">SELL REVENUE</span>
              </div>
            </div>
            <div class="col-sm-2 col-6">
              <div class="description-block border-right">
                <!-- <span class="description-percentage text-success"><i class="fas fa-caret-up"></i> 20%</span> -->
                <h5 class="description-header">{{ $stats['total_charge_amount'] }} Tk.</h5>
                <span class="description-text">VAT</span>
              </div>
            </div>
            <div class="col-sm-2 col-6">
              <div class="description-block border-right">
                <!-- <span class="description-percentage text-success"><i class="fas fa-caret-up"></i> 20%</span> -->
                <h5 class="description-header">{{ $stats['total_charge_amount'] }} Tk.</h5>
                <span class="description-text">SERVICE</span>
              </div>
            </div>
            <div class="col-sm-2 col-6">
              <div class="description-block">
                <!-- <span class="description-percentage text-danger"><i class="fas fa-caret-down"></i> 18%</span> -->
                <h5 class="description-header">{{ $stats['total_discount_amount'] }} Tk.</h5>
                <span class="description-text">TOTAL WAIVER</span>
              </div>
            </div>
          </div>
        </div>
        <!-- /.card-footer -->
      </div>
      <!-- /.card -->
    </div>
    <!-- /.col -->
  </div>
  <!-- /.row -->

  <div class="row">
    <div class="col-md-6 offset-md-3">
      <form method="GET" id="dashboardBroadSearch">
        <div class="form-group">
          <div class="input-group input-group-lg">
            <input type="text" name="search_query" class="form-control" placeholder="PNR / Booking ID / Mobile" required />
            <div class="input-group-btn">
              <button class="btn btn-success btn-lg">Search</button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      <div class="card">
        <!-- /.card-header -->
        <div class="card-body p-0">
          <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
              <a class="nav-link active" id="schedule-tab" data-toggle="tab" href="#schedule" role="tab" aria-controls="schedule" aria-selected="true">Schedules</a>
            </li>
            <li class="nav-item" role="presentation">
              <a class="nav-link" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Bookings</a>
            </li>
            <li class="nav-item" role="presentation">
              <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Cancel</a>
            </li>
            <li class="nav-item" role="presentation">
              <a class="nav-link" id="contact-tab" data-toggle="tab" href="#contact" role="tab" aria-controls="contact" aria-selected="false">VAT</a>
            </li>
          </ul>
          <div class="tab-content" id="myTabContent" style="height: 520px; overflow-y: auto;">
            <div class="tab-pane fade show active" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
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
                  @foreach( $merchant->upcomingSchedules as $schedule )
                <tr>
                  <td>{{ date('d/m/Y', strtotime($schedule->schedule_date)) }} {{ date('h:i a', strtotime($schedule->leaving_at ))}}</td>
                  <td>{{ $schedule->vehicle['name'] }}</td>
                  <td>{{ $schedule->startingPoint['ghat']['name'] }} - {{ $schedule->endingPoint['ghat']['name'] }}</td>
                  <td>{{ ucfirst( $schedule->schedule_type )}}</td>
                  <td>

                  </td>
                </tr>
                @endforeach
                </tbody>
              </table>
            </div>
            <div class="tab-pane fade" id="home" role="tabpanel" aria-labelledby="home-tab">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Bookings</th>
                    <th>Subtotal</th>
                    <th>VAT</th>
                    <th>Charge</th>
                    <th>Waiver</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  @if( $groupBookings )
                  @foreach( $groupBookings as $key => $booking )
                  <?php
                    $date = date('d/m/Y', strtotime($key));
                    $subtotal = 0;
                    $items = 0;
                    $vat = 0;
                    $charge = 0;
                    $discount = 0;
                    $total = 0;
                    foreach( $booking as $book ) {
                      $items += $book['items'];
                      $subtotal += abs($book['subtotal']);
                      $vat += abs($book['vat']);
                      $charge += abs($book['charge']);
                      $discount += abs($book['discount']);
                      $total += abs($book['total']);
                    }
                  ?>
                  <tr>
                    <td>{{ $date }}</td>
                    <td>{{ $items }}</td>
                    <td>{{ number_format($subtotal, 2) }}</td>
                    <td>{{ number_format($vat, 2) }}</td>
                    <td>{{ number_format($charge, 2) }}</td>
                    <td>{{ number_format($discount, 2) }}</td>
                    <td>{{ number_format($total, 2) }}</td>
                  </tr>
                  @endforeach
                  @endif
                </tbody>
              </table>
            </div>
            <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>VAT</th>
                    <th>Charge</th>
                    <th>Waiver</th>
                    <th>Total</th>
                    <th>Refundable</th>
                  </tr>
                </thead>
                <tbody>
                  @if( $cancellations )
                  @foreach( $cancellations as $key => $booking )
                  <?php
                    $date = date('d/m/Y', strtotime($key));
                    $vat = 0;
                    $charge = 0;
                    $discount = 0;
                    $total = 0;
                    foreach( $booking as $book ) {
                      $vat += abs($book['vat']);
                      $charge += abs($book['charge']);
                      $discount += abs($book['discount']);
                      $total += abs($book['total'] + $book['vat'] + $book['charge'] - $book['discount']);
                    }
                  ?>
                  <tr>
                    <td>{{ $date }}</td>
                    <td>{{ number_format($vat, 2) }}</td>
                    <td>{{ number_format($charge, 2) }}</td>
                    <td>{{ number_format($discount, 2) }}</td>
                    <td>{{ number_format($total, 2) }}</td>
                    <td>{{ number_format(($total ), 2)}}</td>
                  </tr>
                  @endforeach
                  @endif
                </tbody>
              </table>
            </div>
            <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>VAT</th>
                  </tr>
                </thead>
                <tbody>
                  @if( $groupBookings )
                  @foreach( $groupBookings as $key => $booking )
                  <?php
                    $date = date('d/m/Y', strtotime($key));
                    $vat = 0;
                    foreach( $booking as $book ) {
                      $vat += abs($book['vat']);
                    }
                  ?>
                  <tr>
                    <td>{{ $date }}</td>
                    <td>{{ $vat }}</td>
                  </tr>
                  @endforeach
                  @endif
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <!-- /.card-header -->
        <div class="card-body p-0">
          <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
              <a class="nav-link active" id="device-tab" data-toggle="tab" href="#device" role="tab" aria-controls="device" aria-selected="true">Devices</a>
            </li>
            <li class="nav-item" role="presentation">
              <a class="nav-link" id="location-tab" data-toggle="tab" href="#location" role="tab" aria-controls="location" aria-selected="false">Locations</a>
            </li>
            <li class="nav-item" role="presentation">
              <a class="nav-link" id="customer-tab" data-toggle="tab" href="#customer" role="tab" aria-controls="customer" aria-selected="false">Customers</a>
            </li>
          </ul>
          <div class="tab-content" id="myTabContent" style="height: 520px; overflow-y: auto;">
            <div class="tab-pane fade show active" id="device" role="tabpanel" aria-labelledby="device-tab">

            </div>
            <div class="tab-pane fade" id="location" role="tabpanel" aria-labelledby="location-tab">

            </div>
            <div class="tab-pane fade" id="customer" role="tabpanel" aria-labelledby="customer-tab">

            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /.row -->

  <div class="modal fade bd-example-modal-xl" data-backdrop="static" id="dashboardSearchModal" tabindex="-1" role="dialog" aria-labelledby="myExtraLargeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="staticBackdropLabel">
            Search bookings
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body p-0">
          <p class="text-center p-5 mb-4">Loading....</p>
        </div>
        <div class="modal-footer text-right">
          <button class="btn btn-danger" data-dismiss="modal" aria-label="Close">Close</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('header')
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
<style type="text/css">
  .info-box.clickable {
    cursor: pointer;
  }
  .info-box.clickable:hover, .info-box.clickable.active {
    background: #219876;
    color: #fff;
  }
</style>
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
  let type = "{{$type}}";

  $('.clickable').click(function(e) {
    e.defaultPrevented;
    type = $(this).data('type');
    window.location.href = "/admin?month=" + month + "&type=" + type;
  });

  $('#dashboardBroadSearch').submit(function(e) {
    e.defaultPrevented;
    let data = $(this).serialize();
    let modal = $('#dashboardSearchModal');
    $(modal).modal('show');
    $.ajaxSetup({
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Accept': '*'
          }
      });
      $.ajax({
          url: "{{ route('dashboard.search')}}",
          type: 'post',
          data: data,
          success: function (response) {
            var html = $.parseHTML(response);
            $(modal).find('.modal-body').html(html);
            $(modal).modal('show');
          }
      });
    return false;
  });

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
          echo $graph['cabin']['count'] . ',';
        endforeach;
        ?>
      ],
      amount: [
        <?php
        foreach( $stats['bookingGraphs'] as $key => $graph ) :
          echo $graph['cabin']['amount'] . ',';
        endforeach;
        ?>
      ]
    },
    "seat": {
      items: [
        <?php
        foreach( $stats['bookingGraphs'] as $key => $graph ) :
          echo $graph['seat']['count'] . ',';
        endforeach;
        ?>
      ],
      amount: [
        <?php
        foreach( $stats['bookingGraphs'] as $key => $graph ) :
          echo $graph['seat']['amount'] . ',';
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
      viewMode: "months",
      minViewMode: "months",
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
