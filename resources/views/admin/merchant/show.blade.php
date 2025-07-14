@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'profile') ? 'active': ''; @endphp"
                   id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Info</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'vehicle') ? 'active': ''; @endphp"
                   id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile"
                   aria-selected="false">vehicles</a>
            </li>
            @if(auth()->user()->hasAnyPermission(['merchant-statistics', 'merchants-statistics']) || auth()->user()->hasAnyRole(['admin', 'merchant']))
                <li class="nav-item">
                    <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'stat') ? 'active': ''; @endphp"
                       id="contact-tab" data-toggle="tab" href="#contact" role="tab" aria-controls="contact"
                       aria-selected="false">Statistics</a>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div
                class="tab-pane fade show @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'profile') ? 'active show': ''; @endphp"
                id="home" role="tabpanel" aria-labelledby="home-tab">
                <div class="row">
                    <div class="col-12 col-md-12 col-lg-8 order-2 order-md-1">

                        <div class="row">
                            <div class="col-12">
                                <div id='calendar'></div>

                                <div style='clear:both'></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-12 col-lg-4 order-1 order-md-2">
                        <h3 class="text-secondary"> {{ $merchant->merchant_name }} @can('merchant-edit')
                                <a
                                    href="{{ route('dashboard.merchant.edit', $merchant->id ) }}"><i
                                        class="fa fa-edit"></i></a>
                            @endcan
                        </h3>
                        <hr>
                        <div class="profile-userpic">
                            <img src="{{ asset('images/' . $merchant->logo )}}" alt="logo">
                        </div>
                        <br>
                        <div class="text-muted">
                            <p class="text-sm">Merchant Registration No.
                                <b class="d-block">{{ $merchant->merchant_reg_no }}</b>
                            </p>
                            <p class="text-sm">Registration Expiry Date
                                <b class="d-block">{{ ( $merchant->merchant_reg_expiry_date ) ? date('d/m/Y', strtotime( $merchant->merchant_reg_expiry_date ) ) : '-- -- ----' }}</b>
                            </p>
                            <hr>
                            <p class="text-sm">Vat policy (Vat Applicable to)
                                <b class="d-block">{{ ucfirst($merchant->vat_applicable_to ) }}
                                    ({{ getOption('vat_amount') }})%</b>
                            </p>
                            <hr>
                            <p class="text-sm">Honorium service charge
                                <b class="d-block">{{ ucfirst($merchant->honorium_service_charge ) }}%</b>
                            </p>
                        </div>

                        <h5 class="mt-5 text-muted">Contact info</h5>
                        <ul class="list-unstyled">
                            <li>
                                <a href="" class="btn-link text-secondary"><i
                                        class="fas fa-fw fa-mobile"></i> {{ $merchant->merchant_mobile }}</a>
                            </li>
                            <li>
                                <a href="" class="btn-link text-secondary"><i
                                        class="fas fa-fw fa-phone"></i> {{ $merchant->merchant_phone }}</a>
                            </li>
                            <li>
                                <a href="" class="btn-link text-secondary"><i
                                        class="fas fa-fw fa-envelope"></i> {{ $merchant->merchant_email }}</a>
                            </li>
                            <li>
                                <a href="" class="btn-link text-secondary"><i
                                        class="fas fa-fw fa-printer"></i> {{ $merchant->merchant_fax }}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'vehicle') ? 'active show': ''; @endphp"
                id="profile" role="tabpanel" aria-labelledby="profile-tab">
                <div class="row">
                    <div class="col-12 col-md-12 col-lg-8 order-2 order-md-1">

                        <div class="row">
                            <div class="col-12">
                                <table class="table table-striped projects" id="dataTable">
                                    <thead>
                                    <tr>
                                        <th style="width: 1%"> #</th>
                                        <th style="width: 8%"><i class="fas fa-image"></i></th>
                                        <th> Name</th>
                                        <th> Type</th>
                                        <th style="width: 20%"> Route</th>
                                        <th style="width:5%"> Cabins</th>
                                        <th style="width:5%"> Seats</th>
                                        <th style="width:5%"> Decks</th>
                                        <th style="width: 8%" class="text-center"> Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>

                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-12 col-lg-4 order-1 order-md-2">
                        <h3 class="text-secondary"><i class="fas fa-plus"></i> Add new vehicle</h3>
                        <hr>
                        <form action="{{ route('dashboard.vehicle.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="merchant_id" value="{{ $merchant->user_id }}">
                            <input type="hidden" name="tab" value="vehicle">
                            <div class="form-group">
                                <label for="routeTypeVal">Service type</label>
                                <select name="vehicle_type"
                                        class="form-control @error('vehicle_type') is-invalid @enderror"
                                        id="serviceTypes" required>
                                    <option value="">Select type</option>
                                    @foreach($service_list as $key => $value)
                                        <option value="{{ $key }}"
                                                @if(old('service_type') == $key) selected @endif>{{ $value }}</option>
                                    @endforeach
                                </select>
                                @error('vehicle_type')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>vehicle Name</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       placeholder="vehicle name" value="{{ old('name') }}" required>
                                @error('name')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Route</label> <a href="{{ route('dashboard.routes.create') }}"
                                                        data-toggle="modal" data-target="#routeModal"><i
                                        class="fas fa-plus"></i> add new</a>
                                <select name="route_id" id="route_id"
                                        class="form-control @error('route_id') is-invalid @enderror"
                                        value="{{ old('route_id') }}" required>
                                    <option value="">Select Route</option>
                                    @if( $routes )
                                        @foreach( $routes as $route )
                                            <option value="{{ $route->id }}"
                                                    @if(old('route_id') == $route->id) selected @endif>{{ $route->route_name }}</option>
                                        @endforeach
                                    @endif
                                </select>

                                @error('route_id')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>


                            <div class="form-group">
                                <label>Reg. No.</label>
                                <input type="text" name="registration_no"
                                       class="form-control @error('registration_no') is-invalid @enderror"
                                       placeholder="Registration no." value="{{ old('registration_no') }}" required>
                                @error('registration_no')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Registration expiration date</label>
                                <div class="input-group">
                                    <input type="text" name="registration_expiry_date"
                                           value="{{ old('registration_expiry_date') }}"
                                           class="form-control datepicker @error('registration_expiry_date') is-invalid @enderror"
                                           data-inputmask-alias="datetime" data-inputmask-inputformat="dd/mm/yyyy"
                                           data-mask required>
                                    <div class="input-group-addon">
                                        <span class="glyphicon glyphicon-th"></span>
                                    </div>
                                </div>
                                @error('registration_expiry_date')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Fitness expiry date</label>
                                <div class="input-group">
                                    <input type="text" name="fitness_expiry_date"
                                           value="{{ old('fitness_expiry_date') }}"
                                           class="form-control datepicker @error('fitness_expiry_date') is-invalid @enderror"
                                           data-inputmask-alias="datetime" data-inputmask-inputformat="dd/mm/yyyy"
                                           data-mask required>
                                    <div class="input-group-addon">
                                        <span class="glyphicon glyphicon-th"></span>
                                    </div>
                                </div>
                                @error('fitness_expiry_date')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group d-none" id="deckFareParent">
                                <label>Deck Passengers capacity</label>
                                <input type="text" name="passengers_capacity"
                                       class="form-control @error('passengers_capacity') is-invalid @enderror"
                                       value="{{ old('passengers_capacity', 0) }}">
                                @error('passengers_capacity')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Number of Floors?</label>
                                <select class="form-control" name="number_of_floor">
                                    @for($i = 1; $i <= 10; $i++)
                                        <option value="{{ $i }}"
                                                @if(old('number_of_floor') === $i) selected @endif>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Default Floor?</label>
                                <select class="form-control" name="default_floor">
                                    @for($i = 1; $i <= 10; $i++)
                                        <option value="{{ $i }}"
                                                @if(old('default_floor') === $i) selected @endif>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Default tab?</label>
                                <select class="form-control" name="default_tab">
                                    <option value="cabin" @if(old('default_tab') == 'cabin') selected @endif>Cabin
                                    </option>
                                    <option value="seat" @if(old('default_tab') == 'seat') selected @endif>Seat</option>
                                    <option value="deck" @if(old('default_tab') == 'deck') selected @endif>Deck</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>AC Available?</label>
                                <select class="form-control" name="ac_available">
                                    <option value="0" @if(old('ac_available') == 0) selected @endif>No</option>
                                    <option value="1" @if(old('ac_available') == 1) selected @endif>Yes</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>NID Verification required?</label>
                                <select class="form-control" name="nid_verification_check">
                                    <option value="0" @if(old('nid_verification_check') == 0) selected @endif>No
                                    </option>
                                    <option value="1" @if(old('nid_verification_check') == 1) selected @endif>Yes
                                    </option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Upload photo</label>
                                <input type="file" name="photo" class="form-control-file">
                            </div>
                            <div class="form-group">
                                <button class="btn btn-block btn-primary" type="submit">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'stat') ? 'active show': ''; @endphp"
                id="contact" role="tabpanel" aria-labelledby="contact-tab">
                <!-- <div class="row mt-3">
            <?php
                                    $totalvehicle = $merchant->vehicles->count();
                                    $totalCabins = 0;
                                    $totalSeats = 0;
                                    $totalDecks = 0;
                                    if ($merchant->vehicles) {
                                        foreach ($merchant->vehicles as $vehicle) {
                                            $totalCabins += $vehicle->cabins->count();
                                            $totalSeats += $vehicle->seats->count();
                                            $totalDecks += $vehicle->passengers_capacity;
                                        }
                                    }
                                    ?>
                    <div class="col-12 col-sm-6 col-md-3">
                      <div class="info-box mb-3">
                        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-ship"></i></span>

                        <div class="info-box-content">
                          <span class="info-box-text">vehicles</span>
                          <span class="info-box-number">{{ $totalvehicle }}</span>
                </div>
              </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
              <div class="info-box">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-bed"></i></span>

                <div class="info-box-content">
                  <span class="info-box-text">Cabins</span>
                  <span class="info-box-number">
                    {{ $totalCabins }}
                </span>
              </div>
            </div>
          </div>

          <div class="clearfix hidden-md-up"></div>

          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
              <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-chair"></i></span>

              <div class="info-box-content">
                <span class="info-box-text">Seats</span>
                <span class="info-box-number">{{ $totalSeats }}</span>
                </div>
              </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
              <div class="info-box mb-3">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-users"></i></span>

                <div class="info-box-content">
                  <span class="info-box-text">Deck tickets</span>
                  <span class="info-box-number">{{ $totalDecks }}</span>
                </div>
              </div>
            </div>
          </div> -->
                <form action="" id="reportFilterForm" method="GET">
                    <div class="row mt-3">
                        <div class="col-sm-3">
                            <select class="form-control" name="route_id" id="filterRoutes" id="items"
                                    data-placeholder="Select route" data-dropdown-css-class="select2-purple"
                                    style="width: 100%;"></select>
                        </div>
                        <div class="col-sm-3">
                            <select class="form-control" name="type" id="filterStatus">
                                <option value="" selected="selected">All party</option>
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
                    <div id="vehicleStatistics" class="p-3" style="width:100%">
                        <h2>Account: All ({{ date('01/m/Y') }} to {{ date('t/m/Y') }})</h2>
                        <table class="table table-striped table-bordered">
                            <thead>
                            <tr>
                                <th></th>
                                <th>vehicle</th>
                                <th>No of Routes</th>
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
                    <div id="routeStatistics" class="p-3 d-none" style="width:100%"></div>
                    <div id="scheduleStatistics" class="p-3 d-none" style="width:100%"></div>
                </div>
            </div>
        </div>
    </section>
    <!-- /.content -->

    <!-- Modal -->
    <div class="modal fade" id="routeModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
         aria-hidden="true">
        <div class="modal-dialog card" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">Add new route</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="card-body">

                    <form id="routeForm" method="POST" action="{{ route('dashboard.routes.store')}}">
                        @csrf
                        <div class="row">
                            <div class="col-sm-4">
                                <!-- text input -->
                                <div class="form-group">
                                    <label>Route name</label>
                                    <input type="text" id="routeNameVal" name="route_name" id="route_name"
                                           class="form-control @error('route_name') is-invalid @enderror"
                                           placeholder="Route name" required readonly>
                                    @error('route_name')
                                    <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                              </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label>Route No.</label>
                                    <input type="text" name="route_no" id="route_no"
                                           class="form-control @error('route_no') is-invalid @enderror" required>
                                    @error('route_no')
                                    <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                              </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label>Route Type</label>
                                    <select name="route_type" id="route_type"
                                            class="form-control  @error('route_type') is-invalid @enderror" required>
                                        <option value="direct">Direct</option>
                                        <option value="local">Local</option>
                                    </select>
                                    @error('route_type')
                                    <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                              </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                        <hr>
                        <h4>Boarding points (Ghat) <span class="badge badge-success pull-right float-right"
                                                         id="addProperty"><i class="fa fa-plus"></i> Add new</span></h4>
                        <hr>
                        <div class="row">
                            <div class="col-sm-12" id="buildyourform">
                                <div class="row fieldwrapper" id="field1">
                                    <div class="col-md-5 form-group">
                                        <div class="select2-purple">
                                            <select name="property_name[]" class="select2" id="starting"
                                                    data-placeholder="Select ghat"
                                                    data-dropdown-css-class="select2-purple" style="width: 100%;"
                                                    required>

                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <select class="fieldname form-control" name="property_type[]">
                                            <option value="start">Starting point</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <input type="number" step="1" class="fieldname form-control"
                                               name="property_position[]" value="1" readonly>
                                    </div>
                                </div>
                                <div class="row fieldwrapper" id="field2">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <div class="select2-purple">
                                                <select name="property_name[]" class="select2" id="ending"
                                                        data-placeholder="Select ghat"
                                                        data-dropdown-css-class="select2-purple" style="width: 100%;">

                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <select class="fieldname form-control" name="property_type[]">
                                            <option value="end">Ending point</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <input type="number" step="1" id="endingPointPosition"
                                               class="fieldname form-control"
                                               name="property_position[]" min="2" max="20" minlength="1"
                                               maxlength="2" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="form-group">
                            <button type="submit" class="btn btn-lg btn-primary">Create route</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal End -->
