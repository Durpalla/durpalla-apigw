@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content content-overlap">
<form method="post" action="{{ route('dashboard.coupon.store')}}" role="form" enctype="multipart/form-data" id="couponForm2">
  <div class="row">
      <!-- /.card-header -->
      <div class="col-md-7">
        <div class="card-body">
              @csrf
              <div class="form-group">
                <label for="name">Name</label>
                <input type="text" name="name" id="name" value="{{old('name')}}" class="form-control @error('name') is-invalid @enderror" placeholder="Coupon name" required="">
                @error('name')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <label for="code">Code</label>
                <input type="text" name="code" id="code" value="{{old('code')}}" class="form-control @error('code') is-invalid @enderror" placeholder="Coupon code" required="">
                @error('code')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <label for="discount_amount">Discount amount</label>
                <div class="input-group mb-3">
                  <div class="input-group-prepend">
                    <div class="input-group-text">
                      Amount
                    </div>
                  </div>
                  <input type="number" min="0" id="discount_amount" name="discount_amount" class="form-control" value="{{ old('discount_amount') }}" required>
                  <div class="input-group-append">
                    <div class="input-group-btn">
                      <select name="discount_type" class="form-control btn btn-default" value="{{ old('discount_type') }}" required>
                        <option value="flat">Fixed</option>
                        <option value="percent">Percentage</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label>Applicable on</label>
                <select name="type" id="applicableToType" class="form-control @error('type') is-invalid @enderror" value="{{ old('type') }}">
                  <option value="">Choose now</option>
                  <option value="merchant" @if(old('type') == 'merchant') selected @endif>Merchant</option>
                  <option value="route" @if(old('type') == 'route') selected @endif>Route</option>
                  <option value="vehicle" @if(old('type') == 'vehicle') selected @endif>vehicle</option>
                  <option value="customer" @if(old('type') == 'customer') selected @endif>Special customers</option>
                  <option value="period" @if(old('type') == 'period') selected @endif>Periodical (Date range)</option>
                </select>
                @error('type')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group" id="pickSomeBlock" @if(old('type') == 'period' OR old('type') == '') style="display: none;" @endif>
                <label>Item</label>
                <div class="select2-purple">
                  <select name="items[]" class="select2" id="items" multiple="multiple" data-placeholder="Select a State" data-dropdown-css-class="select2-purple" style="width: 100%;" required>

                  </select>
                </div>
                @error('customers')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>

              <div class="form-group">
                <label>Offer start</label>
                <div class="input-group">
                    <input type="text" name="offer_start" value="{{ old('offer_start') }}" class="form-control datepicker @error('offer_start', date('d/m/Y') ) is-invalid @enderror" data-inputmask-alias="datetime" data-inputmask-inputformat="dd/mm/yyyy" data-mask required>
                    <div class="input-group-addon">
                        <span class="glyphicon glyphicon-th"></span>
                    </div>
                </div>
                @error('offer_start')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>

              <div class="form-group">
                <label>Offer End</label>
                <div class="input-group">
                    <input type="text" name="offer_end" value="{{ old('offer_end') }}" class="form-control datepicker @error('offer_end', date('d/m/Y') ) is-invalid @enderror" data-inputmask-alias="datetime" data-inputmask-inputformat="dd/mm/yyyy" data-mask required>
                    <div class="input-group-addon">
                        <span class="glyphicon glyphicon-th"></span>
                    </div>
                </div>
                @error('offer_end')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                 <input type="submit" value="Submit" class="btn btn-primary form-control" />
              </div>
        </div>
      </div>
      <div class="col-5 mt-4">
        <div class="form-group clearfix">
                <label>Applicable to</label>
                <div class="icheck-primary">
                  <input type="checkbox" class="cancel-item" id="checkboxPrimaryCabin" name="is_cabin" value="1" checked>
                  <label for="checkboxPrimaryCabin">
                    Cabin
                  </label>
                </div>
                <div class="icheck-primary">
                  <input type="checkbox" class="cancel-item" id="checkboxPrimarySeat" name="is_seat" value="1">
                  <label for="checkboxPrimarySeat">
                    Seat
                  </label>
                </div>
                <div class="icheck-primary">
                  <input type="checkbox" class="cancel-item" id="checkboxPrimaryDeck" name="is_deck" value="1">
                  <label for="checkboxPrimaryDeck">
                    Deck
                  </label>
                </div>
        </div>
        <div class="form-group">
          <label for="checkboxPrimary">Add to offer list?</label>
          <div class="icheck-primary">
            <input type="checkbox" class="cancel-item" id="checkboxPrimary" name="is_offer" value="1" checked>
            <label for="checkboxPrimary">
              Yes
            </label>
          </div>
        </div>
        <div class="form-group">
          <label>Banner / Poster for the offer</label>
          <div class="input-group">
            <span class="input-group-btn">
              <span class="btn btn-default btn-file">
                Browse… <input type="file" name="poster" id="imgInp">
              </span>
            </span>
            <input type="text" class="form-control" readonly><br>
          </div>
          <img id='img-upload'/>
          <span class="help-text">Please upload a standard banner. which will be below 100Kb and size (460X340)px</span>
        </div>
      </div>
  </div>
