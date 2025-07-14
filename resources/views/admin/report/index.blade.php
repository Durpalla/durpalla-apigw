@extends('layouts.master')

@section('content')

    <div class="row">
        @if(auth()->user()->hasRole('admin') || auth()->user()->hasAnyPermission(['report-daily-booking']))
            <div class="col-md-2 col-sm-3 ">
                <div class="wrimagecard wrimagecard-topimage">
                    <a href="{{ route('dashboard.report.booking.daily') }}">
                        <div class="wrimagecard-topimage_header" style="background-color: rgba(119, 178, 88, 0.1)">
                            <center><i class="fa fa-calendar fa-5x"></i></center>
                        </div>
                        <div class="wrimagecard-topimage_title">
                            <h4 class="menuItem">Daily bookings</h4>
                        </div>
                    </a>
                </div>
            </div>
        @endif
        @if(auth()->user()->hasRole('admin') || auth()->user()->hasAnyPermission(['report-daily-trip']))
            <div class="col-md-2 col-sm-3">
                <div class="wrimagecard wrimagecard-topimage">
                    <a href="{{ route('dashboard.report.trip') }}">
                        <div class="wrimagecard-topimage_header" style="background-color: rgba(119, 178, 88, 0.1)">
                            <center><i class="fa fa-route fa-5x"></i></center>
                        </div>
                        <div class="wrimagecard-topimage_title">
                            <h4 class="menuItem">Daily Trip Report
                                <div class="pull-right badge" id="WrControls"></div>
                            </h4>
                        </div>
                    </a>
                </div>
            </div>
        @endif
        @if(auth()->user()->hasRole('admin') || auth()->user()->hasAnyPermission(['report-daily-vehicle']))
            <div class="col-md-2 col-sm-3">
                <div class="wrimagecard wrimagecard-topimage">
                    <a href="{{ route('dashboard.report.vehicle.booking') }}">
                        <div class="wrimagecard-topimage_header" style="background-color: rgba(119, 178, 88, 0.1)">
                            <center><i class="fa fa-ship fa-5x"></i></center>
                        </div>
                        <div class="wrimagecard-topimage_title">
                            <h4 class="menuItem">Daily vehicle bookings
                                <div class="pull-right badge" id="WrControls"></div>
                            </h4>
                        </div>
                    </a>
                </div>
            </div>
        @endif
    </div>
    <!-- /.row -->

    <div class="row">
    </div>
    <!-- /.row -->
@endsection

@section('header')
    <style>
        h4.menuItem {
            font-size: 18px;
        }

        .wrimagecard {
            margin-top: 0;
            margin-bottom: 1.5rem;
            text-align: left;
            position: relative;
            background: #fff;
            box-shadow: 12px 15px 20px 0px rgba(46, 61, 73, 0.15);
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .wrimagecard .fa {
            position: relative;
            font-size: 70px;
        }

        .wrimagecard-topimage_header {
            padding: 20px;
        }

        .wrimagecard-topimage_header img {
            width: 10rem;
            height: 4.5rem;
        }

        a.wrimagecard:hover, .wrimagecard-topimage:hover {
            box-shadow: 2px 4px 8px 0px rgba(46, 61, 73, 0.2);
        }

        .wrimagecard-topimage a {
            width: 100%;
            height: 100%;
            display: block;
        }

        .wrimagecard-topimage_title {
            padding: 20px 24px;
            height: 80px;
            padding-bottom: 0.75rem;
            position: relative;
        }

        .wrimagecard-topimage a {
            border-bottom: none;
            text-decoration: none;
            color: #525c65;
            transition: color 0.3s ease;
        }
    </style>
@endsection

@section('footer')

@endsection
