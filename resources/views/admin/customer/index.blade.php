@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content">
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item">
          <a class="nav-link clickableTab active" data-type="1" id="active-tab" data-toggle="tab" href="#active" role="tab" aria-controls="active" aria-selected="true">Active</a>
        </li>
        <li class="nav-item">
          <a class="nav-link clickableTab" data-type="2" id="active-tab" data-toggle="tab" href="#active" role="tab" aria-controls="active" aria-selected="false">Inactive</a>
        </li>
        <li class="nav-item">
          <a class="nav-link clickableTab" data-type="9" id="active-tab" data-toggle="tab" href="#active" role="tab" aria-controls="active" aria-selected="false">Trash</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ route('dashboard.customer.create') }}" role="tab"><i class="fa fa-plus"></i> Add new</a>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
        <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
            <table class="table table-striped projects display responsive nowrap" id="dataTable">
                <thead>
                    <tr>
                        <th style="width: 1%"> <i class="fas fa-image"></i> </th>
                        <th> Name </th>
                        <th> Email </th>
                        <th> Mobile </th>
                        <th> Created by </th>
                        <th> Created from </th>
                        <th> Joining Date </th>
                        <th style="width: 8%" class="text-center"> Status </th>
                        <th><i class="fas fa-cog"></i></th>
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
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.responsive.min.js') }}"></script>
<script>
      let can_edit = true, can_active = true, can_inactive = true, can_delete = true;
    @can('customer-edit')
        can_edit = true;
    @endcan
        @can('customer-active')
        can_active = true;
    @endcan
        @can('customer-inactive')
        can_inactive = true;
    @endcan
        @can('customer-delete')
        can_delete = true;
    @endcan
    let url = "{{ route('dashboard.customer.index') }}";
    $.fn.dataTable.ext.classes.sPageButton = 'page-item';

