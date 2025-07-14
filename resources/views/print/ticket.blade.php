<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style type="text/css" media="all">
        @media all {
            @page {
                size: 3.5in 8.5in;
                margin: 0mm;
            }

            table, figure {
                page-break-inside: avoid;
            }

            .column {
                width: 33.33%;
                float: left;
            }

            .clearfix {
                clear: both;
            }
            footer {page-break-after: always;}
        }
    </style>
</head>
<body>
@foreach($booking->bookingItems as $item)
    <header></header>
    <div class="printable">
        <div class="column">
            <table border="0" cellpadding="0" cellspacing="5" style="width: 100%">
                <tr>
                    <td style="width: 40%">SL.</td>
                    <td>: {{ $item->booking_id . '-' . $item->id }}</td>
                </tr>
                <tr>
                    <td>Name</td>
                    <td>: {{ $item->customer->name }}</td>
                </tr>
                <tr>
                    <td>Mobile</td>
                    <td>: {{ $item->customer->mobile }}</td>
                </tr>
                <tr>
                    <td>Seat No.</td>
                    <td>: {{ ucfirst($item->booking_type) }} {{ ($item->booking_type === 'deck') ? '(' . json_decode($item->passenger)->person . ' person)' : (($item->mapping && $item->mapping->cabinType) ? $item->mapping->cabinType->letter . '-' . $item->mapping->cabin_no : (($item->mapping) ? $item->mapping->cabin_no : $item->item->cabin_no)) }}</td>
                </tr>
                <tr>
                    <td>Total Fare</td>
                    <td>: {{ $item->price }}</td>
                </tr>
                <tr>
                    <td>Facility</td>
                    <td>:</td>
                </tr>
                <tr>
                    <td>Issued By</td>
                    <td>: {{ $booking->user->hasRole('customer') ? 'Self' : $booking->user->name }}</td>
                </tr>
            </table>
            <p>Powered by: Jolzan.</p>
        </div>
        <div class="column">
            <table border="0" cellpadding="0" cellspacing="5" style="width: 100%">
                <tr>
                    <td style="width: 40%">SL.</td>
                    <td>: {{ $item->booking_id . '-' . $item->id }}</td>
                </tr>
                <tr>
                    <td>Name</td>
                    <td>: {{$item->customer->name}}</td>
                </tr>
                <tr>
                    <td>Mobile</td>
                    <td>: {{$item->customer->mobile}}</td>
                </tr>
                <tr>
                    <td>Issue date</td>
                    <td>: {{ date('d-m-Y', strtotime($item->booking_date)) }}</td>
                </tr>
                <tr>
                    <td>Journey date</td>
                    <td>: {{ date('d-m-Y', strtotime($item->trip_date)) }}</td>
                </tr>
                <tr>
                    <td>Seat No.</td>
                    <td>: {{ ucfirst($item->booking_type) }} {{ ($item->booking_type === 'deck') ? json_decode($item->passenger)->person : (($item->mapping && $item->mapping->cabinType) ? $item->mapping->cabinType->letter . '-' . $item->mapping->cabin_no : (($item->mapping) ? $item->mapping->cabin_no : $item->item->cabin_no)) }}</td>
                </tr>
                <tr>
                    <td>Total Fare</td>
                    <td>: {{ $item->price }}</td>
                </tr>
            </table>
            <img src="{{ asset('qrs/' . $item->booking_id . '.png') }}" style="width: 100px; height: 100px;">
        </div>
        <div class="column">
            <table border="0" cellpadding="0" cellspacing="5" style="width: 100%">
                <tr>
                    <td style="width: 60%">From</td>
                    <td>: {{$item->trip->startFrom->name}}</td>
                </tr>
                <tr>
                    <td>To</td>
                    <td>: {{$item->trip->stopTo->name}}</td>
                </tr>
                <tr>
                    <td>Departure time</td>
                    <td>: {{ date('h:ia', strtotime($item->trip->leaving_at)) }}</td>
                </tr>
                <tr>
                    <td>Arrival time</td>
                    <td>: {{ date('h:ia', strtotime($item->trip->operation_timeline)) }}</td>
                </tr>
                <tr>
                    <td>Boarding time</td>
                    <td>: {{ date('h:ia', strtotime($item->trip->leaving_at) - 30*60) }}</td>
                </tr>
                <tr>
                    <td>Boarding place</td>
                    <td>: </td>
                </tr>
                <tr>
                    <td>Issued by</td>
                    <td>: {{ $booking->user->hasRole('customer') ? 'Self' : $booking->user->name }}</td>
                </tr>
            </table>

            <p>Powered by: jolzan.</p>
        </div>
        <div class="clearfix"></div>
    </div>
    <footer></footer>
@endforeach
</body>
</html>
