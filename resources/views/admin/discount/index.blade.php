@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link clickableTab active" data-type="active" id="active-tab" data-toggle="tab" href="#active" role="tab" aria-controls="active" aria-selected="true">Active</a>
            </li>
            <li class="nav-item">
                <a class="nav-link clickableTab" data-type="inactive" id="inactive-tab" data-toggle="tab" href="#inactive" role="tab" aria-controls="inactive" aria-selected="false">Inactive</a>
            </li>
            @can('discount-create')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('dashboard.discount.create') }}"><i class="fa fa-plus"></i> Add new discount</a>
            </li>
            @endcan
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Action
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item couponBulkAction" data-type="bulkdisable" href="#">Disable</a>
                    </div>
                </div>
                <table class="table table-striped table-bordered projects display responsive nowrap" id="activeDiscounts">
                    <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" class="checkedAll" value="0"></th>
                        <th><div>Merchant</div></th>
                        <th><div>vehicle</div></th>
                        <th><div>Schedule</div></th>
                        <th><div>For</div></th>
                        <th><div>Cabin</div></th>
                        <th><div>Seat</div></th>
                        <th><div>Deck</div></th>
                        <th><div>Amount</div></th>
                        <th><div>Created by</div></th>
                        <th><div>Created at</div></th>
                        <th style="width:135px;v-align:middle;text-align:center;" class="align-middle"><div><i class="fa fa-wrench"></i></div></th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div class="tab-pane fade" id="inactive" role="tabpanel" aria-labelledby="inactive-tab">
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Action
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item couponBulkAction" data-type="bulkenable" href="#">Enable</a>
                    </div>
                </div>
                <table class="table table-striped table-bordered projects display responsive nowrap" id="inactiveDiscounts">
                    <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" class="checkedAll" value="0"></th>
                        <th><div>Merchant</div></th>
                        <th><div>vehicle</div></th>
                        <th><div>Schedule</div></th>
                        <th><div>For</div></th>
                        <th><div>Cabin</div></th>
                        <th><div>Seat</div></th>
                        <th><div>Deck</div></th>
                        <th><div>Amount</div></th>
                        <th><div>Disable by</div></th>
                        <th><div>Disable at</div></th>
                        <th style="width:135px;v-align:middle;text-align:center;" class="align-middle"><div><i class="fa fa-wrench"></i></div></th>
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

</style>
@endsection

@section('footer')
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.responsive.min.js') }}"></script>
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
    var url = "{{ route('dashboard.discount.index') }}";
