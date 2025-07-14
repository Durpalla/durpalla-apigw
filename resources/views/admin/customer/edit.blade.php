@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content content-overlap">
<form method="post" action="{{ route('dashboard.customer.update', $customer->id)}}" role="form" enctype="multipart/form-data" id="couponForm">
  <div class="row">
      <!-- /.card-header -->
      <div class="col-md-7">
        <div class="card-body">
              @csrf
              @method('PUT')
              <div class="form-group">
                <label for="name">Traveller name</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $customer->name) }}" placeholder="Name" required="">
                @error('name')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <label>Email</label>
                <input type="text" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $customer->email) }}" placeholder="Email address" required="">
                @error('email')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <label>Mobile</label>
                <input type="text" name="mobile" id="mobile" class="form-control @error('mobile') is-invalid @enderror" value="{{ old('mobile', $customer->mobile) }}" placeholder="Mobile" required="">
                @error('mobile')
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
      let url = "{{ route('dashboard.discount.suggest') }}";

      // let url = "https://api.github.com/orgs/select2/repos";
      $(items).select2({
        placeholder: "Select schedule",
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