<!DOCTYPE html>
<html>
<head>
    <title>{{ config('app.name') }} Invoice</title>
    <!------ Include the above in your HEAD tag ---------->
    <style type="text/css">

        @import url('https://fonts.googleapis.com/css2?family=Barlow:wght@100;400;600;700&display=swap');

        @page {
            margin: 0px;
            padding: 0;
        }

        html {
            margin: 0px;
            padding: 0;
        }

        body {
            background: #e4e4e4;
            font-family: Barlow, Verdana, sans-serif !important;
            margin: 0;
            padding: 15px;
            padding-top: 0;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
        }

        .header {
            background: #f5f5f5;
            padding: 15px;
            border-bottom: solid 1px #CCC;
        }

        .header .column {
            width: 33%;
            float: left;
        }

        .logo {
            text-align: center;
            margin-top: 5px;
        }

        .header .column .address {
            border-left: 1px solid #CCC;
            text-align: center;
        }

        .header .column .contact {
            text-align: right;
            border-left: 1px solid #CCC;
        }

        .col-9 {
            width: 75%;
            float: left;
        }

        .col-3 {
            width: 25%;
            float: left;
        }

        .col-6 {
            width: 50%;
            float: left;
        }

        .card {
            background: #f5f5f5;
            padding: 10px;
            margin: 10px 10px;
            border: solid 1px #CCC;
        }

        .qrcode {
            width: 100%;
            height: auto;
            text-align: center;
            overflow: hidden;
            padding:5px;
        }

        .qrcode img {
            width: 90%;
            height: auto;
            margin: 5px;
            text-align: center;
            overflow: hidden;
        }

        .qrcode p {
            margin: 0;
        }

        .invoice main {
            padding-bottom: 50px
        }

        .invoice footer {
            width: 100%;
            text-align: center;
            color: #777;
            border-top: 1px solid #aaa;
            padding: 8px 0
        }

        .clearfix {
            clear: both;
        }

        .table {
            width: 100%;
            margin-bottom: 0;
            color: #212529;
        }

        .table th,
        .table td {
            padding: 0.15rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }

        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
        }

        .table tbody + tbody {
            border-top: 2px solid #dee2e6;
        }

        .table-sm th,
        .table-sm td {
            padding: 0.2rem;
        }

        .table-bordered {
            border: 1px solid #dee2e6;
        }

        .table-bordered th,
        .table-bordered td {
            border: 1px solid #dee2e6;
        }

        .table-bordered thead th,
        .table-bordered thead td {
            border-bottom-width: 2px;
        }

        .table-borderless th,
        .table-borderless td,
        .table-borderless thead th,
        .table-borderless tbody + tbody {
            border: 0;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .table-hover tbody tr:hover {
            color: #212529;
            background-color: rgba(0, 0, 0, 0.075);
        }

        .table-primary,
        .table-primary > th,
        .table-primary > td {
            background-color: #b8daff;
        }

        .table-primary th,
        .table-primary td,
        .table-primary thead th,
        .table-primary tbody + tbody {
            border-color: #7abaff;
        }

        .table-hover .table-primary:hover {
            background-color: #9fcdff;
        }

        .table-hover .table-primary:hover > td,
        .table-hover .table-primary:hover > th {
            background-color: #9fcdff;
        }

        .table-secondary,
        .table-secondary > th,
        .table-secondary > td {
            background-color: #d6d8db;
        }

        .table-secondary th,
        .table-secondary td,
        .table-secondary thead th,
        .table-secondary tbody + tbody {
            border-color: #b3b7bb;
        }

        .table-hover .table-secondary:hover {
            background-color: #c8cbcf;
        }

        .table-hover .table-secondary:hover > td,
        .table-hover .table-secondary:hover > th {
            background-color: #c8cbcf;
        }

        .table-success,
        .table-success > th,
        .table-success > td {
            background-color: #c3e6cb;
        }

        .table-success th,
        .table-success td,
        .table-success thead th,
        .table-success tbody + tbody {
            border-color: #8fd19e;
        }

        .table-hover .table-success:hover {
            background-color: #b1dfbb;
        }

        .table-hover .table-success:hover > td,
        .table-hover .table-success:hover > th {
            background-color: #b1dfbb;
        }

        .table-info,
        .table-info > th,
        .table-info > td {
            background-color: #bee5eb;
        }

        .table-info th,
        .table-info td,
        .table-info thead th,
        .table-info tbody + tbody {
            border-color: #86cfda;
        }

        .table-hover .table-info:hover {
            background-color: #abdde5;
        }

        .table-hover .table-info:hover > td,
        .table-hover .table-info:hover > th {
            background-color: #abdde5;
        }

        .table-warning,
        .table-warning > th,
        .table-warning > td {
            background-color: #ffeeba;
        }

        .table-warning th,
        .table-warning td,
        .table-warning thead th,
        .table-warning tbody + tbody {
            border-color: #ffdf7e;
        }

        .table-hover .table-warning:hover {
            background-color: #ffe8a1;
        }

        .table-hover .table-warning:hover > td,
        .table-hover .table-warning:hover > th {
            background-color: #ffe8a1;
        }

        .table-danger,
        .table-danger > th,
        .table-danger > td {
            background-color: #f5c6cb;
        }

        .table-danger th,
        .table-danger td,
        .table-danger thead th,
        .table-danger tbody + tbody {
            border-color: #ed969e;
        }

        .table-hover .table-danger:hover {
            background-color: #f1b0b7;
        }

        .table-hover .table-danger:hover > td,
        .table-hover .table-danger:hover > th {
            background-color: #f1b0b7;
        }

        .table-light,
        .table-light > th,
        .table-light > td {
            background-color: #fdfdfe;
        }

        .table-light th,
        .table-light td,
        .table-light thead th,
        .table-light tbody + tbody {
            border-color: #fbfcfc;
        }

        .table-hover .table-light:hover {
            background-color: #ececf6;
        }

        .table-hover .table-light:hover > td,
        .table-hover .table-light:hover > th {
            background-color: #ececf6;
        }

        .table-dark,
        .table-dark > th,
        .table-dark > td {
            background-color: #c6c8ca;
        }

        .table-dark th,
        .table-dark td,
        .table-dark thead th,
        .table-dark tbody + tbody {
            border-color: #95999c;
        }

        .table-hover .table-dark:hover {
            background-color: #b9bbbe;
        }

        .table-hover .table-dark:hover > td,
        .table-hover .table-dark:hover > th {
            background-color: #b9bbbe;
        }

        .table-active,
        .table-active > th,
        .table-active > td {
            background-color: rgba(0, 0, 0, 0.075);
        }

        .table-hover .table-active:hover {
            background-color: rgba(0, 0, 0, 0.075);
        }

        .table-hover .table-active:hover > td,
        .table-hover .table-active:hover > th {
            background-color: rgba(0, 0, 0, 0.075);
        }

        .table .thead-dark th {
            color: #fff;
            background-color: #343a40;
            border-color: #454d55;
        }

        .table .thead-light th {
            color: #495057;
            background-color: #e9ecef;
            border-color: #dee2e6;
        }

        .table-dark {
            color: #fff;
            background-color: #343a40;
        }

        .table-dark th,
        .table-dark td,
        .table-dark thead th {
            border-color: #454d55;
        }

        .table-dark.table-bordered {
            border: 0;
        }

        .table-dark.table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .table-dark.table-hover tbody tr:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.075);
        }

        .table p {
            margin: 0;
        }

        #invoiceEmergencyNote {
            font-size: 13px;
            text-align: center;
            font-family: Barlow, "SolaimanLipi";
        }

        #bookingItemsTable tr {
            background: #fff;
            border: outset 2px #c4bfbf;
        }

        #bookingItemsTable tr:nth-child(2) {
            background: #fff;
        }

        #printSection .cancelButton {
            cursor: pointer;
        }

        #printSection .invoice {
            position: relative;
            background-color: #FFF;
            min-height: 680px;
            padding: 0;
            border: 1px solid #efefef;
            box-shadow: 0px 1px 6px #8c8c8c;
        }

        #printSection .invoice header {
            padding: 10px 0;
            background: #f5f5f5;
            box-shadow: 0px 3px 10px -2px #a0a0a0;
        }

        #printSection header .column {
            border-left: 1px solid #CCC;
            font-size: 14px;
            font-weight: bold;
            color: #5b5b5b;
        }

        #printSection .card#invoiceCard {
            border-radius: 0 !important;
            border: 2px outset #eee4e4 !important;
            background-color: #f5f5f5;
            /*box-shadow: 3px 3px 6px #8c8c8c;*/
        }

        #printSection .card h4 {
            color: #219876;
            font-size: 13px;
            margin: 0 0 5px 0;
        }

        #printSection table {
            color: #5b5b5b;
        }

        #printSection table tr.cancelled {
            background: #ffc4c4 !important;
            color: red;
        }

        #printSection table tr.pending {
            background: #fdf6e2 !important;
        }

        #printSection table tr.total th {
            color: #219876;
            font-size: 12px;
        }

        #printSection table th, table td {
            padding: 5px 8px;
        }

        #printSection table th {
            font-size: 12px;
            line-height: 16px;
            font-weight: bold;
        }

        #printSection table td {
            font-size: 11px;
            line-height: 16px;
        }

        #printSection .invoice main {
            padding-bottom: 50px
        }

        #printSection .qrcode-pnr.text-center {
            font-size: 14px;
            font-weight: 700;
            color: #219876;
        }

        #printSection .invoice .footer {
            width: 100%;
            text-align: center;
            color: #777;
            border-top: 1px solid #aaa;
            padding: 8px 0
        }

        .table-bordered th, .table-bordered td {
            border: 1px solid #dee2e6;
        }

        /*@media print {*/
        #printSection .invoice {
            font-size: 11px !important;
            overflow: hidden !important
        }

        #printSection .invoice .footer {
            position: absolute;
            bottom: 10px;
            page-break-after: always
        }

        #printSection .invoice > div:last-child {
            page-break-before: always
        }
        h4, h2 {
            margin:8px;
        }
        ul>li {
            line-height: 100%;
            font-size: 10px;
            text-align: justify-all;
        }
        .ticketTitle {
            text-align: center;
            font-size: 16px;
            /* padding: 0; */
            margin: 10px 0 10px 10px;
            line-height: 20px;
            background: #f2f2f2;
            padding: 0;
            color: #219876;
            border: 1px solid #CCC;
            top: auto;
            bottom: 0;
            left: 0;
            right: 0;
        }
        .ticketTitle>h4 {
            padding: 0;
            margin: 0;
        }
        .borderTop {
            border-top: 1px solid #CCC;
            width: 100% !important;
        }
        .cancellationPolicy {
            margin: 0 15px;
        }
        .note {
            background: #f2f2f2;
            padding: 5px;
            text-align: center;
            font-size: 13px;
            border-top: 1px solid #CCC;
        }
        .note p {
            margin: 5px;
        }
        hr {
            margin: 0;
            color: #CCC;
        }
    </style>