//
// Pipelining function for DataTables. To be used to the `ajax` option of DataTables
//
$.fn.dataTable.ext.classes.sPageButton = 'page-item';
$(function(){
    $('.couponBulkAction').click(function(e) {
        e.defaultPrevented;
        let type = $(this).data('type');
        let items = [];
        if( type == 'bulkenable') {
            items = $('#inactiveDiscounts input.couponItemChecked:checked');
        } else {
            items = $('#activeDiscounts input.couponItemChecked:checked');
        }
        if( $(items).length > 0 ) {
            let ids = [];
            $(items).each(function(e) {
                ids.push($(this).data('id'));
            });

            if( ids.length > 0 && type) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    dataType: "json",
                    type: "POST",
                    url: "{{ route('dashboard.discount.action') }}",
                    data: {ids: ids.join(), type: type},
                    success: function (response, textStatus, xhr) {
                        if (response.status == true) {
                            table.draw();
                            inactiveTable.draw();
                            $('.checkedAll').prop("checked", false);
                            Toast.fire({
                                icon: response.label,
                                title: response.content
                            });
                        }
                    }
                });
            }
        }else {
            Toast.fire({
                icon: "error",
                title: "Sorry! no items selected"
            });
        }
    });

    //check all item
    $('.checkedAll').on("click", function( e ) {
        e.defaultPrevented;
        var parent = $(this).parents('table');
        if( $(this).is(":checked") ) {
            $(parent).find(".couponItemChecked").each(function(){
                $(this).prop('checked', true);
            });
        } else {
            $(parent).find(".couponItemChecked").each(function(){
                $(this).prop('checked', false);
            });;
        }
    });
    let table = $('#activeDiscounts').DataTable( {
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
              data.status = 1;
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
                    return '<input type="checkbox" value="' + row['id'] + '" data-id="' + row['id'] + '" class="couponItemChecked">';
                }
            },
        { "data": "merchant.merchant_name" },
        { "data": "vehicle.name" },
        {
            "mRender": function( data, type, row ){
              let str = row['schedule']['route']['route_name'];
              str += ' (' + row['schedule']['schedule_date'] + ')';
              return str;
            },
        },
        {
            "mRender": function( data, type, row ){
                return row['applicable_to']
            },
        },
        {
            "mRender": function( data, type, row ){
                return ( row['is_cabin'] ) ? 'Y' : 'N';
            },
        },
        {
            "mRender": function( data, type, row ){
                return ( row['is_seat'] ) ? 'Y' : 'N';
            },
        },
        {
            "mRender": function( data, type, row ){
                return ( row['is_deck'] ) ? 'Y' : 'N';
            },
        },
        {
            "mRender": function( data, type, row ){
              let str = row['amount'];
              str += ( row['type'] == 'p' ) ? '%' : 'Tk';
                return str;
            },
        },
        { "data": "user.name" },
        { "data": "created_at" },
        {
            "mRender": function( data, type, row ) {
                let str = "";
                str += "<a href='#' data-id='" + row['id'] + "' class='btn btn-secondary ml-2 btn-sm discountAction' data-type='disable'><i class='fa fa-times'></i> Disable</a>";
                return str;
            }
        }
      ],
      "columnDefs": [
      {"targets": [0,3,4], "searchable": false, "orderable": false, "visible": true}
      ],
      "order": [[1, 'asc']]
  } );

    let inactiveTable = $('#inactiveDiscounts').DataTable( {
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
                data.status = 2;
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
                    return '<input type="checkbox" value="' + row['id'] + '" data-id="' + row['id'] + '" class="couponItemChecked">';
                }
            },
            { "data": "merchant.merchant_name" },
            { "data": "vehicle.name" },
            {
                "mRender": function( data, type, row ){
                    let str = row['schedule']['route']['route_name'];
                    str += ' (' + row['schedule']['schedule_date'] + ')';
                    return str;
                },
            },
            {
                "mRender": function( data, type, row ){
                    return row['applicable_to']
                },
            },
            {
                "mRender": function( data, type, row ){
                    return ( row['is_cabin'] ) ? 'Y' : 'N';
                },
            },
            {
                "mRender": function( data, type, row ){
                    return ( row['is_seat'] ) ? 'Y' : 'N';
                },
            },
            {
                "mRender": function( data, type, row ){
                    return ( row['is_deck'] ) ? 'Y' : 'N';
                },
            },
            {
                "mRender": function( data, type, row ){
                    let str = row['amount'];
                    str += ( row['type'] == 'p' ) ? '%' : 'Tk';
                    return str;
                },
            },
            { "data": "disable_by.name" },
            { "data": "updated_at" },
            {
                "mRender": function( data, type, row ) {
                    let str = "";
                    str += "<a href='#' data-id='" + row['id'] + "' class='btn btn-secondary ml-2 btn-sm discountAction' data-type='enable'><i class='fa fa-check'></i> Enable</a>";
                    return str;
                }
            }
        ],
        "columnDefs": [
            {"targets": [0,3,4], "searchable": false, "orderable": false, "visible": true}
        ],
        "order": [[1, 'asc']]
    } );

    // $('#myModal').modal('show');
    $('table').on('click', '.discountAction', function() {
        var url = "{{ route('dashboard.discount.action') }}";
        var type = $(this).data('type');
        var id = $(this).data('id');
        if( type ) {
            var data = {type: type, id: id};
            Swal.fire({
                title: 'Are you sure?',
                text: "You are going to " + type + " this discount",
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
                                inactiveTable.draw();
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
