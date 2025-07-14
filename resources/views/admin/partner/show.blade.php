@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'info') ? 'active': ''; @endphp"
                   id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Info</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'vehicle') ? 'vehicle': ''; @endphp"
                   id="vehicle-tab" data-toggle="tab" href="#vehicle" role="tab" aria-controls="vehicle"
                   aria-selected="false">Vehicles</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'withdrawal') ? 'active': ''; @endphp"
                   id="seat-tab" data-toggle="tab" href="#seat" role="tab" aria-controls="seat" aria-selected="false">Withdrawals</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'history') ? 'active': ''; @endphp"
                   id="history-tab" data-toggle="tab" href="#history" role="tab" aria-controls="history"
                   aria-selected="false">History</a>
            </li>
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div
                class="tab-pane fade show @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'info') ? 'active show': ''; @endphp"
                id="home" role="tabpanel" aria-labelledby="home-tab">
                <div class="row">
                    <div class="col-12 col-md-8 col-lg-8 order-2 order-md-1">

                        <!-- /.row -->
                        <div class="row mt-3">
                            <div class="col-12">

                                <!-- TABLE: LATEST ORDERS -->
                                <div class="card">
                                    <div class="card-header border-transparent">
                                        <h3 class="card-title">Bookings</h3>
                                        <div class="card-tools">
                                            <div class="form-group">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body p-0">
                                        <div id="advancedFilter">
                                            <div class="row pt-2">
                                                <div class="col-sm-3">
                                                    <input type="text" placeholder="Schedule date"
                                                           class="form-controll bookingdatepicker" id="date_from"
                                                           value="">
                                                </div>
                                                <div class="col-sm-3">
                                                    <input type="text" placeholder="Schedule date"
                                                           class="form-controll bookingdatepicker" id="date_to"
                                                           value="">
                                                </div>
                                                <div class="col-sm-4">
                                                    <select class="form-control select2" id="filterRoutes"
                                                            data-placeholder="Select route"
                                                            data-dropdown-css-class="select2-purple"
                                                            style="width: 100%;">
                                                        <option value="">Select route</option>
                                                    </select>
                                                </div>
                                                <div class="col-sm-2">
                                                    <select class="form-control" id="filterType">
                                                        <option value="">Type</option>
                                                        <option value="cabin">Cabin</option>
                                                        <option value="seat">Seat</option>
                                                        <option value="deck">Deck</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table m-0" id="recentBookingsTable">
                                                <thead>
                                                <tr>
                                                    <th>Invoice</th>
                                                    <th>Type</th>
                                                    <th>tickets</th>
                                                    <th>Passenger</th>
                                                    <th>Booking date</th>
                                                    <th>Schedule date</th>
                                                    <th>Payment method</th>
                                                    <th>Status</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- /.table-responsive -->
                                    </div>
                                    <!-- /.card-body -->
                                    <div class="card-footer clearfix">
                                        <!-- <a href="javascript:void(0)" class="btn btn-sm btn-info float-left">Place New Order</a> -->
                                        <a href="{{ route('dashboard.booking.index') }}"
                                           class="btn btn-sm btn-default float-right">View All Orders</a>
                                    </div>
                                    <!-- /.card-footer -->
                                </div>
                                <!-- /.card -->
                            </div>
                            <div class="col-6">

                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3 col-lg-4 order-1 order-md-2">
                        <h3 class="text-secondary"> {{ $partner->name }}
                            @can('partner-edit')
                                <a href="{{ route('partner.edit', $partner->id ) }}"><i class="fa fa-edit"></i></a>
                            @endcan
                        </h3>
                        <hr>
                        @if( $partner->profile_pic != null)
                            <div class="profile-userpic">
                                <img src="{{ asset($partner->profile_pic )}}" alt="logo">
                            </div>
                            <hr>
                        @endif
                        <div class="text-muted">
                            <p class="text-sm">Name
                                <b class="d-block">{{ $partner->name }}</b>
                            </p>
                            <p> Email <br>
                                <b class="d-block">{{ $partner->email }}</b>
                            </p>
                            <p class="text-sm">Mobile
                                <b class="d-block">{{ $partner->mobile }}</b>
                            </p>
                        </div>
                        <h5 class="mt-5 text-muted">More info</h5>
                        <hr>
                        <div class="text-muted">
                            <p class="text-sm"> Incentive
                                <b class="d-block">{{ $partner->incentive->incentive }} </b>
                            </p>
                            <p class="text-sm"> Incentive Type
                                <b class="d-block">{{ $partner->incentive->incentive_type }}
                                    ({{ config('constants.incentive_types')[$partner->incentive->incentive_type] }})</b>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'vehicle') ? 'active show': ''; @endphp"
                id="vehicle" role="tabpanel" aria-labelledby="vehicle-tab">
                <div class="row">
                    <div class="col-8">
                        <h4>Vehicles</h4>
                        <hr>
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Vehicle name</th>
                                <th>Merchant</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($partner->vehicles as $vehicle)
                            <tr>
                                <td>{{ $vehicle->name }}</td>
                                <td>{{ $vehicle->merchant['merchant_name'] }}</td>
                                <td>{{ config('constants.vehicle_status.' . $vehicle->status) }}</td>
                                <td>
                                    <form action="{{ route('partner_vehicle.destroy', $vehicle->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                                        <button type="submit" class="btn btn-danger"><i class="fa fa-times"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="col-4">
                        <h4>Attach vehicle</h4><hr>
                        <form action="{{ route('partner_vehicle.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                            <div class="form-group">
                                <label>Select Vehicle</label>
                                <select name="vehicle_id" class="form-control select2" id="partnerVehicles"
                                        data-placeholder="Select vehicle"
                                        data-dropdown-css-class="select2-purple"
                                        style="width: 100%;">
                                    <option value="">Select vehicle</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-block">Attach vehicle</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'seat') ? 'active show': ''; @endphp"
                id="seat" role="tabpanel" aria-labelledby="seat-tab">
                <div class="row">
                    <div class="col-12">

                        <table class="table table-striped" id="withdrawalTable">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Officer</th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'history') ? 'active show': ''; @endphp"
                id="history" role="tabpanel" aria-labelledby="history-tab">
                <div class="row">
                    <div class="col-12">
                        <table class="table table-striped" id="commissionTable">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Base amount</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Purpose</th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('header')

    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.css') }}">
    <!-- iCheck for checkboxes and radio inputs -->
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
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
        }


        #listGridTab .nav-link {
            padding: 2px 5px 0;
            border: 1px solid #eee;
            background: #fbfbfb;
        }


        .cabin-card .cabinOverlap {
            position: absolute;
            top: 0;
            right: 0;
            width: auto;
            padding: 0px 5px;
            background: yellow;
            font-size: 12px;
        }

        .cabin-card .cabinPrice {
            position: absolute;
            bottom: 0;
            right: 0;
            left: 0;
            background: #219876;
            color: #fff;
            font-size: 18px;
        }

        #cabinsList li.nav-item {
            margin: 15px 5px;
        }

        /* Profile container */
        .profile-userpic {
            height: auto;
            overflow: none;
        }

        .profile-userpic img {
            margin: 0 auto;
            width: auto;
            height: auto;
            max-height: 220px;
            background: #fbfbfb;
            border: 1px solid #eee;
            padding: 10px;
        }
    </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>

    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/pdfmake-0.1.36/pdfmake.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/pdfmake-0.1.36/vfs_fonts.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.flash.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.print.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/bootstrap-switch/bootstrap-toggle.min.js') }}"></script>
    <script>
        let can_edit = false, can_active = false, can_inactive = false, can_delete = false, cabin_create = false,
            cabin_edit = false;
        @can('vehicle-edit')
            can_edit = true;
        @endcan
            @can('vehicle-active')
            can_active = true;
        @endcan
            @can('vehicle-inactive')
            can_inactive = true;
        @endcan
            @can('vehicle-delete')
            can_delete = true;
        @endcan
            @canany(['cabin-create', 'cabins-add'])
            cabin_create = true;
        @endcan
            @canany(['cabin-edit', 'cabins-update'])
            cabin_edit = true;
        @endcan
            $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        $(function () {
            let userID = "{{ $partner->id }}";
            let date_from = '';
            let date_to = '';
            let bookingTable = $('#recentBookingsTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": true,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': "{{ route('partner.bookings', $partner->id)}}",
                    pages: 5, // number of pages to cache
                    'data': function (data) {
                        data.type = $('#filterType').val();
                        data.route_id = $('#filterRoutes').val();
                        data.date_from = date_from;
                        data.date_to = date_to;
                    }
                },
                "lengthChange": false,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "oLanguage": {
                    "sLengthMenu": "Show _MENU_ ",
                },
                "pageLength": 15,
                "bFilter": true,
                "bInfo": false,
                "searching": false,
                "columns": [
                    {
                        "mRender": function (data, type, row) {
                            var str = '<a href="/admin/booking/show/' + row['booking_id'] + '" class="cabin-action2">#' + row['booking_id'] + '</a>';
                            return str;
                        }
                    },
                    {"data": "booking_type"},
                    {
                        "mRender": function (data, type, row) {
                            let passenger = JSON.parse(row['passenger']);
                            let str = '';
                            if (row['booking_type'] == 'cabin' && row['mapping']) {
                                str = '<span class="badge badge-info"><i class="fa fa-bed"></i> ' + row['mapping']['cabin_type']['letter'] + '-' + row['mapping']['cabin_no'] + '</span>';
                            } else if (row['booking_type'] == 'seat' && row['mapping']) {
                                str = '<span class="badge badge-info"><i class="fa fa-chair"></i> ' + row['mapping']['cabin_type']['letter'] + '-' + row['mapping']['cabin_no'] + '</span>';
                            } else {
                                str = '<span class="badge badge-info"><i class="fa fa-ticket-alt"></i> x ' + passenger.person + '</span>';
                            }

                            return '<a href="/admin/booking/show/' + row['booking_id'] + '">' + str + '</a>';
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            let passenger = JSON.parse(row['passenger']);
                            return passenger.name + ' - ' + passenger.mobile;
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            let date = new Date(row['booking_date']);
                            let month = date.getMonth() + 1;
                            return date.getDate() + '/' + month + '/' + date.getFullYear();
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            let date = new Date(row['trip_date']);
                            let month = date.getMonth() + 1;
                            return date.getDate() + '/' + month + '/' + date.getFullYear();
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            let payment_method = '';
                            if (row['booking'] && row['booking']['payment'] != null) {
                                payment_method = row['booking']['payment']['payment_method'];
                            }
                            return payment_method;
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            let str = '';
                            switch (row['status']) {
                                case 0 :
                                    str = '<span class="badge badge-info">Pending</span>';
                                    break;
                                case 1 :
                                    str = '<span class="badge badge-success">Success</span>';
                                    break;
                                default:
                                    str = '<span class="badge badge-danger">Cancelled</span>';
                                    break;
                            }

                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0, 1, 4], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[2, 'asc']],
            });

            let incomeTable = $('#incomeTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": true,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': "{{ route('commission.show', $partner->id)}}",
                    pages: 5, // number of pages to cache
                },
                "lengthChange": false,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "oLanguage": {
                    "sLengthMenu": "Show _MENU_ ",
                },
                "pageLength": 15,
                "bFilter": true,
                "bInfo": false,
                "searching": false,
                "columns": [
                    {"data": "pnr"},
                    {"data": "type"},
                    {"data": "cabin_no"},
                    {"data": "booking_date"},
                    {"data": "price"},
                    {"data": "commission"}
                ],
                "columnDefs": [
                    {"targets": [0, 1, 4], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[2, 'asc']],
            });

            let withdrawalTable = $('#withdrawalTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": true,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': "{{ route('withdrawal.index')}}",
                    pages: 5, // number of pages to cache
                    data: function (data) {
                        data.user_id = userID;
                    }
                },
                "lengthChange": false,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "oLanguage": {
                    "sLengthMenu": "Show _MENU_ ",
                },
                "pageLength": 15,
                "bFilter": true,
                "bInfo": false,
                "searching": false,
                "columns": [
                    {"data": "id"},
                    {"data": "date"},
                    {"data": "method"},
                    {"data": "amount"},
                    {"data": "status"},
                    {"data": "officer"}
                ],
                "columnDefs": [
                    {"targets": [0, 1, 4], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[2, 'asc']],
            });

            let commissionTable = $('#commissionTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": true,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': "{{ route('commission.index')}}",
                    pages: 5, // number of pages to cache
                    data: function (data) {
                        data.user_id = userID;
                    }
                },
                "lengthChange": false,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "oLanguage": {
                    "sLengthMenu": "Show _MENU_ ",
                },
                "pageLength": 15,
                "bFilter": true,
                "bInfo": false,
                "searching": false,
                "columns": [
                    {"data": "id"},
                    {"data": "date"},
                    {"data": "base_amount"},
                    {
                        "mRender": function (data, type, row) {
                            return (row['type'] == 'debit') ? row['amount'] : 0;
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            return (row['type'] == 'credit') ? row['amount'] : 0;
                        }
                    },
                    {"data": "purpose"}
                ],
                "columnDefs": [
                    {"targets": [0, 1, 4], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[2, 'asc']],
            });

            $('#date_from').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
                endDate: "+30d"
            }).on('changeDate', function (ev) {
                date_from = $(this).val();
                $(this).datepicker('hide');
                bookingTable.draw()
            });

            $('#date_to').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
                endDate: "+30d"
            }).on('changeDate', function (ev) {
                date_to = $(this).val();
                $(this).datepicker('hide');
                bookingTable.draw()
            });

            //Initialize Select2 Elements
            $('.select2').select2({
                theme: 'bootstrap4'
            });

            //Initialize Select2 Elements
            $('#partnerVehicles').select2({
                placeholder: "Pick some items",
                theme: 'bootstrap4',
                allowClear: true,
                cache: false,
                ajax: {
                    url: "{{ route('partner.suggest.vehicles') }}",
                    dataType: 'json',
                    type: "GET",
                    quietMillis: 50,
                    data: function (term) {
                        return {
                            term: term.term
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
                endDate: "+30d"
            }).on('changeDate', function (ev) {
                $(this).datepicker('hide');
            });

            //Timepicker
            $('#timepicker').datetimepicker({
                format: 'LT',
                autoclose: true
            });

            //Datemask dd/mm/yyyy
            $('#datemask').inputmask('dd/mm/yyyy', {'placeholder': 'dd/mm/yyyy'});
            //Datemask2 mm/dd/yyyy
            $('#datemask2').inputmask('mm/dd/yyyy', {'placeholder': 'mm/dd/yyyy'});
            //Money Euro
            $('[data-mask]').inputmask();
            //Timepicker

        });
    </script>
@endsection
