@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="card" style="background-color: none;">
                    <div class="card-header">
                        <h3 class="card-title">Trip reports</h3>
                        <div class="card-tools">
                            <div class="btn-group" role="group" aria-label="Basic example">
                                <a href="{{ route('dashboard.schedule.report.export', $schedule->id) }}" class="btn btn-xs btn-success"><i
                                            class="fa fa-file-excel"></i> Download</a>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-8">
                                <!-- TABLE: LATEST ORDERS -->
                                <div class="card">
                                    <div class="card-header border-transparent">
                                        <h3 class="card-title">Booking party wize report</h3>

                                        <div class="card-tools">
                                        </div>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body">
                                        <table class="table table-bordered table-condensed table-striped">
                                            <tr>
                                                <th>Party</th>
                                                <th>Total Vat</th>
                                                <th>Total Charge</th>
                                                <th>Total Amount</th>
                                                <th>Refund Amount</th>
                                                <th>Dues</th>
                                                <th>Balance</th>
                                            </tr>
                                            @foreach(collect($reports['bookings'])->groupBy('party') as $party => $items)
                                                <tr>
                                                    <th>{{ucfirst($party)}}</th>
                                                    <td>{{collect($items)->sum('total_vat')}}</td>
                                                    <td>{{collect($items)->sum('total_charge')}}</td>
                                                    <td>{{collect($items)->sum('total_payable')}}</td>
                                                    <td>{{collect($items)->sum('refunded_amount')}}</td>
                                                    <td>{{collect($items)->sum('dues')}}</td>
                                                    <td>{{collect($items)->sum('balance')}}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                </div>
                                <div class="card">
                                    <div class="card-header border-transparent">
                                        <h3 class="card-title">Type wize report</h3>

                                        <div class="card-tools">
                                        </div>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body">
                                        <table class="table table-bordered table-condensed table-striped">
                                            <tr>
                                                <th>Type</th>
                                                <th>Quantity</th>
                                                <th>Total Vat</th>
                                                <th>Total Charge</th>
                                                <th>Total Discount</th>
                                                <th>Total Amount</th>
                                                <th>Refund Amount</th>
                                            </tr>
                                            @foreach(collect($reports['types'])->groupBy(['type', 'type_name']) as $type => $items)
                                                <tr>
                                                    <th colspan="6">{{ucfirst($type)}}</th>
                                                </tr>
                                                @foreach($items as $type_name => $lists)
                                                    <tr>
                                                        <td>{{$type_name}}</td>
                                                        <td>{{collect($lists)->count()}}</td>
                                                        <td>{{collect($lists)->sum('total_vat')}}</td>
                                                        <td>{{collect($lists)->sum('total_charge')}}</td>
                                                        <td>{{collect($lists)->sum('total_discount')}}</td>
                                                        <td>{{collect($lists)->sum('total_amount')}}</td>
                                                        <td>{{collect($lists)->sum('refund_amount')}}</td>
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        </table>
                                    </div>
                                </div>
                                <div class="card">
                                    <div class="card-header border-transparent">
                                        <h3 class="card-title">Payment collections (Operators)</h3>

                                        <div class="card-tools">
                                        </div>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body">
                                        <table class="table table-bordered table-condensed table-striped">
                                            <tr>
                                                <th>Name of person</th>
                                                <th>Designation</th>
                                                <th>Cash</th>
                                                <th>Bkash</th>
                                                <th>Rocket</th>
                                                <th>Nagad</th>
                                                <th>Total</th>
                                            </tr>
                                            @foreach(collect($reports['collections'])->groupBy(['name']) as $person => $items)
                                                <tr>
                                                    <th>{{ucfirst($person)}}</th>
                                                    <td>{{collect($items)->first()['designation']}}</td>
                                                    <td>{{collect($items)->where('payment_type', 'cash')->sum('amount')}}</td>
                                                    <td>{{collect($items)->where('payment_type', 'bkash')->sum('amount')}}</td>
                                                    <td>{{collect($items)->where('payment_type', 'rocket')->sum('amount')}}</td>
                                                    <td>{{collect($items)->where('payment_type', 'nagad')->sum('amount')}}</td>
                                                    <td>{{collect($items)->sum('amount')}}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-4">
                                <!-- TABLE: LATEST ORDERS -->
                                <h4>Payment method wize report</h4>
                                <table class="table table-bordered table-condensed table-striped">
                                    <tr>
                                        <th>Payment method</th>
                                        <th>Amount</th>
                                    </tr>
                                    @foreach(collect($reports['collections'])->groupBy(['method']) as $method => $items)
                                        <tr>
                                            <th>{{ucfirst($method)}}</th>
                                            <td>{{collect($items)->sum('amount')}}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('header')
    <style type="text/css">


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

    </style>
@endsection

@section('footer')

@endsection
