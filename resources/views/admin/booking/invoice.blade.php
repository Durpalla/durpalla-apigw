@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="card" style="background-color: none;">
                    <div class="card-header">
                        <h3 class="card-title">{{ $title ?? '' }}</h3>
                        <div class="card-tools">
                            <button class="btn btn-default" onclick="window.history.back();"><i class="fa fa-arrow-alt-circle-left"></i> Back</button>
{{--                            <a onclick="" class="btn btn-xs btn-primary"><i class="fa fa-angle-left"></i> Back</a>--}}
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div id="printArea">
                            <!-- info row -->
                            <div class="row invoice-info">
                                <div class="col-sm-4 invoice-col">
                                    From
                                    <address>
                                        <strong>{{ getOption('company_name', 'Jolzan')}}</strong><br>
                                        {{ getOption('company_address', 'Company Address') }}<br>
                                        <!-- San Francisco, CA 94107<br> -->
                                        Phone: {{ getOption('company_phone', '01XXX-XXXXXXX')}}<br>
                                        Email: {{ getOption('company_email', 'info@jolzan.com') }}
                                    </address>
                                </div>
                                <!-- /.col -->
                                <div class="col-sm-4 invoice-col">
                                    To
                                    <address>
                                        <strong>{{ $booking->customer['name'] }}</strong><br>
                                    <!-- {{ $booking->customer['address'] }}<br> -->
                                        Phone: {{ $booking->customer['mobile'] }}<br>
                                        Email: {{ $booking->customer['email'] }}
                                    </address>
                                </div>
                                <!-- /.col -->
                                <div class="col-sm-4 invoice-col">
                                    <b>Invoice #{{ $booking->id }}</b><br>
                                    <b>Payment Due:</b> {{ date('d/m/Y', strtotime( $booking->booking_date )) }}<br>
                                    <b>Trx ID:</b> {{ $booking->payment['transaction_id']}}
                                </div>
                                <!-- /.col -->
                            </div>
                            <!-- /.row -->

                            <!-- Table row -->
                            <div class="row">
                                <div class="col-12 table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Item</th>
                                            <th>Trip</th>
                                            <th>Fare</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @if( $booking->bookingItems )
                                            @foreach( $booking->bookingItems as $k => $item )
                                                @php
                                                    $passenger = json_decode($item['passenger']);
                                                    $boardingPoint = json_decode($item['boardingPoint']);
                                                @endphp
                                                <tr>
                                                    <td>{{ $k+1 }}</td>
                                                    <td>
                                                        {{ ucfirst( $item['booking_type'] ) }}:
                                                        <span class="badge badge-info">
                                  @if( $item['booking_type'] === 'deck')
                                                                <i class="fa fa-ticket-alt"></i>&nbsp;
                                                                x {{ $passenger->person }}
                                                            @elseif( $item['booking_type'] === 'seat')
                                                                <i class="fa fa-chair"></i>
                                                                &nbsp; {{ $item['item']['cabin_no'] }}
                                                            @else
                                                                <i class="fa fa-bed"></i>
                                                                &nbsp; {{ $item['item']['cabin_no'] }}
                                                            @endif
                                                            @if( $boardingPoint )
                                                                <span> <i class="fa fa-map-marker"></i> {{ $boardingPoint['name'] }}</span>
                                                            @endif
                                </span>
                                                    </td>
                                                    <td>
                                                        <i class="fa fa-ship"></i> {{ $item['trip']['vehicle']['name'] }}
                                                        <i class="fa fa-route"></i> {{ ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] . ' - ' .  $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'] . ' - ' . $item['trip']['endingPoint']['ghat']['name'] }}
                                                        <i class="fa fa-calendar"></i> {{ date('d M, Y h:i a', strtotime($item['trip']['leaving_at'])) }}
                                                    </td>
                                                    <td>{{ $item['price'] }}Tk.</td>
                                                </tr>
                                            @endforeach
                                        @endif
                                        </tbody>
                                    </table>
                                </div>
                                <!-- /.col -->
                            </div>
                            <!-- /.row -->

                            <div class="row">
                                <!-- accepted payments column -->
                                <div class="col-6">
                                    <!-- <p class="lead">Note:</p> -->
                                    <!-- <img src="../../dist/img/credit/visa.png" alt="Visa">
                                    <img src="../../dist/img/credit/mastercard.png" alt="Mastercard">
                                    <img src="../../dist/img/credit/american-express.png" alt="American Express">
                                    <img src="../../dist/img/credit/paypal2.png" alt="Paypal"> -->

                                    <p class="text-muted well well-sm shadow-none" style="position: absolute;
                    color: #000;
    bottom: 0;
    left: auto;
    right: auto;
    margin: auto;
    margin-left: 25%;
    border-top: 1px solid #333;
    padding: 5px 25px;">
                                        Authorized signature & Seal
                                    </p>
                                </div>
                                <!-- /.col -->
                                <div class="col-6">
                                <!-- <p class="lead">Amount Due {{ date('d/m/Y', strtotime( $booking->booking_date )) }}</p> -->

                                    <div class="table-responsive">
                                        <table class="table">
                                            <tr>
                                                <th style="width:50%">Subtotal:</th>
                                                <td>{{ $booking->total_amount }}Tk.</td>
                                            </tr>
                                            @if(Auth::user()->type == 'merchant' && Auth::user()->merchant['vat_visibility'] == 1)
                                                <tr>
                                                    <th>Vat ({{ getOption('vat_amount', 0)}}%)</th>
                                                    <td>{{ $booking->vat_total }}</td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <th>Service Charge ({{ getOption('charge_amount', 0) }}%)</th>
                                                <td>{{ $booking->charge_total }}</td>
                                            </tr>
                                            <tr>
                                                <th>Discount:</th>
                                                <td>{{ $booking->total_discount }}</td>
                                            </tr>
                                            <tr>
                                                <th>Total Payable:</th>
                                                <td>{{ number_format( $booking->total_payable, 2) }} Tk.</td>
                                            </tr>
                                            <tr>
                                                <th>Total Paid:</th>
                                                <td>{{ number_format( $booking->payment->paid_amount, 2) }} Tk.</td>
                                            </tr>
                                            <tr>
                                                <th>Dues:</th>
                                                <td>{{ number_format( ($booking->total_payable - $booking->payment->paid_amount), 2) }}
                                                    Tk.
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <!-- /.col -->
                            </div>
                            <!-- /.row -->
                        </div>

                        <!-- this row will not appear when printing -->
                        <div class="row no-print">
                            <div class="col-12 text-right">
                                <a href="{{ route('dashboard.booking.invoice.print', $booking->id) }}" target="_blank"
                                   class="btn btn-secondary" onclick="window.print(); return false;"><i
                                        class="fas fa-print"></i> Print</a>
                                <!-- <button type="button" class="btn btn-success float-right"><i class="far fa-credit-card"></i> Submit
                                  Payment
                                </button>
                                <button type="button" class="btn btn-primary float-right" style="margin-right: 5px;">
                                  <i class="fas fa-download"></i> Generate PDF
                                </button> -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('header')
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

        @media print {
            body * {
                visibility: hidden
            }

            #printArea * {
                visibility: visible
            }

            a:after {
                content: '';
            }

            a[href]:after {
                content: none !important;
            }

            .print-hide {
                display: none !important;
            }

            .form-group {
                float: left;
            }

            .form-control {
                width: 100% !important;
            }

            .print-align-right * {
                text-align: right !important;
            }

            .print-header-title * {
                text-align: center !important;
            }
        }
    </style>
@endsection

@section('footer')
    <script type="text/javascript">
        window.print();
    </script>
@endsection
