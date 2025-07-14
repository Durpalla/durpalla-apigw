<h4>Booking party wize report</h4>
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
<h4>Type wize booking</h4>
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
<h4>Payment collection wize report</h4>
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
