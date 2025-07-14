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
                            <div class="btn-group" role="group" aria-label="Basic example">
                                @canAny(['user-create', 'supervisor-add'])
                                    <a href="{{ route('dashboard.user.create') }}" class="btn btn-xs btn-primary"><i
                                            class="fa fa-plus"></i> Add new</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <table class="table table-striped table-bordered projects display responsive nowrap"
                               id="dataTable">
                            <thead>
                            <tr>
                                <th style="width:5%">
                                    <div><i class="fa fa-image"></i></div>
                                </th>
                                <th>
                                    <div>Name</div>
                                </th>
                                <th>
                                    <div>Mobile</div>
                                </th>
                                <th>
                                    <div>Type</div>
                                </th>
                                <th>
                                    <div>Assigned vehicle</div>
                                </th>
                                <th>
                                    <div>Nid visible till</div>
                                </th>
                                <th>
                                    <div>Status</div>
                                </th>
                                <th style="width:40px;v-align:middle;text-align:center;" class="align-middle">
                                    <div><i class="fa fa-wrench"></i></div>
                                </th>
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

    <!-- Modal -->
    <div class="modal fade" id="staticBackdrop" data-backdrop="static" data-keyboard="false" tabindex="-1"
         aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('supervisor.nidvisibility') }}" method="POST" id="NidVisibilityForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">NID Visible time extend</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="supervisorID" name="supervisor_id" value="">
                        <div class="form-group">
                            <label>Supervisor : <span id="supervisorName"></span></label>
                        </div>
                        <div class="form-group">
                            <label>Extend hours?</label>
                            <div class="input-group mb-3">
                                <input type="number" name="extended_hours" class="form-control" placeholder="Hours" value="2">
                                <div class="input-group-append">
                                    <span class="input-group-text" id="basic-addon2">Hours</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Extend</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('header')

    <link rel="stylesheet" type="text/css"
          href="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/css/responsive.dataTables.min.css') }}"/>
    <style type="text/css">
        /*search box css start here*/
        .list-search-btn {
            font-size: 23px;
        }

        .table-avatar {
            max-width: 50px;
        }

        .search-sec {
            padding: 2rem;
        }

        .search-slt {
            display: block;
            width: 100%;
            font-size: 1.5rem;
            line-height: 1.5;
            color: #55595c;
            background-color: #fff;
            background-image: none;
            border: 1px solid #ccc;
            height: calc(3rem + 2px) !important;
            border-radius: 0;
        }

        .wrn-btn {
            width: 100%;
            font-size: 16px;
            font-weight: 400;
            text-transform: capitalize;
            height: calc(3rem + 2px) !important;
            border-radius: 0;
        }

        @media (min-width: 992px) {
            .search-sec {
                position: relative;
                top: -114px;
                background: rgba(26, 70, 104, 0.51);
            }
        }

        @media (max-width: 992px) {
            .search-sec {
                background: #1A4668;
            }
        }

    </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.responsive.min.js') }}"></script>
    <script>
        let can_edit = false, can_active = false, can_inactive = false, can_delete = false;
        @canany(['user-edit', 'supervisor-edit'])
            can_edit = true;
        @endcanany
            @canany(['user-approve', 'supervisor-active'])
            can_active = true;
        @endcanany
            @canany(['user-suspend', 'supervisor-suspend'])
            can_inactive = true;
        @endcan
            @can('user-delete')
            can_delete = true;
        @endcan
        let modal = $('#staticBackdrop');
        $(document).ready(function () {
            $("#PopS").popover({
                html: true
            }).on('shown.bs.popover', function () {
                $('#datetimepicker1').datetimepicker();
                $('#datetimepicker2').datetimepicker();
            });
        });
        var url = "{{ route('merchant.supervisors') }}";
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.ext.classes.sPageButton = 'page-item';

        $(function () {
            var customFilter = $('#customFilters');
            var keyword = $(customFilter).find('input#keywords');
            var area = $(customFilter).find('select#area');
            var package = $(customFilter).find('select#package');
            var status = $(customFilter).find('select#status');
            var role = $(customFilter).find('select#role');
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
                        data.area = $(area).val();
                        data.package = $(package).val();
                        data.role = $(role).val();
                        data.status = $(status).val();
                    }
                },
                dom: 'lBfrtip',
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
                    {"data": "name"},
                    {"data": "mobile"},
                    {"data": "type"},
                    {
                        "mRender": function (data, type, row) {
                            let str = '';
                            if (row['vehicles'] !== null) {
                                for (var i = 0; i < row['vehicles'].length; i++) {
                                    str += '<a class="badge badge-info" href="/admin/vehicle/show/' + row['vehicles'][i].id + '">' + row['vehicles'][i].name + '</a> ';
                                }
                            }

                            return str;
                        }
                    },
                    {"data": "nid_visiable_at"},
                    {"data": "status"},
                    {
                        "mRender": function (data, type, row) {
                            let str = "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'> ";
                            str += "<a href='/admin/setting/user/show/" + row['id'] + "' class='dropdown-item'><i class='fa fa-eye'></i> View</a> ";
                            str += "<a href='#' data-id='" + row['id'] + "' data-name='" + row['name'] + "' class='dropdown-item nidVisiable'><i class='fa fa-file-alt'></i> NID visiable</a> ";
                            if (can_edit) {
                                str += "<a href='/admin/setting/user/edit/" + row['id'] + "' class='dropdown-item'><i class='fa fa-edit'></i> Edit</a> ";
                            }
                            if (can_active && (row['status'] == 'Pending')) {
                                str += "<a href='#' class='dropdown-item user-action' data-action='active' data-user-id='" + row['id'] + "'><i class='fa fa-check'></i> Active</a> ";
                            }
                            if (can_inactive && (row['status'] == 'Active')) {
                                str += "<a href='#' class='dropdown-item user-action' data-action='delete' data-user-id='" + row['id'] + "'><i class='fa fa-ban'></i> Delete</a> ";
                            }
                            str += "</div> </div>";
                            return str;
                        }
                    }
                ],
                "columnDefs": [
                    {"targets": [0, 4, 5, 7], "searchable": false, "orderable": false, "visible": true}
                ],
                "order": [[1, 'asc']],
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


            //Custom Filters ( Author search )
            $(status).change(function () {
                table.draw();
            });

            // user action
            $('table').on('click', '.nidVisiable', function (e) {
                e.defaultPrevented;
                console.log(this);
                $(modal).modal('show');
                $(modal).find('#supervisorName').text($(this).data('name'));
                $(modal).find('#supervisorID').val($(this).data('id'));
                return false;
            });
        });

        $('#NidVisibilityForm').submit(function(e) {
            let url = $(this).attr('action');
            $.ajaxSetup({

            })
           $.ajax({
               type: "POST",
               url: url,
               dataType: "json",
               data: $(this).serialize(),
               success: function(data) {
                    if(data.status === true) {
                        table.draw();
                        $(modal).modal('hide');
                    }

                   Toast.fire({
                       icon: data.label,
                       title: data.content
                   });
               }
           })
            return false;
        });
    </script>
@endsection
