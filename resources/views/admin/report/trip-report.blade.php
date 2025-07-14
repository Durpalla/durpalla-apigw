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
                            <a href="{{route('dashboard.report.index')}}" type="button" class="btn btn-default"><i class="fa fa-arrow-alt-circle-left"></i> Back</a>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <form action="{{ route('dashboard.report.trip.export') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>vehicle</label>
                                        <select class="form-control select2"  id="filtervehicle" id="items" data-placeholder="Select vehicle" data-dropdown-css-class="select2-purple" style="width: 100%;" required>
                                            <option value="">Select vehicle</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Trip date</label>
                                        <input type="text" class="form-control datepicker" id="booking_date" placeholder="search" value="{{date('d/m/Y')}}" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Select Trip</label>
                                        <div class="select2-purple">
                                            <select name="schedule_id" class="select2" id="items" data-placeholder="Select schedule" data-dropdown-css-class="select2-purple" style="width: 100%;" required></select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Select Type</label>
                                        <div class="select2-purple">
                                            <select name="type" class="form-control">
                                                <option value="cabin">Cabin</option>
                                                <option value="seat">Seat</option>
                                                <option value="deck">Deck</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-success"><i class="fa fa-file-excel"></i> Export trip report</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('header')
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <style type="text/css">
        #advancedFilter {
            background: #f2f2f2;
            z-index: 1;
            padding: 10px;
            border: 1px solid #d9d5d5;
            border-left: 0;
            border-right: 0;
            top: auto;
            left: 0;
            right: 0;
            margin-top: 10px;
        }

        #advancedFilterBtn.active {
            color: #219876;
            background: #eaeaea;
        }
    </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            let vehicle_id = '';
            let trip_date = moment('today').format('YYYY-MM-DD');
            var vehicle = $('select#filtervehicle');
            var booking_date = $('input#booking_date');

            //Custom Filters ( Author search )
            $(vehicle).on('select2:select', function (e) {
                e.defaultPrevented;
                vehicle_id = e.params.data.id;
                initializeSelect2(vehicle_id, trip_date)
            });

            $(vehicle).on('select2:clear', function (e) {
                e.defaultPrevented;
                initializeSelect2(vehicle_id, trip_date)
            });

            $(vehicle).select2({
                placeholder: "Select vehicle",
                theme: 'bootstrap4',
                allowClear: true,
                cache: false,
                ajax: {
                    url: "{{ route('dashboard.vehicle.suggest') }}",
                    dataType: 'json',
                    type: "GET",
                    quietMillis: 50,
                    data: function (term) {
                        return {
                            term: term.term,
                            // merchant: $(merchant).val()
                        };
                    },
                    processResults: function (data) {
                        var myResults = [];
                        $.each(data.results, function (index, item) {
                            myResults.push({
                                'id': item.id,
                                'text': item.name
                            });
                        });
                        return {
                            results: myResults
                        };
                    }
                }
            });

            $('.datepicker').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
                // startDate: "-0d",
                // endDate: "+360d"
                endDate: "+30d"
            }).on('changeDate', function (ev) {
                trip_date = moment(ev.date).format('YYYY-MM-DD');
                console.log(trip_date);
                initializeSelect2(vehicle_id, trip_date)
            });

            function initializeSelect2(vehicle_id, trip_date)
            {
                let items = $('#items');
                $(items).html(null);
                let url = "{{ route('dashboard.schedule.list') }}";

                $(items).select2({
                    placeholder: "Select schedule",
                    allowClear: true,
                    cache: false,
                    theme: 'bootstrap4',
                    ajax: {
                        url: url,
                        dataType: 'json',
                        type: "GET",
                        quietMillis: 50,
                        data: function (term) {
                            return {
                                term: term.term,
                                vehicle_id: vehicle_id,
                                trip_date: trip_date
                            };
                        },
                        processResults: function (data) {
                            var myResults = [];
                            $.each(data.results, function (index, item) {
                                myResults.push({
                                    'id': item.id,
                                    'text': item.name
                                });
                            });
                            return {
                                results: myResults
                            };
                        }
                    }
                });
            }
        });
    </script>
@endsection
