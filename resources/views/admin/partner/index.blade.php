@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link clickableTab active" data-type="{{ \Jolzatra\Constants\AppConst::AGENT_ACTIVE }}" id="active-tab" data-toggle="tab"
                   href="#active" role="tab" aria-controls="active" aria-selected="true">Active</a>
            </li>
            <li class="nav-item">
                <a class="nav-link clickableTab" data-type="{{ \Jolzatra\Constants\AppConst::AGENT_INACTIVE }}" id="inactive-tab" data-toggle="tab"
                   href="#inactive" role="tab" aria-controls="inactive" aria-selected="false">Inactive</a>
            </li>
            @if(auth()->user()->hasAnypermission(['partner-create']) || auth()->user()->hasRole('admin'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('partner.create') }}"><i class="fa fa-plus"></i> Add new
                        partner</a>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <div id="advancedFilter">
                    <div class="row pt-2">
                        <div class="col-sm-4">
                            <input type="text" class="form-control" id="keywords" placeholder="search">
                        </div>
                        <div class="col-sm-6">
                            <div class="input-group date">
                                <input type="text" id="date_from" class="form-control datepicker"
                                       placeholder="Joining date">
                                <span class="input-group-addon m-2">
                                    To
                                </span>
                                <input type="text" id="date_to" class="form-control datepicker"
                                       placeholder="Joining date">
                            </div>
                        </div>
                    </div>
                </div>
                <table class="table table-striped table-bordered projects display responsive nowrap" id="dataTable">
                    <thead>
                    <tr>
                        <th style="width: 3%"><div><i class="fa fa-image"></i></div></th>
                        <th style="width: 20%"><div>Name</div></th>
                        <th><div>Mobile</div></th>
                        <th><div>Email</div></th>
                        <th><div>Address</div></th>
                        <th><div>Incentive</div></th>
                        <th><div>Joining Date</div></th>
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
        @can('partner-edit')
            can_edit = true;
        @endcan
            @can('partner-active')
            can_active = true;
        @endcan
            @can('partner-inactive')
            can_inactive = true;
        @endcan
            @can('partner-delete')
            can_delete = true;
        @endcan
            @if(auth()->user()->hasRole('admin'))
            can_shadow = true;
        @endif
        var url = "{{ route('partner.index') }}";
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
            var status = 1;
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
                            return '<a href="/admin/partner/' + row['id'] + '" class="table-avatar">' + row['name'] + '</a>';
                        }
                    },
                    {"data": "mobile"},
                    {"data": "email"},
                    {"data": "address"},
                    {"data": "incentive"},
                    {"data": "created_at"},
                    {"data": "status"},
                    {
                        "mRender": function (data, type, row) {
                            var str = "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'> " +
                                "<a href='/admin/partner/" + row['id'] + "' class='dropdown-item' data-merchant-id='" + row['id'] + "'><i class='fa fa-eye'></i> View</a> " +
                                "<a href='/admin/partner/" + row['id'] + "/edit' class='dropdown-item'><i class='fa fa-edit'></i> Edit</a>";
                            if (row['deleted_at'] != null) {
                                if (can_delete) {
                                    str += "<a href='#' class='dropdown-item merchant-action' data-action='restore' data-merchant-id='" + row['id'] + "'><i class='fa fa-check'></i> Restore</a>";
                                }
                            } else if (parseInt(row['status']) != 1) {
                                if (can_active) {
                                    str += "<a href='#' class='dropdown-item merchant-action' data-action='active' data-merchant-id='" + row['id'] + "'><i class='fa fa-check'></i> Active</a>";
                                }
                            } else {
                                if (can_delete) {
                                    str += "<a href='#' class='dropdown-item merchant-action' data-action='delete' data-merchant-id='" + row['id'] + "'><i class='fa fa-times'></i> Delete</a>";
                                }
                            }
                            str += "</div> </div>";
                            return str;
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