@endsection

@section('header')
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
    <link href="{{ asset('assets/plugins/fullcalendar/assets/css/fullcalendar.css') }}" rel='stylesheet'/>
    <link href="{{ asset('assets/plugins/fullcalendar/assets/css/fullcalendar.print.css') }}" rel='stylesheet'
          media='print'/>
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <style type="text/css">
        /***
      User Profile Sidebar by @keenthemes
      A component of Metronic Theme - #1 Selling Bootstrap 3 Admin Theme in Themeforest: https://j.mp/metronictheme
      Licensed under MIT
      ***/

        body {
            background: #F1F3FA;
        }

        .nav-tabs .nav-item {
            margin-right: 8px;
        }

        .nav-tabs .nav-link {
            border-top-left-radius: .25rem;
            border-top-right-radius: .25rem;
            border: 1px solid #eee;
            background: #e4e2e2;
            color: #000;
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

        #external-events {
            float: left;
            width: 150px;
            padding: 0 10px;
            text-align: left;
        }

        #external-events h4 {
            font-size: 16px;
            margin-top: 0;
            padding-top: 1em;
        }

        .external-event { /* try to mimick the look of a real event */
            margin: 10px 0;
            padding: 2px 4px;
            background: #3366CC;
            color: #fff;
            font-size: .85em;
            cursor: pointer;
        }

        #external-events p {
            margin: 1.5em 0;
            font-size: 11px;
            color: #666;
        }

        #external-events p input {
            margin: 0;
            vertical-align: middle;
        }

        #calendar {
            margin: 0 auto;
            width: 100%;
            background-color: #FFFFFF;
            border-radius: 6px;
            border: 1px solid #eee;
            /*box-shadow: 0 1px 2px #C3C3C3;
            -webkit-box-shadow: 0px 0px 21px 2px rgba(0,0,0,0.18);
            -moz-box-shadow: 0px 0px 21px 2px rgba(0,0,0,0.18);
            box-shadow: 0px 0px 21px 2px rgba(0,0,0,0.18);*/
        }


    </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/fullcalendar/assets/js/fullcalendar.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/plugins/printjs/print.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/0.9.0rc1/jspdf.min.js"></script>
    @verbatim
        <script>
            let can_edit = false, can_active = false, can_inactive = false, can_delete = false;
            @can('vehicle-edit')
                can_edit = true;
            @endcan
                @can('vehicle-active')
                can_active = true;
            @endcan
                @can('vehicle-inactive')
                can_inactive = true;
            @endcan
                @can('vehicle-delete')
                can_delete = true;
            @endcan
            let pdf = new jsPDF();
            let specialElementHandlers = {
                '#editor': function (element, renderer) {
                    return true;
                }
            };

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
                pdf.fromHTML($(elem).html(), 15, 15, {
                    'width': 170,
                    'elementHandlers': specialElementHandlers
                });
                pdf.save('vehicle_statistics.pdf');
            }

            var url = "{{ route('dashboard.merchant.vehicles', $merchant->user_id) }}";
            //
            // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
            //
            $.fn.dataTable.ext.classes.sPageButton = 'page-item';
            let service_type = 'launch';
            $(function () {
                $('#serviceTypes').change(function () {
                    if ($(this).val() !== '') {
                        service_type = $(this).val();
                    }

                    if (service_type === 'launch') {
                        $('#deckFareParent').removeClass('d-none');
                    } else {
                        $('#deckFareParent').addClass('d-none');
                    }
                });

                $('#route_id').select2({
                    placeholder: "Select route",
                    allowClear: true,
                    cache: false,
                    theme: 'bootstrap4',
                    ajax: {
                        url: "{{ route('dashboard.routes.suggest') }}",
                        dataType: 'json',
                        type: "GET",
                        quietMillis: 50,
                        data: function (term) {
                            return {
                                term: term.term,
                                service_type: service_type
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

                var date = new Date();
                var d = date.getDate();
                var m = date.getMonth();
                var y = date.getFullYear();

                /*  className colors

                className: default(transparent), important(red), chill(pink), success(green), info(blue)

                */


                /* initialize the external events
                -----------------------------------------------------------------*/

                $('#external-events div.external-event').each(function () {

                    // create an Event Object (https://arshaw.com/fullcalendar/docs/event_data/Event_Object/)
                    // it doesn't need to have a start or end
                    var eventObject = {
                        title: $.trim($(this).text()) // use the element's text as the event title
                    };

                    // store the Event Object in the DOM element so we can get to it later
                    $(this).data('eventObject', eventObject);

                    // make the event draggable using jQuery UI
                    $(this).draggable({
                        zIndex: 999,
                        revert: true,      // will cause the event to go back to its
                        revertDuration: 0  //  original position after the drag
                    });

                });


                /* initialize the calendar
                -----------------------------------------------------------------*/

                var calendar = $('#calendar').fullCalendar({
                    header: {
                        left: 'title',
                        center: 'agendaDay,agendaWeek,month',
                        right: 'prev,next today'
                    },
                    editable: false,
                    firstDay: 1, //  1(Monday) this can be changed to 0(Sunday) for the USA system
                    selectable: true,
                    defaultView: 'month',
                    axisFormat: 'h:mm',
                    columnFormat: {
                        month: 'ddd',    // Mon
                        week: 'ddd d', // Mon 7
                        day: 'dddd M/d',  // Monday 9/7
                        agendaDay: 'dddd d'
                    },
                    titleFormat: {
                        month: 'MMMM yyyy', // September 2009
                        week: "MMMM yyyy", // September 2009
                        day: 'MMMM yyyy'                  // Tuesday, Sep 8, 2009
                    },
                    allDaySlot: false,
                    selectHelper: false,
                    droppable: false, // this allows things to be dropped onto the calendar !!!

                    events:
                        {!! json_encode($schedules) !!}




                /*
               /* events: [
                          {
                              title: 'Test',
                              start: "2020-05-12"
                          },
                          {
                              id: 999,
                              title: 'reaz',
                              start: "2020-05-12 16:10:10",
                              allDay: false,
                              className: 'info'
                          },
                          {
                              id: 999,
                              title: 'Repeating Event',
                              start: new Date(y, m, d+4, 16, 0),
                              allDay: false,
                              className: 'info'
                          },
                          {
                              title: 'Meeting',
                              start: new Date(y, m, d, 10, 30),
                              allDay: false,
                              className: 'important'
                          },
                          {
                              title: 'Lunch',
                              start: new Date(y, m, d, 12, 0),
                              end: new Date(y, m, d, 14, 0),
                              allDay: false,
                              className: 'important'
                          },
                          {
                              title: 'Birthday Party',
                              start: new Date(y, m, d+1, 19, 0),
                              end: new Date(y, m, d+1, 22, 30),
                              allDay: false,
                          },
                          {
                              title: 'Click for Google',
                              start: new Date(y, m, 28),
                              end: new Date(y, m, 29),
                              url: 'https://google.com/',
                              className: 'success'
                          }
                      ],

                */

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
                var customFilter = $('#customFilters');
                var keyword = $(customFilter).find('input#keywords');
                var area = $(customFilter).find('select#area');
                var package = $(customFilter).find('select#package');
                var status = $(customFilter).find('select#status');
                var search = $(customFilter).find('button#search');
                var table = $('#dataTable').DataTable({
                    "processing": true,
                    "serverSide": true,
                    "deferRender": true,
                    "bAutoWidth": false,
                    "sPageButtonActive": "active",
                    "ajax": {
                        'url': url,
                        pages: 5, // number of pages to cache
                        'data': function (data) {
                            // Read values
                            data.keyword = $(keyword).val();
                            data.area = $(area).val();
                            data.package = $(package).val();
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
                    "searching": true,
                    "columns": [
                        {"data": "id"},
                        {
                            "mRender": function (data, type, row) {
                                return '<img src="' + row['photo'] + '" class="table-avatar">';
                            }
                        },
                        {
                            "mRender": function (data, type, row) {
                                return '<a href="/admin/vehicle/show/' + row['id'] + '" class="table-avatar">' + row['name'] + '</a>';
                            }
                        },
                        {"data": "vehicle_type"},
                        {"data": "route"},
                        {"data": "cabins"},
                        {"data": "seats"},
                        {
                            "mRender": function (data, type, row) {
                                return (row['vehicle_type'] == 'launch') ? row['capacity'] : '--';
                            }
                        },
                        {
                            "mRender": function (data, type, row) {
                                var str = "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'> <a href='/admin/vehicle/show/" + row['id'] + "' class='dropdown-item' data-vehicle-id='" + row['id'] + "'><i class='fa fa-eye'></i> View</a> <a href='/admin/vehicle/edit/" + row['id'] + "' class='dropdown-item'><i class='fa fa-edit'></i> Edit</a>";
                                if (parseInt(row['status']) == 1) {
                                    if (can_inactive) {
                                        str += "<a href='#' class='dropdown-item vehicle-action' data-action='inactive' data-vehicle-id='" + row['id'] + "'><i class='fa fa-ban'></i> Inactive</a>";
                                    }
                                } else if (parseInt(row['status']) == 0) {
                                    if (can_active) {
                                        str += "<a href='#' class='dropdown-item vehicle-action' data-action='active' data-vehicle-id='" + row['id'] + "'><i class='fa fa-check'></i> Active</a>";
                                    }
                                } else {
                                    if (can_active) {
                                        str += "<a href='#' class='dropdown-item vehicle-action' data-action='reactive' data-vehicle-id='" + row['id'] + "'><i class='fa fa-check'></i> Re-active</a>";
                                    }
                                }
                                if (row['deleted_at'] == '') {
                                    if (can_delete) {
                                        str += "<a href='#' class='dropdown-item vehicle-action' data-action='softdelete' data-vehicle-id='" + row['id'] + "'><i class='fa fa-times'></i> Delete</a>";
                                    }
                                } else {
                                    str += "<a href='#' class='dropdown-item vehicle-action' data-action='delete' data-vehicle-id='" + row['id'] + "'><i class='fa fa-times'></i> Delete</a>";
                                }
                                str += "</div> </div>";
                                return str;
                            }
                        }
                    ],
                    "columnDefs": [
                        {"targets": [0, 1, 5], "searchable": false, "orderable": false, "visible": true}
                    ],
                    "order": [[2, 'asc']],
                    buttons: [
                        'copy', 'excel', 'pdf', 'print'
                    ],

                });

                //Click on Search Button
                $(search).click(function (e) {
                    table.draw();
                });

                //Custom Filters ( title search )
                $(keyword).keyup(function (event) {
                    var keycode = (event.keyCode ? event.keyCode : event.which);
                    // if(keycode == '13'){
                    table.draw();
                    // }
                });

                //Custom Filters ( Author search )
                $(area).change(function () {
                    // var keycode = (event.keyCode ? event.keyCode : event.which);
                    table.draw();
                });

                //Custom Filters ( Author search )
                $(package).change(function () {
                    // var keycode = (event.keyCode ? event.keyCode : event.which);
                    table.draw();
                });

                //Custom Filters ( Author search )
                $(status).change(function () {
                    table.draw();
                });

                // $('#myModal').modal('show');
                $('table').on('click', '.merchant-action', function () {
                    console.log(this);
                    var url = "{{ route('dashboard.merchant.index') }}";
                    var action = $(this).data('action');
                    var id = $(this).data('merchant-id');
                    if (action == 'request') {

                    } else {
                        var data = {action: action, id: id};
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "You are going to " + action + " this merchant account.",
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

                $('#filterRoutes').select2({
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
                                term: term.term,
                                service_type: service_type
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

                $('#reportFilterForm').submit(function (e) {
                    e.defaultPrevented;

                    let data = $(this).serialize();

                    vehicleStatistics(data);
                    $('#routeStatistics').addClass('d-none');
                    $('#scheduleStatistics').addClass('d-none');

                    return false;
                });

                //vehicle statistics table
                function vehicleStatistics($params) {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'Accept': '*'
                        }
                    });
                    $.ajax({
                        url: "{{ route('merchant.dashboard.vehicleStat', $merchant->user_id)}}",
                        type: 'post',
                        data: $params,
                        success: function (response) {
                            var html = $.parseHTML(response);
                            $('#vehicleStatistics').html(response);
                        }
                    });
                }

                vehicleStatistics(null);
                $('.datepicker').click(function (e) {
                    $(this).val("");
                });

                $('.datepicker').datepicker({
                    format: 'dd/mm/yyyy',
                    todayHighlight: 'TRUE',
                    autoclose: true,
                }).on('changeDate', function (ev) {
                    $(this).datepicker('hide');
                });
            });

            function openStatToModal(_this) {
                let container = $('#routeStatistics');
                let vehicle_id = $(_this).data('id');
                let date_from = $(_this).data('date-from');
                let date_to = $(_this).data('date-to');
                let type = $(_this).data('type');
                let route_id = $(_this).data('route-id');
                routeStats({
                    vehicle_id: vehicle_id,
                    date_from: date_from,
                    date_to: date_to,
                    type: type,
                    route_id: route_id
                }, container);
                $('#scheduleStatistics').addClass('d-none');
                return false;
            }

            function scheduleStatModal(_this) {
                let container = $('#scheduleStatistics');
                let vehicle_id = $(_this).data('id');
                let date_from = $(_this).data('date-from');
                let date_to = $(_this).data('date-to');
                let type = $(_this).data('type');
                let route_id = $(_this).data('route-id');
                scheduleStats({
                    vehicle_id: vehicle_id,
                    date_from: date_from,
                    date_to: date_to,
                    type: type,
                    route_id: route_id
                }, container);
                return false;
            }

            //vehicle statistics table
            function routeStats($params, container) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': '*'
                    }
                });
                $.ajax({
                    url: "{{ route('merchant.dashboard.routeStat') }}",
                    type: 'post',
                    data: $params,
                    success: function (response) {
                        var html = $.parseHTML(response);
                        $(container).html(response).removeClass('d-none');
                    }
                });
            }

            //vehicle statistics table
            function scheduleStats($params, container) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': '*'
                    }
                });
                $.ajax({
                    url: "{{ route('merchant.dashboard.scheduleStat') }}",
                    type: 'post',
                    data: $params,
                    success: function (response) {
                        var html = $.parseHTML(response);
                        $(container).html(response).removeClass('d-none');
                    }
                });
            }

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

            /*Modal Route create script*/
            let Url = "{{ route('dashboard.ghat.suggest') }}";

            //Initialize Select2 Elements
            $(".select2").each(function () {
                initializeSelect2(this);
            });

            function initializeSelect2(select2) {
                $(select2).select2({
                    placeholder: "Select ghat",
                    allowClear: true,
                    cache: false,
                    theme: 'bootstrap4',
                    ajax: {
                        url: Url,
                        dataType: 'json',
                        type: "GET",
                        quietMillis: 50,
                        data: function (term) {
                            return {
                                term: term.term,
                                service_type: service_type
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
            };

            $('select#starting, select#ending').on("select2:select", function (e) {
                let starting = $('select#starting').val();
                let ending = $('select#ending').val();
                $.ajax({
                    url: "{{ route('dashboard.route.name') }}",
                    dataType: "json",
                    data: {starting: starting, ending: ending},
                    success: function (data) {
                        if (data.status == true) {
                            $('input#routeNameVal').val(data.route_name);
                        }
                    }
                });
            });

            var intId = 2;
            $("#addProperty").click(function () {
                var lastField = $("#buildyourform div:last");
                intId = parseFloat(intId) + 1;
                console.log(intId);
                var fieldWrapper = $("<div class='row fieldwrapper' id='field" + intId + "'/>");
                fieldWrapper.data("idx", intId);

                var field_name_wrapper = $('<div class="col-md-5 form-group">' +
                    '<div class="select2-purple">' +
                    '</div></div>');
                var select2 = $('<select name="property_name[]" class="select2" data-placeholder="Select ghat" data-dropdown-css-class="select2-purple" style="width: 100%;">' +
                    '</select>');

                var field_paginate = $("<div class='col-md-3 form-group'>" +
                    "<select class='fieldname form-control' name='property_type[]'>" +
                    "<option value='via'>Via</option>" +
                    "</select>" +
                    "</div>");

                let field_position_wrapper = $("\n" +
                    "<div class='col-md-3 form-group'>\n" +
                    "<input type='number' step='1' class='fieldname form-control' name='property_position[]' min='2' max='20' minlength='1' maxlength='2' required>\n" +
                    "</div>");

                var removeButton = $("<div class='col-md-1'>" +
                    "<button type='button' class='btn btn-danger remove'>" +
                    "<i class='fa fa-trash-o'></i>" +
                    "X</button>" +
                    "</div> </div>");

                removeButton.click(function (e) {
                    removeField(this);
                });
                fieldWrapper.append(field_name_wrapper);
                field_name_wrapper.append(select2);
                fieldWrapper.append(field_paginate);
                fieldWrapper.append(field_position_wrapper);
                fieldWrapper.append(removeButton);
                $("#buildyourform").append(fieldWrapper);
                initializeSelect2($(select2));
            });

            function removeField(_this) {
                var id = _this.getAttribute('data-id');
                if (id) {

                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    $.ajax({
                        url: '',
                        type: 'post',
                        data: {field_id: id},
                        success: function (data) {
                            if (data == 'true') {
                                var msg = '<div class="alert alert-success alert-dismissible">' +
                                    '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>' +
                                    '<strong>Successfully deleted field</strong>.' +
                                    '</div>';
                                $('#msg').html(msg);
                            }
                            //console.log(data);

                        }
                    });
                }
                var parent = $(_this).parents('.fieldwrapper');
                $(parent).remove();
            }

            $('#routeForm').submit(function (event) {
                event.defaultPrevented;
                var data = $(this).serialize();
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                $.ajax({
                    url: "{{ route('dashboard.routes.store') }}",
                    type: "POST",
                    data: data,
                    success: function (response) {
                        if (response.status == true) {
                            var $option = $("<option/>", {
                                value: response.route.id,
                                text: response.route.name
                            });
                            $('#route_id').append($option);
                            $('#route_id').val(response.route.id);
                            $('#routeModal').modal('hide');
                        }
                        Toast.fire({
                            icon: response.label,
                            title: response.content
                        });
                    },
                });
                return false;
            });
        </script>
    @endverbatim
@endsection
