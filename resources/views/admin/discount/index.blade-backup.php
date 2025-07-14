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
                    @can('coupon-create')
                    <a href="{{ route('dashboard.discount.create') }}" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i> Add discount rule</a>
                    @endcan
                </div>
              </div>
            </div>
            <!-- /.card-header -->
            <div class="card-body">

              <table class="table table-striped table-bordered projects" id="dataTable">
                  <thead>
                      <tr>
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

</style>
@endsection

@section('footer')
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
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
    var url = "{{ route('dashboard.discount.index') }}";
//
// Pipelining function for DataTables. To be used to the `ajax` option of DataTables
//
$.fn.dataTable.ext.classes.sPageButton = 'page-item';
$(function(){
    var customFilter = $('#customFilters');
    var keyword = $(customFilter).find('input#keywords');
    var status = $(customFilter).find('select#status');
    var search = $(customFilter).find('button#search');
    console.log(keyword);
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
                if( row['status'] == 1 ) {
                    str += "<a href='#' data-id='" + row['id'] + "' class='btn btn-secondary ml-2 btn-sm discountAction' data-type='disable'><i class='fa fa-phone'></i> Disable</a>";
                } else {
                    str += "<a href='#' data-id='" + row['id'] + "' class='btn btn-secondary ml-2 btn-sm discountAction' data-type='enable'><i class='fa fa-phone'></i> Enable</a>";
                }
                return str;
            }
        }
      ],
      "columnDefs": [
      {"targets": [2,3], "searchable": false, "orderable": false, "visible": true}
      ],
      "order": [[0, 'asc']]
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
