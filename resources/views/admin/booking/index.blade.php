@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active clickableTab" data-type="0" id="pending-tab" data-toggle="tab" href="#pending"
                   role="tab" aria-controls="pending" aria-selected="true">Pending</a>
            </li>
            <li class="nav-item">
                <a class="nav-link clickableTab" data-type="3" id="advance-tab" data-toggle="tab" href="#advance"
                   role="tab" aria-controls="advance" aria-selected="true">Advance</a>
            </li>
            <li class="nav-item">
                <a class="nav-link clickableTab" data-type="1" id="completed-tab" data-toggle="tab" href="#completed"
                   role="tab" aria-controls="completed" aria-selected="false">Completed</a>
            </li>
            <li class="nav-item">
                <a class="nav-link clickableTab" data-type="2" id="failed-tab" data-toggle="tab" href="#failed"
                   role="tab" aria-controls="failed" aria-selected="false">Failed</a>
            </li>
        </ul>
        <div class="tab-content" id="myTabContent" style="background: #fff;">
            <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                <div id="advancedFilter">
                    <div class="row pt-2">
                        <div class="col-sm-1 d-none" id="cancelButtonParent">
                            <button class="btn btn-danger" id="cancelButton"><i class="fa fa-times-circle"></i> Cancel</button>
                        </div>
                        <div class="col-sm-3" id="keywordParent">
                            <input type="text" class="form-control" id="keywords" placeholder="search">
                        </div>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <input type="text" id="date_from" class="form-control datepicker"
                                       placeholder="Booking date">
                                <span class="input-group-addon m-2">
                                    To
                                </span>
                                <input type="text" id="date_to" class="form-control datepicker"
                                       placeholder="Booking date">
                            </div>
                        </div>
                        <div class="col-sm-1">
                            <div class="input-group">
                                <input type="text" id="journey_date" class="form-control datepicker"
                                       placeholder="Journey date">
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <select class="form-control select2" id="filterMerchant" data-placeholder="Select merchant"
                                    data-dropdown-css-class="select2-purple" style="width: 100%;">
                                <option value="">Select merchant</option>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <select class="form-control select2" id="filtervehicle" data-placeholder="Select vehicle"
                                    data-dropdown-css-class="select2-purple" style="width: 100%;">
                                <option value="">Select vehicle</option>
                            </select>
                        </div>
                    </div>
                </div>
                <table class="table table-striped projects" id="dataTable">
                    <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="checkedAll" value="1">
                        </th>
                        <th style="width: 1%"><i class="fas fa-image"></i></th>
                        <th style="width: 20%"> Customer Info</th>
                        <th style="width:5%">Items</th>
                        <th>Cancelled Items</th>
                        <th>Booking date</th>
                        <th>Journey dates</th>
                        <th>Subtotal</th>
                        <th>Discount</th>
                        <th>VAT</th>
                        <th>Service Charge</th>
                        <th>Honorium charge</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Dues</th>
                        <th>Platform</th>
                        <th style="width: 8%" class="text-center">Status</th>
                        <th style="width: 60px"><i class="fas fa-cog"></i></th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
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
    <link rel="stylesheet" type="text/css"
          href="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/css/responsive.dataTables.min.css') }}"/>
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
    <script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/pdfmake-0.1.36/pdfmake.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/pdfmake-0.1.36/vfs_fonts.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.flash.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/dataTable/Buttons-1.5.6/js/buttons.print.min.js') }}"></script>
    <script>
        let can_edit = false, can_active = false, can_inactive = false, can_delete = false, visibility = true;
        @can('order-edit')
            can_edit = true;
        @endcan
            @can('order-active')
            can_active = true;
        @endcan
            @can('order-inactive')
            can_inactive = true;
        @endcan
            @can('order-delete')
            can_delete = true;
        @endcan
            @if(Auth::user()->type == 'merchant' && Auth::user()->merchant['vat_visibility'] == 0)
            visibility = false;
        @endif
        var url = "{{ route('dashboard.booking.index') }}";
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        let service = "{{ $service_type }}";
        $(function () {
            $('#advancedFilterBtn').click(function (e) {
                e.defaultPrevented;
                $(this).toggleClass('active');
                $('#advancedFilter').toggleClass('d-none');
            });
            let type = 0;
            let status = 0;
            var customFilter = $('#advancedFilter');
            var keyword = $('input#keywords');
            var merchant = $(customFilter).find('select#filterMerchant');
            var vehicle = $(customFilter).find('select#filtervehicle');
            var date_from = $(customFilter).find('input#date_from');
            var date_to = $(customFilter).find('input#date_to');
            var journey_date = $(customFilter).find('input#journey_date');
            var search = $(customFilter).find('button#search');
            var table = $('#dataTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": true,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': url,
                    pages: 5, // number of pages to cache
                    'data': function (data) {
                        // Read values
                        data.keyword = $(keyword).val();
                        data.merchant = $(merchant).val();
                        data.vehicle = $(vehicle).val();
                        data.date_from = $(date_from).val();
                        data.date_to = $(date_to).val();
                        data.journey_date = $(journey_date).val();
                        data.service_type = service;
                        data.status = status;
                        data.type = type;
                    }
                },
                "lengthChange": true,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "oLanguage": {
                    "sLengthMenu": "Show _MENU_ ",
                },
                "pageLength": 25,
                "bFilter": true,
                "bInfo": true,
                "searching": false,
                "dom": "lBfrtip",
                "columns": [
                    {
                        "mRender": function(data, type, row) {
                            return (row['status'] === 'COMPLETE') ? '<input type="checkbox" class="checkedItem" data-id="' + row['id'] + '">' : '';
                        }
                    },
                    {"data": "id"},
                    {
                        "mRender": function (data, type, row) {
                            return '<a href="/admin/customer/show/' + row['customer_id'] + '" class="table-avatar">' + row['customer_name'] + ', ' + row['customer_email'] + ', ' + row['customer_mobile'] + '</a>';
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            return '<a href="/admin/booking/show/' + row['id'] + '" class="btn btn-secondary">' + row['booking_items'] + '</a>'
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            return '<a href="/admin/booking/show/' + row['id'] + '"  class="btn btn-outline-danger">' + row['cancelled_items'] + '</a>'
                        }
                    },
                    {"data": "created_at"},
                    {"data": "journey_dates"},
                    {"data": "total"},
                    {"data": "discount"},
                    {"data": "vat_total"},
                    {"data": "charge_total"},
                    {"data": "honorium_charge"},
                    {"data": "subtotal"},
                    {"data": "paid_amount"},
                    {"data": "dues"},
                    {"data": "platform"},
                    {
                        "mRender": function (data, type, row) {
                            return row['status'];
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            var str = "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'>";

                            str += "<a href='/admin/booking/show/" + row['id'] + "' class='dropdown-item' data-booking-id='" + row['id'] + "'><i class='fa fa-eye'></i> View</a>";
                            if (can_active) {
                                str += "<a href='/admin/booking/edit/" + row['id'] + "' class='dropdown-item'><i class='fa fa-edit'></i> Edit</a>";
                            }
                            if (parseInt(row['status']) == 1) {
                                if (can_inactive) {
                                    str += "<a href='#' class='dropdown-item booking-action' data-action='inactive' data-booking-id='" + row['id'] + "'><i class='fa fa-ban'></i> Inactive</a>";
                                }
                            } else if (parseInt(row['status']) == 0) {
                                if (can_active) {
                                    str += "<a href='#' class='dropdown-item booking-action' data-action='active' data-booking-id='" + row['id'] + "'><i class='fa fa-check'></i> Active</a>";
                                }
                            } else {
                                if (can_active) {
                                    str += "<a href='#' class='dropdown-item booking-action' data-action='reactive' data-booking-id='" + row['id'] + "'><i class='fa fa-check'></i> Re-active</a>";
                                }
                            }
                            if (row['deleted_at'] == '') {
                                if (can_delete) {
                                    str += "<a href='#' class='dropdown-item booking-action' data-action='softdelete' data-booking-id='" + row['id'] + "'><i class='fa fa-times'></i> Delete</a>";
                                }
                            } else {
                                if (can_delete) {
                                    str += "<a href='#' class='dropdown-item booking-action' data-action='delete' data-booking-id='" + row['id'] + "'><i class='fa fa-times'></i> Delete</a>";
                                }
                            }
                            str += "<a href='#' class='dropdown-item booking-action' data-action='summary' data-booking-id='" + row['id'] + "'><i class='fa fa-chart-bar'></i> Summary</a>";
                            str += "</div> </div>";
                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0, 1, 2, 6], "searchable": false, "orderable": false, "visible": true},
                    {"visible": visibility, "targets": 9}
                ],
                "order": [[4, 'desc']],
                buttons: [
                    {
                        extend:    'copy',
                        text:      '<i class="fas fa-copy"></i> ',
                        titleAttr: 'Copy to clipboard',
                        className: 'btn btn-default',
                        messageTop: 'Booking list',
                        footer: true,
                        exportOptions: {
                            columns: [0, 1, 2, 3,4,5,6,7,8,9, 10, 11, 12, 13]
                        }
                    },
                    {
                        extend:    'print',
                        text:      '<i class="fa fa-print"></i> ',
                        titleAttr: 'Print bookings',
                        className: 'btn btn-info',
                        messageTop: 'Booking list',
                        footer: true,
                        exportOptions: {
                            columns: [0, 1, 2, 3,4,5,6,7,8,9, 10, 11, 12, 13]
                        }
                    },
                    {
                        extend:    'csv',
                        text:      '<i class="fa fa-file-excel"></i> ',
                        titleAttr: 'Export bookings',
                        className: 'btn btn-success',
                        messageTop: 'Booking list',
                        footer: true,
                        exportOptions: {
                            columns: [0, 1, 2, 3,4,5,6,7,8,9, 10, 11, 12, 13]
                        }
                    }
                ]
            });

            $('.clickableTab').click(function () {
                type = $(this).data('type');
                status = type;
                table.draw();
                if(parseInt(type) === 1) {
                    $('#cancelButtonParent').removeClass('d-none');
                    $('#keywordParent').addClass('col-sm-2').removeClass('col-sm-3');
                } else {
                    $('#cancelButtonParent').addClass('d-none');
                    $('#keywordParent').addClass('col-sm-3').removeClass('col-sm-2');
                }
            });

            let hash = window.location.hash.substr(1);

            if(hash == 'pending') {
                status = 0;
                table.draw();
                $('#keywordParent').addClass('col-sm-3').removeClass('col-sm-2');
            } else if(hash == 'advance'){
                status = 3;
                table.draw();
                $('#keywordParent').addClass('col-sm-3').removeClass('col-sm-2');
            } else if(hash == 'completed'){
                status = 1;
                table.draw();
                $('#cancelButtonParent').removeClass('d-none');
                $('#keywordParent').addClass('col-sm-2').removeClass('col-sm-3');
            } else if(hash == 'failed'){
                status = 2;
                table.draw();
                $('#keywordParent').addClass('col-sm-3').removeClass('col-sm-2');
            }

            //Click on Search Button
            $(search).click(function (e) {
                table.draw();
            });

            //Custom Filters ( title search )
            $(keyword).keyup(function (event) {
                var keycode = (event.keyCode ? event.keyCode : event.which);
                // if(keycode == '13'){
                table.draw();
                // }
            });

            //Custom Filters ( Author search )
            $(merchant).on('select2:select', function (e) {
                e.defaultPrevented;
                table.draw();
                $(vehicle).val("").trigger('change');
            });

            $(merchant).on('select2:clear', function (e) {
                e.defaultPrevented;
                table.draw();
                $(vehicle).val("").trigger('change');
            });

            $(merchant).select2({
                placeholder: "Select merchant",
                theme: 'bootstrap4',
                allowClear: true,
                cache: false,
                ajax: {
                    url: "{{ route('dashboard.merchant.suggest') }}",
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

            //Custom Filters ( Author search )
            $(vehicle).on('select2:select', function (e) {
                e.defaultPrevented;
                table.draw();
            });

            //Custom Filters ( Author search )
            $(vehicle).on('select2:clear', function (e) {
                e.defaultPrevented;
                table.draw();
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
                            merchant: $(merchant).val(),
                            service_type: service
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

            //check all item
            $('#checkedAll').on("click", function (e) {
                e.defaultPrevented;
                var parent = $(this).parents('table');
                if ($(this).is(":checked")) {
                    $(parent).find(".checkedItem").each(function () {
                        $(this).prop('checked', true);
                    });
                } else {
                    $(parent).find(".checkedItem").each(function () {
                        $(this).prop('checked', false);
                    });
                }
            });

            $('#cancelButton').click(function (e) {
                e.defaultPrevented;
                ids = [];
                let items = $('#dataTable input.checkedItem:checked');
                var url = "{{ route('booking.cancel.batch') }}";
                let self = $(this);
                if ($(items).length > 0) {
                    $(items).each(function (e) {
                        ids.push($(this).data('id'));
                    });

                    if (ids.length > 0) {
                        let confirmed = confirm('Are you sure to cancel all selected items');

                        if (confirmed) {
                            $.ajaxSetup({
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                            $.ajax({
                                dataType: "json",
                                type: "POST",
                                url: url,
                                data: {ids: ids.join()},
                                success: function (response, textStatus, xhr) {
                                    table.draw();
                                    if (response.status === true) {
                                        table.draw();
                                        Toast.fire({
                                            icon: response.label,
                                            title: response.content
                                        });
                                    }
                                }
                            });
                        }
                    }
                } else {
                    Toast.fire({
                        icon: "error",
                        title: "Sorry! no items selected"
                    });
                }

                return false;
            });

            //Datemask dd/mm/yyyy
            $('#datemask').inputmask('dd/mm/yyyy', {'placeholder': 'dd/mm/yyyy'})
            //Money Euro
            $('[data-mask]').inputmask()
            $('.datepicker').click(function (e) {
                $(this).val("");
            });

            $('.datepicker').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
                // startDate: "-0d",
                // endDate: "+360d"
                endDate: "+30d"
            }).on('changeDate', function (ev) {
                $(this).datepicker('hide');
                table.draw();
            });

            //Custom Filters ( Author search )
            $(status).change(function () {
                table.draw();
            });

            // $('#myModal').modal('show');
            $('table').on('click', '.booking-action', function () {
                console.log(this);
                let url = "{{ route('dashboard.booking.index') }}";
                var action = $(this).data('action');
                var id = $(this).data('booking-id');
                if (action == 'summary') {
                    url = "{{ route('dashboard.booking.summary') }}";
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    $.ajax({
                        dataType: "json",
                        type: "POST",
                        url: url,
                        data: {id: id},
                        success: function (response, textStatus, xhr) {
                            if (response.status == true) {
                                let modalTitle = $(defaultModal).find('.modal-title').text('View summary');
                                let modalBody = $(defaultModal).find('.modal-body');
                                $(modalBody).html("");
                                $(modalBody).append('<table class="table">' +
                                    '<tr>' +
                                    '<th>Booking ID</th>' +
                                    '<td>#' + response.stat.booking_id + '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<th>Booking date</th>' +
                                    '<td>' + response.stat.booking_date + '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<th>Customer Name</th>' +
                                    '<td>' + response.stat.customer_name + '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<th>Customer mobile</th>' +
                                    '<td>' + response.stat.customer_mobile + '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<th>Booking Amount</th>' +
                                    '<td>' + response.stat.booking_total.toFixed(2) + '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<th>Discount</th>' +
                                    '<td>' + response.stat.booking_discount.toFixed(2) + '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<th>Service charge</th>' +
                                    '<td>' + response.stat.booking_charge.toFixed(2) + '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<th>VAT</th>' +
                                    '<td>' + response.stat.booking_vat.toFixed(2) + '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<th>Bank charge</th>' +
                                    '<td>' + response.stat.booking_bank_charge.toFixed(2) + '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<th>Total Paid</th>' +
                                    '<td>' + response.stat.booking_payable.toFixed(2) + '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<th>Cancelled amount</th>' +
                                    '<td>' + response.stat.cancelled_amount.toFixed(2) + '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<th>Balance</th>' +
                                    '<td>' + (parseFloat(response.stat.booking_store_amount) - parseFloat(response.stat.cancelled_amount)).toFixed(2) + '</td>' +
                                    '</tr>' +
                                    '</table>');
                                $(defaultModal).find('.modal-footer').hide();
                                $(defaultModal).modal('show');
                            } else {
                                Toast.fire({
                                    icon: response.label,
                                    title: response.content
                                });
                            }
                        }
                    });
                } else {
                }
                return false;
            });
        });
    </script>
@endsection
