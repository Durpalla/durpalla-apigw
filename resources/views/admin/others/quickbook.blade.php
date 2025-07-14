@extends('layouts.master')

@section('content')
    <?php $user = auth()->user(); ?>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <form role="form" action="{{ route('dashboard.quickbook') }}" method="GET"
                              id="quickbookSearchForm">
                            <div class="row">
                                <div class="col-sm-3">
                                    <select class="form-control select2bs4" name="departure_from"
                                            style="width: 100%;">
                                        <option value="" selected="selected">From</option>
                                        @foreach( $ghats as $key => $value )
                                            <option value="{{ $value }}"
                                                    @if($trip_from && $trip_from == $value ) selected @endif>{{ $value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <select class="form-control select2bs4" name="departure_to"
                                            style="width: 100%;">
                                        <option value="" selected="selected">To</option>
                                        @foreach( $ghats as $key => $value )
                                            <option value="{{ $value }}"
                                                    @if( $trip_to && $trip_to == $value ) selected @endif>{{ $value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-2">
                                    <select class="form-control" name="type" id="serviceDropdowns">
                                        @foreach($service_list as $key => $value)
                                            <option value="{{ $key }}"
                                                    @if($type == $key) selected @endif>{{ $value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                <span class="fa fa-calendar"></span>
                                            </div>
                                        </div>
                                        <input type="text" name="trip_date"
                                               value="{{ date('d/m/Y', strtotime($trip_date)) }}"
                                               class="form-control datepicker @error('trip_date', date('d/m/Y') ) is-invalid @enderror"
                                               data-inputmask-alias="datetime"
                                               data-inputmask-inputformat="dd/mm/yyyy" data-mask required>
                                        {{--                                        <div class="input-group-btn">--}}
                                        {{--                                            <button type="submit" class="btn btn-primary"><i--}}
                                        {{--                                                    class="fa fa-search"></i> Search trip--}}
                                        {{--                                            </button>--}}
                                        {{--                                        </div>--}}
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- /.card-header -->

                    <div class="row mt-3">
                        <div class="col-md-8 col-xs-7">
                            @if( $schedules )
                                @foreach( $schedules as $schedule )
                                    <div class="accordion mb-4" id="quickbookAccordion">
                                        <div class="row row-striped">
                                            <div class="col-2 text-center">
                                                <h1 class="display-4"><span
                                                        class="badge badge-secondary">{{ date('d', strtotime( $schedule['schedule_date'] ) ) }}</span>
                                                </h1>
                                                <h2 class="display-4">{{ date('M', strtotime( $schedule['schedule_date'] ) ) }}</h2>
                                            </div>
                                            <div class="col-7">
                                                <h3 class="text-uppercase">
                                                    <a href="{{ route('dashboard.vehicle.show', $schedule['vehicle_id'] ) }}"
                                                       target="ext">
                                                        <strong>{{ strtoupper( $schedule['vehicle_name'] ) }}</strong>
                                                    </a>
                                                </h3>
                                                <ul class="list-inline">
                                                    <li class="list-inline-item"><i class="fa fa-calendar-o"
                                                                                    aria-hidden="true"></i> {{ date('D', strtotime( $schedule['schedule_date'] ) ) }}
                                                    </li>
                                                    <li class="list-inline-item"><i class="fa fa-clock-o"
                                                                                    aria-hidden="true"></i> {{ date('h:i A', strtotime( $schedule['leaving_at'] ) ) }}
                                                    </li>
                                                    <li class="list-inline-item">
                                                        <i class="fa fa-route"></i>
                                                        {{ $schedule['route_name'] }}
                                                    </li>
                                                    <li class="list-inline-item"><i class="fa fa-location-arrow"
                                                                                    aria-hidden="true"></i>
                                                    </li>
                                                </ul>
                                                <p><em><b>Stoppages: </b></em>
                                                    @foreach( $schedule['stoppages'] as $stoppage)
                                                        <span class='badge badge-info'>{{ $stoppage['name'] }}</span>
                                                    @endforeach
                                                </p>
                                            </div>
                                            <div class="col-3 seat-cabin-stat">
                                                <b>Available now</b>
                                                <hr class="mt-1 mb-2"/>
                                                @if($schedule['service_type'] === 'launch')
                                                    <p class="mb-0"><i
                                                            class="fas fa-bed"></i> {{ $schedule['cabin_available'] }}
                                                        /{{ $schedule['total_cabins'] }} Cabin</p>
                                                @endif
                                                <p class="mb-0"><i
                                                        class="fas fa-chair"></i> {{ $schedule['seat_available'] }}
                                                    /{{ $schedule['total_seats'] }} Seat</p>
                                                @if($schedule['service_type'] === 'launch')
                                                    <p class="mb-0"><i
                                                            class="fas fa-ticket-alt"></i> {{ $schedule['total_tickets'] }}
                                                        /{{ $schedule['total_tickets'] }} Ticket</p>
                                                @endif
                                                <div
                                                    class="btn-group btn-block btn-group-block btn-group-sm text-center mt-2">
                                                    <a href="{{ route('dashboard.schedule.show', $schedule['trip_id'] ) }}"
                                                       class="btn btn-secondary"><i class="fa fa-chart-bar"></i>
                                                        Statistics</a>
                                                    <a href="#" class="btn btn-success openModal"
                                                       data-trip-id="{{$schedule['trip_id']}}" data-toggle="collapse"
                                                       data-target="#collapse{{$schedule['trip_id']}}"
                                                       aria-expanded="true"
                                                       data-floor="{{ $schedule['default_floor'] }}"
                                                       aria-controls="collapse{{$schedule['trip_id']}}">Book now</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="collapse{{$schedule['trip_id']}}" class="collapse"
                                             aria-labelledby="heading{{$schedule['trip_id']}}"
                                             data-parent="#quickbookAccordion">
                                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                                @if($schedule['service_type'] === 'launch')
                                                    <li class="nav-item">
                                                        <a class="nav-link active" id="cabin-tab" data-toggle="tab"
                                                           href="#cabin" role="tab" aria-controls="cabin"
                                                           aria-selected="true">Cabin</a>
                                                    </li>
                                                @endif
                                                <li class="nav-item">
                                                    <a class="nav-link @if($schedule['service_type'] !== 'launch') active @endif"
                                                       id="seat-tab" data-toggle="tab" href="#seat"
                                                       role="tab" aria-controls="seat"
                                                       aria-selected="false">Seat</a>
                                                </li>
                                                @if($schedule['service_type'] === 'launch')
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="deck-tab" data-toggle="tab" href="#deck"
                                                           role="tab" aria-controls="deck"
                                                           aria-selected="false">Deck</a>
                                                    </li>
                                                @endif
                                            </ul>
                                            <div class="tab-content" id="myTabContent">
                                                <div
                                                    class="tab-pane fade @if($schedule['service_type'] === 'launch') show active @endif"
                                                    id="cabin" role="tabpanel"
                                                    aria-labelledby="cabin-tab">
                                                    <div class="row quickbookParent">
                                                        <div class="col-sm-10">
                                                            <div class="cabinFilter">
                                                                <div class="row">
                                                                    <div class="col-sm-6">
                                                                        <div class="form-group">
                                                                            <label>Floor</label>
                                                                            <select class="form-control cabinFloor"
                                                                                    data-type="cabin"
                                                                                    data-trip-id="{{ $schedule['trip_id'] }}">
                                                                                <option value="">Select</option>
                                                                                <option value="1"
                                                                                        @if($schedule['service_type'] !== 'launch') selected @endif>
                                                                                    1st floor
                                                                                </option>
                                                                                @if($schedule['service_type'] === 'launch')
                                                                                    <option value="2" selected>2nd
                                                                                        floor
                                                                                    </option>
                                                                                    <option value="3">3rd floor</option>
                                                                                    <option value="4">4th floor</option>
                                                                                    <option value="5">5th floor</option>
                                                                                @endif
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <div class="form-group">
                                                                            <label>Cabin type</label>
                                                                            <select class="form-control cabinType"
                                                                                    data-type="cabin">
                                                                                <option value="">All</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-12">
                                                            <strong>Available now</strong>
                                                            <div class="availableCabins d-flex">
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-12">
                                                            <div class="cabinLayout">
                                                                <div class="d-flex flex-row align-self-stretch mb-3">
                                                                    ...
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div
                                                    class="tab-pane fade @if($schedule['service_type'] !== 'launch') show active @endif"
                                                    id="seat" role="tabpanel"
                                                    aria-labelledby="seat-tab">
                                                    <div class="row quickbookParent">
                                                        <div class="col-sm-10 seatFilter">
                                                            <div class="seatFilter">
                                                                <div class="row">
                                                                    <div class="col-sm-6">
                                                                        <div class="form-group">
                                                                            <label>Floor</label>
                                                                            <select class="form-control cabinFloor"
                                                                                    data-type="seat"
                                                                                    data-trip-id="{{ $schedule['trip_id'] }}">
                                                                                <option value="">Select</option>
                                                                                <option value="1"
                                                                                        @if($schedule['service_type'] !== 'launch') selected @endif>
                                                                                    1st floor
                                                                                </option>
                                                                                @if($schedule['service_type'] === 'launch')
                                                                                    <option value="2" selected>2nd
                                                                                        floor
                                                                                    </option>
                                                                                    <option value="3">3rd floor</option>
                                                                                    <option value="4">4th floor</option>
                                                                                    <option value="5">5th floor</option>
                                                                                @endif
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <div class="form-group">
                                                                            <label>Seat type</label>
                                                                            <select class="form-control seatType"
                                                                                    data-type="seat">
                                                                                <option value="">All</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-12">
                                                            <strong>Available now</strong>
                                                            <div class="availableSeats d-flex">
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-12">
                                                            <div class="seatLayout">
                                                                <div class="d-flex flex-row align-self-stretch mb-3">
                                                                    ...
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="tab-pane fade" id="deck" role="tabpanel"
                                                     aria-labelledby="deck-tab">
                                                    <div class="row">
                                                        <div class="col-md-5">
                                                            <form action="" class="addToCartDeck" id="quickbookForm"
                                                                  method="POST">
                                                                @csrf
                                                                <input type="hidden" name="trip_id"
                                                                       value="{{ $schedule['trip_id'] }}">
                                                                <div class="form-group">
                                                                    <label class="">Select ticket</label>
                                                                    <select class="form-control" name="deck_id"
                                                                            id="deckID">
                                                                        <option value="">Select ticket</option>
                                                                    </select>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label class="">Select ticket</label>
                                                                    <select class="form-control" name="passengers">
                                                                        <option value="">Select passenger</option>
                                                                        <option value="1">1 Person</option>
                                                                        <option value="2">2 Person</option>
                                                                        <option value="3">3 Person</option>
                                                                        <option value="4">4 Person</option>
                                                                        <option value="5">5 Person</option>
                                                                    </select>
                                                                </div>
                                                                <div class="form-group">
                                                                    <button class="btn btn-success" type="submit">Add to
                                                                        cart
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <div class="col-sm-4">
                            <form role="form" id="confirmOrderForm" action="{{ route('dashboard.quickbook.confirm') }}"
                                  method="POST">
                                <div class="card in">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fa fa-shopping-cart"></i> Cart</h5>
                                    </div>
                                    <div class="card-content">
                                        <div class="card-body p-0">
                                            <table class="table table-striped" id="cartItems">
                                                @php
                                                    $totalAmount = 0;
                                                @endphp
                                                @if( $carts )
                                                    <tr>
                                                        <th style="min-width:180px;">vehicle name</th>
                                                        <th style="min-width:80px;">Item</th>
                                                        <th style="min-width:80px;">Fare</th>
                                                        @if($user->type == 'merchant' && $user->merchant['vat_visibility'] == '1')
                                                            <th style="min-width:80px;">Vat</th>
                                                        @endif
                                                        <th style="min-width:80px;">Charge</th>
                                                        <th style="min-width:80px;">Discount</th>
                                                        <th style="min-width:80px;">Total</th>
                                                        <th style="width:40px;"><i class="fa fa-times"></i></th>
                                                    </tr>
                                                    @foreach( $carts as $k => $cart)
                                                        @php
                                                            $total = abs($cart['fare'] + $cart['total_vat'] + $cart['total_charge'] - $cart['discount']);
                                                            $totalAmount += $total;
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $cart['vehicle_name'] }}Tk.</td>
                                                            <td>
                                                                @if( $cart['type'] == 'deck' )
                                                                    {{ $cart['type'] }}
                                                                    X {{ ( $cart['passenger'] ) ? $cart['passenger']['person'] : '1' }}
                                                                @else
                                                                    {{ $cart['type'] }}: {{ $cart['cabin_no'] }}
                                                                @endif
                                                            </td>
                                                            <td>{{ $cart['fare'] }}Tk.</td>
                                                            @if($user->type == 'merchant' && $user->merchant['vat_visibility'] == '1')
                                                                <td>{{ $cart['total_vat'] }}Tk.</td>
                                                            @endif
                                                            <td>{{ $cart['total_charge'] }}Tk.</td>
                                                            <td>{{ $cart['discount'] }}Tk.</td>
                                                            <td>{{ number_format($total, 2) }}
                                                                Tk.
                                                            </td>
                                                            <th><a href="#" class="removeCartItem"
                                                                   data-index="{{ $k }}"><i class="fa fa-times"></i></a>
                                                            </th>
                                                        </tr>
                                                    @endforeach
                                                @endif
                                            </table>
                                        </div>
                                    </div>
                                    <div class="card-footer text-right">
                                        <div class="clearfix"></div>
                                        <div class="col-sm-12 mt-3">
                                            <label>
                                                <input type="checkbox" id="forAgentBooking" value="1"> Agent
                                                booking?
                                            </label>
                                        </div>
                                        <div class="col-sm-12 mt-3 d-none" id="agentChoose">
                                            <div class="form-group text-left">
                                                <select class="form-control select2" name="agent_id" id="agent"
                                                        data-placeholder="Select agent"
                                                        data-dropdown-css-class="select2-purple"
                                                        style="width: 100%;">
                                                    <option value="">Select agent</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text"><i class="fa fa-user"></i></div>
                                                </div>
                                                <input type="text" class="form-control" name="customer_name"
                                                       placeholder="Customer name" required>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="row">
                                                <div class="col-sm-7 pl-2">
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text"><i class="fa fa-mobile"></i>
                                                            </div>
                                                        </div>
                                                        <input type="text" class="form-control" name="customer_mobile"
                                                               placeholder="Customer mobile"
                                                               data-inputmask="'mask': ['99999999999', '99999999999']"
                                                               data-mask required>
                                                    </div>
                                                </div>
                                                <div class="col-sm-5 pr-2">
                                                    <select class="form-control" name="payment_method"
                                                            id="payment_method">
                                                        <option value="cash">Cash</option>
                                                        <option value="bkash">Bkash</option>
                                                        <option value="rocket">Rocket</option>
                                                        <option value="nagad">Nagad</option>
                                                    </select>
                                                </div>
                                                <div class="clearfix"></div>
                                                <div class="col-sm-12 mt-3 d-none" id="trxIDBlock">
                                                    <div class="form-group text-left">
                                                        <input type="text" class="form-control" name="trx_id"
                                                               placeholder="Transaction ID">
                                                    </div>
                                                </div>
                                                <div class="clearfix"></div>
                                                <div class="col-sm-12 mt-3">
                                                    <div class="form-group text-left">
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text">Paid amount</div>
                                                            </div>
                                                            <input type="text" class="form-control"
                                                                   value="{{round($totalAmount, 2)}}" id="paidAmount"
                                                                   name="paid_amount"
                                                                   placeholder="Paid amount">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-success btn-lg" type="submit">Confirm order</button>
                                    </div>
                                    <div class="cartCollapse"><i class="fa fa-angle-double-left"></i></div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Modal -->
    <div class="modal fade" id="quickbookModal" data-backdrop="static" data-keyboard="false" tabindex="-1"
         aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">Modal title</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3">
                            Info section
                        </div>
                        <div class="col-md-5">
                            Layout section
                        </div>
                        <div class="col-sm-5 col-md-3">
                            Booking section
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <iframe id="ifrPaySlip" name="ifrPaySlip" scrolling="yes" style="display:none"></iframe>
@endsection

@section('header')
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
    <style type="text/css">
        #confirmOrderForm .card {
            position: fixed;
            bottom: 57px;
            right: 0;
            z-index: 1;
            margin-bottom: 0;
            border-radius: 0;
            width: 320px;
        }

        #confirmOrderForm .card:hover {
            /*width: auto;
            min-width: 320px;
            max-width: 80%;*/
        }

        #confirmOrderForm .card.expand {
            width: auto;
            min-width: 420px;
            max-width: 80%;
        }

        #confirmOrderForm .card .card-content {
            min-height: 220px;
            max-height: 420px;
            margin-bottom: 240px;
            overflow-y: auto;
            overflow-x: hidden;
        }

        #confirmOrderForm .card .card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
        }

        #confirmOrderForm .card .cartCollapse {
            position: absolute;
            top: 25%;
            left: -39px;
            background: #fff;
            color: #333;
            padding: 0px 8px;
            font-size: 24px;
            border: 2px solid #dbd5d5;
            border-right: 0;
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
            cursor: pointer;
        }

        #confirmOrderForm .card .cartCollapse:hover {
            background: #efefef;
            color: #000;
        }

        #cabinLayoutBody {
            height: auto;
            min-height: 160px;
            max-height: 320px;
        }

        #cabinLayoutBody:hover {
            overflow: auto;
        }

        #seatCabinLayout {
            display: table-cell;
        }

        #seatCabinLayout .nav-item {
            display: table-cell;
        }

        .accordion {
            width: 100%;
        }

        .row-striped:nth-of-type(odd) {
            background-color: #efefef;
            /*border-left: 4px #000000 solid;*/
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
            /*border-left: 4px #efefef solid;*/
        }

        .row-striped {
            padding: 15px 0;
            margin-right: 0;
            margin-left: 0;
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
            width: 80px;
            text-align: center;
            padding: 20px 5px;
            font-size: 16px;
            font-weight: bold;
            padding-bottom: 25px;
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
            padding: 0px 6px;
            background: #a8a6a6;
            font-size: 14px;
            color: yellow;
        }

        .cabin-card .cabinPrice {
            position: absolute;
            bottom: 0;
            right: 0;
            left: 0;
            background: #219876;
            color: #fff;
            font-size: 16px;
        }

        .cabin-card.cabin-disable .cabinPrice {
            background: #CCC;
        }

        .cabin-card.cabin-empty {
            background: none;
            width: 80px;
            height: 80px;
            box-shadow: none;
        }

        .display-41 {
            text-align: center;
            padding-left: 1rem;
        }

        #cabinsList li.nav-item {
            margin: 15px 5px;
        }

        #quickbookModal .modal-dialog {
            width: 100% !important;
            max-width: 100%;
            margin: 0 auto;
        }

        #myTab {
            border-top: 3px solid #28a745;
            border-top-left-radius: 0px;
            border-top-right-radius: 0px;
        }

        #quickbookAccordion .tab-content > .active {
            padding: 15px;
            border: 1px solid #efefef;
        }

        #quickbookAccordion .cabinLayout, #quickbookAccordion .seatLayout {
            width: 100%;
            overflow: auto;
            height: 360px;
        }
        #quickbookAccordion .cabinLayout .p-2, #quickbookAccordion .seatLayout .p-2 {
            min-width:80px;
        }

        .p-2.riverside {
            background: #E3E3E3;
            border: 1px solid aliceblue;
        }

        .riverSide {
            text-align: center;
            font-weight: bold;
        }

        .availableCabins, .availableSeats {
            width: 100%;
            overflow-x: auto;
        }
        .availableCabins .card, .availableSeats .card {
            min-width: 80px !important;
        }

        .availableCabins .cabin-card, .availableSeats .cabin-card {
            margin-right: 10px;
        }

        /* style sheet for "A4" printing */
        @media print and (width: 8.5in) and (height: 3.5in) {
            body {
                width: 8.5in;
                height: 35 . in;
            }

            @page {
                margin: 1cm;
            }
        }
    </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script>
        let vat_visibility = true;
        @if($user->type == 'merchant' && $user->merchant['vat_visibility'] == '0')
            vat_visibility = false;
        @endif
        jQuery(function ($) {
            let currentModal = '';
            let quickbookModal = $('#quickbookModal');
            let service_type = "{{$type}}";

            $('#forAgentBooking').click(function (e) {
                e.defaultPrevented;
                if ($(this).is(":checked") === true) {
                    $(this).parents('form').find('#agentChoose').removeClass('d-none');
                    initSelect2();
                } else {
                    $(this).parents('form').find('#agentChoose').addClass('d-none');
                }
            });
            function initSelect2() {
                let agentSuggestUrl = "{{ route('agent.suggest') }}";
                $('#agent').select2({
                    placeholder: "Select agent",
                    theme: 'bootstrap4',
                    allowClear: true,
                    cache: false,
                    ajax: {
                        url: agentSuggestUrl,
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
            }

            function print() {

                var yourDOCTYPE = "<!DOCTYPE html>";
                var printPreview = window.open('', 'print_preview');
                var printDocument = printPreview.document;
                printDocument.open();
                // var head =
                "<head>" +
                "<style>@media print{ .to-print{height:3.5in; width:8.5in; margin: 1cm; background:red; border: 1px solid black; } }</style>" +
                "</head>";
                printDocument.write(yourDOCTYPE +
                    "<html>" +
                    head +
                    "<body>" +
                    "<div class='to-print'>" +
                    "your content to print can be put here or you can simply use document.getElementById('id-content-toprint')" +
                    "</div>" +
                    "</body>" +
                    "</html>");
                printPreview.print();
                printPreview.close()

            }

            $('#serviceDropdowns').change(function (e) {
                if ($(this).val() !== '') {
                    service_type = $(this).val();
                    $('#quickbookSearchForm').trigger('submit');
                }
            });

            $('.select2bs4').on('select2:select', function (e) {
                if (e.params.data.id !== '') {
                    $('#quickbookSearchForm').trigger('submit');
                }
            });

            $('.openModal').click(function (e) {
                // print(),;
                // return false;
                e.defaultPrevented;
                if ($(this).hasClass('open')) {
                    $(this).removeClass('open');
                } else {
                    $(this).addClass('open');
                    let trip = $(this).data('trip-id');
                    let floor = $(this).data('floor');
                    layout(this, trip, floor);
                    $(this).parents('#quickbookAccordion').find('.cabinFloor').each(function (e) {
                        if (service_type === 'launch') {
                            $(this).val(2);
                        } else {
                            $(this).val(1);
                        }
                    });
                }
            });

            function layout(_this, trip, floor) {
                let parent = $(_this).parents('#quickbookAccordion');
                let layout = $(parent).find('#myTabContent');
                let url = "/admin/trip/" + trip + '?floor=' + floor;
                initiateLayout(layout);
                $.ajax({
                    type: "GET",
                    dataType: "json",
                    url: url,
                    success: function (response) {
                        if (response.success == true) {
                            generateLayout(response.data, layout);
                        }
                    }
                });
            }

            $(document).on("change", '.cabinFloor', function (e) {
                let trip = $(this).data('trip-id');
                let floor = $(this).val();
                layout(this, trip, floor);
                $(this).parents('#quickbookAccordion').find('.cabinFloor').each(function (e) {
                    $(this).val(floor);
                });
            });

            $(document).on("change", '.cabinType, .seatType', function (e) {
                let type = $(this).data('type');
                let typeID = $(this).val();
                let items, layout;
                let parent = $(this).parents('.quickbookParent');
                if (type == 'seat') {
                    items = $(parent).find('.cabin-card');
                } else {
                    items = $(parent).find('.cabin-card');
                }
                if (items.length) {
                    $.each(items, function (i, item) {
                        if (typeID == '') {
                            activateItem(item);
                        } else if ($(item).data('type-id') == typeID) {
                            activateItem(item);
                        } else {
                            disableItem(item);
                        }
                    });
                }
            });

            function activateItem(item) {
                if ($(item).data('status') === 1 && !$(item).hasClass('cabin-selected')) {
                    $(item).removeClass('cabin-disable').addClass('cabin-active');
                }
                $(item).removeClass('d-none');
            }

            function disableItem(item) {
                if (!$(item).hasClass('cabin-selected')) {
                    $(item).removeClass('cabin-active').addClass('cabin-disable');
                }
            }

            function hideItem(item) {
                $(item).addClass('d-none');
            }

            function generateLayout(data, elem) {
                console.log(data);
                let cabinLayout = $(elem).find('.cabinLayout');
                let seatLayout = $(elem).find('.seatLayout');
                let cabinType = $(elem).find('.cabinType');
                let seatType = $(elem).find('.seatType');
                if (data[0].cabins !== null && Object.keys(data[0].cabins).length > 0) {
                    formatCabin(data[0].cabins, cabinLayout);
                    formatType(data[0].cabin_types, cabinType);
                }
                if (data[0].seats !== null && Object.keys(data[0].seats).length > 0) {
                    formatCabin(data[0].seats, seatLayout);
                    formatType(data[0].seat_types, seatType);
                }
                if (data[0].decks !== null && Object.keys(data[0].decks).length > 0) {
                    formatDeck(data[0].decks, elem);
                }
            }

            function formatCabin(data, elem) {
                let column = [];
                let availableCabins = "";
                let availableSeats = "";
                $.each(data, function (i, items) {
                    column[i] = $("<div class='p-2'></div>");
                    let riversideText = (service_type == 'bus') ? 'Window side' : 'River side';
                    if (i == 1 || i == Object.keys(data).pop()) {
                        $(column[i]).addClass('riverside').append('<p class="riverSide">' + riversideText + '</p>');
                    } else {
                        $(column[i]).append('<p>&nbsp;</p>');
                    }
                    $.each(items, function (j, item) {
                        let cabin;
                        let acClass = (item.cabin_is_ac) ? '' : 'd-none';
                        if (item.cabin_type == 'empty') {
                            cabin = $('<div class="card cabin-card cabin-empty"></div>');
                        } else {
                            let cabin_class = (item.status == 1) ? 'cabin-active' : 'cabin-disable';
                            cabin = $('<div class="card cabin-card ' + cabin_class + '" data-status="' + item.status + '" data-id="' + item.item_id + '" data-trip="' + item.trip_id + '" data-cabin-no="' + item.cabin_no + '" data-type-id="' + item.cabin_type_id + '">' +
                                '<span class="cabinOverlap ' + acClass + '">AC</span>' +
                                '<span class="cabinNumber">' + item.cabin_no + '</span>' +
                                '<span class="cabinPrice">৳' + item.fare + '</span>' +
                                '</div>');
                            cabin.onclick = function (e) {
                                // console.log(e);
                            }
                        }

                        // $(elem).parents('.quickbookParent').find('.availableCabins').html("<p>Hello</p>");
                        if (item.status == 1) {
                            if (item.cabin_type == 'cabin') {
                                availableCabins += '<div class="card cabin-card cabin-active" data-status="1" data-id="' + item.item_id + '" data-trip="' + item.trip_id + '" data-cabin-no="' + item.cabin_no + '" data-type-id="2"><span class="cabinOverlap ' + acClass + '">AC</span><span class="cabinNumber">' + item.cabin_no + '</span><span class="cabinPrice">৳' + item.fare + '</span></div>';
                            } else {
                                availableSeats += '<div class="card cabin-card cabin-active" data-status="1" data-id="' + item.item_id + '" data-trip="' + item.trip_id + '" data-cabin-no="' + item.cabin_no + '" data-type-id="2"><span class="cabinOverlap ' + acClass + '">AC</span><span class="cabinNumber">' + item.cabin_no + '</span><span class="cabinPrice">৳' + item.fare + '</span></div>';
                            }
                        }
                        console.log(cabin);
                        $(column[i]).append(cabin);
                    });
                });
                $(elem).parents('.quickbookParent').find('.availableCabins').html(availableCabins);
                $(elem).parents('.quickbookParent').find('.availableSeats').html(availableSeats);
                $(elem).find('.d-flex').html(column);
            }

            function formatDeck(data, elem) {
                $.each(data, function (i, item) {
                    var $option = $("<option/>", {
                        value: item.id,
                        text: item.from + ' - ' + item.to + ' (' + item.fare + ')'
                    });
                    $(elem).find('#deckID').append($option);
                });
            }

            function formatType(data, elem) {
                console.log(data);
                $.each(data, function (i, item) {
                    var $option = $("<option/>", {
                        value: item.id,
                        text: item.name
                    });
                    $(elem).append($option);
                });
            }

            function initiateLayout(elem) {
                $(elem).find('.d-flex').html("");
                $(elem).find('#availableCabins').html("");
                $(elem).find('#availableSeats').html("");
                $(elem).find('#deckID').html("");
                $(elem).find('.cabinType').html("<option value=''>All</option>");
                $(elem).find('.seatType').html("<option value=''>All</option>");
                return true;
            }

            $('.cartCollapse').click((e) => {
                e.defaultPrevented;
                let self = $(this);
                let parent = $('#confirmOrderForm div.card');
                // console.log(e);
                $(parent).toggleClass('expand');
                if ($(parent).hasClass('expand')) {
                    if (currentModal != '') {
                        $(currentModal).modal('hide');
                    }
                    $(self).find('.cartCollapse i.fa').removeClass('fa-angle-double-left').addClass('fa-angle-double-right');
                } else {
                    $(self).find('.cartCollapse i.fa').removeClass('fa-angle-double-right').addClass('fa-angle-double-left');
                }
            });
            $('#payment_method').change(function (e) {
                e.defaultPrevented;
                let method = $(this).val();
                if (method == 'cash') {
                    $('#trxIDBlock').addClass('d-none');
                } else {
                    $('#trxIDBlock').removeClass('d-none');
                    $('#trxIDBlock').find('input').attr('required', true);
                }
            });
            @if($trip_id )
            $("#modal-{{$trip_id}}").modal("show");
            currentModal = $("#modal-{{$trip_id}}");
            @endif
            let cartItems = $('#cartItems');
            $(document).on("click", '.cabin-active', function (e) {
                let cabinCard = $(this);
                let parent = $(this).parents('#quickbookAccordion');
                // console.log(this)
                let item_id = $(this).attr('data-id');
                let trip_id = $(this).attr('data-trip');
                let url = "{{ route('dashboard.quickbook.add') }}";
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
                    contentType: 'application/json; charset=utf-8',
                    data: JSON.stringify({item_id: item_id, trip_id: trip_id}),
                    success: function (response, textStatus, xhr) {
                        if (response.success == true) {
                            $.each($(parent).find("[data-id='" + item_id + "']"), (i, item) => {
                                $(item).addClass('cabin-selected').removeClass('cabin-active');
                            });
                            decorateCartItems(response.carts);
                            Toast.fire({
                                icon: 'success',
                                title: response.message
                            });
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: response.message
                            });
                        }
                    }
                });
            });
            $('.addToCartDeck').submit(function (e) {
                e.defaultPrevented;
                let url = "{{ route('dashboard.quickbook.addDeckCart') }}";
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
                    data: $(this).serialize(),
                    success: function (response, textStatus, xhr) {
                        if (response.success == true) {
                            decorateCartItems(response.carts);
                            Toast.fire({
                                icon: 'success',
                                title: response.message
                            });
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: response.message
                            });
                        }
                    }
                });
                return false;
            });

            function decorateCartItems(carts) {
                $(cartItems).html("");
                let visibility_class = (vat_visibility) ? '' : 'd-none';
                if (carts !== null) {
                    let totalAmount = 0;
                    $(cartItems).html('<tr><th>vehicle name</th><th>Item</th><th>Fare</th><th class="' + visibility_class + '">Vat</th><th>Charge</th><th>Discount</th><th>Total</th><th><i class="fa fa-times"></i></th></tr>');
                    for (item in carts) {
                        let total = ((carts[item].fare + carts[item].total_vat + carts[item].total_charge) - carts[item].discount);
                        totalAmount = totalAmount + total;
                        let icon = 'bed';
                        let cabinNo = carts[item].cabin_no;
                        if (carts[item].type == 'seat') {
                            icon = 'chair';
                        } else if (carts[item].type == 'deck') {
                            icon = 'ticket-alt';
                            cabinNo = ' x ' + carts[item].passenger.person;
                        }
                        let removeIcon = '<a href="#" class="removeCartItem" data-index="' + item + '"><i class="fa fa-times"></i></a>';
                        let cart = '<tr>' +
                            '<td>' + carts[item].vehicle_name + '</td>' +
                            '<td><span class="badge badge-info"><i class="fa fa-' + icon + '"> ' + cabinNo + '</span></td>' +
                            '<td>' + carts[item].fare.toFixed(2) + ' Tk.</td>' +
                            '<td class="' + visibility_class + '">' + carts[item].total_vat.toFixed(2) + ' Tk.</td>' +
                            '<td>' + carts[item].total_charge.toFixed(2) + ' Tk.</td>' +
                            '<td>' + carts[item].discount.toFixed(2) + ' Tk.</td>' +
                            '<td>' + total.toFixed(2) + ' Tk.</td>' +
                            '<td>' + removeIcon + '</td>' +
                            '</tr>';
                        $(cartItems).append(cart);
                    }
                    $('#paidAmount').val(totalAmount.toFixed(2));
                }
            }

            $(cartItems).on('click', '.removeCartItem', function (e) {
                e.defaultPrevented;
                let cabinCard = $(this).parents('.cabin-card');
                let index = $(this).attr('data-index');
                let url = "{{ route('dashboard.quickbook.removeCartItem') }}"
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
                    contentType: 'application/json; charset=utf-8',
                    data: JSON.stringify({item_index: index}),
                    success: function (response, textStatus, xhr) {
                        if (response.success == true) {
                            $(cabinCard).addClass('cabin-selected').removeClass('cabin-active');
                            decorateCartItems(response.carts);
                            Toast.fire({
                                icon: 'success',
                                title: response.message
                            });
                            location.reload();
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: response.message
                            });
                        }
                    }
                });
                return false;
            });

            $('#confirmOrderForm').submit(function (e) {
                e.defaultPrevented;
                let url = $(this).attr('action');
                let data = $(this).serialize();
                let form = $(this);
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
                            let invoice = response.invoice;
                            let ticketUrl = "/ticket/print/" + response.order_id;
                            let newWin = window.frames[0];
                            newWin.document.write('<body onload="window.print()"><iframe style="position:fixed; top:0px; left:0px; bottom:0px; right:0px; width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden; z-index:999999;" src="' + ticketUrl + '"></body>');
                            newWin.document.close();
                            $(cartItems).html("");
                            Toast.fire({
                                icon: 'success',
                                title: response.message
                            });
                            $(form).trigger('reset');
                            // location.reload();
                            // swal({
                            //     title: 'Success',
                            //     text: response.message,
                            //     type: 'success',
                            //     showCancelButton: true,
                            //     confirmButtonColor: '#3085d6',
                            //     cancelButtonColor: '#d33',
                            //     confirmButtonText: 'Print invoice?',
                            //     cancelButtonText: 'Ok',
                            //     confirmButtonClass: 'btn btn-primary',
                            //     cancelButtonClass: 'btn btn-default',
                            //     buttonsStyling: false,
                            //     closeOnConfirm: false,
                            //     closeOnCancel: false
                            // }).then(function(isConfirm) {
                            //     if (isConfirm === true) {
                            //         window.open(invoice, '_blank');
                            //     }
                            // });
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: response.message
                            });
                        }
                    }
                });
                return false;
            });
            //Initialize Select2 Elements
            $('.select2').select2()

            //Initialize Select2 Elements
            $('.select2bs4').select2({
                theme: 'bootstrap4'
            })

            //Datemask dd/mm/yyyy
            $('#datemask').inputmask('dd/mm/yyyy', {'placeholder': 'dd/mm/yyyy'})
            //Datemask2 mm/dd/yyyy
            $('#datemask2').inputmask('mm/dd/yyyy', {'placeholder': 'mm/dd/yyyy'})
            //Money Euro
            $('[data-mask]').inputmask()

            $('.datepicker').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
                startDate: "-0d",
                endDate: "+30d"
            }).on('changeDate', function (ev) {
                $(this).datepicker('hide');
                $('#quickbookSearchForm').trigger('submit');
            });
        })
        ;

        function setCurrentModal(id) {
            currentModal = $('#modal-' + id);
        }
    </script>
@endsection
