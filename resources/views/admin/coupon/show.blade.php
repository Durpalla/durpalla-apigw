@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content">
  <ul class="nav nav-tabs" id="myTab" role="tablist">
    <li class="nav-item">
      <a class="nav-link @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'profile') ? 'active': ''; @endphp" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Coupons</a>
    </li>
    <li class="nav-item">
      <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'stat') ? 'active': ''; @endphp" id="contact-tab" data-toggle="tab" href="#contact" role="tab" aria-controls="contact" aria-selected="false">Statistics</a>
    </li>
  </ul>
  <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
    <div class="tab-pane fade show @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'profile') ? 'active show': ''; @endphp" id="home" role="tabpanel" aria-labelledby="home-tab">
      <div class="row">
        <div class="col-12 col-md-12 col-lg-8 order-2 order-md-1">

          <table class="table table-striped table-bordered" id="dataTable">
              <thead>
                  <tr>
                      <th><div>Name</div></th>
                      <th><div>CODE</div></th>
                      <th><div>Type</div></th>
                      <th><div>Applicable to</div></th>
                      <th><div>Validity</div></th>
                      <th><div>Created by</div></th>
                      <th><div>Customer Applied</div></th>
                      <th style="width:135px;v-align:middle;text-align:center;" class="align-middle"><div><i class="fa fa-wrench"></i></div></th>
                  </tr>
              </thead>
              <tbody>
              </tbody>
          </table>

          </div>
          <div class="col-12 col-md-12 col-lg-4 order-1 order-md-2">
            <h3 class="text-secondary"><i class="fa fa-plus"></i> Add new coupon</h3><hr>
            <div class="form-group">
              <label for="name">Name</label>
              <input type="text" name="name" id="name" class="form-control" placeholder="Coupon name">
            </div>
            <div class="form-group">
              <label for="code">Code</label>
              <input type="text" name="code" id="code" class="form-control" placeholder="Coupon code">
            </div>
            <div class="form-group">
              <label>Applicable on</label>
              <select name="schedule_type" class="form-control @error('schedule_type') is-invalid @enderror" value="{{ old('schedule_type') }}" required>
                <option value="merchant">Merchant</option>
                <option value="route">Route</option>
                <option value="vehicle">vehicle</option>
                <option value="customer">Special Customers</option>
              </select>
              @error('schedule_type')
              <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
              </span>
              @enderror
            </div>
            <div class="form-group">
              <label>Pick customers</label>
              <div class="select2-purple">
                <select name="customers" class="select2" multiple="multiple" data-placeholder="Select a State" data-dropdown-css-class="select2-purple" style="width: 100%;">
                      <option>Alabama</option>
                      <option>Alaska</option>
                      <option>California</option>
                      <option>Delaware</option>
                      <option>Tennessee</option>
                      <option>Texas</option>
                      <option>Washington</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'stat') ? 'active show': ''; @endphp" id="contact" role="tabpanel" aria-labelledby="contact-tab">

      </div>
    </div>
  </section>
  <!-- /.content -->
@endsection

@section('header')
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">  <!-- Select2 -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
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
    var url = "{{ route('dashboard.coupon.index') }}";
//
// Pipelining function for DataTables. To be used to the `ajax` option of DataTables
//
$.fn.dataTable.ext.classes.sPageButton = 'page-item';
$(function(){
      //Initialize Select2 Elements
    $('.select2').select2()

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })

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
        { "data": "name" },
        {
            "mRender": function( data, type, row ){
                return row['code'].toUpperCase()
            },
        },
        {
            "mRender": function( data, type, row ){
                return row['type'].charAt(0).toUpperCase() + row['type'].slice(1)
            },
        },
        { "data": "applied_to" },
        {
            "mRender": function( data, type, row ){
                return row['offer_start'] + ' - ' + row['offer_end'];
            }
        },
        { "data": "user.name" },
        { "data": "user.name" },
        {
            "mRender": function( data, type, row ){
                return "<a href='/admin/setting/role/edit/" + row['id'] + "' class='btn btn-secondary btn-xs'><i class='fa fa-edit'></i></a>";
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

    function deleteLibraryItem( id ) {
        var parent = $(this).parents('tr');
        var url = "{{ url('dashboard/library/delete/') }}/" + id;
        var data = null;
        var confirmed = confirm('Are you sure to delete this item?');
        if( confirmed ) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.ajax({
                type: "DELETE",
                url: url,
                data: data,
                success: function(data, textStatus, xhr){
                    if( data.success == true ) {
                        $(parent).remove();
                    }
                    return false;
                }
            });
        }
        return false;
    }
});
</script>
@endsection
