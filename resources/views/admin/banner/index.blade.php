@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link clickableTab active" data-type="1" id="active-tab" data-toggle="tab"
                   href="#active" role="tab" aria-controls="active" aria-selected="true">Active</a>
            </li>
            <li class="nav-item">
                <a class="nav-link clickableTab" data-type="2" id="inactive-tab" data-toggle="tab"
                   href="#inactive" role="tab" aria-controls="inactive" aria-selected="false">Inactive</a>
            </li>
            @if(auth()->user()->hasAnypermission(['coupon-create']) || auth()->user()->hasRole('admin'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('dashboard.banner.create') }}"><i class="fa fa-plus"></i> Add new banner</a>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <div id="advancedFilter" class="d-none">
                    <div class="row pt-2">
                        <div class="col-sm-4">
                            <select class="form-control select2" id="filterMerchant" id="items"
                                    data-placeholder="Select merchant" data-dropdown-css-class="select2-purple"
                                    style="width: 100%;">
                                <option value="">Select merchant</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <select class="form-control select2" id="filterRoutes" id="items"
                                    data-placeholder="Select route" data-dropdown-css-class="select2-purple"
                                    style="width: 100%;">
                                <option value="">Select route</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <select class="form-control" id="status">
                                <option value="">Select status</option>
                                <option value="1" selected>Enabled</option>
                                <option value="2">Disabled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Action
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item couponBulkAction" data-type="enable" href="#">Enable</a>
                        <a class="dropdown-item couponBulkAction" data-type="disable" href="#">Disable</a>
                    </div>
                </div>
                <table class="table table-striped table-bordered projects" id="dataTable">
                    <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="checkedAll" value="0"></th>
                        <th style="width: 3%">
                            <div><i class="fa fa-image"></i></div>
                        </th>
                        <th>
                            <div>Name</div>
                        </th>
                        <th>
                            <div>Validity</div>
                        </th>
                        <th>
                            <div>Created by</div>
                        </th>
                        <th>
                            <div>Status</div>
                        </th>
                        <th style="v-align:middle;text-align:center;" class="align-middle">
                            <div><i class="fa fa-wrench"></i></div>
                        </th>
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
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
    <style type="text/css">
        /***
      User Profile Sidebar by @keenthemes
      A component of Metronic Theme - #1 Selling Bootstrap 3 Admin Theme in Themeforest: https://j.mp/metronictheme
      Licensed under MIT
      ***/

        body {
            background: #F1F3FA;
        }

        .nav-tabs .nav-item {
            margin-right: 8px;
        }

        .nav-tabs .nav-link {
            border-top-left-radius: .25rem;
            border-top-right-radius: .25rem;
            border: 1px solid #eee;
            background: #e4e2e2;
            color: #000;
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

        #advancedFilter {
            position: absolute;
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
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        let can_edit = false, can_active = false, can_inactive = false, can_delete = false;
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
        var url = "{{ route('dashboard.banner.index') }}";
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        $(function () {
            $('.couponBulkAction').click(function (e) {
                e.defaultPrevented;
                let items = $('#dataTable input.couponItemChecked:checked');
                let type = $(this).data('type');
                if ($(items).length > 0) {
                    let ids = [];
                    $(items).each(function (e) {
                        ids.push($(this).data('id'));
                    });

                    if (ids.length > 0 && type) {
                        $.ajaxSetup({
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            }
                        });
                        $.ajax({
                            dataType: "json",
                            type: "POST",
                            url: "{{ route('dashboard.coupon.action') }}",
                            data: {ids: ids.join(), type: type},
                            success: function (response, textStatus, xhr) {
                                if (response.status == true) {
                                    table.draw();
                                    Toast.fire({
                                        icon: response.label,
                                        title: response.content
                                    });
                                }
                            }
                        });
                    }
                } else {
                    Toast.fire({
                        icon: "error",
                        title: "Sorry! no items selected"
                    });
                }
            });
            $('#advancedFilterBtn').click(function (e) {
                e.defaultPrevented;
                $(this).toggleClass('active');
                $('#advancedFilter').toggleClass('d-none');
            });

            //check all item
            $('#checkedAll').on("click", function (e) {
                e.defaultPrevented;
                var parent = $(this).parents('table');
                if ($(this).is(":checked")) {
                    $(parent).find(".couponItemChecked").each(function () {
                        $(this).prop('checked', true);
                    });
                } else {
                    $(parent).find(".couponItemChecked").each(function () {
                        $(this).prop('checked', false);
                    });
                }
            });
            var customFilter = $('#advancedFilter');
            var route = $(customFilter).find('select#filterRoutes');
            var merchant = $(customFilter).find('select#filterMerchant');
            var keyword = $(customFilter).find('input#keywords');
            var status = 1;
            var search = $(customFilter).find('button#search');
            var table = $('#dataTable').DataTable({
                "processing": true,
                "serverSide": true,
                "deferRender": false,
                "autoWidth": false,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': url,
                    pages: 5, // number of pages to cache
                    'data': function (data) {
                        // Read values
                        data.keyword = $(keyword).val();
                        data.merchant = $(merchant).val();
                        data.route = $(route).val();
                        data.status = status;
                    }
                },
                dom: 'lBfrtip',
                "lengthChange": false,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "oLanguage": {
                    "sLengthMenu": "Show _MENU_ ",
                },
                "pageLength": 25,
                "bFilter": false,
                "bInfo": true,
                "searching": false,
                "columns": [
                    {
                        "mRender": function (data, type, row) {
                            return '<input type="checkbox" value="' + row['id'] + '" data-id="' + row['id'] + '" class="couponItemChecked">';
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            let photo = row['poster'];
                            return '<img src="' + photo + '" class="table-avatar" />';
                        }
                    },
                    {"data": "name"},
                    {
                        "mRender": function (data, type, row) {
                            let offer_start = new Date(row['offer_start']);
                            let offer_end = new Date(row['offer_end']);
                            let startMonth = offer_start.getMonth() + 1;
                            let endMonth = offer_end.getMonth() + 1;
                            offer_start = offer_start.getDate() + '/' + startMonth + '/' + offer_start.getFullYear();
                            offer_end = offer_end.getDate() + '/' + endMonth + '/' + offer_end.getFullYear();
                            return offer_start + ' - ' + offer_end;
                        }
                    },
                    {"data": "user.name"},
                    {
                        "mRender": function (data, type, row) {
                            return (row['status'] == 1) ? 'Active' : 'Disable';
                        }
                    },
                    {
                        "mRender": function (data, type, row) {
                            let str = "<a href='/admin/vehicle/banner/edit/" + row['id'] + "' class='btn btn-secondary btn-sm'><i class='fa fa-edit'></i></a>";
                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0, 1, 2, 3], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[2, 'asc']]
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

            //Custom Filters ( Author search )
            $('.clickableTab').click(function (e) {
                e.defaultPrevented;
                status = $(this).data('type');
                table.draw();
            });
        });
    </script>
@endsection
