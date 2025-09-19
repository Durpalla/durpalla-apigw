@extends('metis::layouts.master')

@section('header')
@endsection

@section('content')
    <div id="content">
        <div class="outer">
            <div class="inner bg-light lter">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="box">
                            <header>
                                <div class="icons"><i class="fa fa-table"></i></div>
                                <h5>{{ $title ?? 'Access blocks' }}</h5>
                                <div class="toolbar">
                                    <nav style="padding: 8px;">
                                </div>
                                <!-- /.toolbar -->
                            </header>
                            <div id="collapse4" class="body">
                                <table id="dataTable"
                                       class="table table-bordered table-condensed table-hover table-striped">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Block Type</th>
                                        <th>Identity</th>
                                        <th>Attempts</th>
                                        <th>Blocked At</th>
                                        <th>Unblocked At</th>
                                        <th>Created at</th>
                                        <th style="width:95px;" class="align-middle">
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
                <!-- /.row -->
                <!--End Datatables-->
            </div>
            <!-- /.inner -->
        </div>
        <!-- /.outer -->
    </div>
    <!-- /#content -->
@endsection

@section('footer')
    <script>
        let table = $('#dataTable').DataTable({
            serverSide: true,
            processing: true,
            "ajax": {
                url: "{{ route('access-block.index') }}",
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
                {data: 'id', order: false},
                {data: 'type', order: false},
                {data: 'identity', order: false},
                {data: 'attempts', order: false},
                {data: 'blocked_at', order: false},
                {data: 'unblocked_at', order: false},
                {data: 'created_at', order: false},
                {data: 'action', order: false}
            ],
            "createdRow": function (row, data, index) {
                // if ( data[6] == 'Disable' ){
                //     $(row).addClass('highlightError');
                // }
            }
        });

        jQuery('table').on('click', '.unblockAccess', function () {
            let id = $(this).data('id');
            let action = $(this).data('action');
            $(modalForm).attr('action', action);
            $(modal).find('.modal-title').text('Unblock user access');
            $(modal).find('.modal-dialog').removeClass('modal-xl');
            $(modal).find('.modal-body').html(
                '<input type="hidden" name="id" value="' + id + '"> ' +
                '<input type="hidden" name="_method" value="PUT"> ' +
                '<div class="form-group"> ' +
                '<label>Reason (*)</label> ' +
                '<textarea name="reason" class="form-control" placeholder="Write a reason to unblock" required></textarea> ' +
                '</div>');
            $(modal).find('.modal-footer').show();
            $(modal).modal('show');
        });

        $('#filterPanel input').on('keyup', function () {
            table.draw();
        });

        $('#filterPanel select').on('change', function () {
            table.draw();
        });

        $(document).on("ajaxComplete", function (event, xhr, settings) {
            if (settings.type === 'POST' && xhr.status === 200) {
                table.draw();
            }
        });
    </script>
@endsection

