@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content content-overlap">
  <div class="row">
    <div class="col-12">
      <!-- /.card-header -->
      <div class="col-md-7">
        <div class="card-body">
             <form method="post" action="{{ route('dashboard.page.store')}}" role="form">
              @csrf
              <div class="form-group">
                <label>Title</label>
                <input type="text" class="form-control" name="title" placeholder="Title"/>
              </div>
              <div class="form-group">
                <label>Page slug</label>
                <input type="text" class="form-control" name="slug" placeholder="Page slug"/>
              </div>

              <div class="form-group">
                <label>Content</label>
                <textarea class="form-control bcontent" id="summernote" name="body" style="min-height: 420px;"></textarea>
              </div>
              <div class="form-group">
                 <input type="submit" name="Submit" value="Submit" class="btn btn-primary form-control" />
              </div>
            </form>
        </div>
      </div>
      <div class="col-sm-5">
      </div>
    </div>
  </div>
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

  <!-- only for text editor -->
  <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.16/dist/summernote.min.css" rel="stylesheet">

  <style type="text/css">
    .btn-file {
    position: relative;
    overflow: hidden;
}
  .note-editable {
    min-height: 320px;
  }

.btn-file input[type=file] {
    position: absolute;
    top: 0;
    right: 0;
    min-width: 100%;
    min-height: 100%;
    font-size: 100px;
    text-align: right;
    filter: alpha(opacity=0);
    opacity: 0;
    outline: none;
    background: white;
    cursor: inherit;
    display: block;
}

input[readonly] {
  background-color: white !important;
  cursor: text !important;
}
  </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script src="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <!-- text editor -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.16/dist/summernote.min.js"></script>

<script>
  $(function() {
  $("#summernote").summernote();

  $(document).on('change', '.btn-file :file', function() {
    var input = $(this);
    var numFiles = input.get(0).files ? input.get(0).files.length : 1;
    console.log(input.get(0).files);
    var label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
    input.trigger('fileselect', [numFiles, label]);
  });

  $('.btn-file :file').on('fileselect', function(event, numFiles, label){
    var input = $(this).parents('.input-group').find(':text');
    var log = numFiles > 1 ? numFiles + ' files selected' : label;

    if( input.length ) {
      input.val(log);
    } else {
      if( log ){ alert(log); }
    }

  });
});
</script>
@endsection
