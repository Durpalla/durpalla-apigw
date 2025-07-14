@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">{{ $title ?? '' }}</h3>
              <div class="card-tools">
                <div class="btn-group" role="group" aria-label="btn-group">
                  <a href="{{ route('dashboard.blog.index') }}" class="btn btn-xs btn-primary"><i class="fa fa-angle-left"></i> back</a>
                </div>
              </div>
            </div>
            <!-- /.card-header -->

            <form role="form" action="{{ route('dashboard.blog.update', $blog->id) }}" method="POST" enctype="multipart/form-data">
            <div class="row">
              <div class="col-md-7">
                <div class="card-body">

                    @csrf
                    <div class="row">
                      <div class="col-sm-12">
                        <!-- text input -->
                        <div class="form-group">
                          <label>Title</label>
                          <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" placeholder="Title" value="{{ old('title', $blog->title) }}" required>
                          @error('title')
                              <span class="invalid-feedback" role="alert">
                                  <strong>{{ $message }}</strong>
                              </span>
                          @enderror
                        </div>
                        <div class="form-group">
                          <label>Content</label>
                           <textarea class="form-control bcontent" id="summernote" class="form-control @error('content_body') is-invalid @enderror" name="content" required>{{ old('body', $blog->body) }}</textarea>
                          @error('content_body')
                              <span class="invalid-feedback" role="alert">
                                  <strong>{{ $message }}</strong>
                              </span>
                          @enderror
                        </div>
                      </div>
                    </div>
                    <div class="form-group">
                      <button type="submit" class="btn btn-lg btn-primary">Update merchant</button>
                    </div>
                  </div>
                </div>

            <div class="col-sm-5 pr-4">
              <div class="form-group">
                  <label>Upload Image</label>
                  <div class="input-group">
                      <span class="input-group-btn">
                          <span class="btn btn-default btn-file">
                              Browse… <input type="file" name="logo" id="imgInp">
                          </span>
                      </span>
                      <input type="text" class="form-control" readonly>
                  </div>
                  <img id='img-upload' src="{{ asset('images').'/'.$blog->image}}" />
                  <span class="help-text">Please upload a standard logo. which will be below 100Kb</span>
              </div>
            </div>
          </div>
        </form>
        </div>
      </div>
    </div>
  </section>
@endsection

@section('header')
<link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.16/dist/summernote.min.css" rel="stylesheet">
<style type="text/css">
.btn-file {
    position: relative;
    overflow: hidden;
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

#img-upload{
    width: 100%;
}
</style>
@endsection

@section('footer')
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
<script src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.16/dist/summernote.min.js"></script>

<script>
  $(function () {

    //text editor
    $('#summernote').summernote();
    //Initialize Select2 Elements
    $('.select2').select2()

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
