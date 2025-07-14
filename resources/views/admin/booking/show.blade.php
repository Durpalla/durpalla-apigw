@extends('layouts.master')

@section('content')
    @php
        $hasCancellableItems = false;
    @endphp
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="card" style="background-color: none;">
                    <div class="card-header">
                        <h3 class="card-title"><strong>Date:</strong>
                            <em>{{ date('d M, Y h:i A', strtotime($booking->created_at) ) }}</em></h3>
                        <div class="card-tools">
                            <button class="btn btn-default" onclick="window.history.back();"><i
                                    class="fa fa-arrow-alt-circle-left"></i> Back
                            </button>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-9">
                                <!-- info row -->
                                <div class="row invoice-info">
                                    <div class="col-sm-4 invoice-col">
                                        Customer Info:
                                        <address>
                                            Name: <strong>{{ $booking->customer['name'] }}</strong><br>
                                            Email: <em>{{ $booking->customer['email']}}</em><br>
                                            Mobile: <em>{{ $booking->customer['mobile']}}</em><br>
                                        </address>
                                    </div>
                                    <!-- /.col -->
                                    <div class="col-sm-4 invoice-col">

                                        <!-- <address>
                                          <strong>John Doe</strong><br>
                                          795 Folsom Ave, Suite 600<br>
                                          San Francisco, CA 94107<br>
                                          Phone: (555) 539-1037<br>
                                          Email: john.doe@example.com
                                        </address> -->
                                    </div>
                                    <!-- /.col -->
                                    <div class="col-sm-4 invoice-col">
                                        <!-- <b>Invoice #007612</b><br>
                                        <br>
                                        <b>Order ID:</b> 4F3S8J<br>
                                        <b>Payment Due:</b> 2/22/2014<br>
                                        <b>Account:</b> 968-34567 -->
                                    </div>
                                    <!-- /.col -->
                                </div>
                                <!-- /.row -->

                                <!-- Table row -->
                                <div class="row">
                                    <div class="col-12 table-responsive">
                                        <h5>Booking items</h5>
                                        <table class="table table-striped">
                                            <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Item</th>
                                                <th>Trip</th>
                                                <th>Passenger</th>
                                                <th>Fare</th>
                                                <th>Total</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @if( $booking->bookingItems )
                                                @foreach( $booking->bookingItems as $k => $item )
                                                    @php
                                                        $bgColor = 'bg-light';
                                                        if( $item['status'] == 2 ) {
                                                          $bgColor = 'bg-danger';
                                                        }
                                                        if($item['status'] == 0) {
                                                          $bgColor = 'bg-warning';
                                                        }
                                                          if( $item->status == 1 && ($item->trip && $item->trip['leaving_at'] > date('Y-m-d H:i:s', time() + 1800))) {
                                                            $hasCancellableItems = true;
                                                          }
                                                            $passenger = json_decode($item['passenger']);
                                                            $boardingPoint = json_decode($item['boardingPoint']);
                                                    @endphp
                                                    <tr class="{{ $bgColor }}">
                                                        <td>{{ $k+1 }}</td>
                                                        <td>
                                                            {{ ucfirst( $item['booking_type'] ) }}:
                                                            <span class="badge badge-info">
                                @if( $item['booking_type'] == 'deck')
                                                                    <i class="fa fa-ticket-alt"></i>
                                                                    &nbsp; {{ $passenger->person }}
                                                                @elseif( $item['booking_type'] == 'seat')
                                                                    <i class="fa fa-chair"></i>
                                                                    &nbsp; {{($item['item']['cabinType']) ? $item['item']['cabinType']['letter'] . '-' : '' }}{{ $item['item']['cabin_no'] }}
                                                                @else
                                                                    <i class="fa fa-bed"></i>
                                                                    &nbsp; {{($item['item']['cabinType']) ? $item['item']['cabinType']['letter'] . '-' : '' }}{{ $item['item']['cabin_no'] }}
                                                                @endif
                                                                @if( $boardingPoint )
                                                                    <span> <i class="fa fa-map-marker"></i> {{ $boardingPoint['name'] }}</span>
                                                                @endif
                              </span>
                                                        </td>
                                                        <td>
                                                            @if($item['trip'])
                                                                <a href="{{ route('dashboard.vehicle.show', $item['trip']['vehicle_id']) }}"
                                                                   target="ext">
                                                                    <?php
                                                                    $type = ($item['trip']['vehicle']) ? $item['trip']['vehicle']['vehicle_type'] : 'launch';
                                                                    $icon = $type;
                                                                    switch ($type) {
                                                                        case 'launch' :
                                                                            $icon = 'ship';
                                                                            break;
                                                                        case 'air':
                                                                            $icon = 'plain';
                                                                            break;
                                                                    }
                                                                    ?>
                                                                    <i class="fa fa-{{$icon}}"></i> {{ $item['trip']['vehicle']['name'] }}
                                                                </a><br/>
                                                                <strong><i
                                                                        class="fa fa-route"></i> {{ ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] . ' - ' .  $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'] . ' - ' . $item['trip']['endingPoint']['ghat']['name'] }}
                                                                </strong>
                                                                <i class="fa fa-calendar"></i> {{ date('d M, Y h:i a', strtotime($item['trip']['leaving_at'])) }}
                                                            @endif
                                                        </td>
                                                        <th>
                                                            @if( $passenger )
                                                                {{ $passenger->name }} - {{ $passenger->mobile }}
                                                            @else
                                                                ------------
                                                            @endif
                                                        </th>
                                                        <td>{{ number_format($item['price'], 2) }} Tk.</td>
                                                        <td>{{ number_format($item['price'], 2) }} Tk.</td>
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
                                    </div>
                                    <!-- /.col -->
                                    <div class="col-6">
                                        <div class="table-responsive">
                                            <table class="table">
                                                <tr>
                                                    <th style="width:50%">Subtotal:</th>
                                                    <td>{{ number_format($booking->total_amount, 2) }}Tk.</td>
                                                </tr>
                                                @if(Auth::user()->type == 'merchant' && Auth::user()->merchant['vat_visibility'] == 1)
                                                    <tr>
                                                        <th>Vat ({{ $booking->vat_amount }}%)</th>
                                                        <td>{{ number_format($booking->vat_total, 2) }} Tk.</td>
                                                    </tr>
                                                @endif
                                                <tr>
                                                    <th>Service Charge ({{ $booking->charge_amount }}%)</th>
                                                    <td>{{ number_format($booking->charge_total, 2) }} Tk.</td>
                                                </tr>
                                                <tr>
                                                    <th>Discount:</th>
                                                    <td>{{ number_format($booking->total_discount, 2) }} Tk.</td>
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
                            <div class="col-3">
                                <h4>Payment gateway feedback
                                    <!-- <a href="#" class="btn btn-xs btn-info"><i class="fa fa-eye"></i> View log</a> --></h4>
                                <table class="table">
                                    <tr>
                                        <th style="width:50%">Status</th>
                                        <td>
                                            @php
                                                switch($booking->payment['status']) {
                                                  case 'success':
                                                    $payment_badge = 'success';
                                                  break;
                                                  case 'fail':
                                                    $payment_badge = 'danger';
                                                  break;
                                                  case 'canceled':
                                                    $payment_badge = 'danger';
                                                  break;
                                                  case 'pending':
                                                    $payment_badge = 'info';
                                                  break;
                                                  default:
                                                    $payment_badge = 'info';
                                                  break;
                                                }
                                            @endphp
                                            <span class="badge badge-{{$payment_badge}}">
                                {{ ucfirst( $booking->payment['status'] ) }}
                              </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:50%">Method</th>
                                        <td>{{ $booking->payment['payment_method'] }}</td>
                                    </tr>
                                    <tr>
                                        <th>Trx ID</th>
                                        <td>
                                            <div
                                                style="overflow: auto;overflow-wrap: break-word;">{{ $booking->payment['transaction_id'] }}</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Bank Trx</th>
                                        <td>{{ $booking->payment['bank_tran_id'] }}</td>
                                    </tr>
                                    <tr>
                                        <th>Paid:</th>
                                        <td>{{ number_format($booking->payment['paid_amount'], 2) }} {{ $booking->payment['currency'] }}</td>
                                    </tr>
                                    <tr>
                                        <th>In store:</th>
                                        <td>{{ number_format($booking->payment['store_amount'], 2) }} {{ $booking->payment['currency'] }}</td>
                                    </tr>
                                    <tr>
                                        <th>Bank charge:</th>
                                        <td>{{ number_format(abs($booking->payment['paid_amount'] - $booking->payment['store_amount']), 2) }} {{ $booking->payment['currency'] }}</td>
                                    </tr>
                                </table>
                                @if( $booking->cancellations )
                                    <h4>Cancellations</h4>
                                    <table class="table">
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Items</th>
                                            <th>Status</th>
                                        </tr>
                                        @foreach( $booking->cancellations as $cancellation)
                                            <tr>
                                                <td>{{ date('d/m/Y h:i a', strtotime( $cancellation['created_at'] ) ) }}</td>
                                                <td>{{ ( $cancellation['type'] == 'p' ) ? 'Partial' : 'All' }}</td>
                                                <td>
                                                    @php
                                                        $items = explode(',', $cancellation['items']);
                                                    @endphp
                                                    <a class="btn btn-secondary btn-sm"
                                                       href="{{ route('dashboard.cancellation.show', $cancellation['id'])}}">{{ count($items)}}</a>
                                                </td>
                                                <td>
                                                    @php
                                                        switch($cancellation['status']){
                                                          case 0:
                                                            echo 'Pending';
                                                            break;
                                                          case 1:
                                                            echo 'Approved';
                                                            break;
                                                        }
                                                    @endphp
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @endif
                            </div>
                        </div>

                        <!-- this row will not appear when printing -->
                        <div class="row no-print">
                            <div class="col-12">
                                <div class="btn-group">
                                    <a href="{{ route('dashboard.booking.invoice', $booking->id )}}" target="_blank"
                                       class="btn btn-secondary"><i class="fas fa-print"></i> Print invoice</a>
                                    <a href="{{ route('ticket.print', $booking->id )}}" target="_blank"
                                       class="btn btn-outline-secondary"
                                       onclick="printTicket('{{ route('ticket.print', $booking->id )}}'); return false;"><i
                                            class="fas fa-print"></i> Print Ticket</a>
                                </div>
                                <div class="btn-group float-right">
                                    @if(in_array($booking->status, [\Jolzatra\Constants\AppConst::BOOKING_FAILED, \Jolzatra\Constants\AppConst::BOOKING_PENDING]))
                                        <button type="button" class="btn btn-success mr-2 bookingConfirm"
                                                data-id="{{ $booking->id }}" data-toggle="modal"
                                                data-target="#failedBookingConfirmModal"><i
                                                class="far fa-check-circle"></i> Booking
                                            Confirm
                                        </button>
                                    @endif
                                    @if( !$booking->cancelationRequests && $booking->status != 'PENDING' && $hasCancellableItems)
                                        <button type="button" class="btn btn-danger mr-2 bookingCancel"
                                                data-id="{{ $booking->id }}"><i class="fa fa-times"></i> Cancel booking
                                        </button>
                                    @endif
                                    @if($booking->status == \Jolzatra\Constants\AppConst::BOOKING_COMPLETE)
                                        <button type="button" class="btn btn-success sendInvoiceToEmail"
                                                data-type="email"
                                        " style="margin-right: 5px;" data-id="{{ $booking->id }}">
                                        <i class="fas fa-envelope"></i> Send to email
                                        </button>
                                        <button type="button" class="btn btn-primary sendInvoiceToEmail" data-type="sms"
                                                style="margin-right: 5px;" data-id="{{ $booking->id }}">
                                            <i class="fas fa-phone"></i> Send to SMS
                                        </button>
                                    @endif
                                    <a href="{{ route('invoice.view', $booking->id) }}" class="btn btn-success">View invoice</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @if( in_array($booking->status, [\Jolzatra\Constants\AppConst::BOOKING_FAILED, \Jolzatra\Constants\AppConst::BOOKING_PENDING]))
        <div class="modal fade" data-backdrop="static" id="failedBookingConfirmModal" tabindex="-1" role="dialog"
             aria-labelledby="myExtraLargeModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">
                            Confirm booking: #{{ $booking->id }}
                            ({{ date('d/m/Y', strtotime( $booking->booking_date ) ) }})
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ route('dashboard.booking.failed.confirm', $booking->id) }}" method="POST"
                              id="bookingCancellationForm">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="booking_id" value="{{ $booking->id }}">
                            <div class="form-group">
                                <label>Booking Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control"
                                       value="{{ $booking->payment['transaction_id'] }}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Bank Trx ID</label>
                                <input type="text" name="bank_tran_id" class="form-control" placeholder="Bank Trx ID"
                                       required>
                            </div>
                            <div class="form-group">
                                <label>Paid amount</label>
                                <input type="number" step=".10" name="paid_amount" class="form-control"
                                       value="{{ $booking->total_payable }}" required>
                            </div>
                            <div class="form-group">
                                <label>Store amount</label>
                                <input type="number" step=".10" name="store_amount" class="form-control" placeholder="0.00"
                                       required>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-lg">Confirm Booking</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
    @if( !$booking->cancelationRequests && $hasCancellableItems)
        <div class="modal fade" data-backdrop="static" id="bookingCancelModal" tabindex="-1" role="dialog"
             aria-labelledby="myExtraLargeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">
                            Cancel booking: #{{ $booking->id }}
                            ({{ date('d/m/Y', strtotime( $booking->booking_date ) ) }})
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ route('dashboard.cancellation.store') }}" method="POST"
                              id="bookingCancellationForm">
                            @csrf
                            <input type="hidden" name="booking_id" value="{{ $booking->id }}">
                            <div class="row">
                                <div class="col-7">
                                    <div class="form-group">
                                        <label>Cancel type</label>
                                        <select class="form-control" name="type" id="cancelType" required>
                                            <option value="all" selected="">All</option>
                                            <option value="partial">Partial</option>
                                        </select>
                                    </div>
                                    <div class="form-group clearfix" id="cancellationItems" style="display: none;">
                                        <label><strong>Select Items</strong></label><br>
                                        @foreach( $booking->bookingItems as $item )
                                            @php
                                                $passenger = json_decode( $item['passenger'] );
                                                $icon = 'ticket-alt';
                                                if( $item->booking_type === 'cabin' ) {
                                                  $icon = 'bed';
                                                } elseif( $item['booking_type'] === 'seat') {
                                                  $icon = 'chair';
                                                }
                                                if( $item->status == 1) {
                                            @endphp
                                            <div class="icheck-primary">
                                                <input type="checkbox" class="cancel-item"
                                                       id="checkboxPrimary{{ $item['id'] }}" name="items[]"
                                                       value="{{ $item['id'] }}" checked>
                                                <label for="checkboxPrimary{{ $item['id'] }}">
                                                    {{ ucfirst( $item['booking_type'] ) }} &nbsp;
                                                    <span class="badge badge-info"><i class="fa fa-{{ $icon }}"></i> &nbsp;
                    {{ ( $item['booking_type'] === 'deck' ) ? ' x ' . $passenger->person : $item['item']['cabin_no'] }}</span>
                                                    Trip: {{ $item['trip']['vehicle']['name'] }}
                                                    - {{ date('d/m/Y', strtotime( $item['trip_date'] ) ) }}
                                                </label>
                                            </div>
                                            @php } @endphp
                                        @endforeach
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-lg">Send request</button>
                                    </div>
                                </div>
                                <div class="col-5">
                                    <h5>Booking info</h5>
                                    <table class="table">
                                        <tr>
                                            <td>ID#</td>
                                            <td><em>{{ $booking->id }}</em></td>
                                        </tr>
                                        <tr>
                                            <td>Date</td>
                                            <td><em>{{ date('d/m/Y', strtotime( $booking->booking_date ) ) }}</em></td>
                                        </tr>
                                        <tr>
                                            <td>Customer name</td>
                                            <td><em>{{ $booking->customer['name'] }}</em></td>
                                        </tr>
                                        <tr>
                                            <td>Customer mobile</td>
                                            <td><em>{{ $booking->customer['mobile'] }}</em></td>
                                        </tr>
                                        <tr>
                                            <td>Payment method</td>
                                            <td><em>{{ $booking->payment['payment_method'] }}</em></td>
                                        </tr>
                                        <tr>
                                            <td>Payment status</td>
                                            <td>
                                                <span
                                                    class="badge badge-{{$payment_badge}}">{{ ucfirst( $booking->payment['status'] ) }}</span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <iframe id="ifrPaySlip" name="ifrPaySlip" scrolling="yes" style="display:none"></iframe>
