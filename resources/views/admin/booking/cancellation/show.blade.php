@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">
          <div class="card" style="background-color: none;">
            <div class="card-header">
              <h3 class="card-title"><strong>Date:</strong> <em>{{ date('d M, Y h:i A', strtotime($cancellation->booking->booking_date) ) }}</em></h3>
              <div class="card-tools">
{{--                <strong>Time:</strong> <em>{{ date('h:i A', strtotime($cancellation->booking->created_at) ) }}</em>--}}
                  <button class="btn btn-default" onclick="window.history.back();"><i class="fa fa-arrow-alt-circle-left"></i> Back</button>
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
                        Name: <strong>{{ $cancellation->customer['name'] }}</strong><br>
                        Email: <em>{{ $cancellation->customer['email']}}</em><br>
                        Mobile: <em>{{ $cancellation->customer['mobile']}}</em><br>
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
                          @php
                            $totalAmount = 0;
                            $totalDiscount = 0;
                            $totalVat = 0;
                            $totalServiceCharge = 0;
                          @endphp
                          @if( $cancellation->bookingItems )
                          @foreach( $cancellation->bookingItems as $k => $item )
                          @if( in_array($item->id, explode(',', $cancellation->items) ) )
                          @php
                          $passenger = json_decode($item['passenger']);
                          $boardingPoint = json_decode($item['boardingPoint']);
                          $totalAmount += $item['price'];
                          $totalDiscount += $item['discount'];
                          if( $item['vat_applicable_to'] == 'customer') {
                            $totalVat += abs($item['price']*($item['vat_amount'] / 100));
                          }
                          $totalServiceCharge += abs($item['price']*($item['charge_amount'] / 100));
                          @endphp
                          <tr>
                            <td>{{ $k+1 }}</td>
                            <td>
                              {{ ucfirst( $item['booking_type'] ) }}:
                              <span class="badge badge-info">
                                @if( $item['booking_type'] === 'deck')
                                <i class="fa fa-ticket-alt"></i>&nbsp; {{ $passenger->person }}
                                @elseif( $item['booking_type'] === 'seat')
                                <i class="fa fa-chair"></i>&nbsp; {{ $item['item']['cabin_no'] }}
                                @else
                                <i class="fa fa-bed"></i>&nbsp; {{ $item['item']['cabin_no'] }}
                                @endif
                                @if( $boardingPoint )
                                <span> <i class="fa fa-map-marker"></i> {{ $boardingPoint['name'] }}</span>
                                @endif
                              </span>
                            </td>
                            <td>
                              <a href="{{ route('dashboard.vehicle.show', $item['trip']['vehicle_id']) }}" target="ext">
                                <i class="fa fa-ship"></i> {{ $item['trip']['vehicle']['name'] }}
                              </a><br/>
                              <strong><i class="fa fa-route"></i> {{ ( $item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] . ' - ' .  $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'] . ' - ' . $item['trip']['endingPoint']['ghat']['name'] }}</strong>
                              <i class="fa fa-calendar"></i> {{ date('d M, Y h:i a', strtotime($item['trip']['leaving_at'])) }}
                            </td>
                            <th>
                              @if( $passenger )
                              {{ $passenger->name }} - {{ $passenger->mobile }}
                              @else
                              ------------
                              @endif
                            </th>
                            <td>{{ number_format( $item['price'], 2) }} Tk.</td>
                            <td>{{ number_format( abs($item['price']), 2) }} Tk.</td>
                          </tr>
                          @endif
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
                            <td>{{ number_format($totalAmount, 2) }}Tk.</td>
                          </tr>
                          <tr>
                            <th>Vat ({{ $cancellation->booking['vat_amount']}}%)</th>
                            <td>{{ number_format($totalVat, 2) }} Tk.</td>
                          </tr>
                          <tr>
                            <th>Service Charge ({{ $cancellation->booking['charge_amount'] }}%)</th>
                            <td>{{ number_format($totalServiceCharge, 2) }} Tk.</td>
                          </tr>
                          <tr>
                            <th>Discount:</th>
                            <td>{{ number_format($totalDiscount, 2) }} Tk.</td>
                          </tr>
                          <tr>
                            <th>Total:</th>
                            <td>{{ number_format( ($totalAmount + $totalVat + $totalServiceCharge - $totalDiscount), 2) }} Tk.</td>
                          </tr>

                          <tr>
                            <th>Refundable:</th>
                            <td>
                              <?php
                              $refundable = 0;
                              if( in_array($cancellation->booking->status, ['COMPLETE', 'ACTIVE']) ) {
                                $refundable += abs($totalAmount - $totalDiscount);
                                if( $cancellation->vat_refundable ) {
                                  $refundable += abs($totalVat);
                                }
                                if( $cancellation->charge_refundable  ) {
                                  $refundable += abs($totalServiceCharge);
                                }
                              }
                              ?>
                              {{ number_format( $refundable, 2) }} Tk.
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
                  <h4>Payment info <a href="#" class="btn btn-xs btn-info"><i class="fa fa-eye"></i> View log</a></h4>
                  <table class="table">
                          <tr>
                            <th style="width:50%">Status</th>
                            <td>
                              @php
                              switch($cancellation->payment['status']) {
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
                                {{ ucfirst( $cancellation->payment['status'] ) }}
                              </span>
                            </td>
                          </tr>
                          <tr>
                            <th style="width:50%">Method</th>
                            <td>{{ $cancellation->payment['payment_method'] }}</td>
                          </tr>
                          <tr>
                            <th>Trx ID</th>
                            <td><div style="overflow: auto;overflow-wrap: break-word;">{{ $cancellation->payment['transaction_id'] }}</div></td>
                          </tr>
                          <tr>
                            <th>Bank Trx</th>
                            <td>{{ $cancellation->payment['bank_transaction_id'] }}</td>
                          </tr>
                          <tr>
                            <th>In store:</th>
                            <td>{{ $cancellation->payment['store_amount'] }}</td>
                          </tr>
                          <tr>
                            <th>Currency:</th>
                            <td>{{ $cancellation->payment['currency'] }}</td>
                          </tr>
                        </table>
                        @if( $cancellation->cancelationRequests )
                        <h4>Booking cancellation</h4>
                        <table class="table">
                          <tr>
                            <td>Date/Time</td>
                            <td>{{ date('d/m/Y h:i a', strtotime( $cancellation->cancelationRequests['created_at'] ) ) }}</td>
                          </tr>
                          <tr>
                            <td>Type</td>
                            <td>{{ ( $cancellation->cancelationRequests['type'] == 'P' ) ? 'Partial' : 'All' }}</td>
                          </tr>
                          <tr>
                            <td>Status</td>
                            <td>{{ $cancellation->cancelationRequests['status'] }}</td>
                          </tr>
                        </table>
                      @endif
                </div>
              </div>

              <!-- this row will not appear when printing -->
              <div class="row no-print">
                <div class="col-12">
                  <strong>Status: </strong>
                  <?php
                  switch($cancellation->status) {
                    case'0':
                      echo '<span class="badge badge-info">Pending</span>';
                    break;
                    case'1':
                      echo '<span class="badge badge-success">Approved</span>';
                    break;
                    case'2' :
                      echo '<span class="badge badge-success">Processing</span>';
                    break;
                    case'3' :
                      echo '<span class="badge badge-success">Refunded</span>';
                    break;
                    case'9' :
                      echo '<span class="badge badge-danger">Declined</span>';
                    break;
                  }
                  ?>
                  <div class="btn-group float-right">
                    @if( $cancellation->status == 0 )
                    <button type="button" class="btn btn-success cancellationHandler" data-action="approve" data-id="{{ $cancellation->id }}"><i class="fa fa-check"></i> Approve</button>
                    <button type="button" class="btn btn-danger cancellationHandler" data-action="decline" data-id="{{ $cancellation->id }}"><i class="fa fa-times"></i> Decline</button>
                    @endif
                    @if( $cancellation->status == 1 )
                      <button type="button" class="btn btn-info cancellationHandler" data-action="processing" data-id="{{ $cancellation->id }}"><i class="fa fa-exchange-alt"></i> Processing</button>
                    @endif
                    @if( $cancellation->status == 2 )
                      <button type="button" class="btn btn-success cancellationHandler" data-action="refunded" data-id="{{ $cancellation->id }}"><i class="fa fa-times"></i> Refunded</button>
                    @endif
                  </div>
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
jQuery(function($) {
  let modal = $('#bookingCancelModal');
  $('.bookingCancel').click(function() {
    $(modal).modal('show');
  });
  $('#cancelType').change(function(e) {
    let type = $(this).val();

    if( type === 'all' ) {
      $('input.cancel-item').each(function() {
        $(this).attr('checked', true);
      });
      $('#cancellationItems').hide();
    } else {
      $('input.cancel-item').each(function() {
        $(this).attr('checked', false);
      });
      $('#cancellationItems').show();
    }
  });
  $("input.cancel-item").change(function(){
    if ($('input.cancel-item:checked').length == $('input.cancel-item').length) {
      $('#cancelType').val('all');
      $('#cancellationItems').hide();
    }
  });
  $('.cancellationHandler').click(function(e) {
    e.defaultPrevented;
    let url = "{{ route('dashboard.cancellation.action') }}";
    let id = $(this).attr('data-id');
    let action = $(this).data('action');
    let data = {id: id, action: action};
    let _this = $(this);
    if( action ) {
      Swal.fire({
          title: 'Are you sure?',
          text: "You are going to " + action + " this cancellation request.",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes'
      }).then((isConfirm) => {
          if (isConfirm) {
              $.ajaxSetup({
                  headers: {
                      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                      'Accept': 'application/json'
                  },
                  cache: false
              });
              $.ajax({
                  dataType: "json",
                  type: "POST",
                  url: url,
                  data: data,
                  success: function (response, textStatus, xhr) {
                      if (response.status == true) {
                          $(_this).parent('div').hide();
                      }
                      Toast.fire({
                          icon: response.label,
                          title: response.content
                      });
                  }
              });
          }
      });
    }
    return false;
  });
  $('#bookingCancellationForm').submit( function(e) {
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
});
</script>
@endsection
