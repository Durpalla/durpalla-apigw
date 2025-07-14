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
                   href="#inactive" role="tab" aria-controls="inactive" aria-selected="false">Expired</a>
            </li>
            @if(auth()->user()->hasAnypermission(['social-create']) || auth()->user()->hasRole('admin'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('dashboard.social.create') }}"><i class="fa fa-plus"></i> Add new poster</a>
                </li>
            @endif
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <div id="advancedFilter" class="d-none">
                    <div class="row pt-2">
                        <div class="col-sm-4">
                        </div>
                        <div class="col-sm-4">
                        </div>
                    </div>
                </div>
                <table class="table table-striped table-bordered projects" id="dataTable">
                    <thead>
                    <tr>
                        <th style="width: 3%">
                            <div><i class="fa fa-image"></i></div>
                        </th>
                        <th>
                            <div>Poster name</div>
                        </th>
                        <th>
                            <div>vehicle</div>
                        </th>
                        <th>
                            <div>Router</div>
                        </th>
                        <th>
                            <div>Valid till</div>
                        </th>
                        <th>
                            <div>Created by</div>
                        </th>
                        <th>
                            <div>Share count</div>
                        </th>
                        <th style="min-width:25%;v-align:middle;text-align:center;" class="align-middle">
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
    <script>(function(d, s, id) {
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) return;
            js = d.createElement(s); js.id = id;
            js.src = "https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v3.0";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));</script>
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
        var url = "{{ route('dashboard.social.index') }}";
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        $(function () {
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
                            return '<img src="' + row['photo'] + '" class="table-avatar" />';
                        }
                    },
                    {"data": "name"},
                    {"data": "vehicle_name"},
                    {"data": "route_name"},
                    {"data": "validity"},
                    {"data": "created_by"},
                    {"data": "counter"},
                    {
                        "mRender": function (data, type, row) {
                            let str = "<a href='/admin/vehicle/social/edit/" + row['id'] + "' class='btn btn-secondary btn-sm'><i class='fa fa-edit'></i></a>";
                            str += '<a href="/social/share/'+ row['id'] +'" class="btn btn-primary btn-sm ml-2"><i class="fa fa-eye"></i></a>';
                            str += '<div class="fb-share-button" data-size="large" data-href="/social/share/' + row['id'] + '" data-layout="button_count">';
                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0, 1, 2, 3], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[1, 'asc']]
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
