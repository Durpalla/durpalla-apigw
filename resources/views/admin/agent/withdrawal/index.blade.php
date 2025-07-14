@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link clickableTab active" data-type="{{ \Jolzatra\Constants\AppConst::WITHDRAWAl_PENDING }}" id="pending-tab" data-toggle="tab"
                   href="#pending" role="tab" aria-controls="pending" aria-selected="true">Pending</a>
            </li>
            <li class="nav-item">
                <a class="nav-link clickableTab" data-type="{{ \Jolzatra\Constants\AppConst::WITHDRAWAl_COMPLETE }}" id="completed-tab" data-toggle="tab"
                   href="#completed" role="tab" aria-controls="completed" aria-selected="false">Completed</a>
            </li>
            <li class="nav-item">
                <a class="nav-link clickableTab" data-type="{{ \Jolzatra\Constants\AppConst::WITHDRAWAl_CANCELLED }}" id="cancelled-tab" data-toggle="tab"
                   href="#cancelled" role="tab" aria-controls="cancelled" aria-selected="false">Cancelled</a>
            </li>
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
{{--                <div id="advancedFilter">--}}
{{--                    <div class="row pt-2">--}}
{{--                        <div class="col-sm-4">--}}
{{--                            <input type="text" class="form-control" id="keywords" placeholder="search">--}}
{{--                        </div>--}}
{{--                        <div class="col-sm-6">--}}
{{--                            <div class="input-group date">--}}
{{--                                <input type="text" id="date_from" class="form-control datepicker"--}}
{{--                                       placeholder="Joining date">--}}
{{--                                <span class="input-group-addon m-2">--}}
{{--                                    To--}}
{{--                                </span>--}}
{{--                                <input type="text" id="date_to" class="form-control datepicker"--}}
{{--                                       placeholder="Joining date">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="col-sm-2">--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
                <table class="table table-striped table-bordered projects display responsive nowrap" id="dataTable">
                    <thead>
                    <tr>
                        <th style="width: 3%"><div><i class="fa fa-image"></i></div></th>
                        <th style="width: 20%"><div>Name</div></th>
                        <th><div>Date request</div></th>
                        <th><div>Method</div></th>
                        <th><div>Transaction</div></th>
                        <th><div>Amount</div></th>
                        <th><div>Officer</div></th>
                        <th style="width: 8%" class="text-center"><div>Status</div></th>
                        <th style="width: 45px" class="text-center"><div><i class="fas fa-cog"></i></div></th>
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
        #advancedFilter, #advancedFilter2 {
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

        .datepicker {
            width: fit-content;
        }
    </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.responsive.min.js') }}"></script>
    <script>
        let can_edit = false, can_active = false, can_inactive = false, can_delete = false, can_shadow = false;
        @can('merchant-edit')
            can_edit = true;
        @endcan
            @can('merchant-active')
            can_active = true;
        @endcan
            @can('merchant-inactive')
            can_inactive = true;
        @endcan
            @can('merchant-delete')
            can_delete = true;
        @endcan
            @if(auth()->user()->hasRole('admin'))
            can_shadow = true;
        @endif
        var url = "{{ route('withdrawal.index') }}";
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';

        $(function () {
            $('#advancedFilterBtn').click(function (e) {
                e.defaultPrevented;
                $(this).toggleClass('active');
                $('#advancedFilter').toggleClass('d-none');
            });
            var customFilter = $('#advancedFilter');
            var keyword = $('input#keywords');
            var route = $(customFilter).find('select#filterRoutes');
            var date_from = $(customFilter).find('input#date_from');
            var date_to = $(customFilter).find('input#date_to');
            var status = {{\Jolzatra\Constants\AppConst::WITHDRAWAl_PENDING}};
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
                        data.route = $(route).val();
                        data.date_from = $(date_from).val();
                        data.date_to = $(date_to).val();
                        data.status = status;
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
                "columns": [
                    {
                        "mRender": function (data, type, row) {
                            return '<img src="' + row['photo'] + '" class="table-avatar">';
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            return '<a href="/admin/agent/withdrawal/' + row['id'] + '" class="table-avatar">' + row['name'] + '</a>';
                        }
                    },
                    {"data": "date"},
                    {"data": "method"},
                    {"data": "transaction"},
                    {"data": "amount"},
                    {"data": "officer"},
                    {"data": "status"},
                    {
                        "mRender": function (data, type, row) {
                            return "<a href='/admin/agent/withdrawal/" + row['id'] + "' class='btn btn-default' data-withdrawal-id='" + row['id'] + "'><i class='fa fa-eye'></i></a>";
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0, 1, 5], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[5, 'desc']],
                buttons: [
                    'copy', 'excel', 'pdf', 'print'
                ]
            });

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
            $(route).on('select2:select', function (e) {
                e.defaultPrevented;
                table.draw();
            });

            $(route).on('select2:clear', function (e) {
                e.defaultPrevented;
                table.draw();
            });

            $(route).select2({
                placeholder: "Pick some items",
                theme: 'bootstrap4',
                allowClear: true,
                cache: false,
                ajax: {
                    url: "{{ route('dashboard.routes.suggest') }}",
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

            $('.clickableTab').click(function(e) {
                e.defaultPrevented;
                status = $(this).data('type');
                table.draw();
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
            $('table').on('click', '.merchant-action', function () {
                console.log(this);
                var url = "{{ route('dashboard.merchant.action') }}";
                var action = $(this).data('action');
                var id = $(this).data('merchant-id');
                if (action == 'request') {

                } else {
                    var data = {action: action, id: id};
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You are going to " + action + " this merchant account.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes'
                    }).then((result) => {
                        if (result.value) {

                            $.ajaxSetup({
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                            $.ajax({
                                dataType: "json",
                                type: "POST",
                                url: url,
                                data: data,
                                success: function (response, textStatus, xhr) {
                                    if (response.status == true) {
                                        table.draw();
                                        Toast.fire({
                                            icon: response.label,
                                            title: response.content
                                        });
                                        // Swal.fire(
                                        //     'Deleted!',
                                        //     'Your file has been deleted.',
                                        //     'success'
                                        // );
                                    }
                                }
                            });

                        }
                    });
                }
                return false;
            });
        });
    </script>
@endsection