@endsection

@section('header')
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
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
    <script type="text/javascript">
        function printTicket(url) {
            let newWin = window.frames[0];
            newWin.document.write('<body onload="window.print()"><iframe style="position:fixed; top:0px; left:0px; bottom:0px; right:0px; width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden; z-index:999999;" src="' + url + '"></body>');
            newWin.document.close();
        }

        jQuery(function ($) {
            let modal = $('#bookingCancelModal');
            $('.bookingCancel').click(function () {
                $(modal).modal('show');
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
            $('#bookingCancellationForm').submit(function (e) {
                e.defaultPrevented;
                let url = $(this).attr('action');
                let data = $(this).serialize();
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'Application/json'
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
                        // location.reload();
                    }
                });
                return false;
            });

            $('.sendInvoiceToEmail').click(function (e) {
                e.defaultPrevented;
                let id = $(this).attr('data-id');
                let url = "{{ route('dashboard.booking.invoice.send') }}";
                let type = $(this).data('type');
                $(loading).toggleClass('d-none');
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
                    data: {id: id, type: type},
                    success: function (response, textStatus, xhr) {
                        Toast.fire({
                            icon: response.label,
                            title: response.content
                        });
                    },
                    complete: function (state, status, xhr) {
                        $(loading).toggleClass('d-none');
                    }
                });
            });
        });
    </script>
@endsection