$(function(){
    let type = 1;
    $('#advancedFilterBtn').click(function(e) {
        e.defaultPrevented;
        $(this).toggleClass('active');
        $('#advancedFilter').toggleClass('d-none');
    });
    var customFilter = $('#advancedFilter');
    var keyword = $('input#keywords');
    var status = $(customFilter).find('select#status');
    var search = $(customFilter).find('button#search');
    var table = $('#dataTable').DataTable( {
        "processing": true,
        "serverSide": true,
        "deferRender": true,
        "bAutoWidth": false,
        "sPageButtonActive": "active",
        "ajax": {
           'url': url,
           pages: 5, // number of pages to cache
           'data': function(data){
              // Read values
              data.keyword = $(keyword).val();
              data.status = type;
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
        "searching": true,
        "columns": [
            { "mRender": function(data, type, row)
                {
                    return '<img src="'+ row['photo'] +'" class="table-avatar">';
                }
            },
            { "mRender": function(data, type, row)
                {
                    return '<a href="/admin/customer/show/'+ row['id'] +'" class="table-avatar">' + row['name'] + '</a>';
                }
            },
            { "data": "email" },
            { "data": "mobile" },
            { "data": "created_by" },
            { "data": "platform" },
            { "data": "joining_date" },
            { "mRender": function(data, type, row)
                {
                    let status = '';
                    if( row['deleted_at'] == null ) {
                        status = (parseInt(row['status']) == 1) ? 'Active' : ((parseInt(row['status']) == 0 ) ? 'Pending' : 'Inactive');
                    } else {
                        status = 'Deleted';
                    }
                    return status;
                }
            },
            {"mRender": function ( data, type, row )
                {
                    var str =  "<div class='btn-group'> <button class='btn btn-secondary btn-sm dropdown-toggle' type='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'><i class='fa fa-ellipsis-h' aria-hidden='true'></i></button> <div class='dropdown-menu dropdown-menu-right'>";

                    str += "<a href='/admin/customer/show/" + row['id'] + "' class='dropdown-item' data-customer-id='" + row['id'] + "'><i class='fa fa-eye'></i> View</a>";

                    if(can_active) {
                        str += "<a href='#' class='dropdown-item customer-action' data-action='resetPassword' data-customer-id='" + row['id'] + "'><i class='fa fa-cog'></i> Reset password</a>";
                    }
                    if( can_edit ) {
                        str += "<a href='/admin/customer/edit/" + row['id'] + "' class='dropdown-item'><i class='fa fa-edit'></i> Edit</a>";
                    }
                    if( parseInt(row['status']) == 1 ) {
                        if( can_inactive ) {
                            str += "<a href='#' class='dropdown-item customer-action' data-action='inactive' data-customer-id='" + row['id'] + "'><i class='fa fa-ban'></i> Inactive</a>";
                        }
                    } else if(parseInt(row['status']) == 0) {
                        if( can_active ) {
                            str += "<a href='#' class='dropdown-item customer-action' data-action='active' data-customer-id='" + row['id'] + "'><i class='fa fa-check'></i> Active</a>";
                        }
                    } else {
                        if( can_active ) {
                            str += "<a href='#' class='dropdown-item customer-action' data-action='reactive' data-customer-id='" + row['id'] + "'><i class='fa fa-check'></i> Re-active</a>";
                        }
                    }
                    if( row['deleted_at'] == null ) {
                        if( can_delete ) {
                            str += "<a href='#' class='dropdown-item customer-action' data-action='delete' data-customer-id='" + row['id'] + "'><i class='fa fa-times'></i> Delete</a>";
                        }
                    } else {
                        if( can_delete ) {
                            str += "<a href='#' class='dropdown-item customer-action' data-action='restore' data-customer-id='" + row['id'] + "'><i class='fa fa-times'></i> Restore</a>";
                        }
                    }
                    str += "</div> </div>";
                    return str;
                }
            }
      ],
      "columnDefs": [
      {"targets": [0,1, 5], "searchable": false, "orderable": false, "visible": true}
      ],
      "order": [[2, 'asc']],
      buttons: [
           'copy', 'excel', 'pdf', 'print'
        ]
  } );

    $('.clickableTab').click(function() {
        type = $(this).data('type');
        table.draw();
    });

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
    $('.datepicker').click(function(e) {
        $(this).val("");
    });

    //Custom Filters ( Author search )
    $(status).change( function() {
        table.draw();
    } );

    // $('#myModal').modal('show');
    $('table').on('click', '.customer-action', function() {
        console.log( this );
        var url = "{{ route('dashboard.customer.action') }}";
        var action = $(this).data('action');
        var id = $(this).data('customer-id');
        if( action == 'request' ) {

        } else {
            var data = {action: action, id: id};
            Swal.fire({
                title: 'Are you sure?',
                text: "You are going to " + action + " this customer account.",
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
                        // dataType: "json",
                        type: "POST",
                        url: url,
                        data: data,
                        success: function (response, textStatus, xhr) {
                            // response = JSON.parse( response );
                            if (response.status == true) {
                                table.draw();
                            }
                            Toast.fire({
                                icon: response.label,
                                title: response.content
                            });
                        }
                    });

                }
            });
            // if (confirmed) {
            // }
        }
        return false;
    });

    $('table').on('click', '.customer-request', function() {
        var id = $(this).data('customer-id');
        var name = $(this).data('customer-name');
        var url = "{{ route('dashboard.customer.store') }}";
        var modalContent = $(confirmModal).find('.modal-body');
        $(confirmModal).find('form').attr('action', url);
        $(modalContent).html("");
        $(confirmModal).find('.modal-title').text('Make request').css('text-transform', 'capitalize');
        $(modalContent).append('\n' +
            '<div class="form-group">\n' +
            '<label class="control-label">Customer : </label>\n' +
            '<span><strong>' + name + '</strong></span>\n' +
            '<input type="hidden" name="customer_id" value="' + id + '">\n' +
            '</div>');
        $(modalContent).append('\n' +
            '<div class="form-group">\n' +
            '<label class="control-label">Request for</label>\n' +
            '<select name="type" class="form-control" id="customerRequestType">\n' +
            '<option value="">Select type</option>\n' +
            '<option value="Package">Change Package</option>\n' +
            '<option value="Email">Change Email</option>\n' +
            '<option value="Username">Change Username</option>\n' +
            '<option value="Password">Change Password</option>\n' +
            '<option value="CustomerID">Change Customer ID</option>\n' +
            '<option value="Primary contact">Change Primary mobile</option>\n' +
            '<option value="Secondary contact">Change Secondary mobile</option>\n' +
            '</select>\n' +
            '<input type="hidden" name="customer_id" value="' + id + '">\n' +
            '</div>');
        $(modalContent).find('#customerRequestType').change(function(e) {
            var type = $(this).val();
            if( ['Package'].includes(type) ) {
                var url = "{{ route('dashboard.customer.index') }}";
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    type: "POST",
                    url: url,
                    data: {type: type, customer_id: id},
                    success: function (response, textStatus, xhr) {
                        // response = JSON.parse( response );
                        if (response.status == true) {
                            $(modalContent).append('\n' +
                                '<div class="form-group">\n' +
                                '<label class="control-label">New ' + type + '</label>\n' +
                                '<select name="new_property" id="newPackageSelect" class="form-control">\n' +
                                '<option value="">Choose package</option>\n' +
                                '</select>\n' +
                                '</div>');
                            console.log( response.data );
                            $.each(response.data, function (key, value) {
                                console.log( key + ' - ' + value );
                                $(modalContent).find('#newPackageSelect')
                                    .append($("<option></option>")
                                        .attr("value", key)
                                        .text(value));
                            });

                            $(confirmModal).modal('show');
                        } else {
                            Swal.fire(
                                'Warning!',
                                'Action not succeded',
                                'error'
                            );
                        }
                    }
                });
            } else {
                $(modalContent).append('\n' +
                    '<div class="form-group">\n' +
                    '<label class="control-label">New ' + type + '</label>\n' +
                    '<input type="text" class="form-control" name="new_property" placeholder="New '+ type + '" required>\n' +
                    '</div>');
                $(confirmModal).modal('show');
            }
        });
        $(confirmModal).modal('show');
        return false;
    });

});
</script>
@endsection