</form>
</section>

@endsection

@section('header')
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.css') }}">
  <!-- iCheck for checkboxes and radio inputs -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
  <!-- Select2 -->
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">

<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
<style>
  <style type="text/css">

  </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script src="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.16/dist/summernote.min.js"></script>

<script>
  $(function() {

    $('#couponForm').on('submit',(function(e){
      e.defaultPrevented;
      $.ajaxSetup({
          headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          }
      });
      let url = $(this).attr('action');
      let form = $(this);
      console.log(url);
      $.ajax({
        url: url,
        type: "POST",
        data:  new FormData(this),
        contentType: false,
        cache: false,
        processData:false,
        success: function(data){
          if( data.status ) {
            $(form).trigger("reset");
            $('#pickSomeBlock').hide();
          }
          Toast.fire({
              icon: data.label,
              title: data.content
          });
        },
        error: function(){
          Toast.fire({
              icon: 'error',
              title: 'Sorry! something went wrong'
          });
        }
      });
      return false;
    }));

    $('#applicableToType').change( function(e) {
      let val = $(this).val();
      initializeSelect2(val);
    });

    @if( old('type') != 'period' )
      initializeSelect2("{{ old('type') }}");
    @endif

    function initializeSelect2(val)
    {
      let items = $('#items');
      $(items).html(null);
      let url = "{{ route('dashboard.merchant.suggest') }}";
      switch(val) {
        case'route':
          url = "{{ route('dashboard.routes.suggest') }}";
          $('#pickSomeBlock').show();
        break;
        case'vehicle':
          url = "{{ route('dashboard.vehicle.suggest') }}";
          $('#pickSomeBlock').show();
        break;
        case'customer':
          url = "{{ route('dashboard.customer.suggest') }}";
          $('#pickSomeBlock').show();
        break;
        case'period':
          $('#pickSomeBlock').hide();
        break;
        case'merchant':
          url = "{{ route('dashboard.merchant.suggest') }}";
          $('#pickSomeBlock').show();
        break;
        default:
          $('#pickSomeBlock').hide();
        break;
      }

      // let url = "https://api.github.com/orgs/select2/repos";
      $(items).select2({
        placeholder: "Pick some items",
          allowClear: true,
          cache: false,
          ajax: {
              url: url,
              dataType: 'json',
              type: "GET",
              quietMillis: 50,
              data: function (term) {
                  return {
                      term: term.term
                  };
              },
              processResults: function (data) {
                  var myResults = [];
                  $.each(data.results, function (index, item) {
                      myResults.push({
                          'id': item.id,
                          'text': item.name
                      });
                  });
                  return {
                      results: myResults
                  };
              }
          }
      });
    }

    //Initialize Select2 Elements
    $('.select2bs4').select2({
      theme: 'bootstrap4'
    })

    //Datemask dd/mm/yyyy
    $('#datemask').inputmask('dd/mm/yyyy', { 'placeholder': 'dd/mm/yyyy' })
    //Datemask2 mm/dd/yyyy
    $('#datemask2').inputmask('mm/dd/yyyy', { 'placeholder': 'mm/dd/yyyy' })
    //Money Euro
    $('[data-mask]').inputmask()

    $('.datepicker').datepicker({
      format: 'dd/mm/yyyy',
      todayHighlight:'TRUE',
      autoclose: true,
      startDate: "-0d",
      endDate: "+360d"
    }).on('changeDate', function (ev) {
         $(this).datepicker('hide');
    });

    $(document).on('change', '.btn-file :file', function() {
      var input = $(this),
      label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
      input.trigger('fileselect', [label]);
    });

    $('.btn-file :file').on('fileselect', function(event, label) {
        var input = $(this).parents('.input-group').find(':text'),
            log = label;
        if( input.length ) {
            input.val(log);
        } else {
            if( log ) alert(log);
        }
    });
    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#img-upload').attr('src', e.target.result);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    $("#imgInp").change(function(){
        readURL(this);
    });

});
</script>
@endsection
