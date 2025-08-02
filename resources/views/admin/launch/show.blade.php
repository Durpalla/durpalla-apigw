@extends('layouts.master')

@section('content')

    <?php
    $availableRoutes = [];
    $row = ['id' => $vehicle->route['id'], 'name' => $vehicle->route['route_name']];
    array_push($availableRoutes, $row);
    ?>
        <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'info') ? 'active': ''; @endphp"
                   id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Info</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'schedule') ? 'active': ''; @endphp"
                   id="profile-tab"
                   href="{{ route('dashboard.vehicle.show', ['id' => $vehicle->id, 'tab' => 'schedule']) }}"
                >Schedules</a>
            </li>
            @if($vehicle->vehicle_type === 'launch')
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
            @if($vehicle->vehicle_type === 'launch')
                <li class="nav-item">
                    <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'deck') ? 'active': ''; @endphp"
                       id="deck-tab" data-toggle="tab" href="#deck" role="tab" aria-controls="deck"
                       aria-selected="false">Deck</a>
                </li>
            @endif
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'supervisor') ? 'active': ''; @endphp"
                   id="supervisor-tab" data-toggle="tab" href="#supervisor" role="tab" aria-controls="supervisor"
                   aria-selected="false">Operators</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'stat') ? 'active': ''; @endphp"
                   id="contact-tab" data-toggle="tab" href="#contact" role="tab" aria-controls="contact"
                   aria-selected="false">Statistics</a>
            </li>
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div
                class="tab-pane fade show @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'info') ? 'active show': ''; @endphp"
                id="home" role="tabpanel" aria-labelledby="home-tab">
                <div class="row">
                    <div class="col-12 col-md-8 col-lg-8 order-2 order-md-1">

                        <div class="row mt-3">
                            @if($vehicle->vehicle_type === 'launch')
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-info elevation-1"><i
                                                class="fas fa-bed"></i></span>

                                        <div class="info-box-content">
                                            <span class="info-box-text">Cabins</span>
                                            <span class="info-box-number">{{ $vehicle->cabins_count }}</span>
                                        </div>
                                        <!-- /.info-box-content -->
                                    </div>
                                    <!-- /.info-box -->
                                </div>
                                <!-- /.col -->
                            @endif
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="info-box mb-3">
                                    <span class="info-box-icon bg-danger elevation-1"><i
                                            class="fas fa-chair"></i></span>

                                    <div class="info-box-content">
                                        <span class="info-box-text">Seats</span>
                                        <span class="info-box-number">{{ $vehicle->seats_count }}</span>
                                    </div>
                                    <!-- /.info-box-content -->
                                </div>
                                <!-- /.info-box -->
                            </div>
                            <!-- /.col -->

                            <!-- fix for small devices only -->
                            <div class="clearfix hidden-md-up"></div>
                            @if($vehicle->vehicle_type === 'launch')
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="info-box mb-3">
                                        <span class="info-box-icon bg-success elevation-1"><i
                                                class="fas fa-ticket-alt"></i></span>

                                        <div class="info-box-content">
                                            <span class="info-box-text">Decks</span>
                                            <span class="info-box-number">{{ $vehicle->passengers_capacity }}</span>
                                        </div>
                                        <!-- /.info-box-content -->
                                    </div>
                                    <!-- /.info-box -->
                                </div>
                                <!-- /.col -->
                            @endif
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
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body p-0">
                                        <div id="advancedFilter">
                                            <div class="row pt-2">
                                                <div class="col-sm-3">
                                                    <input type="text" placeholder="Schedule date"
                                                           class="form-controll bookingdatepicker" id="date_from"
                                                           value="">
                                                </div>
                                                <div class="col-sm-3">
                                                    <input type="text" placeholder="Schedule date"
                                                           class="form-controll bookingdatepicker" id="date_to"
                                                           value="">
                                                </div>
                                                <div class="col-sm-4">
                                                    <select class="form-control select2" id="filterRoutes" id="items"
                                                            data-placeholder="Select route"
                                                            data-dropdown-css-class="select2-purple"
                                                            style="width: 100%;">
                                                        <option value="">Select route</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-2">
                                                    <select class="form-control" id="filterType">
                                                        <option value="">Type</option>
                                                        <option value="cabin">Cabin</option>
                                                        <option value="seat">Seat</option>
                                                        <option value="deck">Deck</option>
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
                        <h3 class="text-secondary"> {{ $vehicle->name }}
                            <em>{{ ( $vehicle->route ) ? ' [' . $vehicle->route['route_name'] . ']' : '' }}</em> @can('vehicle-edit')
                                <a href="{{ route('dashboard.vehicle.edit', $vehicle ?? ''->id ) }}"><i
                                        class="fa fa-edit"></i></a>
                            @endcan</h3>
                        <hr>
                        @if( $vehicle->photo != null)
                            <div class="profile-userpic">
                                <img src="{{ asset('vehicles/' . $vehicle->photo )}}" alt="logo">
                            </div>
                            <hr>
                        @endif
                        <div class="text-muted">
                            <p class="text-sm">Route
                                <b class="d-block">{{ $vehicle->route['route_name'] }}</b>
                            </p>
                            <p> Stoppages <br>
                                <span
                                    class='badge badge-info'>{{ $vehicle->route['startingPoint']['ghat']['name'] }}</span>
                                @if( $vehicle->route['boardingVias'] )
                                    @foreach( $vehicle->route['boardingVias'] as $stoppage)
                                        <span class='badge badge-info'>{{ $stoppage['ghat']['name'] }}</span>
                                    @endforeach
                                @endif
                                <span
                                    class='badge badge-info'>{{ $vehicle->route['endingPoint']['ghat']['name'] }}</span>
                            </p>
                            <p class="text-sm">License No.
                                <b class="d-block">{{ $vehicle->registration_no }}</b>
                            </p>
                            <p class="text-sm">License expiry date
                                <b class="d-block">{{ date('d/m/Y', strtotime( $vehicle->registration_expiry_date )) }}</b>
                            </p>
                            <p class="text-sm">Fitness expiry date
                                <b class="d-block">{{ date('d/m/Y', strtotime( $vehicle->fitness_expiry_date )) }}</b>
                            </p>
                            <!-- <p class="text-sm">Passengers capacity
              <b class="d-block">{{ $vehicle->passengers_capacity }}</b>
            </p> -->
                        </div>
                        <h5 class="mt-5 text-muted">Merchant info</h5>
                        <hr>
                        <div class="text-muted">
                            <p class="text-sm">Merchant Name.
                                <b class="d-block">{{ $vehicle->merchant['merchant_name'] }}</b>
                            </p>
                            <p class="text-sm">Merchant Registration No.
                                <b class="d-block">{{ $vehicle->merchant['merchant_reg_no'] }}</b>
                            </p>
                            <p class="text-sm">Merchant Address
                                <b class="d-block">{{ $vehicle->merchant['merchant_address'] }}</b>
                            </p>
                            <p class="text-sm">Merchant mobile
                                <b class="d-block">{{ $vehicle->merchant['merchant_mobile'] }}</b>
                            </p>
                            <p class="text-sm">Merchant Phone
                                <b class="d-block">{{ $vehicle->merchant['merchant_phone'] }}</b>
                            </p>
                            <p class="text-sm">Merchant Fax
                                <b class="d-block">{{ $vehicle->merchant['merchant_fax'] }}</b>
                            </p>
                            <p class="text-sm">Merchant Email
                                <b class="d-block">{{ $vehicle->merchant['merchant_email'] }}</b>
                            </p>
                        </div>
                        @if( $vehicle->supervisors )
                            <h5 class="mt-5 text-muted">Contact info</h5>
                            <hr>
                            <ul class="list-unstyled">
                                @foreach( $vehicle->supervisors as $supervisor )
                                    <li>
                                        <a href="" class="btn-link text-secondary"><i
                                                class="fas fa-fw fa-user"></i> {{ $supervisor['user']['name'] }}
                                            [Supervisor]</a>
                                    </li>
                                    <li>
                                        <a href="" class="btn-link text-secondary"><i
                                                class="fas fa-fw fa-phone"></i> {{ $supervisor['user']['mobile'] }}</a>
                                    </li>
                                    <li>
                                        <a href="" class="btn-link text-secondary"><i
                                                class="fas fa-fw fa-envelope"></i> {{ $supervisor['user']['email'] }}
                                        </a>
                                    </li>
                                @endforeach
                                <!-- <li>
                <a href="" class="btn-link text-secondary"><i class="far fa-fw fa-file-word"></i> Contract-10_12_2014.docx</a>
              </li> -->
                            </ul>
                        @endif
                        <!-- <div class="text-center mt-5 mb-3">
              <a href="#" class="btn btn-sm btn-primary">Add files</a>
              <a href="#" class="btn btn-sm btn-warning">Report contact</a>
            </div> -->
                    </div>
                </div>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'schedule') ? 'active show': ''; @endphp"
                id="profile" role="tabpanel" aria-labelledby="profile-tab">
                <div class="row">
                    <div class="col-12 col-md-12 col-lg-8 order-2 order-md-1">

                        <div class="row">
                            <div class="col-12">
                                <h4>Up-coming schedules</h4>
                                <hr>
                                @if( $schedules )
                                    @foreach( $schedules as $schedule )
                                        @if($schedule->status == \App\Constants\AppConst::SCHEDULE_ACTIVE)
                                            @php
                                                $row = ['id' => $schedule->route['id'], 'name' => $schedule->route['route_name']];
                                                if( !in_array( $row, $availableRoutes ) ) {
                                                  array_push( $availableRoutes, $row);
                                                }
                                            @endphp
                                            <div
                                                style="background-color: {{($schedule->status == \App\Constants\AppConst::SCHEDULE_RESCHEDULE || $schedule->status == \App\Constants\AppConst::SCHEDULE_CANCEL) ? "#ffcccc" : ""}} "
                                                class="row row-striped">
                                                <div class="col-2 text-center">
                                                    <h1 class="display-4"><span
                                                            class="badge badge-secondary">{{ date('d', strtotime( $schedule->schedule_date ) ) }}</span>
                                                    </h1>
                                                    <h2 class="display-4">{{ date('M', strtotime( $schedule->schedule_date ) ) }}</h2>
                                                </div>
                                                <div class="col-7">
                                                    <h3 class="text-uppercase">
                                                        <strong>{{ ( $schedule['schedule_type'] == 'reverse' ) ? strtoupper($schedule['route']['endingPoint']['ghat']['name']) . '-' . strtoupper($schedule['route']['startingPoint']['ghat']['name']) : strtoupper($schedule['route']['startingPoint']['ghat']['name']) . '-' . strtoupper($schedule['route']['endingPoint']['ghat']['name']) }}</strong>
                                                    </h3>
                                                    <ul class="list-inline">
                                                        <li class="list-inline-item"><i class="fa fa-calendar-o"
                                                                                        aria-hidden="true"></i> {{ date('D', strtotime( $schedule['schedule_date'] ) ) }}
                                                        </li>
                                                        <li class="list-inline-item"><i class="fa fa-clock-o"
                                                                                        aria-hidden="true"></i> {{ date('h:i A', strtotime( $schedule['leaving_at'] ) ) }}
                                                        </li>
                                                        <li class="list-inline-item"><i class="fa fa-location-arrow"
                                                                                        aria-hidden="true"></i> {{ ( $schedule['schedule_type'] == 'reverse' ) ? $schedule['route']['endingPoint']['ghat']['name'] : $schedule['route']['startingPoint']['ghat']['name'] }}
                                                        </li>
                                                    </ul>
                                                    <p>
                                                            <?php
                                                            $stoppages = [];
                                                            array_push($stoppages, $schedule->startingPoint['ghat']['name']);
                                                            foreach ($schedule->boardingVias as $boarding) {
                                                                array_push($stoppages, $boarding['ghat']['name']);
                                                            }
                                                            array_push($stoppages, $schedule->endingPoint['ghat']['name']);

                                                            if ($schedule->schedule_type == 'reverse') {
                                                                krsort($stoppages);
                                                            }
                                                            ?>
                                                        @foreach( $stoppages as $k => $stoppage)
                                                            <span class='badge badge-info'>{{ $stoppage }}</span>
                                                        @endforeach
                                                    </p>
                                                </div>
                                                <div class="col-3 seat-cabin-stat">

                                                    <div class="row">
                                                        <div class="col-md-9"><b>Available now</b>
                                                            <hr class="mt-1 mb-2"/>
                                                        </div>
                                                        <div class="col-md-3">
                                                            @if( $schedule->status == 'ACTIVE')
                                                                <div class='btn-group'>
                                                                    <button
                                                                        class='btn btn-secondary btn-sm dropdown-toggle'
                                                                        type='button' data-toggle='dropdown'
                                                                        aria-haspopup='true' aria-expanded='false'>
                                                                        <i class='fa fa-ellipsis-h'
                                                                           aria-hidden='true'></i>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-right">
                                                                        <a href="{{route('dashboard.schedule.cancel',[$schedule->id, $vehicle->id] )}}"
                                                                           class="dropdown-item"> Cancel Schedule</a>
                                                                        <a href="{{route('dashboard.schedule.reschedule', [$schedule->id, $vehicle->id])}}"
                                                                           class="dropdown-item">Reschedule</a>
                                                                        <a href="#"
                                                                           class="dropdown-item schedule-action"
                                                                           data-action='pause'
                                                                           data-schedule-id='{{ $schedule->id }}'>Pause</a>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if($vehicle->vehicle_type === 'launch')
                                                        <p class="mb-0"><i
                                                                class="fas fa-bed"></i> {{ round($schedule->cabin_mappings_count - $schedule->cabin_bookings_count) }}
                                                            /{{ $schedule->cabin_mappings_count }} Cabin</p>
                                                    @endif
                                                    <p class="mb-0"><i
                                                            class="fas fa-chair"></i> {{ round($schedule->seat_mappings_count - $schedule->seat_bookings_count) }}
                                                        /{{ $schedule->seat_mappings_count }} Seat</p>
                                                    @if($vehicle->vehicle_type === 'launch')
                                                        <p class="mb-0"><i
                                                                class="fas fa-ticket-alt"></i> {{ round($vehicle->passengers_capacity - $schedule->seat_bookings_count) }}
                                                            /{{ $vehicle->passengers_capacity }} Ticket</p>
                                                    @endif
                                                    <div
                                                        class="btn-group btn-block btn-group-block btn-group-sm text-center mt-2">
                                                        <a href="{{ route('dashboard.schedule.show', $schedule->id ) }}"
                                                           class="btn btn-secondary"><i class="fa fa-chart-bar"></i>
                                                            Statistics</a>
                                                        <a href="{{ route('dashboard.quickbook', ['route_id' => $schedule->route_id, 'trip_date' => date('d/m/Y', strtotime( $schedule->schedule_date ) ), 'type' => $vehicle->vehicle_type, 'trip_id' => $schedule->id])}}"
                                                           class="btn btn-success"><i class="fa fa-check-circle"></i>
                                                            Quick book</a>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-12 col-lg-4 order-1 order-md-2">
                        @canany(['schedule-assign', 'schedule-create'])
                            <h3 class="text-secondary"><i class="fas fa-plus"></i> Add new schedule</h3>
                            <hr>
                            <form action="{{ route('dashboard.schedule.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                                <input type="hidden" name="merchant_id" value="{{ $vehicle->merchant_id }}">
                                <input type="hidden" name="tab" value="schedule">
                                <div class="form-group">
                                    <label>Route</label>
                                    <select name="route_id" class="form-control @error('route_id') is-invalid @enderror"
                                            value="{{ old('route_id') }}" required>
                                        @foreach( $routes as $route )
                                            <option value="{{ $route->id }}"
                                                    @if( old('route_id', $vehicle->route_id) == $route->id ) selected @endif>{{ $route->route_name }}
                                                ({{ ucfirst( $route->route_type ) }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('route_id')
                                    <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label class="clearfix">Schedule type</label><br>
                                    <input type="checkbox" name="schedule_type" @if(old('schedule_type') == 1) checked
                                           @endif data-toggle="toggle" value="1" data-on="Reverse" data-off="Straight">
                                </div>
                                <div class="form-group">
                                    <label>Date</label>

                                    <div class="input-group">
                                        <input type="text" name="schedule_date" value="{{ old('schedule_date') }}"
                                               class="form-control schedulepicker" data-inputmask-alias="datetime"
                                               data-inputmask-inputformat="dd/mm/yyyy" data-mask required>
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
                                        <input type="text" name="schedule_time" value="{{ old('schedule_time') }}"
                                               class="form-control disabled datetimepicker-input" data-format="H:i"
                                               data-target="#timepicker"/>
                                        <div class="input-group-append" data-target="#timepicker"
                                             data-toggle="datetimepicker">
                                            <div class="input-group-text"><i class="far fa-clock"></i></div>
                                        </div>
                                    </div>
                                    <!-- /.input group -->
                                </div>
                                <div class="form-group">
                                    <label>Operation Hour</label>
                                    <input type="number" name="operation_hour" value="8" class="form-control" step=".5">
                                </div>
                                <div class="form-group">
                                    <button class="btn btn-block btn-primary" type="submit">Save</button>
                                </div>
                            </form>
                        @endcanany
                    </div>
                </div>
            </div>

            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'cabin') ? 'active show': ''; @endphp"
                id="cabin" role="tabpanel" aria-labelledby="cabin-tab">
                <div class="row">
                    <div class="col-12 col-md-12 col-lg-8 order-2 order-md-1">

                        <div class="row">
                            <div class="col-12">
                                <ul class="nav nav-tabs justify-content-end" id="listGridTab" role="tablist">
                                    <li class="nav-item">
                                        <input type="number" id="cabin_no" class="form-control form-control-sm"
                                               placeholder="Cabin no">
                                    </li>
                                    <li class="nav-item">
                                        <select id="cabinFloorFilter" class="form-control form-control-sm">
                                            <option value="">All floor</option>
                                            @for($i = 1; $i <= $vehicle->number_of_floor; $i++)
                                                <option value="{{ $i }}">Floor {{ $i }}</option>
                                            @endfor
                                        </select>
                                    </li>
                                    <li class="nav-item">
                                        <select id="cabinRowFilter" class="form-control form-control-sm">
                                            <option value="">Row</option>
                                            <option value="1">Row 1</option>
                                            <option value="2">Row 2</option>
                                            <option value="3">Row 3</option>
                                            <option value="4">Row 4</option>
                                            <option value="5">Row 5</option>
                                        </select>
                                    </li>
                                    <li class="nav-item">
                                        <input type="number" class="form-control form-control-sm" id="cabin_position"
                                               placeholder="Position">
                                    </li>
                                    <li class="nav-item">
                                        <select id="cabinTypeFilter" class="form-control form-control-sm">
                                            <option value="">Cabin type</option>
                                            @if( $cabin_types )
                                                @foreach( $cabin_types as $type )
                                                    @if( $type->type == 'cabin')
                                                        <option value="{{ $type->id }}">{{ $type->name }}
                                                            ({{ ( $type->is_ac ) ? 'AC' : 'Non-Ac' }})
                                                        </option>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </select>
                                    </li>
                                    <li class="nav-item">
                                        <select id="cabinOwnerFilter" class="form-control form-control-sm">
                                            <option value="">Owner</option>
                                            <option value="merchant">Merchant</option>
                                            <option value="jolzan">Jolzan</option>
                                        </select>
                                    </li>
                                    <li class="nav-item">
                                        <select id="cabinReservationFilter" class="form-control form-control-sm">
                                            <option value="">Status</option>
                                            <option value="1">Reserved</option>
                                            <option value="0">Open</option>
                                        </select>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link active" id="listview-tab" data-toggle="tab" href="#listview"
                                           role="tab" aria-controls="listview" aria-selected="true"><i
                                                class="fa fa-list"></i></a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="gridview-tab" data-toggle="tab" href="#gridview"
                                           role="tab" aria-controls="gridview" aria-selected="false"><i
                                                class="fa fa-th"></i></a>
                                    </li>
                                </ul>
                                <div class="tab-content" id="listGridTabContent"
                                     style="padding: 15px; background: #fff;">
                                    <div class="tab-pane fade show active show" id="listview" role="tabpanel"
                                         aria-labelledby="listview-tab">
                                        <table class="table table-striped table-bordered" id="cabinsTable">
                                            <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Floor.</th>
                                                <th>Row</th>
                                                <th>Position</th>
                                                <th>Type</th>
                                                <th>AC</th>
                                                <th>Owner</th>
                                                <th>Counter</th>
                                                <th>Fare</th>
                                                <th>Charge</th>
                                                <th>Reserved</th>
                                                <th><i class="fa fa-cog"></i></th>
                                            </tr>
                                            </thead>
                                        </table>
                                    </div>
                                    <div class="tab-pane fade" id="gridview" role="tabpanel"
                                         aria-labelledby="gridview-tab">
                                        <div class="row">
                                            <div class="accordion" id="accordionExample">
                                                <div class="card">
                                                    <div class="card-header" id="headingOne">
                                                        <h2 class="mb-0">
                                                            <button class="btn btn-link" type="button"
                                                                    data-toggle="collapse" data-target="#collapseOne"
                                                                    aria-expanded="true" aria-controls="collapseOne">
                                                                First Floor
                                                            </button>
                                                        </h2>
                                                    </div>

                                                    <div id="collapseOne" class="collapse show"
                                                         aria-labelledby="headingOne" data-parent="#accordionExample">
                                                        <div class="card-body">
                                                            <ul class="nav justify-content-center" id="cabinsList">
                                                                @foreach( $vehicle->cabins as $row => $cabins )
                                                                    <li class="nav-item">
                                                                        @foreach( $cabins as $cabin )
                                                                            @if( $cabin['floor'] == '1')
                                                                                <div class="card cabin-card">
                                                                                    @if( $cabin['cabinType']['is_ac'])
                                                                                        <span
                                                                                            class="cabinOverlap">AC</span>
                                                                                    @endif
                                                                                    <span class="cabinNumber">{{ $cabin['cabinType']['letter'] }}-{{ $cabin['cabin_no'] }}</span>
                                                                                    <span
                                                                                        class="cabinPrice">৳{{ $cabin['fare'] }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card">
                                                    <div class="card-header" id="headingTwo">
                                                        <h2 class="mb-0">
                                                            <button class="btn btn-link collapsed" type="button"
                                                                    data-toggle="collapse" data-target="#collapseTwo"
                                                                    aria-expanded="true" aria-controls="collapseTwo">
                                                                Second Floor
                                                            </button>
                                                        </h2>
                                                    </div>

                                                    <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo"
                                                         data-parent="#accordionExample">
                                                        <div class="card-body">
                                                            <ul class="nav justify-content-center" id="cabinsList">
                                                                @foreach( $vehicle->cabins as $row => $cabins )
                                                                    <li class="nav-item">
                                                                        @foreach( $cabins as $cabin )
                                                                            @if( $cabin['floor'] == '2')
                                                                                <div class="card cabin-card">
                                                                                    @if( ($cabin['cabinType']) && $cabin['cabinType']['is_ac'])
                                                                                        <span
                                                                                            class="cabinOverlap">AC</span>
                                                                                    @endif
                                                                                    <span class="cabinNumber">{{ ($cabin['cabinType']) ? $cabin['cabinType']['letter'] : '' }}-{{ $cabin['cabin_no'] }}</span>
                                                                                    <span
                                                                                        class="cabinPrice">৳{{ $cabin['fare'] }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card">
                                                    <div class="card-header" id="headingThree">
                                                        <h2 class="mb-0">
                                                            <button class="btn btn-link collapsed" type="button"
                                                                    data-toggle="collapse" data-target="#collapseThree"
                                                                    aria-expanded="true" aria-controls="collapseThree">
                                                                Third Floor
                                                            </button>
                                                        </h2>
                                                    </div>

                                                    <div id="collapseThree" class="collapse"
                                                         aria-labelledby="headingThree" data-parent="#accordionExample">
                                                        <div class="card-body">
                                                            <ul class="nav justify-content-center" id="cabinsList">
                                                                @foreach( $vehicle->cabins as $row => $cabins )
                                                                    <li class="nav-item">
                                                                        @foreach( $cabins as $cabin )
                                                                            @if( $cabin['floor'] == '3')
                                                                                <div class="card cabin-card">
                                                                                    @if( ($cabin['cabinType']) && $cabin['cabinType']['is_ac'])
                                                                                        <span
                                                                                            class="cabinOverlap">AC</span>
                                                                                    @endif
                                                                                    <span class="cabinNumber">{{ ($cabin['cabinType']) ? $cabin['cabinType']['letter'] : '' }}-{{ $cabin['cabin_no'] }}</span>
                                                                                    <span
                                                                                        class="cabinPrice">৳{{ $cabin['fare'] }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-12 col-lg-4 order-1 order-md-2">
                        @canany(['cabins-add', 'cabin-create'])
                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'info') ? 'active': ''; @endphp"
                                       id="addcabin-tab" data-toggle="tab" href="#addcabin"
                                       aria-controls="addcabin" aria-selected="true">Add new cabin</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link"
                                       id="batchcabin-tab" data-toggle="tab" href="#batchcabin"
                                       aria-controls="batchcabin"
                                       aria-selected="false">Batch upload</a>
                                </li>
                            </ul>
                            <div class="tab-content" id="myTabContent"
                                 style="padding: 15px; background: #fff; border: 1px solid #dee2e6; border-top:0;">
                                <div class="tab-pane fade show active"
                                     id="addcabin" role="tabpanel" aria-labelledby="addcabin-tab">
                                    <form action="{{ route('dashboard.cabin.store') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                                        <input type="hidden" name="tab" value="cabin">
                                        <div class="form-group">
                                            <label>Booking Ownership.</label>
                                            <select name="ownership"
                                                    class="form-control @error('ownership') is-invalid @enderror"
                                                    required>
                                                <option value="">Select ownership</option>
                                                @foreach($party_dropdowns as $key => $value)
                                                    <option value="{{ $key }}">{{ $value }}</option>
                                                @endforeach
                                            </select>
                                            @error('ownership')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Belongs to (Counter)</label>
                                            <select name="ghat_id"
                                                    class="form-control @error('ghat_id') is-invalid @enderror">
                                                <option value="">Select counter</option>
                                                @foreach($ghat_dropdowns as $id => $value)
                                                    <option value="{{$id}}"
                                                            @if(old('ghat_id') == $id) selected @endif>{{$value}}</option>
                                                @endforeach
                                            </select>
                                            @error('ghat_id')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Cabin No.</label>
                                            <input type="text" name="cabin_no"
                                                   class="form-control @error('cabin_no') is-invalid @enderror"
                                                   placeholder="Cabin number" value="{{ old('cabin_no') }}" required>
                                            @error('cabin_no')
                                            <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Cabin Type</label>
                                            <select name="type_id" id="cabinTypes"
                                                    class="form-control @error('type_id') is-invalid @enderror"
                                                    value="{{ old('type_id') }}" required>
                                                <option value="">Select type</option>
                                                @if( $cabin_types )
                                                    @foreach( $cabin_types as $type )
                                                        @if( $type->type == 'cabin')
                                                            <option value="{{ $type->id }}">{{ $type->name }}
                                                                ({{ ( $type->is_ac ) ? 'AC' : 'Non-Ac' }})
                                                                [{{ $type->capacity }} person]
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                @endif
                                            </select>
                                            <a href="#" class="text-primary openTypeModal" data-type="cabin">Add new
                                                type</a>
                                            @error('type_id')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Floor</label>
                                            <select name="floor"
                                                    class="form-control @error('floor') is-invalid @enderror"
                                                    value="{{ old('floor') }}" required>
                                                <option value="">Select floor</option>
                                                @for($i = 1; $i <= $vehicle->number_of_floor; $i++)
                                                    <option value="{{ $i }}">Floor {{ $i }}</option>
                                                @endfor
                                            </select>
                                            @error('floor')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Adult Fare</label>
                                            <input type="number" name="fare"
                                                   class="form-control @error('fare') is-invalid @enderror"
                                                   value="{{ old('fare', 500) }}" required>
                                            @error('fare')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Child Fare</label>
                                            <input type="number" name="child_fare"
                                                   class="form-control @error('child_fare') is-invalid @enderror"
                                                   value="{{ old('child_fare', 500) }}" required>
                                            @error('child_fare')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Infant Fare</label>
                                            <input type="number" name="infant_fare"
                                                   class="form-control @error('infant_fare') is-invalid @enderror"
                                                   value="{{ old('infant_fare', 500) }}" required>
                                            @error('infant_fare')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Service charge</label>
                                            <input type="number" name="service_charge"
                                                   class="form-control @error('service_charge') is-invalid @enderror"
                                                   value="{{ old('service_charge', 0) }}" required>
                                            @error('service_charge')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Position on column</label>
                                            <select name="cabin_row"
                                                    class="form-control @error('cabin_row') is-invalid @enderror"
                                                    value="{{ old('cabin_row') }}" required>
                                                <option value="1">Column 1</option>
                                                <option value="2">Column 2</option>
                                                <option value="3">Column 3</option>
                                                <option value="4">Column 4</option>
                                                <option value="5">Column 5</option>
                                            </select>
                                            @error('cabin_row')
                                            <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Position on Row</label>
                                            <input type="number" min="0" max="100" step="1" name="cabin_position"
                                                   class="form-control @error('cabin_position') is-invalid @enderror"
                                                   value="{{ old('cabin_position', 1) }}" required>
                                            @error('cabin_position')
                                            <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="checkboxPrimary">Keep reserved?</label>
                                            <div class="icheck-primary">
                                                <input type="checkbox" class="cancel-item" id="checkboxPrimary"
                                                       name="is_reserved" value="1">
                                                <label for="checkboxPrimary">
                                                    Yes
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <button class="btn btn-block btn-primary" type="submit">Save</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="tab-pane fade"
                                     id="batchcabin" role="tabpanel" aria-labelledby="batchcabin-tab">
                                    <form action="{{ route('dashboard.vehicle.cabin.batch') }}"
                                          enctype="multipart/form-data" method="POST">
                                        @csrf
                                        <input type="hidden" name="type" value="cabin">
                                        <input type="hidden" name="tab" value="cabin">
                                        <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                                        <input type="hidden" name="merchant_id" value="{{ $vehicle->merchant_id }}">
                                        <div class="form-group">
                                            <label>Choose batch file (.xlsx, .xls)</label>
                                            <input type="file" name="attachment" class="form-control-file"/>
                                        </div>
                                        <div class="form-group">
                                            <a href="{{ asset('default/seat-cabin-import-example.xlsx') }}">Download
                                                example file</a>
                                        </div>
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary">Batch create</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endcanany
                    </div>
                </div>
            </div>

            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'seat') ? 'active show': ''; @endphp"
                id="seat" role="tabpanel" aria-labelledby="seat-tab">
                <div class="row">
                    <div class="col-12 col-md-12 col-lg-8 order-2 order-md-1">

                        <div class="row">
                            <div class="col-12">
                                <ul class="nav nav-tabs justify-content-end" id="listGridTab" role="tablist">
                                    <li class="nav-item">
                                        <input type="number" id="seat_no" class="form-control form-control-sm"
                                               placeholder="Cabin no">
                                    </li>
                                    <li class="nav-item">
                                        <select id="seatFloorFilter" class="form-control form-control-sm">
                                            <option value="">@if($vehicle->vehicle_type === 'bus')
                                                    Decker
                                                @else
                                                    Floor
                                                @endif</option>
                                            @if($vehicle->vehicle_type === 'bus')
                                                <option value="1">Lower</option>
                                                @if($vehicle->number_of_floor > 1)
                                                    <option value="2">Upper</option>
                                                @endif
                                            @elseif($vehicle->vehicle_type === 'launch')
                                                @for($i = 1; $i <= $vehicle->number_of_floor; $i++)
                                                    <option value="{{ $i }}">Floor {{ $i }}</option>
                                                @endfor
                                            @else
                                                <option value="1">Floor 1</option>
                                            @endif
                                        </select>
                                    </li>
                                    <li class="nav-item">
                                        <select id="seatRowFilter" class="form-control form-control-sm">
                                            <option value="">Row</option>
                                            <option value="1">Row 1</option>
                                            <option value="2">Row 2</option>
                                            <option value="3">Row 3</option>
                                            <option value="4">Row 4</option>
                                            <option value="5">Row 5</option>
                                        </select>
                                    </li>
                                    <li class="nav-item">
                                        <input type="number" class="form-control form-control-sm" id="seat_position"
                                               placeholder="Position">
                                    </li>
                                    <li class="nav-item">
                                        <select id="seatTypeFilter" class="form-control form-control-sm">
                                            <option value="">Cabin type</option>
                                            @if( $cabin_types )
                                                @foreach( $cabin_types as $type )
                                                    @if( $type->type == 'seat')
                                                        <option value="{{ $type->id }}">{{ $type->name }}
                                                            ({{ ( $type->is_ac ) ? 'AC' : 'Non-Ac' }})
                                                        </option>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </select>
                                    </li>
                                    <li class="nav-item">
                                        <select id="seatOwnerFilter" class="form-control form-control-sm">
                                            <option value="">Owner</option>
                                            <option value="merchant">Merchant</option>
                                            <option value="jolzan">Jolzan</option>
                                        </select>
                                    </li>
                                    <li class="nav-item">
                                        <select id="seatReservationFilter" class="form-control form-control-sm">
                                            <option value="">Status</option>
                                            <option value="1">Reserved</option>
                                            <option value="0">Open</option>
                                        </select>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link active" id="setListview-tab" data-toggle="tab"
                                           href="#setListview" role="tab" aria-controls="setListview"
                                           aria-selected="true"><i class="fa fa-list"></i></a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="seatGridview-tab" data-toggle="tab" href="#seatGridview"
                                           role="tab" aria-controls="seatGridview" aria-selected="false"><i
                                                class="fa fa-th"></i></a>
                                    </li>
                                </ul>
                                <div class="tab-content" id="listGridTabContent"
                                     style="padding: 15px; background: #fff;">
                                    <div class="tab-pane fade show active show" id="setListview" role="tabpanel"
                                         aria-labelledby="setListview-tab">
                                        <table class="table table-striped table-bordered" id="seatsTable">
                                            <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>@if($vehicle->vehicle_type === 'bus')
                                                        Decker
                                                    @else
                                                        Floor
                                                    @endif</th>
                                                <th>Row</th>
                                                <th>Position</th>
                                                <th>type</th>
                                                <th>AC</th>
                                                <th>Owner</th>
                                                <th>Counter</th>
                                                <th>Fare</th>
                                                <th>Charge</th>
                                                <th>Reserved</th>
                                                <th><i class="fa fa-cog"></i></th>
                                            </tr>
                                            </thead>
                                        </table>
                                    </div>
                                    <div class="tab-pane fade" id="seatGridview" role="tabpanel"
                                         aria-labelledby="seatGridview-tab">
                                        <div class="accordion" id="accordionSeat">
                                            <div class="card">
                                                <div class="card-header" id="seatHeadingOne">
                                                    <h2 class="mb-0">
                                                        <button class="btn btn-link" type="button"
                                                                data-toggle="collapse" data-target="#seatCollapseOne"
                                                                aria-expanded="true" aria-controls="seatCollapseOne">
                                                            First Floor
                                                        </button>
                                                    </h2>
                                                </div>

                                                <div id="seatCollapseOne" class="collapse show"
                                                     aria-labelledby="seatHeadingOne" data-parent="#accordionExample">
                                                    <div class="card-body">
                                                        <ul class="nav justify-content-center" id="cabinsList">
                                                            @foreach( $vehicle->seats as $row => $seats )
                                                                <li class="nav-item">
                                                                    @foreach( $seats as $seat )
                                                                        @if( $seat['floor'] == '1')
                                                                            <div class="card cabin-card">
                                                                                @if($seat['cabinType'] && $seat['cabinType']['is_ac'])
                                                                                    <span class="cabinOverlap">AC</span>
                                                                                @endif
                                                                                <span class="cabinNumber">{{ $seat['cabinType']['letter'] ?? '' }}-{{ $seat['cabin_no'] }}</span>
                                                                                <span
                                                                                    class="cabinPrice">৳{{ $seat['fare'] }}</span>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card">
                                                <div class="card-header" id="seatHeadingTwo">
                                                    <h2 class="mb-0">
                                                        <button class="btn btn-link collapsed" type="button"
                                                                data-toggle="collapse" data-target="#seatCollapseTwo"
                                                                aria-expanded="true" aria-controls="seatCollapseTwo">
                                                            Second Floor
                                                        </button>
                                                    </h2>
                                                </div>

                                                <div id="seatCollapseTwo" class="collapse"
                                                     aria-labelledby="seatHeadingTwo" data-parent="#accordionExample">
                                                    <div class="card-body">
                                                        <ul class="nav justify-content-center" id="cabinsList">
                                                            @foreach( $vehicle->seats as $row => $seats )
                                                                <li class="nav-item">
                                                                    @foreach( $seats as $seat )
                                                                        @if( $seat['floor'] == '2')
                                                                            <div class="card cabin-card">
                                                                                @if( $seat['cabinType'] && $seat['cabinType']['is_ac'])
                                                                                    <span class="cabinOverlap">AC</span>
                                                                                @endif
                                                                                <span class="cabinNumber">{{ $seat['cabinType']['letter'] ?? '' }}-{{ $seat['cabin_no'] }}</span>
                                                                                <span
                                                                                    class="cabinPrice">৳{{ $seat['fare'] }}</span>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card">
                                                <div class="card-header" id="seatHeadingThree">
                                                    <h2 class="mb-0">
                                                        <button class="btn btn-link collapsed" type="button"
                                                                data-toggle="collapse" data-target="#seatCollapseThree"
                                                                aria-expanded="true" aria-controls="seatCollapseThree">
                                                            Third Floor
                                                        </button>
                                                    </h2>
                                                </div>

                                                <div id="seatCollapseThree" class="collapse"
                                                     aria-labelledby="seatHeadingThree" data-parent="#accordionExample">
                                                    <div class="card-body">
                                                        <ul class="nav justify-content-center" id="cabinsList">
                                                            @foreach( $vehicle->seats as $row => $seats )
                                                                <li class="nav-item">
                                                                    @foreach( $seats as $seat )
                                                                        @if( $seat['floor'] == '3')
                                                                            <div class="card cabin-card">
                                                                                @if( $seat['cabinType'] && $seat['cabinType']['is_ac'])
                                                                                    <span class="cabinOverlap">AC</span>
                                                                                @endif
                                                                                <span
                                                                                    class="cabinNumber">{{ ($seat['cabinType']) ? $seat['cabinType']['letter'] . '-' : '' }} {{ $seat['cabin_no'] }}</span>
                                                                                <span
                                                                                    class="cabinPrice">৳{{ $seat['fare'] }}</span>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-12 col-lg-4 order-1 order-md-2">
                        @canany(['cabins-add', 'cabin-create'])
                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link show active"
                                       id="addseat-tab" data-toggle="tab" href="#addseat" role="tab"
                                       aria-controls="addseat" aria-selected="true">Add new seat</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link"
                                       id="batchseat-tab" data-toggle="tab" href="#batchseat" role="tab"
                                       aria-controls="batchseat"
                                       aria-selected="false">Batch upload</a>
                                </li>
                            </ul>
                            <div class="tab-content" id="myTabContent"
                                 style="padding: 15px; background: #fff; border: 1px solid #dee2e6; border-top:0;">
                                <div class="tab-pane fade show active"
                                     id="addseat" role="addseat" aria-labelledby="addseat-tab">
                                    <form action="{{ route('dashboard.cabin.store') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                                        <input type="hidden" name="tab" value="seat">
                                        <div class="form-group">
                                            <label>Booking Ownership.</label>
                                            <select name="ownership"
                                                    class="form-control @error('ownership') is-invalid @enderror"
                                                    required>
                                                <option value="">Select ownership</option>
                                                @foreach($party_dropdowns as $key => $value)
                                                    <option value="{{ $key }}">{{ $value }}</option>
                                                @endforeach
                                            </select>
                                            @error('ownership')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Belongs to (Counter)</label>
                                            <select name="ghat_id"
                                                    class="form-control @error('ghat_id') is-invalid @enderror">
                                                <option value="">Select counter</option>
                                                @foreach($ghats as $id => $value)
                                                    <option value="{{$id}}"
                                                            @if(old('ghat_id') == $id) selected @endif>{{$value}}</option>
                                                @endforeach
                                            </select>
                                            @error('ghat_id')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Seat No.</label>
                                            <input type="text" name="cabin_no"
                                                   class="form-control @error('cabin_no') is-invalid @enderror"
                                                   placeholder="Seat number" value="{{ old('cabin_no') }}" required>
                                            @error('cabin_no')
                                            <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Seat Type</label>
                                            <select name="type_id" id="seatTypes"
                                                    class="form-control @error('type_id') is-invalid @enderror"
                                                    value="{{ old('type_id') }}" required>
                                                <option value="">Select type</option>
                                                @if( $cabin_types )
                                                    @foreach( $cabin_types as $type )
                                                        @if( $type->type == 'seat')
                                                            <option value="{{ $type->id }}">{{ $type->name }}
                                                                ({{ ( $type->is_ac ) ? 'AC' : 'Non-Ac' }})
                                                                [{{ $type->capacity }} person]
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                @endif
                                            </select>
                                            <a href="#" class="text-primary openTypeModal" data-type="seat">Add new
                                                type</a>
                                            @error('type_id')
                                            <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>@if($vehicle->vehicle_type === 'bus')
                                                    Decker
                                                @else
                                                    Floor
                                                @endif</label>
                                            <select name="floor"
                                                    class="form-control @error('floor') is-invalid @enderror"
                                                    value="{{ old('floor') }}" required>
                                                @if($vehicle->vehicle_type === 'bus')
                                                    <option value="1">Lower</option>
                                                    @if($vehicle->number_of_floor > 1)
                                                        <option value="2">Upper</option>
                                                    @endif
                                                @elseif($vehicle->vehicle_type === 'launch')
                                                    @for($i = 1; $i <= $vehicle->number_of_floor; $i++)
                                                        <option value="{{ $i }}">Floor {{ $i }}</option>
                                                    @endfor
                                                @else
                                                    <option value="1">1st floor</option>
                                                @endif
                                            </select>
                                            @error('floor')
                                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Adult Fare</label>
                                            <input type="number" name="fare"
                                                   class="form-control @error('fare') is-invalid @enderror"
                                                   value="{{ old('fare', 500) }}" required>
                                            @error('fare')
                                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Child Fare</label>
                                            <input type="number" name="child_fare"
                                                   class="form-control @error('child_fare') is-invalid @enderror"
                                                   value="{{ old('child_fare', 500) }}" required>
                                            @error('child_fare')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Infant Fare</label>
                                            <input type="number" name="infant_fare"
                                                   class="form-control @error('infant_fare') is-invalid @enderror"
                                                   value="{{ old('infant_fare', 500) }}" required>
                                            @error('infant_fare')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Service charge</label>
                                            <input type="number" name="service_charge"
                                                   class="form-control @error('service_charge') is-invalid @enderror"
                                                   value="{{ old('service_charge', 0) }}" required>
                                            @error('service_charge')
                                            <span class="invalid-feedback" role="alert">
                                              <strong>{{ $message }}</strong>
                                            </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Position on column</label>
                                            <select name="cabin_row"
                                                    class="form-control @error('cabin_row') is-invalid @enderror"
                                                    value="{{ old('cabin_row') }}" required>
                                                <option value="1">Col 1</option>
                                                <option value="2">Col 2</option>
                                                <option value="3">Col 3</option>
                                                <option value="4">Col 4</option>
                                                <option value="5">Col 5</option>
                                                <option value="6">Col 6</option>
                                                <option value="7">Col 7</option>
                                                <option value="8">Col 8</option>
                                                <option value="9">Col 9</option>
                                                <option value="10">Col 10</option>
                                                <option value="11">Col 11</option>
                                                <option value="12">Col 12</option>
                                                <option value="13">Col 13</option>
                                                <option value="14">Col 14</option>
                                                <option value="15">Col 15</option>
                                                <option value="16">Col 16</option>
                                                <option value="17">Col 17</option>
                                                <option value="18">Col 18</option>
                                                <option value="19">Col 19</option>
                                                <option value="20">Col 20</option>
                                                <option value="21">Col 21</option>
                                                <option value="22">Col 22</option>
                                                <option value="23">Col 23</option>
                                                <option value="24">Col 24</option>
                                                <option value="25">Col 25</option>
                                                <option value="26">Col 26</option>
                                                <option value="27">Col 27</option>
                                                <option value="28">Col 28</option>
                                                <option value="29">Col 29</option>
                                                <option value="30">Col 30</option>
                                            </select>
                                            @error('cabin_row')
                                            <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Position on Row</label>
                                            <input type="number" min="0" max="100" step="1" name="cabin_position"
                                                   class="form-control @error('cabin_position') is-invalid @enderror"
                                                   value="{{ old('cabin_position', 1) }}" required>
                                            @error('cabin_position')
                                            <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="checkboxPrimary">Keep reserved?</label>
                                            <div class="icheck-primary">
                                                <input type="checkbox" class="cancel-item" id="checkboxPrimary"
                                                       name="is_reserved" value="1">
                                                <label for="checkboxPrimary">
                                                    Yes
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <button class="btn btn-block btn-primary" type="submit">Save</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="tab-pane fade"
                                     id="batchseat" role="tabpanel" aria-labelledby="batchseat-tab">
                                    <form action="{{ route('dashboard.vehicle.cabin.batch') }}"
                                          enctype="multipart/form-data" method="POST">
                                        @csrf
                                        <input type="hidden" name="type" value="seat">
                                        <input type="hidden" name="tab" value="seat">
                                        <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                                        <input type="hidden" name="merchant_id" value="{{ $vehicle->merchant_id }}">
                                        <div class="form-group">
                                            <label>Choose batch file (.xlsx, .xls)</label>
                                            <input type="file" name="attachment" class="form-control-file"/>
                                        </div>
                                        <div class="form-group">
                                            <a href="{{ asset('default/seat-cabin-import-example.xlsx') }}">Download
                                                example file</a>
                                        </div>
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary">Batch create</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endcanany
                    </div>
                </div>
            </div>

            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'deck') ? 'active show': ''; @endphp"
                id="deck" role="tabpanel" aria-labelledby="deck-tab">
                <div class="row">
                    <div class="col-12 col-md-12 col-lg-8 order-2 order-md-1">

                        <div class="row">
                            <div class="col-12">
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
                        </div>
                    </div>
                    <div class="col-12 col-md-12 col-lg-4 order-1 order-md-2">
                        @canany(['deckfare-create'])
                            <h4 class="text-secondary"><i class="fas fa-plus"></i> Deck fare entry</h4>
                            <hr>
                            <form action="{{ route('dashboard.deckfare.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                                <input type="hidden" name="merchant_id" value="{{ $vehicle->merchant_id }}">
                                <input type="hidden" name="tab" value="deck">
                                <div class="form-group">
                                    <label for="deckRouteSelect">Route</label>
                                    <select name="route_id" class="form-control" id="deckRouteSelect" required>
                                        <option value="">---Choose---</option>
                                        @if( $availableRoutes )
                                            @foreach( $availableRoutes as $route )
                                                <option value="{{ $route['id'] }}">{{ $route['name'] }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="deckDepartureFrom">From</label>
                                    <select name="departure_from"
                                            class="form-control @error('departure_from') is-invalid @enderror"
                                            value="{{ old('departure_from') }}" required id="deckDepartureFrom">
                                        <option value="">Start from</option>
                                        <option
                                            value="{{ $vehicle->route['startingPoint']['id'] }}">{{ $vehicle->route['startingPoint']['ghat']['name'] }}</option>
                                        @if( $vehicle->route['boardingVias'] )
                                            @foreach( $vehicle->route['boardingVias'] as $via )
                                                <option value="{{ $via['id'] }}">{{ $via['ghat']['name'] }}</option>
                                            @endforeach
                                        @endif
                                        <option
                                            value="{{ $vehicle->route['endingPoint']['id'] }}">{{ $vehicle->route['endingPoint']['ghat']['name'] }}</option>
                                    </select>
                                    @error('departure_from')
                                    <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label for="deckDepartureTo">To</label>
                                    <select name="departure_to"
                                            class="form-control @error('departure_to') is-invalid @enderror"
                                            value="{{ old('departure_to') }}" required id="deckDepartureTo">
                                        <option value="">Select to</option>
                                        <option
                                            value="{{ $vehicle->route['startingPoint']['id'] }}">{{ $vehicle->route['startingPoint']['ghat']['name'] }}</option>
                                        @if( $vehicle->route['boardingVias'] )
                                            @foreach( $vehicle->route['boardingVias'] as $via )
                                                <option value="{{ $via['id'] }}">{{ $via['ghat']['name'] }}</option>
                                            @endforeach
                                        @endif
                                        <option
                                            value="{{ $vehicle->route['endingPoint']['id'] }}">{{ $vehicle->route['endingPoint']['ghat']['name'] }}</option>
                                    </select>
                                    @error('departure_to')
                                    <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label>Deck Fare</label>
                                    <input type="number" name="deck_fare" min="10" max="5000" step="10"
                                           class="form-control @error('deck_fare') is-invalid @enderror"
                                           value="{{ old('deck_fare', 50) }}" required>
                                    @error('deck_fare')
                                    <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label>Reverse Fare</label>
                                    <input type="number" name="reverse_fare" min="10" max="5000" step="10"
                                           class="form-control @error('reverse_fare') is-invalid @enderror"
                                           value="{{ old('reverse_fare', 50) }}" required>
                                    @error('reverse_fare')
                                    <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <button class="btn btn-block btn-primary" type="submit">Save</button>
                                </div>
                            </form>
                        @endcanany
                    </div>
                </div>
            </div>

            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'supervisor') ? 'active show': ''; @endphp"
                id="supervisor" role="tabpanel" aria-labelledby="supervisor-tab">
                <div class="row mt-3">
                    <div class="col-8">
                        <table class="table table-striped table-bordered projects">
                            <thead>
                            <tr>
                                <th style="width:5%"><i class="fa fa-image"></i></th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Assigned by</th>
                                <th>Assigned date</th>
                                <th>Master</th>
                                <th>Assigned Device</th>
                                <th>Incentive</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if( $vehicle->supervisors )
                                @foreach( $vehicle->supervisors as $supervisor )
                                    <tr>
                                        <td>
                                            <img
                                                src="{{ $supervisor['user']['profile_pic'] ? asset( $supervisor['user']['profile_pic'] ) : asset('default/avatar.png') }}"
                                                class="table-avatar"></td>
                                        <td>{{ $supervisor['user']['name']}}</td>
                                        <td>{{ $supervisor['user']['email']}}</td>
                                        <td>{{ $supervisor['user']['mobile']}}</td>
                                        <td>{{ ($supervisor['assignator']) ? $supervisor['assignator']['name'] : '' }}</td>
                                        <td>{{ date('d M, Y', strtotime( $supervisor['created_at'] ) ) }}</td>
                                        <td>{{ ($supervisor->master) ? $supervisor->master['name'] : 'N/A' }}</td>
                                        <td>---</td>
                                        <td>
                                            {{ $supervisor['supervisor_incentive'] }} {{ ($supervisor['incentive_type'] == 'fixed') ? 'Tk.' : '%'}}
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                            </tbody>
                        </table>
                    </div>
                    <div class="col-4">
                        <h3 class="text-secondary"><i class="fas fa-plus"></i> Assign new supervisor</h3>
                        <hr>
                        <form action="{{ route('dashboard.supervisor.assign') }}" method="POST">
                            @csrf
                            <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                            <input type="hidden" name="tab" value="supervisor">
                            <div class="form-group">
                                <label>Select supervisor</label>
                                <select name="supervisor_id" class="form-control" required>
                                    <option value="">Select supervisor</option>
                                    @if( $supervisors )
                                        @foreach( $supervisors as $supervisor )
                                            <option
                                                value="{{ $supervisor['id'] }}">{{ $supervisor['name'] }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('cabin_no')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Select Master</label>
                                <select name="master_id" class="form-control">
                                    <option value="">Select master</option>
                                    @if( $vehicle->supervisors )
                                        @foreach( $vehicle->supervisors as $supervisor )
                                            <option
                                                value="{{ $supervisor->user_id }}">{{ $supervisor->user['name'] }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('master_id')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Supervisor incentive</label>
                                <div class="input-group">
                                    <input type="number" id="inputPassword5" name="supervisor_incentive" value="0"
                                           class="form-control" aria-describedby="passwordHelpBlock" required>
                                    <div class="input-group-append">
                                        <div class="input-group-text p-0">
                                            <select name="incentive_type" class="form-control">
                                                <option value="percent"
                                                        @if(getOption('incentive_type') == 'percent') selected @endif>
                                                    Percentage
                                                </option>
                                                <option value="fixed"
                                                        @if(getOption('incentive_type') == 'fixed') selected @endif>
                                                    Fixed
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <button class="btn btn-block btn-lg btn-primary" type="submit">Assign to this
                                    vehicle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <form action="" id="officersReportFilterForm" method="GET">
                    <div class="row mt-3">
                        <div class="col-sm-3">
                            <select class="form-control" name="route_id" id="filterRoutes3" id="items"
                                    data-placeholder="Select route" data-dropdown-css-class="select2-purple"
                                    style="width: 100%;"></select>
                        </div>
                        <div class="col-sm-3">
                            <select class="form-control" name="type" id="filterStatus">
                                <option value="" value="selected">All party</option>
                                <option value="merchant">Merchant</option>
                                <option value="jolzan">Jolzan</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <div class="input-group">
                                <input type="text" name="date_from" id="date_from" class="form-control datepicker"
                                       placeholder="Schedule date" value="{{ date('01/m/Y') }}" required>
                                <span class="input-group-addon m-2">To</span>
                                <input type="text" name="date_to" id="date_to" class="form-control datepicker"
                                       placeholder="Schedule date" value="{{ date('t/m/Y') }}" required>
                                <div class="input-group-btn">
                                    <button class="btn btn-success"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </form>
                <div class="row mt-3">
                    <div id="vehicleOfficersStatistics" class="p-3" style="width:100%">
                        <div class="row">
                            <div class="col-9">
                                <h2>Booking Report : ({{ date('01/m/Y') }} to {{ date('t/m/Y') }})</h2>
                            </div>
                            <div class="col-3 text-right">
                                <button type="button" class="btn btn-primary" onclick="printJS('vehicleStat', 'html')">
                                    <i
                                        class="fa fa-print"></i> Print
                                </button>
                                <button type="button" class="btn btn-primary"
                                        onclick="tableToExcel('vehicleStatistics', 'vehicle-statistics', 'statistics.xls')">
                                    <i class="fa fa-file-excel"></i></button>
                            </div>
                        </div>
                        <table class="table table-striped table-bordered" id="officersStat">
                            <thead>
                            <tr>
                                <th>+/-</th>
                                <th>Officer group</th>
                                <th>Route</th>
                                <th>Officer</th>
                                <th>Total Booking</th>
                                <th>Cabin</th>
                                <th>Seat</th>
                                <th>Deck</th>
                                <th>Total Sell Amount</th>
                                <th>Cabin Sell</th>
                                <th>Seat Seall</th>
                                <th>Deck Sell</th>
                            </tr>
                            </thead>
                            <tbody>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'stat') ? 'active show': ''; @endphp"
                id="contact" role="tabpanel" aria-labelledby="contact-tab">
                <form action="" id="reportFilterForm" method="GET">
                    <div class="row mt-3">
                        <div class="col-sm-3">
                            <select class="form-control" name="route_id" id="filterRoutes2" id="items"
                                    data-placeholder="Select route" data-dropdown-css-class="select2-purple"
                                    style="width: 100%;"></select>
                        </div>
                        <div class="col-sm-3">
                            <select class="form-control" name="type" id="filterStatus">
                                <option value="" value="selected">All party</option>
                                <option value="merchant">Merchant</option>
                                <option value="jolzan">Jolzan</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <div class="input-group">
                                <input type="text" name="date_from" id="date_from" class="form-control datepicker"
                                       placeholder="Schedule date" value="{{ date('01/m/Y') }}" required>
                                <span class="input-group-addon m-2">To</span>
                                <input type="text" name="date_to" id="date_to" class="form-control datepicker"
                                       placeholder="Schedule date" value="{{ date('t/m/Y') }}" required>
                                <div class="input-group-btn">
                                    <button class="btn btn-success"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </form>
                <div class="row mt-3">
                    <div class="col-12">
                        <div id="container" class="chart-container"></div>
                        <!-- /.card -->
                    </div>
                </div>
                <div class="row mt-3">
                    <div id="vehicleStatistics" class="p-3" style="width:100%">
                        <div class="row">
                            <div class="col-9">
                                <h2>Account: All ({{ date('01/m/Y') }} to {{ date('t/m/Y') }})</h2>
                            </div>
                            <div class="col-3 text-right">
                                <button type="button" class="btn btn-primary" onclick="printJS('vehicleStat', 'html')">
                                    <i
                                        class="fa fa-print"></i> Print
                                </button>
                                <button type="button" class="btn btn-primary"
                                        onclick="tableToExcel('vehicleStatistics', 'vehicle-statistics', 'statistics.xls')">
                                    <i class="fa fa-file-excel"></i></button>
                            </div>
                        </div>
                        <table class="table table-striped table-bordered" id="vehicleStat">
                            <thead>
                            <tr>
                                <th></th>
                                <th>vehicle</th>
                                <th>No of Schedules</th>
                                <th>No of Passengers</th>
                                <th>Total Ticket sell amount</th>
                                <th>Total Vat amount</th>
                                <th>Waiver</th>
                                <th>No of discount applied</th>
                                <th>Discount amount</th>
                                <th>No of coupon applied</th>
                                <th>Coupon amount</th>
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
    <!-- /.content -->
    <!-- Modal -->
    <div class="modal fade" id="typeModal" data-backdrop="static" tabindex="-1" role="dialog"
         aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('dashboard.cabintype.store') }}" id="typeForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">Add cabin type</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        @csrf
                        <input type="hidden" name="type" value="cabin">
                        <input type="hidden" name="service_type" value="{{ $vehicle->vehicle_type }}">
                        <!-- text input -->
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   placeholder="Cabin type" value="{{ old('name') }}" required>
                            @error('name')
                            <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Type Letter.</label>
                            <input type="text" name="letter" value="{{ old('letter') }}"
                                   placeholder="Exp. (S = Single, D = Double, F = Family)"
                                   class="form-control @error('letter') is-invalid @enderror" required>
                            @error('letter')
                            <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Passenger capacity</label>
                            <select name="capacity" value="{{ old('capacity') }}"
                                    class="form-control @error('capacity') is-invalid @enderror" required>
                                <option>1</option>
                                <option>2</option>
                                <option>3</option>
                                <option>4</option>
                                <option>5</option>
                            </select>
                            @error('capacity')
                            <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <input type="checkbox" id="isAc" name="is_ac" value="1">
                            <label>AC available</label>
                            @error('is_ac')
                            <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
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
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/plugins/highchart/styles.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/plugins/printjs/print.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/plugins/bootstrap-switch/bootstrap-toggle.min.css') }}">
    <style type="text/css">
        .toggle-on.btn {
            padding-right: 24px;
            width: 50%;
        }

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
            padding: 15px 8px;
            font-size: 24px;
            font-weight: bold;
            padding-bottom: 35px;
        }

        .cabin-card .cabinOverlap {
            position: absolute;
            top: 0;
            right: 0;
            width: auto;
            padding: 0px 5px;
            background: yellow;
            font-size: 12px;
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

        .display-41 {
            text-align: center;
            padding-left: 1rem;
        }

        #cabinsList li.nav-item {
            margin: 15px 5px;
        }

        /* Profile container */
        .profile-userpic {
            height: auto;
            overflow: none;
        }

        .profile-userpic img {
            margin: 0 auto;
            width: auto;
            height: auto;
            max-height: 220px;
            background: #fbfbfb;
            border: 1px solid #eee;
            padding: 10px;
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

    <script src="{{ asset('assets/plugins/highchart/highcharts.js') }}"></script>
    <script src="{{ asset('assets/plugins/highchart/grouped-categories.js') }}"></script>
    <script src="{{ asset('assets/plugins/printjs/print.min.js') }}"></script>
    <script src="{{ asset('js/html2canvas.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/0.9.0rc1/jspdf.min.js"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/pdfmake-0.1.36/pdfmake.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/pdfmake-0.1.36/vfs_fonts.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.flash.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.print.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/bootstrap-switch/bootstrap-toggle.min.js') }}"></script>
    @verbatim
        <script>
            let can_edit = true, can_active = true, can_inactive = true, can_delete = true, cabin_create = true,
                cabin_edit = true;
            let pdf = new jsPDF();
            let specialElementHandlers = {
                '#editor': function (element, renderer) {
                    return true;
                }
            };
            var url = "{{ route('dashboard.vehicle.schedules', $vehicle->id) }}";

            function tableToExcel(table, name, filename) {
                let uri = 'data:application/vnd.ms-excel;base64,',
                    template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><title></title><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--><meta http-equiv="content-type" content="text/plain; charset=UTF-8"/></head><body><table>{table}</table></body></html>',
                    base64 = function (s) {
                        return window.btoa(decodeURIComponent(encodeURIComponent(s)))
                    }, format = function (s, c) {
                        return s.replace(/{(\w+)}/g, function (m, p) {
                            return c[p];
                        })
                    }

                if (!table.nodeType) table = document.getElementById(table)
                var ctx = {worksheet: name || 'Worksheet', table: table.innerHTML}

                var link = document.createElement('a');
                link.download = filename;
                link.href = uri + base64(format(template, ctx));
                link.click();
            }

            function makePdf(elem) {
                let table = document.getElementById('vehicleStatistics');
                pdf.fromHTML($(table).html(), 15, 15, {
                    'width': 570,
                    'elementHandlers': specialElementHandlers
                });
                pdf.save('vehicle_statistics.pdf');
            }

            //
            // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
            //
            $.fn.dataTable.ext.classes.sPageButton = 'page-item';
            $.fn.dataTable.pipeline = function (opts) {
                // Configuration options
                var conf = $.extend({
                    pages: 5,     // number of pages to cache
                    url: "{{ route('dashboard.vehicle.schedules', $vehicle->id) }}",      // script url
                    data: null,   // function or object with parameters to send to the server
                                  // matching how `ajax.data` works in DataTables
                    method: 'GET' // Ajax HTTP method
                }, opts);

                // Private variables for storing the cache
                var cacheLower = -1;
                var cacheUpper = null;
                var cacheLastRequest = null;
                var cacheLastJson = null;

                return function (request, drawCallback, settings) {
                    var ajax = true;
                    var requestStart = request.start;
                    var drawStart = request.start;
                    var requestLength = request.length;
                    var requestEnd = requestStart + requestLength;

                    if (settings.clearCache) {
                        // API requested that the cache be cleared
                        ajax = true;
                        settings.clearCache = false;
                    } else if (cacheLower < 0 || requestStart < cacheLower || requestEnd > cacheUpper) {
                        // outside cached data - need to make a request
                        ajax = true;
                    } else if (JSON.stringify(request.order) !== JSON.stringify(cacheLastRequest.order) ||
                        JSON.stringify(request.columns) !== JSON.stringify(cacheLastRequest.columns) ||
                        JSON.stringify(request.search) !== JSON.stringify(cacheLastRequest.search)
                    ) {
                        // properties changed (ordering, columns, searching)
                        ajax = true;
                    }

                    // Store the request for checking next time around
                    cacheLastRequest = $.extend(true, {}, request);

                    if (ajax) {
                        // Need data from the server
                        if (requestStart < cacheLower) {
                            requestStart = requestStart - (requestLength * (conf.pages - 1));

                            if (requestStart < 0) {
                                requestStart = 0;
                            }
                        }

                        cacheLower = requestStart;
                        cacheUpper = requestStart + (requestLength * conf.pages);

                        request.start = requestStart;
                        request.length = requestLength * conf.pages;

                        // Provide the same `data` options as DataTables.
                        if (typeof conf.data === 'function') {
                            // As a function it is executed with the data object as an arg
                            // for manipulation. If an object is returned, it is used as the
                            // data object to submit
                            var d = conf.data(request);
                            if (d) {
                                $.extend(request, d);
                            }
                        } else if ($.isPlainObject(conf.data)) {
                            // As an object, the data given extends the default
                            $.extend(request, conf.data);
                        }

                        settings.jqXHR = $.ajax({
                            "type": conf.method,
                            "url": conf.url,
                            "data": request,
                            "dataType": "json",
                            "cache": true,
                            "success": function (json) {
                                cacheLastJson = $.extend(true, {}, json);

                                if (cacheLower != drawStart) {
                                    json.data.splice(0, drawStart - cacheLower);
                                }
                                if (requestLength >= -1) {
                                    json.data.splice(requestLength, json.data.length);
                                }

                                drawCallback(json);
                            }
                        });
                    } else {
                        json = $.extend(true, {}, cacheLastJson);
                        json.draw = request.draw; // Update the echo for each response
                        json.data.splice(0, requestStart - cacheLower);
                        json.data.splice(requestLength, json.data.length);

                        drawCallback(json);
                    }
                }
            };

            // Register an API method that will empty the pipelined data, forcing an Ajax
            // fetch on the next draw (i.e. `table.clearPipeline().draw()`)
            $.fn.dataTable.Api.register('clearPipeline()', function () {
                return this.iterator('table', function (settings) {
                    settings.clearCache = true;
                });
            });
            $(function () {
                $('#vehicleStat').DataTable({
                    "lengthChange": false,
                    "bFilter": false,
                    "bInfo": false,
                    "searching": false
                });
                var cabinUrl = "{{ route('dashboard.vehicle.cabins', $vehicle->id) }}";
                let cabinFloor = $('#cabinFloorFilter');
                let cabinNo = $('#cabin_no');
                let cabinRow = $('#cabinRowFilter');
                let cabinPosition = $('#cabin_position');
                let cabinType = $('#cabinTypeFilter');
                let cabinOwner = $('#cabinOwnerFilter');
                let cabinReservation = $('#cabinReservationFilter');
                let seatFloor = $('#seatFloorFilter');
                let seatNo = $('#seat_no');
                let seatRow = $('#seatRowFilter');
                let seatPosition = $('#seat_position');
                let seatType = $('#seatTypeFilter');
                let seatOwner = $('#seatOwnerFilter');
                let seatReservation = $('#seatReservationFilter');
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
                            data.floor = $(seatFloor).val();
                            data.no = $(seatNo).val();
                            data.row = $(seatRow).val();
                            data.position = $(seatPosition).val();
                            data.cabin_type = $(seatType).val();
                            data.owner = $(seatOwner).val();
                            data.reservation = $(seatReservation).val();
                            // Read values
                            // data.keyword = $(keyword).val();
                            // data.area = $(area).val();
                            // data.package = $(package).val();
                            // data.status = $(status).val();
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
                    "dom": "lBfrtip",
                    "columns": [
                        {"data": "cabin_no"},
                        {"data": "floor"},
                        {"data": "row"},
                        {"data": "position"},
                        {"data": "type_name"},
                        {"data": "is_ac"},
                        {"data": "ownership"},
                        {"data": "counter"},
                        {"data": "fare"},
                        {"data": "service_charge"},
                        {"data": "is_reserved"},
                        {
                            "mRender": function (data, type, row) {
                                let str = '';
                                if (cabin_edit) {
                                    str += '<a href="/admin/vehicle/cabin/edit/' + row['id'] + '" class="cabin-action2"><i class="fa fa-edit"></i></a>';
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
                        'copy', 'excel', 'pdf', 'csv'
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
                            data.floor = $(cabinFloor).val();
                            data.no = $(cabinNo).val();
                            data.row = $(cabinRow).val();
                            data.position = $(cabinPosition).val();
                            data.cabin_type = $(cabinType).val();
                            data.owner = $(cabinOwner).val();
                            data.reservation = $(cabinReservation).val();
                            // Read values
                            // data.keyword = $(keyword).val();
                            // data.area = $(area).val();
                            // data.package = $(package).val();
                            // data.status = $(status).val();
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
                    "dom": "lBfrtip",
                    "columns": [
                        {"data": "cabin_no"},
                        {"data": "floor"},
                        {"data": "row"},
                        {"data": "position"},
                        {"data": "type_name"},
                        {"data": "is_ac"},
                        {"data": "ownership"},
                        {"data": "counter"},
                        {"data": "fare"},
                        {"data": "service_charge"},
                        {"data": "is_reserved"},
                        {
                            "mRender": function (data, type, row) {
                                let str = '';
                                if (cabin_edit) {
                                    str += '<a href="/admin/vehicle/cabin/edit/' + row['id'] + '" class="cabin-action2"><i class="fa fa-edit"></i></a>';
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
                        'copy', 'excel', 'pdf', 'csv'
                    ]
                });
                let date_from = '';
                let date_to = '';
                let bookingTable = $('#recentBookingsTable').DataTable({
                    "processing": true,
                    "serverSide": true,
                    "deferRender": true,
                    "bAutoWidth": false,
                    "sPageButtonActive": "active",
                    "ajax": {
                        'url': "{{ route('dashboard.vehicle.bookings', $vehicle->id)}}",
                        pages: 5, // number of pages to cache
                        'data': function (data) {
                            data.type = $('#filterType').val();
                            data.route_id = $('#filterRoutes').val();
                            data.date_from = date_from;
                            data.date_to = date_to;
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
                                let passenger = JSON.parse(row['passenger']);
                                let str = '';
                                if (row['booking_type'] == 'cabin' && row['mapping']) {
                                    str = '<span class="badge badge-info"><i class="fa fa-bed"></i> ' + row['mapping']['cabin_type']['letter'] + '-' + row['mapping']['cabin_no'] + '</span>';
                                } else if (row['booking_type'] == 'seat' && row['mapping']) {
                                    str = '<span class="badge badge-info"><i class="fa fa-chair"></i> ' + row['mapping']['cabin_type']['letter'] + '-' + row['mapping']['cabin_no'] + '</span>';
                                } else {
                                    str = '<span class="badge badge-info"><i class="fa fa-ticket-alt"></i> x ' + passenger.person + '</span>';
                                }

                                return '<a href="/admin/booking/show/' + row['booking_id'] + '">' + str + '</a>';
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
                        {
                            "mRender": function (data, type, row) {
                                let payment_method = '';
                                if (row['booking'] && row['booking']['payment'] != null) {
                                    payment_method = row['booking']['payment']['payment_method'];
                                }
                                return payment_method;
                            }
                        },
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
                    "order": [[2, 'asc']],
                });

                $('#date_from').datepicker({
                    format: 'dd/mm/yyyy',
                    todayHighlight: 'TRUE',
                    autoclose: true,
                    endDate: "+30d"
                }).on('changeDate', function (ev) {
                    date_from = $(this).val();
                    $(this).datepicker('hide');
                    bookingTable.draw()
                });

                $('#date_to').datepicker({
                    format: 'dd/mm/yyyy',
                    todayHighlight: 'TRUE',
                    autoclose: true,
                    endDate: "+30d"
                }).on('changeDate', function (ev) {
                    date_to = $(this).val();
                    $(this).datepicker('hide');
                    bookingTable.draw()
                });

                $('#filterRoutes').change(function (e) {
                    bookingTable.draw();
                });

                $('#filterType').change(function (e) {
                    bookingTable.draw();
                });

                let deckFaresTable = $('#deckFaresTable').DataTable({
                    "processing": true,
                    "serverSide": true,
                    "deferRender": true,
                    "bAutoWidth": false,
                    "sPageButtonActive": "active",
                    "ajax": {
                        'url': "{{ route('dashboard.vehicle.deckfares', $vehicle->id)}}",
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
                                if (cabin_edit) {
                                    return '<a href="/admin/vehicle/fares/edit/' + row['id'] + '"><i class="fa fa-edit"></i></a>';
                                } else {
                                    return '';
                                }
                            }
                        }
                    ],
                    "columnDefs": [
                        {"targets": [5], "searchable": false, "orderable": false, "visible": true}
                    ],
                    "order": [[0, 'asc']],
                });

                $(cabinFloor).change(function () {
                    table.draw();
                });
                $(cabinNo).keyup(function () {
                    table.draw();
                });
                $(cabinRow).change(function () {
                    table.draw();
                });
                $(cabinPosition).keyup(function () {
                    table.draw();
                });
                $(cabinType).change(function () {
                    table.draw();
                });
                $(cabinOwner).change(function () {
                    table.draw();
                });
                $(cabinReservation).change(function () {
                    table.draw();
                });

                $(seatFloor).change(function () {
                    ctable.draw();
                });
                $(seatNo).keyup(function () {
                    ctable.draw();
                });
                $(seatRow).change(function () {
                    ctable.draw();
                });
                $(seatPosition).keyup(function () {
                    ctable.draw();
                });
                $(seatType).change(function () {
                    ctable.draw();
                });
                $(seatOwner).change(function () {
                    ctable.draw();
                });
                $(seatReservation).change(function () {
                    ctable.draw();
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
                // $('#myModal').modal('show');
                $('table').on('click', '.cabin-action', function () {
                    console.log(this);
                    var url = "{{ route('dashboard.vehicle.index') }}";
                    var action = $(this).data('action');
                    var id = $(this).data('vehicle-id');
                    if (action == 'request') {

                    } else {
                        var data = {action: action, id: id};
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "You are going to " + action + " this vehicle account.",
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

                $('table').on('click', '.vehicle-request', function () {
                    var id = $(this).data('vehicle-id');
                    var name = $(this).data('vehicle-name');
                    var url = "{{ route('dashboard.vehicle.store') }}";
                    var modalContent = $(confirmModal).find('.modal-body');
                    $(confirmModal).find('form').attr('action', url);
                    $(modalContent).html("");
                    $(confirmModal).find('.modal-title').text('Make request').css('text-transform', 'capitalize');
                    $(modalContent).append('\n' +
                        '<div class="form-group">\n' +
                        '<label class="control-label">Customer : </label>\n' +
                        '<span><strong>' + name + '</strong></span>\n' +
                        '<input type="hidden" name="vehicle_id" value="' + id + '">\n' +
                        '</div>');
                    $(modalContent).append('\n' +
                        '<div class="form-group">\n' +
                        '<label class="control-label">Request for</label>\n' +
                        '<select name="type" class="form-control" id="vehicleRequestType">\n' +
                        '<option value="">Select type</option>\n' +
                        '<option value="Package">Change Package</option>\n' +
                        '<option value="Email">Change Email</option>\n' +
                        '<option value="Username">Change Username</option>\n' +
                        '<option value="Password">Change Password</option>\n' +
                        '<option value="CustomerID">Change Customer ID</option>\n' +
                        '<option value="Primary contact">Change Primary mobile</option>\n' +
                        '<option value="Secondary contact">Change Secondary mobile</option>\n' +
                        '</select>\n' +
                        '<input type="hidden" name="vehicle_id" value="' + id + '">\n' +
                        '</div>');
                    $(modalContent).find('#vehicleRequestType').change(function (e) {
                        var type = $(this).val();
                        if (['Package'].includes(type)) {
                            var url = "{{ route('dashboard.vehicle.index') }}";
                            $.ajaxSetup({
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                            $.ajax({
                                type: "POST",
                                url: url,
                                data: {type: type, vehicle_id: id},
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
                                        console.log(response.data);
                                        $.each(response.data, function (key, value) {
                                            console.log(key + ' - ' + value);
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
                                '<input type="text" class="form-control" name="new_property" placeholder="New ' + type + '" required>\n' +
                                '</div>');
                            $(confirmModal).modal('show');
                        }
                    });
                    $(confirmModal).modal('show');
                    return false;
                });

                $('.openTypeModal').click(function (e) {
                    e.defaultPrevented;
                    var typeModal = $('#typeModal');
                    $(typeModal).find('form#typeForm').trigger("reset");
                    let type = $(this).attr('data-type');
                    $(typeModal).find('.modal-title').html('Add ' + type + ' type');
                    $(typeModal).find('[name="type"]').val($(this).attr('data-type'));
                    $(typeModal).modal("show");
                    return false;
                });

                $('#typeForm').submit(function (e) {
                    e.defaultPrevented;
                    var data = $(this).serialize();
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    $.ajax({
                        type: "POST",
                        url: "{{route('dashboard.cabintype.store')}}",
                        data: data,
                        dataType: 'json',
                        success: function (data) {
                            if (data.status == true) {
                                $('#typeModal').modal('hide');
                                var $option = $("<option/>", {
                                    value: data.item.id,
                                    text: data.item.name,
                                    selected: true
                                });

                                if (data.item.type == 'seat') {
                                    $('#seatTypes').append($option);
                                    $("#seatTypes").val(data.item.id);
                                } else {
                                    $('#cabinTypes').append($option);
                                    $("#cabinTypes").val(data.item.id);
                                }
                            }

                            Toast.fire({
                                icon: data.label,
                                title: data.content
                            });
                        },
                        error: function (jqXHR, status, err) {
                            Toast.fire({
                                icon: data.label,
                                title: data.content
                            });
                        }
                    });

                    return false;
                });

                $('#deckRouteSelect').change(function (e) {
                    e.defaultPrevented;
                    let id = $(this).val();

                    if (id) {
                        $.ajaxSetup({
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            }
                        });
                        $.ajax({
                            type: "POST",
                            url: "{{route('dashboard.route.properties')}}",
                            data: {route_id: id},
                            dataType: 'json',
                            success: function (data) {
                                if (data.status == true) {
                                    $('#deckDepartureFrom').html("");
                                    $('#deckDepartureTo').html("");
                                    for (let i = 0; i < data.items.length - 1; i++) {
                                        var $option = $("<option/>", {
                                            value: data.items[i].id,
                                            text: data.items[i].name
                                        });

                                        $('#deckDepartureFrom').append($option);
                                    }

                                    for (let i = 1; i < data.items.length; i++) {
                                        var $option = $("<option/>", {
                                            value: data.items[i].id,
                                            text: data.items[i].name
                                        });

                                        $('#deckDepartureTo').append($option);
                                    }
                                }
                            },
                            error: function (jqXHR, status, err) {
                                Toast.fire({
                                    icon: data.label,
                                    title: data.content
                                });
                            }
                        });
                    }
                    return false;
                });

                //Initialize Select2 Elements
                $('.select2').select2();

                //Initialize Select2 Elements
                $('.select2bs4').select2({
                    theme: 'bootstrap4'
                });
                $('.datepicker').datepicker({
                    format: 'dd/mm/yyyy',
                    todayHighlight: 'TRUE',
                    autoclose: true,
                    endDate: "+30d"
                }).on('changeDate', function (ev) {
                    $(this).datepicker('hide');
                });
                $('.schedulepicker').datepicker({
                    format: 'dd/mm/yyyy',
                    todayHighlight: 'TRUE',
                    autoclose: true,
                    startDate: "0d",
                    endDate: "+30d"
                }).on('changeDate', function (ev) {
                    $(this).datepicker('hide');
                });

                //Timepicker
                $('#timepicker').datetimepicker({
                    format: 'LT',
                    autoclose: true
                });

                //Datemask dd/mm/yyyy
                $('#datemask').inputmask('dd/mm/yyyy', {'placeholder': 'dd/mm/yyyy'});
                //Datemask2 mm/dd/yyyy
                $('#datemask2').inputmask('mm/dd/yyyy', {'placeholder': 'mm/dd/yyyy'});
                //Money Euro
                $('[data-mask]').inputmask();
                //Timepicker

                $('#filterRoutes, #filterRoutes2, #filterRoutes3').select2({
                    placeholder: "Pick some items",
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

                $('.schedule-action').click(function () {
                    var url = "{{ route('dashboard.schedule.action') }}";
                    var action = $(this).data('action');
                    var id = $(this).data('schedule-id');
                    if (action) {
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
                                        response = JSON.parse(response);
                                        if (response.status == true) {
                                            // Toast.fire({
                                            //     icon: response.label,
                                            //     title: response.content
                                            // });
                                            Swal.fire({
                                                title: 'Success',
                                                text: response.content + '. You want reload page?',
                                                icon: 'success',
                                                showCancelButton: true,
                                                confirmButtonColor: '#3085d6',
                                                cancelButtonColor: '#d33',
                                                confirmButtonText: 'Yes'
                                            }).then((result) => {
                                                if (result.value) {
                                                    window.location = reload();
                                                }
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

                $('#reportFilterForm').submit(function (e) {
                    e.defaultPrevented;

                    let data = $(this).serialize();

                    vehicleStatistics(data);
                    loadChartData(data);

                    return false;
                });

                $('#officersReportFilterForm').submit(function (e) {
                    e.defaultPrevented;
                    let data = $(this).serialize();
                    vehicleOfficerStatistics(data);
                    return false;
                })

                //vehicle statistics table
                function vehicleStatistics(params) {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    $.ajax({
                        url: "{{ route('dashboard.vehicle.scheduleStat', $vehicle->id)}}",
                        type: 'post',
                        data: params,
                        dataType: "html",
                        success: function (response) {
                            var html = $.parseHTML(response);
                            $('#vehicleStatistics').html(response);
                        }
                    });
                }

                function vehicleOfficerStatistics(params) {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    $.ajax({
                        url: "{{ route('dashboard.vehicle.officerReport', $vehicle->id)}}",
                        type: 'post',
                        data: params,
                        dataType: "html",
                        success: function (response) {
                            var html = $.parseHTML(response);
                            $('#vehicleOfficersStatistics').html(response);
                        }
                    });
                }

                let data1 = [];
                let data2 = [];
                let data3 = [];
                let category1 = [];
                let category2 = [];
                let category3 = [];
                let categories = [];

                function initChart() {
                    var chart = new Highcharts.Chart({
                        chart: {
                            renderTo: "container",
                            type: "column"
                        },
                        title: {
                            useHTML: true,
                            x: -10,
                            y: 8,
                            text: 'Daily booking graph'
                        },
                        series: [{
                            name: 'Cabin',
                            data: data1
                        }, {
                            name: 'Seat',
                            data: data2
                        }, {
                            name: 'Deck',
                            data: data3
                        }],
                        xAxis: {
                            categories: categories
                            // categories: [{
                            //     name: "Dhaka-Barisal",
                            //     categories: category1
                            // }, {
                            //     name: "Dhaka-Charfassion",
                            //     categories: category2
                            // }, {
                            //     name: "1st Term - 2nd Term",
                            //     categories: category3
                            // }]
                        },
                        yAxis: {
                            min: 0,
                            title: {
                                text: 'Total booking comparison'
                            },
                            stackLabels: {
                                enabled: true,
                                style: {
                                    fontWeight: 'bold',
                                    color: ( // theme
                                        Highcharts.defaultOptions.title.style &&
                                        Highcharts.defaultOptions.title.style.color
                                    ) || 'gray'
                                }
                            }
                        },
                        legend: {
                            align: 'right',
                            x: -30,
                            verticalAlign: 'top',
                            y: 25,
                            floating: true,
                            backgroundColor:
                                Highcharts.defaultOptions.legend.backgroundColor || 'white',
                            borderColor: '#CCC',
                            borderWidth: 1,
                            shadow: false
                        },
                        tooltip: {
                            headerFormat: '<b>{point.x}</b><br/>',
                            pointFormat: '{series.name}: {point.y}<br/>Total: {point.stackTotal}'
                        },
                        plotOptions: {
                            column: {
                                stacking: 'normal',
                                dataLabels: {
                                    enabled: true
                                }
                            }
                        },

                    });
                }

                function loadChartData(params) {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    $.ajax({
                        url: "{{ route('dashboard.vehicle.scheduleChart', $vehicle->id)}}",
                        type: 'post',
                        data: params,
                        dataType: "json",
                        success: function (response) {
                            if (response.status == true) {
                                categories = JSON.parse(JSON.stringify(response.categories));
                                data1 = JSON.parse(JSON.stringify(response.series.cabin));
                                data2 = JSON.parse(JSON.stringify(response.series.seat));
                                data3 = JSON.parse(JSON.stringify(response.series.deck));

                                initChart();
                            }
                        }
                    });
                }

                loadChartData(null);
                vehicleStatistics(null);
                vehicleOfficerStatistics(null);
            });

            function toggleRow(_this) {
                let parent = $(_this).parents('table');
                let id = $(_this).data('id');
                let icon = $(_this).find('i.fa');
                if (icon.hasClass('fa-plus')) {
                    $(icon).addClass('fa-minus').removeClass('fa-plus');
                } else {
                    $(icon).addClass('fa-plus').removeClass('fa-minus');
                }
                $(parent).find('.collapse-' + id).each(function (e) {
                    $(this).toggleClass('d-none');
                });
            }
        </script>
    @endverbatim
@endsection
