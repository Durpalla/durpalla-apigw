<table>
    <thead>
    <tr>
        <th>INV#</th>
        <th>Customer info</th>
        <th>Booking date</th>
        <th>Journey</th>
        <th>Leaving time</th>
        <th>Route name</th>
        <th>vehicle name</th>
        <th>Type</th>
        <th>Cabin/Seat No.</th>
        <th>Passenger</th>
        <th>Fare</th>
        <th>VAT</th>
        <th>Charge</th>
        <th>Total</th>
        <th>Other passenger info</th>
        <th>Booking party</th>
    </tr>
    </thead>
    <tbody>
    @foreach($reports as $booking)
    <tr>
        <td>{{$booking['invoice']}}</td>
        <td>{{$booking['customer_info']}}</td>
        <td>{{date('d/m/Y', strtotime($booking['booking_date']))}}</td>
        <td>{{date('d/m/Y', strtotime($booking['journey_date']))}}</td>
        <td>{{date('h:i A', strtotime($booking['journey_date']))}}</td>
        <td>{{$booking['route']}}</td>
        <td>{{$booking['launch']}}</td>
        <td>{{$booking['type']}}</td>
        <td>{{$booking['cabin_letter'] . '-' . $booking['cabin_no']}}</td>
        <td>{{$booking['passenger']}}</td>
        <td>{{$booking['fare']}}</td>
        <td>{{$booking['vat']}}</td>
        <td>{{$booking['discount']}}</td>
        <td>{{$booking['total']}}</td>
        <td>{{$booking['charge']}}</td>
        <td>{{$booking['party']}}</td>
    </tr>
    @endforeach
    </tbody>
</table>
