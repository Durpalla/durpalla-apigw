@extends('layouts.master')
@section('content')
    <div class="card">
        <div class="invoice p-5">
            <div class="row">
                <div class="col-sm-7">
                    <table class="table">
                        <tr>
                            <th>Agent</th>
                            <td>: {{ $withdrawal->user['name'] }}</td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>: {{ $withdrawal->user['email'] }}</td>
                        </tr>
                        <tr>
                            <th>Mobile</th>
                            <td>: {{ $withdrawal->user['mobile'] }}</td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td>: {{ $withdrawal->user['meta']['address'] }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="payment border-top mt-3 mb-3 border-bottom table-responsive">
                <table class="table table-borderless">
                    <tbody>
                    <tr>
                        <td>
                            <div class="py-2"><span class="d-block text-muted">Withdrawal Date</span>
                                <span>{{ $withdrawal->created_at->format('d/m/Y') }}</span></div>
                        </td>
                        <td>
                            <div class="py-2">
                                <span class="d-block text-muted">Withdrawal No.</span>
                                <span>{{ $withdrawal->id }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="py-2">
                                <span class="d-block text-muted">Method</span>
                                <span>
                                    {{ ucfirst($withdrawal->agentPaymentMethod['type']) }}
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="py-2">
                                <span class="d-block text-muted">Withdrawal details</span>
                                <span class="d-block">Method: {{ $withdrawal->agentPaymentMethod['type'] }}</span>
                                <span>Account: {{ $withdrawal->agentPaymentMethod['account_no'] }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="py-2">
                                <span class="d-block text-muted">Status</span>
                                @if($withdrawal->status === 1)
                                    <span class="badge badge-success">Completed</span>
                                @elseif($withdrawal->status === 2)
                                    <span class="badge badge-danger">Declined</span>
                                @else
                                    <span class="badge badge-info">Declined</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="product border-bottom table-responsive">

            </div>
            <div class="row d-flex justify-content-end">
                <div class="col-md-5">
                    <form action="{{ route('withdrawal.update', $withdrawal->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <table class="table table-borderless">
                            <tbody class="totals">
                            <tr>
                                <td>
                                    <div class="text-left">
                                        <span class="text-muted">Balance</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-right"><span>{{ $withdrawal->balance }}</span></div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="text-left"><span class="text-muted">Withdrawal amount</span></div>
                                </td>
                                <td>
                                    <div class="text-right"><span class="text-success">{{ $withdrawal->amount }}</span></div>
                                </td>
                            </tr>
                            @if($withdrawal->status === \Jolzatra\Constants\AppConst::WITHDRAWAl_PENDING)
                                <tr class="border-top border-bottom">
                                    <td>
                                        <div class="text-left"><span class="font-weight-bold">Trx ID</span></div>
                                    </td>
                                    <td>
                                        <div class="text-right">
                                            <input type="text" name="transaction_reference" id="trxID"
                                                   class="form-control" required>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="text-left"><span class="font-weight-bold">Status</span></div>
                                    </td>
                                    <td>
                                        <div class="text-right">
                                            <select name="status" id="withdrawalStatus" class="form-control">
                                                <option value="{{\Jolzatra\Constants\AppConst::WITHDRAWAl_COMPLETE}}">Approve
                                                </option>
                                                <option value="{{ \Jolzatra\Constants\AppConst::WITHDRAWAl_CANCELLED }}">
                                                    Decline
                                                </option>
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td>
                                        <button type="submit" class="btn btn-primary">Submit</button>
                                    </td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <script>
        jQuery(function ($) {
            $('#withdrawalStatus').change(function (e) {
                if ($(this).val() === '1') {
                    $('#trxID').attr('required', true);
                } else {
                    $('#trxID').attr('required', false);
                }
            });
        });
    </script>
@endsection