</head>
<body>
<div class="test">
    <div id="printSection">
        <div class="invoice overflow-auto">
            <div>
                <div class="header">
                    <div class="column">
                        <div class="logo">
                            <img src="{{asset('default/logo2.png')}}" data-holder-rendered="true"
                                 style="width:auto;max-width:180px;"/>
                        </div>
                    </div>
                    <div class="column">
                        <div class="address">
                            <address class="mb-2">{{ getOption('address_line_1') }}</address>
                            <address class="mb-2">{{ getOption('address_line_2') }}</address>
                        </div>
                    </div>
                    <div class="column">
                        <div class="contact">
                            <address class="mb-2">{{ getOption('company_hotline', '09XXXXXXXX') }}</address>
                            <address class="mb-2">{{ getOption('company_email', 'support@jolzan.com') }}</address>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div class="body">
                    <div class="row">
                        <div class="col-9">
                            <div class="card" style="margin-right: 0; padding-top:0;">
                                <table style="width: 100%">
                                    <tr>
                                        <th style="text-align: left;">Book by :</th>
                                        <th style="text-align: center;">Contact :</th>
                                        <th style="text-align: right;">Booking Date :</th>
                                    </tr>
                                    <tr>
                                        <td style="text-align: left;">{{ $invoice['customer']->name }}</td>
                                        <td style="text-align: center;">{{ $invoice['customer']->mobile }}</td>
                                        <td style="text-align: right;">{{ $invoice['booking_date_formated'] }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div id="invoiceEmergencyNote">
                                <div>
                                    <p style="margin:0;">{!! getOption('invoice_note', 'May your journey be free from stress and bring you home safely. Safe Travels !<br />
                                    Urgency - XXXXX') !!}</>
                                </div>
                            </div>

                            <div class="ticketTitle">
                                <h4>Launch Ticket</h4>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="qrcode">
                                <img src="{{asset('qrs/' . $invoice['id'] . '.png')}}"/>
                                <p><strong>PNR</strong> {{ $invoice['id'] }}</p>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                @if( $invoice['items'] )
                                    @foreach( $invoice['items'] as $item )
                                        <h4>Journey date: {{ date('d M Y', strtotime($item['date'])) }}</h4>
                                        <table class="table table-bordered" id="bookingItemsTable" style="margin-bottom: 10px;">
                                            @if( $item['tickets'] )
                                                @foreach( $item['tickets'] as $ticket )
                                                    <tr>
                                                        <td>
                                                            <p class="mb-2 p-0">
                                                                <strong>Passenger: </strong>{{ $ticket['passenger']->name }}
                                                            </p>
                                                            <hr>
                                                            <p class="mb-2 p-0">
                                                                <strong>vehicle: </strong>{{ $ticket['vehicle_name'] }}
                                                            </p>
                                                            <hr>
                                                            @if( $ticket['cabin_type'] != 'deck')
                                                                <p class="mb-2 p-0">
                                                                    <strong>{{ ucfirst($ticket['cabin_type']) }}
                                                                        :</strong> {{ $ticket['cabin_no'] }}</p>
                                                            @else
                                                                <p class="mb-2 p-0"><strong>Deck:</strong>
                                                                    X {{ $ticket['passenger']->person }}</p>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <p class="mb-2 p-0">
                                                                <strong>Mobile: </strong> {{ $ticket['passenger']->mobile }}
                                                            </p>
                                                            <hr>
                                                            <p class="mb-2 p-0">
                                                                <strong>From: </strong>{{ $ticket['from'] }}</p>
                                                            <hr>
                                                        </td>
                                                        <td>
                                                            <p class="mb-6">&nbsp;</p>
                                                            <hr>
                                                            <p class="mb-2 p-0"><strong>To: </strong>{{ $ticket['to'] }}
                                                            </p>
                                                            <hr>
                                                            <p class="mb-2 p-0">
                                                                <strong>Fare:</strong> {{ $ticket['price'] }} Tk.</p>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                        </table>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="cancellationPolicy">
                            {!! getOption('cancellation_policy') !!}
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="card">
                                <h4>Payment Method :</h4>
                                <table class="table table-sm">
                                    <tr>
                                        <th>{{ ($invoice['payment']->payment_method) ? $invoice['payment']->payment_method : '.' }}</th>
                                        <th class="text-right">{{ $invoice['payment']->payment_method }}</th>
                                    </tr>
                                    <tr>
                                        <th>Trx ID</th>
                                        <th class="text-right">{{ $invoice['payment']->transaction_id }}</th>
                                    </tr>
                                    <tr>
                                        <th>Amount Paid</th>
                                        <th class="text-right">{{ round($invoice['payment']->paid_amount, 2) }}</th>
                                    </tr>
                                    <tr>
                                        <th>Bank Charge</th>
                                        <th class="text-right"></th>
                                    </tr>
                                    <tr class="total">
                                        <th>Status</th>
                                        <th class="text-right">
                                            <span
                                                class="badge badge-success badge-xs">{{ $invoice['payment']->status }}</span>
                                        </th>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card ">
                                <h4>Payment Details :</h4>
                                <table class="table table-sm">
                                    <tr>
                                        <th>Subtotal</th>
                                        <th class="text-right">{{ $invoice['total_amount']}}</th>
                                    </tr>
                                    <tr>
                                        <th>Vat ({{ $invoice['vat_amount']}}%)</th>
                                        <th class="text-right">{{ $invoice['vat_total']}}</th>
                                    </tr>
                                    <tr>
                                        <th>Charge ({{ $invoice['charge_amount']}}%)</th>
                                        <th class="text-right">{{ $invoice['charge_total']}}</th>
                                    </tr>
                                    <tr>
                                        <th>Discount</th>
                                        <th class="text-right">{{ $invoice['total_discount']}}</th>
                                    </tr>
                                    <tr class="total">
                                        <th>Total</th>
                                        <th class="text-right">{{ $invoice['total_payable']}}</th>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                    <div class="row" style="margin: 0 15px;">
                        {!! getOption('booking_policy') !!}
                    </div>
                    <div class="note">
                        <p>Note : Please print this email/attachment pdf file for your reference on your ticket.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
