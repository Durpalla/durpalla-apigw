@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="card" style="background-color: none;">
                    <div class="card-header" style="padding: .65rem 1.25rem;">
                        <h3 class="card-title">{{ $title ?? '' }}
                            @canany(['vehicle-create'])
                                <a href="{{ route('dashboard.sponsor.create') }}" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i> Add new</a>
                            @endcan
                        </h3>
                        <div class="card-tools pt-2">
                            <div class="btn-group" role="group" aria-label="Basic example">
                                <div class="input-group input-group-sm" role="group" aria-label="Basic example">
                                    <input type="text" class="form-control" id="keywords" placeholder="search">
                                    <button class="btn btn-warning btn-sm"><i class="fa fa-search"></i></button>
                                    <button class="btn btn-sm ml-2" id="advancedFilterBtn"><i class="fa fa-sliders-h"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="clearfix"></div>
                        <div id="advancedFilter" class="d-none">
                            <div class="row pt-2">
                                <div class="col-sm-4">
                                </div>
                                <div class="col-sm-4">
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
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <table class="table table-striped table-bordered projects" id="dataTable">
                            <thead>
                            <tr>
                                <th style="width: 3%"><div><i class="fa fa-image"></i></div></th>
                                <th><div>Title</div></th>
                                <th><div>Created At</div></th>
                                <th style="v-align:middle;text-align:center;" class="align-middle"><div><i class="fa fa-wrench"></i></div></th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection

@section('header')
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
    <script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script>
        let can_edit = false, can_active = false, can_inactive = false, can_delete = false;
        @can('sponsor-edit')
            can_edit = true;
        @endcan
            @can('sponsor-active')
            can_active = true;
        @endcan
            @can('sponsor-inactive')
            can_inactive = true;
        @endcan
            @can('sponsor-delete')
            can_delete = true;
            @endcan
        var url = "{{ route('dashboard.sponsor.index') }}";
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';
        $(function(){
            $('#advancedFilterBtn').click(function(e) {
                e.defaultPrevented;
                $(this).toggleClass('active');
                $('#advancedFilter').toggleClass('d-none');
            });

            var customFilter = $('#advancedFilter');
            var keyword = $(customFilter).find('input#keywords');
            var status = $(customFilter).find('select#status');
            var search = $(customFilter).find('button#search');
            var table = $('#dataTable').DataTable( {
                "processing": true,
                "serverSide": true,
                "deferRender": false,
                "autoWidth": false,
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                "ajax": {
                    'url': url,
                    pages: 5, // number of pages to cache
                    'data': function(data){
                        // Read values
                        data.keyword = $(keyword).val();
                        data.status = $(status).val();
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
                        "mRender": function( data, type, row ) {
                            let photo = row['attachment'];
                            return '<img src="' + photo + '" class="table-avatar" />';
                        }
                    },
                    { "data": "title" },
                    {
                        "mRender": function( data, type, row ) {
                            let offer_start = new Date(row['created_at']);
                            let startMonth = offer_start.getMonth() + 1;
                            offer_start = offer_start.getDate() + '/' + startMonth + '/' + offer_start.getFullYear();
                            return offer_start;
                        }
                    },
                    {
                        "mRender": function( data, type, row ){
                            let str = "<a href='/admin/setting/sponsor/edit/" + row['id'] + "' class='btn btn-secondary btn-sm'><i class='fa fa-edit'></i></a>";
                            if( row['status'] == 1 ) {
                                str += "<a href='#' data-id='" + row['id'] + "' class='btn btn-secondary ml-2 btn-sm broadCust' data-type='disable'><i class='fa fa-times'></i> Disable</a>";
                            } else {
                                str += "<a href='#' data-id='" + row['id'] + "' class='btn btn-secondary ml-2 btn-sm broadCust' data-type='enable'><i class='fa fa-check'></i> Enable</a>";
                            }
                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0,1,2,3], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[1, 'asc']]
            } );

            //Click on Search Button
            $(search).click( function(e) {
                table.draw();
            });

            //Custom Filters ( title search )
            $(keyword).keyup( function(event) {
                var keycode = (event.keyCode ? event.keyCode : event.which);
                // if(keycode == '13'){
                table.draw();
                // }
            } );

            //Custom Filters ( Author search )
            $(status).change( function() {
                if( $(this).val() != ''){
                    table.draw();
                }
            } );

            $('table').on('click', '.broadCust', function() {
                var url = "{{ route('dashboard.sponsor.action') }}";
                var type = $(this).data('type');
                var id = $(this).data('id');
                if( type ) {
                    var data = {type: type, id: id};
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You are going to " + type + " broadcusting for this coupon",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes'
                    }).then((result) => {
                        if (result.value) {
                            $(loading).toggleClass('d-none');
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
                                        Toast.fire({
                                            icon: response.label,
                                            title: response.content
                                        });
                                        table.draw();
                                    }
                                },
                                complete: function(response, status, xhr) {
                                    $(loading).toggleClass('d-none');
                                }
                            });
                        }
                    });
                    // if (confirmed) {
                    // }
                }
                return false;
            });
        });
    </script>
@endsection
