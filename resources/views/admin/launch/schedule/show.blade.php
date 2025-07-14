@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'info') ? 'active': ''; @endphp"
                   id="info-tab" data-toggle="tab" href="#info" role="tab" aria-controls="info" aria-selected="true">Info</a>
            </li>
            @if($schedule->vehicle->vehicle_type === 'launch')
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'cabin') ? 'active': ''; @endphp"
                   id="cabin-tab" data-toggle="tab" href="#cabin" role="tab" aria-controls="cabin"
                   aria-selected="false">Cabins</a>
            </li>
            @endif
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'seat') ? 'active': ''; @endphp"
                   id="seat-tab" data-toggle="tab" href="#seat" role="tab" aria-controls="seat" aria-selected="false">Seats</a>
            </li>
            @if($schedule->vehicle->vehicle_type === 'launch')
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'deck') ? 'active': ''; @endphp"
                   id="deck-tab" data-toggle="tab" href="#deck" role="tab" aria-controls="deck" aria-selected="false">Deck</a>
            </li>
            @endif
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'discount') ? 'active': ''; @endphp"
                   id="discount-tab" data-toggle="tab" href="#discount" role="tab" aria-controls="discount"
                   aria-selected="false">Discount</a>
            </li>
            @if(auth()->user()->hasAnyPermission(['schedule-extend']) || auth()->user()->hasAnyRole(['admin', 'merchant']))
                <li class="nav-item">
                    <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'operation') ? 'active': ''; @endphp"
                       id="operation-tab" data-toggle="tab" href="#operation" role="tab" aria-controls="operation"
                       aria-selected="false">Operation hours</a>
                </li>
            @endif
            @if(auth()->user()->hasAnyPermission(['schedule-batch-update']) || auth()->user()->hasAnyRole(['admin', 'merchant']))
                <li class="nav-item">
                    <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'batch-update') ? 'active': ''; @endphp"
                       id="batchupdate-tab" data-toggle="tab" href="#batchupdate" role="tab" aria-controls="batchupdate"
                       aria-selected="false">Batch Update</a>
                </li>
            @endif
            @if(auth()->user()->hasAnyPermission(['schedule-quota-transfer']) || auth()->user()->hasAnyRole(['admin', 'merchant', 'counter-officer']))
                <li class="nav-item">
                    <a class="nav-link bg-gradient-danger" href="{{ route('dashboard.schedule.transferquota', $schedule->id) }}">Transfer Quota</a>
                </li>
            @endif
            @if(auth()->user()->hasAnyPermission(['schedule-report']) || auth()->user()->hasAnyRole(['admin', 'merchant']))
                <li class="nav-item">
                    <a class="nav-link bg-gradient-blue" href="{{ route('dashboard.schedule.report', $schedule->id) }}">Report</a>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div
                class="tab-pane fade @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'info') ? 'active show': ''; @endphp"
                id="info" role="tabpanel" aria-labelledby="info-tab">
                <div class="row">
                    <div class="col-12 col-md-8 col-lg-8 order-2 order-md-1">

                        <div class="row mt-3">
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="info-box">
                                    <span class="info-box-icon bg-info elevation-1"><i class="fas fa-bed"></i></span>

                                    <div class="info-box-content">
                                        <span class="info-box-text">Cabin booking</span>
                                        <span class="info-box-number">
                                            {{ count( $schedule->cabinBookings ) }} / {{ count( $schedule->cabinMappings ) }}
                                        </span>
                                    </div>
                                    <!-- /.info-box-content -->
                                </div>
                                <!-- /.info-box -->
                            </div>
                            <!-- /.col -->
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="info-box mb-3">
                                    <span class="info-box-icon bg-danger elevation-1"><i
                                            class="fas fa-chair"></i></span>

                                    <div class="info-box-content">
                                        <span class="info-box-text">Seat booking</span>
                                        <span
                                            class="info-box-number">{{ count( $schedule->seatBookings ) }} / {{ count( $schedule->seatMappings ) }}</span>
                                    </div>
                                    <!-- /.info-box-content -->
                                </div>
                                <!-- /.info-box -->
                            </div>
                            <!-- /.col -->

                            <!-- fix for small devices only -->
                            <div class="clearfix hidden-md-up"></div>

                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="info-box mb-3">
                                    <span class="info-box-icon bg-success elevation-1"><i
                                            class="fas fa-users"></i></span>

                                    <div class="info-box-content">
                                        <span class="info-box-text">Deck booking</span>
                                        <span class="info-box-number">
                    @php
                        $total = 0;
                        if( $schedule->ticketBookings ) {
                          foreach( $schedule->ticketBookings as $ticket ) {
                            $passenger = json_decode( $ticket['passenger'] );
                            $total += ( $passenger ) ? (int) $passenger->person : 0;
                          }
                        }
                        echo $total;
                    @endphp
                   / {{ $schedule->vehicle['passengers_capacity'] }}</span>
                                    </div>
                                    <!-- /.info-box-content -->
                                </div>
                                <!-- /.info-box -->
                            </div>
                            <!-- /.col -->
                        </div>

                        <!-- /.row -->
                        <div class="row mt-3">
                            <div class="col-12">

                                <!-- TABLE: LATEST ORDERS -->
                                <div class="card">
                                    <div class="card-header border-transparent">
                                        <h3 class="card-title">Recent bookings</h3>

                                        <div class="card-tools">
                                            <div class="form-group">
                                                <input type="text" name="keyword" class="form-controll" id="keywords"
                                                       placeholder="Invoice, Ticket">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body p-0">
                                        <div id="advancedFilter">
                                            <div class="row pt-2">
                                                <div class="col-sm-4">
                                                    <div class="form-group">
                                                        <input type="text" class="form-control"
                                                               placeholder="Name, Mobile" id="passengerDetails"
                                                               value="">
                                                    </div>
                                                </div>
                                                <div class="col-sm-3">
                                                    <input type="text" class="form-control bookingdatepicker"
                                                           placeholder="Booking date" id="booking_date" value="">
                                                </div>
                                                <div class="col-sm-3">
                                                    <input type="text" class="form-control bookingdatepicker"
                                                           placeholder="Schedule date" id="schedule_date" value="">
                                                </div>
                                                <div class="col-sm-2">
                                                    <select class="form-control" id="filterType">
                                                        <option value="">Type</option>
                                                        <option value="cabin">Cabin</option>
                                                        <option value="seat">Seat</option>
                                                        <option value="deck">Deck</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-2">
                                                    <select class="form-control" id="filterMethod">
                                                        <option value="">Payment method</option>
                                                        <option value="cash">Cash</option>
                                                        <option value="bkash">BKash</option>
                                                        <option value="rocket">Rocket</option>
                                                        <option value="master">Mastercard</option>
                                                        <option value="visa">Visa</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-2">
                                                    <select class="form-control" id="filterStatus">
                                                        <option value="">Status</option>
                                                        <option value="complete">Complete</option>
                                                        <option value="pending">Pending</option>
                                                        <option value="cancel">Cancel</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table m-0" id="recentBookingsTable">
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
                                        <!-- /.table-responsive -->
                                    </div>
                                    <!-- /.card-body -->
                                    <div class="card-footer clearfix">
                                        <!-- <a href="javascript:void(0)" class="btn btn-sm btn-info float-left">Place New Order</a> -->
                                        <a href="{{ route('dashboard.booking.index') }}"
                                           class="btn btn-sm btn-default float-right">View All Orders</a>
                                    </div>
                                    <!-- /.card-footer -->
                                </div>
                                <!-- /.card -->
                            </div>
                            <div class="col-6">

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
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3 col-lg-4 order-1 order-md-2">
                        <h3 class="text-secondary"> {{ $schedule->vehicle['name'] }}
                            <em>[{{ $schedule->route['route_name'] }}]</em></h3>
                        <hr>
                        <div class="text-muted">
                            <p class="text-sm">Route
                                <b class="d-block">{{ $schedule->vehicle['route']['route_name'] }}</b>
                            </p>
                            <p> Stoppages <br>
                                <?php
                                $stoppages = [];
                                array_push($stoppages, $schedule->startingPoint['ghat']['name']);
                                foreach ($schedule->boardingVias as $boarding) {
                                    array_push($stoppages, $boarding['ghat']['name']);
                                }
                                array_push($stoppages, $schedule->endingPoint['ghat']['name']);

                                if ($schedule->schedule_type == 'reverse') {
                                    krsort($stoppages);
                                    $stoppages = array_values($stoppages);
                                }
                                ?>
                                @foreach( $stoppages as $k => $stoppage)
                                    <span class='badge badge-info'>{{ $stoppage }}</span>
                                @endforeach
                            </p>
                            <p class="text-sm">License No.
                                <b class="d-block">{{ $schedule->vehicle['merchant']['merchant_reg_no'] }}</b>
                            </p>
                            <p class="text-sm">License expiry date
                                <b class="d-block">{{ date('d/m/Y', strtotime($schedule->vehicle['registration_expiry_date'])) }}</b>
                            </p>
                            <p class="text-sm">Fitness expiry date
                                <b class="d-block">{{ date('d/m/Y', strtotime($schedule->vehicle['fitness_expiry_date'])) }}</b>
                            </p>
                        <!-- <p class="text-sm">Passengers capacity
              <b class="d-block">{{ $schedule->vehicle['passengers_capacity'] }}</b>
            </p> -->
                        </div>
                        <h5 class="mt-5 text-muted">Merchant info</h5>
                        <hr>
                        <div class="text-muted">
                            <p class="text-sm">Merchant Name.
                                <b class="d-block">{{ $schedule->vehicle['merchant']['merchant_name'] }}</b>
                            </p>
                            <p class="text-sm">Merchant Registration No.
                                <b class="d-block">{{ $schedule->vehicle['merchant']['merchant_reg_no'] }}</b>
                            </p>
                            <p class="text-sm">Merchant Address
                                <b class="d-block">{{ $schedule->vehicle['merchant']['merchant_address'] }}</b>
                            </p>
                            <p class="text-sm">Merchant mobile
                                <b class="d-block">{{ $schedule->vehicle['merchant']['merchant_mobile'] }}</b>
                            </p>
                            <p class="text-sm">Merchant Phone
                                <b class="d-block">{{ $schedule->vehicle['merchant']['merchant_phone'] }}</b>
                            </p>
                            <p class="text-sm">Merchant Fax
                                <b class="d-block">{{ $schedule->vehicle['merchant']['merchant_fax'] }}</b>
                            </p>
                            <p class="text-sm">Merchant Email
                                <b class="d-block">{{ $schedule->vehicle['merchant']['merchant_email'] }}</b>
                            </p>
                        </div>
                        <h5 class="mt-5 text-muted">Contact info</h5>
                        <hr>
                        <ul class="list-unstyled">
                            <li>
                                <a href="" class="btn-link text-secondary"><i
                                        class="fas fa-fw fa-mobile"></i> {{ $schedule->vehicle['vehicle_mobile'] }}</a>
                            </li>
                            <li>
                                <a href="" class="btn-link text-secondary"><i
                                        class="fas fa-fw fa-phone"></i> {{ $schedule->vehicle['vehicle_phone'] }}</a>
                            </li>
                            <li>
                                <a href="" class="btn-link text-secondary"><i
                                        class="fas fa-fw fa-envelope"></i> {{ $schedule->vehicle['vehicle_email'] }}</a>
                            </li>
                            <li>
                                <a href="" class="btn-link text-secondary"><i
                                        class="fas fa-fw fa-printer"></i> {{ $schedule->vehicle['vehicle_fax'] }}</a>
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
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'cabin') ? 'active show': ''; @endphp"
                id="cabin" role="tabpanel" aria-labelledby="cabin-tab">
                <div class="row pt2">
                    <div class="col-sm-2">
                        @if(auth()->user()->hasAnyPermission(['booking-assign-honorium']) || auth()->user()->hasRole('admin'))
                            <button type="button" class="btn btn-outline-warning setHonoriumBtn" data-type="cabin">Set
                                honorium
                            </button>
                        @endif
                    </div>
                    <div class="col-sm-2">
                        <input type="text" class="form-control" id="cabinNo" placeholder="Cabin no.">
                    </div>
                    <div class="col-sm-2">
                        <select class="form-control" id="cabinFloor">
                            <option value="">Select floor</option>
                            @foreach(config('constants.floors') as $key => $value)
                                <option value="{{ $key }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-2">
                        <select class="form-control" id="cabinType">
                            <option value="">Select type</option>
                            @foreach($cabin_type_dropdowns as $cabin_type)
                                <option value="{{$cabin_type['id']}}">{{$cabin_type['name']}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-2">
                        <select class="form-control" id="cabinOwner">
                            <option value="">Owner</option>
                            @foreach(config('constants.owners') as $key => $value)
                                <option value="{{ $key }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-1">
                        <select class="form-control" id="cabinReserved">
                            <option value="">Select option</option>
                            <option value="1">Reserved</option>
                            <option value="0">Not Reserved</option>
                        </select>
                    </div>
                    <div class="col-sm-1">
                        <select class="form-control" id="cabinBooked">
                            <option value="">Select option</option>
                            <option value="1">Booked</option>
                            <option value="0">Available</option>
                        </select>
                    </div>
                </div>
                <table class="table table-striped table-bordered" id="cabinsTable">
                    <thead>
                    <tr>
                        <th><input type="checkbox" class="checkedAll" value="1"></th>
                        <th>No.</th>
                        <th>Floor.</th>
                        <th>Row</th>
                        <th>Position</th>
                        <th>Type</th>
                        <th>AC</th>
                        <th>Owner</th>
                        <th>Counter</th>
                        <th>Honorium</th>
                        <th>Fare</th>
                        <th style="width:80px;">Reserved?</th>
                        <th style="width:80px;">Booked?</th>
                        <th style="width:60px;"><i class="fa fa-cog"></i></th>
                    </tr>
                    </thead>
                </table>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'seat') ? 'active show': ''; @endphp"
                id="seat" role="tabpanel" aria-labelledby="seat-tab">
                <div class="row pt2">
                    <div class="col-sm-2">
                        @if(auth()->user()->hasAnyPermission(['booking-assign-honorium']) || auth()->user()->hasRole('admin'))
                            <button type="button" class="btn btn-outline-warning setHonoriumBtn" data-type="seat">Set
                                honorium
                            </button>
                        @endif
                    </div>
                    <div class="col-sm-2">
                        <input type="text" class="form-control" id="seatNo" placeholder="Cabin no.">
                    </div>
                    <div class="col-sm-2">
                        <select class="form-control" id="seatFloor">
                            <option value="">Select floor</option>
                            @foreach(config('constants.floors') as $key => $value)
                                <option value="{{ $key }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-2">
                        <select class="form-control" id="seatType">
                            <option value="">Select type</option>
                            @foreach($seat_type_dropdowns as $seat_type)
                                <option value="{{$cabin_type['id']}}">{{$cabin_type['name']}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-2">
                        <select class="form-control" id="seatOwner">
                            <option value="">Owner</option>
                            @foreach(config('constants.owners') as $key => $value)
                                <option value="{{ $key }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-1">
                        <select class="form-control" id="seatReserved">
                            <option value="">Select option</option>
                            <option value="1">Reserved</option>
                            <option value="0">Not Reserved</option>
                        </select>
                    </div>
                    <div class="col-sm-1">
                        <select class="form-control" id="seatBooked">
                            <option value="">Select option</option>
                            <option value="1">Booked</option>
                            <option value="0">Available</option>
                        </select>
                    </div>
                </div>
                <table class="table table-striped table-bordered" id="seatsTable">
                    <thead>
                    <tr>
                        <th><input type="checkbox" class="checkedAll" value="1"></th>
                        <th>No.</th>
                        <th>Floor.</th>
                        <th>Row</th>
                        <th>Position</th>
                        <th>type</th>
                        <th>AC</th>
                        <th>Owner</th>
                        <th>Counter</th>
                        <th>Honorarium</th>
                        <th>Fare</th>
                        <th style="width:80px;">Reserved?</th>
                        <th style="width:80px;">Booked?</th>
                        <th style="width:60px;"><i class="fa fa-cog"></i></th>
                    </tr>
                    </thead>
                </table>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'deck') ? 'active show': ''; @endphp"
                id="deck" role="tabpanel" aria-labelledby="deck-tab">

                <h4>Deck fare</h4>
                <hr>
                <table class="table table-striped table-bordered" id="deckFaresTable">
                    <thead>
                    <tr>
                        <th>Route</th>
                        <th>Starting point</th>
                        <th>Ending point</th>
                        <th>Fare</th>
                        <th>Reverse Fare</th>
                        <th><i class="fa fa-cog"></i></th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'discount') ? 'active show': ''; @endphp"
                id="discount" role="tabpanel" aria-labelledby="discount-tab">
                <div class="row">
                    <div class="col-sm-9">
                        <table class="table m-0">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Created at</th>
                                <th>Created by</th>
                                <th>Applicable to</th>
                                <th>Amount</th>
                                <th>Cabin</th>
                                <th>Seat</th>
                                <th>Deck</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach( $schedule->discounts as $discount )
                                <tr>
                                    <td>{{ $discount->id }}</td>
                                    <td>{{ date('d/m/Y h:i a', strtotime( $discount->created_at )) }}</td>
                                    <td>{{ $discount->user['name'] }}</td>
                                    <td>{{ $discount->applicable_to }}</td>
                                    <td>{{ $discount->amount }} {{ ($discount->type == 'p') ? '%' : 'Tk.'}}</td>
                                    <td>{{ $discount->is_cabin ? 'Yes' : 'No' }}</td>
                                    <td>{{ $discount->is_seat ? 'Yes' : 'No' }}</td>
                                    <td>{{ $discount->is_deck ? 'Yes' : 'No' }}</td>
                                    <td>Active</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="col-sm-3">
                        @canany(['schedule-assign', 'schedule-create', 'route-mapping'])
                            <h3 class="text-secondary"><i class="fas fa-plus"></i> Add new discount</h3>
                            <hr>
                            <form action="{{ route('dashboard.discount.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="merchant_id" value="{{ $schedule->merchant_id }}">
                                <input type="hidden" name="vehicle_id" value="{{ $schedule->vehicle_id }}">
                                <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                                <input type="hidden" name="tab" value="discount">
                                <div class="form-group">
                                    <label for="amount">Write a note</label>
                                    <input type="text" name="description" class="form-control"
                                           placeholder="Write a note">
                                </div>
                                <div class="form-group">
                                    <label>Applicable for</label>
                                    <select name="applicable_to" id="applicableToType"
                                            class="form-control @error('applicable_to') is-invalid @enderror"
                                            value="{{ old('applicable_to') }}" style="width:100%" required>
                                        <option value="">Choose now</option>
                                        <option value="merchant"
                                                @if(old('applicable_to') == 'merchant') selected @endif>Merchant
                                        </option>
                                        <option value="jolzan"
                                                @if(old('applicable_to') == 'jolzan') selected @endif>Jolzan
                                        </option>
                                        <option value="both" @if(old('applicable_to') == 'both') selected @endif>Both
                                        </option>
                                    </select>
                                    @error('applicable_to')
                                    <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label for="amount">Discount amount</label>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                Amount
                                            </div>
                                        </div>
                                        <input type="number" min="0" id="amount" name="amount" class="form-control"
                                               value="{{ old('amount', 0) }}" required>
                                        <div class="input-group-append">
                                            <div class="input-group-btn">
                                                <select name="type" class="form-control btn btn-default"
                                                        value="{{ old('type') }}" required>
                                                    <option value="p">Percentage</option>
                                                    <option value="f">Fixed</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group clearfix">
                                    <label>Applicable to</label>
                                    <div class="icheck-primary">
                                        <input type="checkbox" class="cancel-item" id="checkboxPrimaryCabin"
                                               name="is_cabin" value="1" checked>
                                        <label for="checkboxPrimaryCabin">
                                            Cabin
                                        </label>
                                    </div>
                                    <div class="icheck-primary">
                                        <input type="checkbox" class="cancel-item" id="checkboxPrimarySeat"
                                               name="is_seat" value="1">
                                        <label for="checkboxPrimarySeat">
                                            Seat
                                        </label>
                                    </div>
                                    <div class="icheck-primary">
                                        <input type="checkbox" class="cancel-item" id="checkboxPrimaryDeck"
                                               name="is_deck" value="1">
                                        <label for="checkboxPrimaryDeck">
                                            Deck
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <button class="btn btn-block btn-primary" type="submit">Save</button>
                                </div>
                            </form>
                        @endcanany
                    </div>
                </div>
            </div>

            <div class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'operation') ? 'active show': ''; @endphp"
                id="operation" role="tabpanel" aria-labelledby="operation-tab">
                <div class="row">
                    <div class="col-sm-5">
                        <div class="card">
                            <div class="card-header"><h4>Operation hours</h4></div>
                            @php
                            $operationStart = strtotime($schedule->leaving_at) - (3*60*60);
                            $operationEnd = strtotime($schedule->leaving_at) + ($schedule->operation_hour * 60 * 60);
                            @endphp
                            <table class="table table-striped">
                                <tr>
                                    <th>Operation Start</th>
                                    <td>{{ date('d/m/Y h:iA', $operationStart) }}</td>
                                </tr>
                                <tr>
                                    <th>Operation End</th>
                                    <td>{{ date('d/m/Y h:iA', $operationEnd) }}</td>
                                </tr>
                                <tr>
                                    <th>Total Hours</th>
                                    <td>{{ round($schedule->operation_hour + 3) }} Hours</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <div class="card">
                            <div class="card-header"><h4>Extend operation hour</h4></div>
                            <div class="card-body">
                                <form action="{{ route('dashboard.schedule.extend', $schedule->id) }}" method="POST">
                                    @method('PUT')
                                    @csrf
                                    <input type="hidden" name="tab" value="operation">
                                    <div class="form-group">
                                        <label for="operation_hour">Hours to extend</label>
                                        <input type="number" name="operation_hour" class="form-control" value="1" placeholder="1.30" required>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-success">Extend operation</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'batch-update') ? 'active show': ''; @endphp"
                 id="batchupdate" role="tabpanel" aria-labelledby="batchupdate-tab">
                <div class="row">
                    <div class="col-sm-5">
                        <div class="card">
                            <div class="card-header"><h4>Download Mapping files</h4></div>
                            <table class="table">
                                <tr>
                                    <th>Cabins</th>
                                    <th>Seats</th>
                                </tr>
                                <tr>
                                    <td>
                                        <a href="{{ route('dashboard.schedule.exportmapping', $schedule->id) }}" class="btn btn-success">Download cabins</a>
                                    </td>
                                    <td>
                                        <a href="{{ route('dashboard.schedule.exportmapping', ['id' => $schedule->id, 'type' => 'seat']) }}" class="btn btn-success">Download seats</a>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <div class="card">
                            <div class="card-header"><h4>Batch update</h4></div>
                            <div class="card-body">
                                <form action="{{ route('dashboard.schedule.batchupdate', $schedule->id) }}" method="POST" enctype="multipart/form-data">
                                    @method('PUT')
                                    @csrf
                                    <input type="hidden" name="tab" value="batch-update">
                                    <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                                    <div class="form-group">
                                        <label for="operation_hour">Attach Excel, CSV file</label>
                                        <input type="file" name="attachment" class="form-control-file" required>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">Update</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
    <?php
    $books = [];
    if ($schedule->bookingItems) {
        foreach ($schedule->bookingItems as $item) {
            if ($item['booking_type'] != 'deck') {
                array_push($books, $item['cabin_id']);
            }
        }
    }
    $locks = [];
    if ($schedule->locks) {
        foreach ($schedule->locks as $lock) {
            array_push($books, $lock['cabin_id']);
        }
    }
    ?>
    <div class="modal fade" data-backdrop="static" id="CabinHonoriumModal" tabindex="-1" role="dialog"
         aria-labelledby="myExtraLargeModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">
                        Booking honorium (Cabin)
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('dashboard.schedule.honorium') }}" method="POST" class="bookingHonoriumForm">
                        @csrf
                        <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                        <div class="row">
                            <div class="col-12">
                                <table class="table">
                                    @foreach( $schedule->cabinMappings as $item )
                                        @php
                                            $icon = 'ticket-alt';
                                            if( $item->type === 'cabin' ) {
                                              $icon = 'bed';
                                            } elseif( $item['type'] === 'seat') {
                                              $icon = 'chair';
                                            }
                                            $available = ( in_array($item['cabin_id'], $books) || in_array($item['cabin_id'], $locks) || ($item['ownership'] != 'jolzan')  || ($item['honorium'] == 1) ) ? false : true;
                                        @endphp
                                        <div
                                            class="icheck-primary @if($available) available @else not-available @endif">
                                            <input type="checkbox" class="cancel-item"
                                                   id="checkboxPrimary{{ $item['id'] }}" name="items[]"
                                                   value="{{ $item['id'] }}" @if($available) checked
                                                   @else disabled @endif>
                                            <label for="checkboxPrimary{{ $item['id'] }}">
                                                {{ ucfirst( $item['cabin']['type'] ) }}
                                                &nbsp; {{ ($item['cabin']['cabinType']) ? $item['cabin']['cabinType']['letter'] : '' }}{{ $item['cabin']['cabin_no'] }}
                                                : {{ ($item['cabin']['cabinType']) ? $item['cabin']['cabinType']['name'] : '' }}
                                                ( {{ ($item['cabin']['cabinType'] && $item['cabin']['cabinType']['is_ac'] ) ? 'AC' : 'Non-AC'}}
                                                )
                                                [Fare-{{ $item['fare'] }}]
                                            </label>
                                        </div>
                                    @endforeach
                                </table>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary btn-lg">Set honorium</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" data-backdrop="static" id="SeatHonoriumModal" tabindex="-1" role="dialog"
         aria-labelledby="myExtraLargeModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">
                        Booking honorium (Seat)
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('dashboard.schedule.honorium') }}" method="POST" class="bookingHonoriumForm">
                        @csrf
                        <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                        <div class="row">
                            <div class="col-12">
                                <table class="table">
                                    @foreach( $schedule->seatMappings as $item )
                                        @php
                                            $icon = 'ticket-alt';
                                            if( $item->type === 'cabin' ) {
                                              $icon = 'bed';
                                            } elseif( $item['type'] === 'seat') {
                                              $icon = 'chair';
                                            }
                                            $available = ( in_array($item['cabin_id'], $books) || in_array($item['cabin_id'], $locks) || ($item['ownership'] != 'jolzan') || ($item['honorium'] == 1) ) ? false : true;
                                        @endphp
                                        <div
                                            class="icheck-primary @if($available) available @else not-available @endif">
                                            <input type="checkbox" class="cancel-item"
                                                   id="checkboxPrimary{{ $item['id'] }}" name="items[]"
                                                   value="{{ $item['id'] }}" @if($available) checked
                                                   @else disabled @endif>
                                            <label for="checkboxPrimary{{ $item['id'] }}">
                                                {{ ucfirst( $item['type'] ) }}
                                                &nbsp; {{ $item['cabin']['cabinType']['letter'] }}
                                                -{{ $item['cabin']['cabin_no'] }}
                                                : {{ $item['cabin']['cabinType']['name'] }}
                                                ( {{ ($item['cabin']['cabinType']['is_ac'] ) ? 'AC' : 'Non-AC'}})
                                                [Fare-{{ $item['fare'] }}]
                                            </label>
                                        </div>
                                    @endforeach
                                </table>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary btn-lg">Set honorium</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="mappingEditModal" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">Modal title</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST" id="mappingUpdateForm">
                        @method('put')
                        @csrf
                        <input type="hidden" name="mapping_id" id="mappingID">
                        <div class="form-group">
                            <label>Cabin row</label>
                            <input type="number" name="cabin_row" class="form-control" id="cabinRow" value="" step="1" required>
                        </div>
                        <div class="form-group">
                            <label>Cabin Position</label>
                            <input type="number" name="cabin_position" class="form-control" id="cabinPosition" value="" step="1" required>
                        </div>
                        <div class="form-group">
                            <label>Cabin owner</label>
                            <select name="ownership" class="form-control" id="ownership" required>
                                <option value="">Select</option>
                                @foreach($party_dropdowns as $key => $value)
                                    <option value="{{$key}}">{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fare</label>
                            <input type="number" name="fare" id="cabinFare" class="form-control" value="" required>
                        </div>
                        <div class="form-group">
                            <label>Service charge</label>
                            <input type="number" name="service_charge" id="cabinCharge" class="form-control" value="" required>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('header')
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">

    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.css') }}">
    <!-- iCheck for checkboxes and radio inputs -->
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
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
        }

        #advancedFilterBtn.active {
            color: #219876;
            background: #eaeaea;
        }

        .btn-file {
            position: relative;
            overflow: hidden;
        }

        .btn-file input[type=file] {
            position: absolute;
            top: 0;
            right: 0;
            min-width: 100%;
            min-height: 100%;
            font-size: 100px;
            text-align: right;
            filter: alpha(opacity=0);
            opacity: 0;
            outline: none;
            background: white;
            cursor: inherit;
            display: block;
        }

        #img-upload {
            width: 100%;
        }

        .accordion {
            width: 100%;
        }

        .row-striped:nth-of-type(odd) {
            background-color: #efefef;
            border-left: 4px #000000 solid;
        }

        #listGridTab {
        }

        #listGridTab .nav-link {
            padding: 2px 5px 0;
            border: 1px solid #eee;
            background: #fbfbfb;
        }

        #listGridTabContent {
            padding: 0;
        }

        .row-striped:nth-of-type(even) {
            background-color: #ffffff;
            border-left: 4px #efefef solid;
        }

        .row-striped {
            padding: 15px 0;
        }

        .grid {
            position: relative;
        }

        .item {
            display: block;
            position: absolute;
            width: 100px;
            height: 100px;
            margin: 5px;
            z-index: 1;
            background: #000;
            color: #fff;
        }

        .item.muuri-item-dragging {
            z-index: 3;
        }

        .item.muuri-item-releasing {
            z-index: 2;
        }

        .item.muuri-item-hidden {
            z-index: 0;
        }

        .item-content {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .cabin-card {
            text-align: center;
            padding: 25px;
            font-size: 24px;
            font-weight: bold;
            padding-bottom: 35px;
            background-color: #666;
        }

        .cabin-card.cabin-active {
            color: #fff;
            cursor: pointer;
        }

        .cabin-card.cabin-disable {
            background-color: #f2f2f2;
        }

        .cabin-card.cabin-selected {
            background-color: #fff;
        }

        .cabin-card .cabinOverlap {
            position: absolute;
            top: 0;
            right: 0;
            width: auto;
            padding: 2px 10px;
            background: yellow;
            font-size: 16px;
        }

        .cabin-card .cabinPrice {
            position: absolute;
            bottom: 0;
            right: 0;
            left: 0;
            background: #219876;
            color: #fff;
            font-size: 18px;
        }

        .cabin-card.cabin-disable .cabinPrice {
            background: #CCC;
        }

        .display-41 {
            text-align: center;
            padding-left: 1rem;
        }

        #cabinsList li.nav-item {
            margin: 15px 5px;
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
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <script type="text/javascript">
        jQuery(function ($) {
            let cabinModal = $('#CabinHonoriumModal');
            let seatModal = $('#SeatHonoriumModal');
            let editModal = $('#mappingEditModal');
            $('#bookingHonorium').click(function () {
                $(cabinModal).modal('show');
            });
            $('#seatBookingHonorium').click(function () {
                $(seatModal).modal('show');
            });
            $('.checkedAll').click(function (e) {
                e.defaultPrevented;
                var parent = $(this).parents('table');
                if ($(this).is(":checked")) {
                    $(parent).find(".selectedItem").each(function () {
                        $(this).prop('checked', true);
                    });
                } else {
                    $(parent).find(".selectedItem").each(function () {
                        $(this).prop('checked', false);
                    });
                    ;
                }
            });
            $('.setHonoriumBtn').click(function (e) {
                e.defaultPrevented;
                ids = [];
                let type = $(this).data('type');
                let items;
                if (type == 'seat') {
                    items = $('#seatsTable input.selectedItem:checked');
                } else {
                    items = $('#cabinsTable input.selectedItem:checked');
                }
                var url = "{{ route('dashboard.schedule.honorium') }}";
                let self = $(this);
                if ($(items).length > 0) {
                    $(items).each(function (e) {
                        ids.push($(this).val());
                    });

                    if (ids.length > 0) {
                        let confirmed = confirm('Are you sure to set these selected ' + type + 's as honorium?');

                        if (confirmed) {
                            $.ajaxSetup({
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                            $.ajax({
                                // dataType: "json",
                                type: "POST",
                                url: url,
                                data: {ids: ids.join()},
                                success: function (response, textStatus, xhr) {
                                    if (type == 'cabin') {
                                        table.draw();
                                    } else {
                                        ctable.draw();
                                    }
                                    Toast.fire({
                                        icon: response.label,
                                        title: response.content
                                    });
                                }
                            });
                        }
                    }
                } else {
                    Toast.fire({
                        icon: "error",
                        title: "Sorry! no items selected"
                    });
                }

                return false;
            });
            $('#cancelType').change(function (e) {
                let type = $(this).val();

                if (type === 'all') {
                    $('input.cancel-item').each(function () {
                        $(this).attr('checked', true);
                    });
                    $('#cancellationItems').hide();
                } else {
                    $('input.cancel-item').each(function () {
                        $(this).attr('checked', false);
                    });
                    $('#cancellationItems').show();
                }
            });
            $("input.cancel-item").change(function () {
                if ($('input.cancel-item:checked').length == $('input.cancel-item').length) {
                    $('#cancelType').val('all');
                    $('#cancellationItems').hide();
                }
            });
            $('.bookingHonoriumForm').submit(function (e) {
                e.defaultPrevented;
                let url = $(this).attr('action');
                let data = $(this).serialize();
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    cache: false
                });
                $.ajax({
                    dataType: "json",
                    type: "POST",
                    url: url,
                    data: data,
                    success: function (response, textStatus, xhr) {
                        if (response.success == true) {
                            $(modal).modal('hide');
                        }
                        Toast.fire({
                            icon: response.label,
                            title: response.content
                        });
                    }
                });
                return false;
            });

            let booking_date = '';
            let schedule_date = '';
            let keyword = $('#keywords');
            let passenger = $('#passengerDetails');
            let type = $('#filterType');
            let paymentMethod = $('#filterMethod');
            let status = $('#filterStatus');
            let bookingTable = $('#recentBookingsTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": true,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': "{{ route('dashboard.schedule.bookings', $schedule->id)}}",
                    pages: 5, // number of pages to cache
                    'data': function (data) {
                        data.type = $('#filterType').val();
                        data.booking_date = booking_date;
                        data.schedule_date = schedule_date;
                        data.keyword = $(keyword).val();
                        data.passenger = $(passenger).val();
                        data.method = $(paymentMethod).val();
                        data.status = $(status).val();
                    }
                },
                "lengthChange": false,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "oLanguage": {
                    "sLengthMenu": "Show _MENU_ ",
                },
                "pageLength": 15,
                "bFilter": true,
                "bInfo": false,
                "searching": false,
                "columns": [
                    {
                        "mRender": function (data, type, row) {
                            var str = '<a href="/admin/booking/show/' + row['booking_id'] + '" class="cabin-action2">#' + row['booking_id'] + '</a>';
                            return str;
                        }
                    },
                    {"data": "booking_type"},
                    {
                        "mRender": function (data, type, row) {
                            // let passenger = JSON.parse(row['passenger']);
                            let str = '';
                            if (row['booking_type'] == 'cabin') {
                                str = '<span class="badge badge-info"><i class="fa fa-bed"></i> ' + row['item']['cabin_no'] + '</span>';
                            } else if (row['booking_type'] == 'seat') {
                                str = '<span class="badge badge-info"><i class="fa fa-chair"></i> ' + row['item']['cabin_no'] + '</span>';
                            } else {
                                str = '<span class="badge badge-info"><i class="fa fa-ticket-alt"></i> x ' + passenger.person + '</span>';
                            }

                            return str;
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            let passenger = JSON.parse(row['passenger']);
                            return passenger.name + ' - ' + passenger.mobile;
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            let date = new Date(row['booking_date']);
                            let month = date.getMonth() + 1;
                            return date.getDate() + '/' + month + '/' + date.getFullYear();
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            let date = new Date(row['trip_date']);
                            let month = date.getMonth() + 1;
                            return date.getDate() + '/' + month + '/' + date.getFullYear();
                        }
                    },
                    {"data": "booking.payment.payment_method"},
                    {
                        "mRender": function (data, type, row) {
                            let str = '';
                            switch (row['status']) {
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
                    {"targets": [0, 1, 4], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[4, 'asc']],
            });

            $('#booking_date').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
                endDate: "+30d"
            }).on('changeDate', function (ev) {
                booking_date = $(this).val();
                $(this).datepicker('hide');
                bookingTable.draw()
            });

            $('#schedule_date').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
                endDate: "+30d"
            }).on('changeDate', function (ev) {
                schedule_date = $(this).val();
                $(this).datepicker('hide');
                bookingTable.draw()
            });

            $(type).change(function (e) {
                bookingTable.draw();
            });

            $(passenger).keyup(function (e) {
                bookingTable.draw();
            });

            $(paymentMethod).change(function (e) {
                bookingTable.draw();
            });

            $(status).change(function (e) {
                bookingTable.draw();
            });

            //Custom Filters ( title search )
            $(keyword).keyup(function (event) {
                var keycode = (event.keyCode ? event.keyCode : event.which);
                // if(keycode == '13'){
                bookingTable.draw();
            });
            var cabinUrl = "{{ route('dashboard.schedule.cabins', $schedule->id) }}";
            let cabinNo = $('#cabinNo');
            let seatNo = $('#seatNo');
            let cabinFloor = $('#cabinFloor');
            let seatFloor = $('#seatFloor');
            let cabinOwner = $('#cabinOwner');
            let seatOwner = $('#seatOwner');
            let cabinType = $('#cabinType');
            let seatType = $('#seatType');
            let cabinReserved = $('#cabinReserved');
            let seatReserved = $('#seatReserved');
            let cabinBooked = $('#cabinBooked');
            let seatBooked = $('#seatBooked');
            var ctable = $('#seatsTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": true,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': cabinUrl,
                    pages: 5, // number of pages to cache
                    'data': function (data) {
                        data.type = 'seat';
                        data.cabin_no = $(seatNo).val();
                        data.floor = $(seatFloor).val();
                        data.cabin_type = $(seatType).val();
                        data.owner = $(seatOwner).val();
                        data.is_reserved = $(seatReserved).val();
                        data.is_booked = $(seatBooked).val();
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
                        "mRender": function (data, type, row) {
                            return '<input type="checkbox" class="selectedItem" value="' + row['id'] + '">';
                        }
                    },
                    {"data": "cabin_no"},
                    {"data": "floor"},
                    {"data": "row"},
                    {"data": "position"},
                    {"data": "type_name"},
                    {"data": "is_ac"},
                    {"mRender": function(data, type, row)
                        {
                            let str = row['ownership'];
                            if(row['can_edit']) {
                                str = '<select class="form-control changeOwnership" data-id="' + row['id'] + '" data-action="changeOwnership">';
                                $.each(parties, function (key, value) {
                                    let selected = (value === row['ownership']) ? 'selected' : '';
                                    str += '<option value="' + key + '" ' + selected + '>' + value + '</option>';
                                });
                                str += '</select>';
                            }
                            return str;
                        }
                    },
                    {"data": "counter"},
                    {"data": "honorium"},
                    {"data": "fare"},
                    {"data": "is_reserved"},
                    {"data": "booked"},
                    {
                        "mRender": function (data, type, row) {
                            let str = '';
                            if (row['booked'] == 'No') {
                                str += "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'>";

                                if (row['is_reserved'] == 'Y') {
                                    str += "<a href='/admin/vehicle/schedule/mapping/action' data-action='release' data-id='" + row['id'] + "' class='dropdown-item mappingAction' data-type='seat'><i class='fa fa-times'></i> Release</a>";
                                } else {
                                    str += "<a href='/admin/vehicle/schedule/mapping/action' data-action='reserve' class='dropdown-item mappingAction' data-id='" + row['id'] + "' data-type='seat'><i class='fa fa-save'></i> Reserve</a>";
                                }
                                if(row['can_edit']) {
                                    str += "<a href='/admin/vehicle/schedule/mapping/action' data-action='unlock' data-id='" + row['id'] + "' class='dropdown-item mappingAction' data-type='seat'><i class='fa fa-times'></i> Unlock</a>";
                                    str += "<a href='#' data-action='edit' class='dropdown-item mappingEdit' data-id='" + row['id'] + "' data-type='cabin'><i class='fa fa-edit'></i> Edit</a>";
                                }
                                str += "</div> </div>";
                            }
                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0, 1, 4], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[2, 'asc']],
                buttons: [
                    'copy', 'excel', 'pdf', 'print'
                ]
            });

            table = $('#cabinsTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": true,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': cabinUrl,
                    pages: 5, // number of pages to cache
                    'data': function (data) {
                        data.type = 'cabin';
                        data.cabin_no = $(cabinNo).val();
                        data.floor = $(cabinFloor).val();
                        data.cabin_type = $(cabinType).val();
                        data.owner = $(cabinOwner).val();
                        data.is_reserved = $(cabinReserved).val();
                        data.is_booked = $(cabinBooked).val();
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
                        "mRender": function (data, type, row) {
                            return '<input type="checkbox" class="selectedItem" value="' + row['id'] + '">';
                        }
                    },
                    {"data": "cabin_no"},
                    {"data": "floor"},
                    {"data": "row"},
                    {"data": "position"},
                    {"data": "type_name"},
                    {"data": "is_ac"},
                    {"mRender": function(data, type, row)
                        {
                            let str = row['ownership'];
                            if(row['can_edit']) {
                                str = '<select class="form-control changeOwnership" data-id="' + row['id'] + '" data-action="changeOwnership">';
                                $.each(parties, function (key, value) {
                                    let selected = (value === row['ownership']) ? 'selected' : '';
                                    str += '<option value="' + key + '" ' + selected + '>' + value + '</option>';
                                });
                                str += '</select>';
                            }
                            return str;
                        }
                    },
                    {"data": "counter"},
                    {"data": "honorium"},
                    {"data": "fare"},
                    {"data": "is_reserved"},
                    {"data": "booked"},
                    {
                        "mRender": function (data, type, row) {
                            let str = '';
                            if (row['booked'] == 'No') {
                                str = "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'>";
                                if (row['is_reserved'] == 'Y') {
                                    str += "<a href='/admin/vehicle/schedule/mapping/action' data-action='release' data-id='" + row['id'] + "' class='dropdown-item mappingAction' data-type='cabin'><i class='fa fa-times'></i> Release</a>";
                                    str += "<a href='/admin/vehicle/schedule/mapping/book/" + row['id'] + "' class='dropdown-item'><i class='fa fa-check'></i> Book now</a>";
                                } else {
                                    str += "<a href='/admin/vehicle/schedule/mapping/action' data-action='reserve' class='dropdown-item mappingAction' data-id='" + row['id'] + "' data-type='cabin'><i class='fa fa-save'></i> Reserve</a>";
                                }
                                if(row['can_edit']) {
                                    str += "<a href='/admin/vehicle/schedule/mapping/action' data-action='unlock' data-id='" + row['id'] + "' class='dropdown-item mappingAction' data-type='cabin'><i class='fa fa-times'></i> Unlock</a>";
                                    str += "<a href='#' data-action='edit' class='dropdown-item mappingEdit' data-id='" + row['id'] + "' data-type='cabin'><i class='fa fa-edit'></i> Edit</a>";
                                }
                                str += "</div> </div>";
                            }
                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0, 1, 4], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[2, 'asc']],
                buttons: [
                    'copy', 'excel', 'pdf', 'print'
                ]
            });

            $("#cabinType, #cabinOwner, #cabinFloor, #cabinReserved, #cabinBooked").change(function () {
                table.draw();
            });

            $("#seatType, #seatOwner, #seatFloor, #seatReserved, #seatBooked").change(function () {
                ctable.draw();
            });

            $('table').on('change', '.changeOwnership', function(e) {
                e.defaultPrevented;
                console.log(this);
                var url = "{{ route('dashboard.schedule.mapping.action') }}";
                var action = $(this).data('action');
                let value = $(this).val();
                let type = $(this).data('type');
                var id = $(this).data('id');
                if (action) {
                    var data = {action: action, id: id, owner: value};
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You are going to " + action + " this item.",
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
                                    response = JSON.parse(response);
                                    if (response.status == true) {
                                        if (type == 'cabin') {
                                            table.draw();
                                        } else {
                                            ctable.draw();
                                        }
                                        Toast.fire({
                                            icon: response.label,
                                            title: response.content
                                        });
                                    }
                                }
                            });

                        }
                    });
                }
                return false;
            });

            $('#mappingUpdateForm').submit(function(e) {
                e.defaultPrevented;
                let url = $(this).attr("action");
                $.ajax({
                    type: "PUT",
                    url: url,
                    data: $(this).serialize(),
                    dataType: "json",
                    success: function(response) {
                        if(response.status == true) {
                            $(editModal).modal('hide');
                        }
                        Toast.fire({
                            icon: response.label,
                            title: response.content
                        });
                    }
                });
                return false;
            });

            $('table').on('click', '.mappingEdit', function () {
                let url = "/admin/vehicle/schedule/mapping/edit/" + $(this).data('id');
                let updateUrl = "/admin/vehicle/schedule/mapping/update/" + $(this).data('id');
                // $(editModal).find('.modal-body').html("");
                $(editModal).find('form').attr("action", updateUrl);
                $.ajax({
                    type: "GET",
                    url: url,
                    data: null,
                    dataType: "json",
                    success: function(response) {
                        if(response.status == true) {
                            console.log(response.data);
                            $(editModal).find('.modal-title').html(response.data.type + " " + response.data.cabin_no);
                            $(editModal).find('#mappingID').val(response.data.id);
                            $(editModal).find('#ownership').val(response.data.ownership.toLowerCase());
                            $(editModal).find('#cabinRow').val(response.data.cabin_row);
                            $(editModal).find('#cabinPosition').val(response.data.cabin_position);
                            $(editModal).find('#cabinFare').val(response.data.fare);
                            $(editModal).find('#cabinCharge').val(response.data.service_charge);
                            $(editModal).modal('show');
                        }
                    }
                });
                return false;
            })

            $('table').on('click', '.mappingAction', function () {
                var url = "{{ route('dashboard.schedule.mapping.action') }}";
                var action = $(this).data('action');
                let type = $(this).data('type');
                var id = $(this).data('id');
                if (action) {
                    var data = {action: action, id: id};
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You are going to " + action + " this item.",
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
                                    response = JSON.parse(response);
                                    if (response.status == true) {
                                        if (type == 'cabin') {
                                            table.draw();
                                        } else {
                                            ctable.draw();
                                        }
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


            let deckFaresTable = $('#deckFaresTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": true,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': "{{ route('dashboard.vehicle.deckfares', $schedule->vehicle_id)}}",
                    pages: 5, // number of pages to cache
                    'data': function (data) {
                        data.type = 'cabin';
                        data.floor = $(cabinFloor).val();
                    }
                },
                "lengthChange": false,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "oLanguage": {
                    "sLengthMenu": "Show _MENU_ ",
                },
                "pageLength": 15,
                "bFilter": true,
                "bInfo": false,
                "searching": false,
                "columns": [
                    {"data": "route.route_name"},
                    {"data": "departure_from.ghat.name"},
                    {"data": "departure_to.ghat.name"},
                    {"data": "fare"},
                    {"data": "reverse_fare"},
                    {
                        "mRender": function (data, type, row) {
                            return '<a href="/admin/vehicle/fares/edit/' + row['id'] + '"><i class="fa fa-edit"></i></a>';
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [5], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[0, 'asc']],
            });
            $('#cabin-tab').click(function () {
                // alert('you clicked cabin tab')
            });

            $('#seat-tab').click(function () {
                // alert('you clicked seat tab')
            });
        });
    </script>
@endsection
