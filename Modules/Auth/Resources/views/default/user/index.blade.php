@extends('default::layouts.master')

@section('header')
@endsection

@section('content')

    <x-default::toolbar title="{{ $title ?? 'Administrators'}}">
        <a href="{{ route('user.create') }}" class="btn btn-success btn-sm">
            <i class="fa fa-plus-circle"></i> Add new administer
        </a>
    </x-default::toolbar>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="dataTable" class="table table-bordered table-condensed table-hover table-striped">
                        <thead>
                        <tr>
                            <th class="table-ids">ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="table-actions">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <script>
        $(function () {
            let table = $('#dataTable').DataTable({
                serverSide: true,
                processing: true,
                "ajax": {
                    url: "{{ route('user.index') }}",
                    data: function (data) {
                        data.status = $('#filterPanel #status').val();
                        data.keyword = $('#filterPanel #keywords').val();
                    }
                },
                "bAutoWidth": false,
                "sPageButtonActive": "active",
                dom: 'lr<"toolbar">tip',
                stateSave: true,
                "stateDuration": 60 * 60 * 24 * 7,
                deferRender: true,
                "lengthChange": true,
                lengthMenu: [[25, 50, 100, 500, -1], [25, 50, 100, 500, "All"]],
                "pageLength": 50,
                "bFilter": true,
                "bInfo": true,
                "searching": true,
                "order": [[0, "DESC"]],
                columns: [
                    {"data": 'id', order: true},
                    {"data": 'name'},
                    {"data": 'email'},
                    {"data": 'role', searching: false, sortable: false},
                    {"data": 'status'},
                    {"data": 'actions', searching: false, sortable: false}
                ],
                "createdRow": function (row, data, index) {
                    // if ( data[6] == 'Disable' ){
                    //     $(row).addClass('highlightError');
                    // }
                }
            });

            $('table').on("change", '.toggleStatus', function (e) {
                e.preventDefault();
                let $checkbox = $(this);
                let userId = $(this).data('id');
                let url = "/dashboard/auth/user/" + userId + "/status-update";
                let status = $checkbox.is(':checked') ? 1 : 0;

                Swal.fire({
                    title: 'Are you sure?',
                    text: "You are going to update user status",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, proceed',
                    cancelButtonText: 'Cancel'
                }).then((res) => {
                    if (!res.isConfirmed) {
                        // revert checkbox state if canceled
                        $checkbox.prop('checked', !$checkbox.is(':checked'));
                        return;
                    }

                    $.ajax({
                        type: "PUT",
                        url: url,
                        data: {bundle_id: userId, status: status},
                        success: function (response, xhr) {
                            console.log(response);
                            defaultToast(response.status, response.message);

                            return response.status;
                        },
                        error: function (xhr) {
                            Swal.close();
                            // revert checkbox if failed
                            $checkbox.prop('checked', !$checkbox.is(':checked'));

                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'error',
                                title: 'Update failed',
                                text: xhr.responseJSON?.message || xhr.statusText,
                                showConfirmButton: false,
                                timer: 3000
                            });
                        }
                    });

                    return false;
                });
            });
        });
    </script>
@endsection

