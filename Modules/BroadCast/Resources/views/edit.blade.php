@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content content-overlap">
        <form method="post" action="{{ route('broadcast.update', $broadcast->id)}}" role="form" enctype="multipart/form-data"
              id="couponForm2">
            @method('PUT')
            <div class="row">
                <!-- /.card-header -->
                <div class="col-md-7">
                    <div class="card-body">
                        @csrf
                        <div class="form-group">
                            <label for="name">Title / Subject</label>
                            <input type="text" name="title" id="name" value="{{old('title', $broadcast->title)}}"
                                   class="form-control @error('title') is-invalid @enderror" placeholder="Title"
                                   required="">
                            @error('title')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="type">Type</label>
                            <select class="form-control" name="type" id="type" required>
                                <option value="all" @if(old('type', $broadcast->type) === 'all') selected @endif>All Type</option>
                                <option value="sms" @if(old('type', $broadcast->type) === 'sms') selected @endif>Sms</option>
                                <option value="email" @if(old('type', $broadcast->type) === 'email') selected @endif>Email</option>
                                <option value="message" @if(old('type', $broadcast->type) === 'message') selected @endif>Message</option>
                                <option value="fcm" @if(old('type', $broadcast->type) === 'fcm') selected @endif>FCM</option>
                                <option value="notification" @if(old('type', $broadcast->type) === 'notification') selected @endif>Notification</option>
                            </select>
                            @error('type')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="discount_amount">Message</label>
                            <p><small>Dynamic variable {name}</small></p>
                            <textarea name="message" class="form-control @error('message') is-invalid @enderror" cols="10"
                                      style="height: 220px" placeholder="Dear {name}, Welcome to {{ getOption('company_website') }}"
                                      required>{{ old('message', $broadcast->message) }}</textarea>
                        </div>
                        <div class="form-group">
                            <input type="submit" value="Submit" class="btn btn-primary form-control"/>
                        </div>
                    </div>
                </div>
                <div class="col-5 mt-4">
                    <div class="form-group">
                        <label for="group">Customers group</label>
                        <select class="form-control" name="group" id="type" required>
                            <option value="all" @if(old('group', $broadcast->group) === 'all') selected @endif>All</option>
                            <option value="individual" @if(old('group', $broadcast->group) === 'individual') selected @endif>Custom
                            </option>
                        </select>
                        @error('group')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="customers">Select customers</label>
                        <select class="form-control select2-blue" id="customers"
                                placeholder="Select customers"></select>
                    </div>
                    <div class="form-group">
                        <label>Attachment</label>
                        <div class="input-group">
                            <div class="input-group-btn">
                                <div class="btn btn-default btn-file">
                                    Browse… <input type="file" name="attachment" id="imgInp">
                                </div>
                            </div>
                            <input type="text" class="form-control" readonly><br>
                        </div>
                        <img id='img-upload' src="{{ asset($broadcast->attachment) }}"/>
                        <span class="help-text">Please upload a standard image. which will be below 100Kb and size (460X340)px</span>
                    </div>
                </div>
            </div>
        </form>
    </section>

@endsection

@section('header')
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.css') }}">
    <!-- iCheck for checkboxes and radio inputs -->
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">

    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
    <style>
    #img-upload {
        width: 100%;
        height: auto;
        padding: 15px;
        background: #fff;
    }
    </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.16/dist/summernote.min.js"></script>

    <script>
        $(function () {

            $('#couponForm').on('submit', (function (e) {
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
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    success: function (data) {
                        if (data.status) {
                            $(form).trigger("reset");
                            $('#pickSomeBlock').hide();
                        }
                        Toast.fire({
                            icon: data.label,
                            title: data.content
                        });
                    },
                    error: function () {
                        Toast.fire({
                            icon: 'error',
                            title: 'Sorry! something went wrong'
                        });
                    }
                });
                return false;
            }));

            $('#applicableToType').change(function (e) {
                let val = $(this).val();
                initializeSelect2(val);
            });

            @if( old('type') != 'period' )
            initializeSelect2("{{ old('type') }}");
            @endif

            function initializeSelect2(val) {
                let items = $('#items');
                $(items).html(null);
                let url = "{{ route('dashboard.merchant.suggest') }}";
                switch (val) {
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
            $('#datemask').inputmask('dd/mm/yyyy', {'placeholder': 'dd/mm/yyyy'})
            //Datemask2 mm/dd/yyyy
            $('#datemask2').inputmask('mm/dd/yyyy', {'placeholder': 'mm/dd/yyyy'})
            //Money Euro
            $('[data-mask]').inputmask()

            $('.datepicker').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
                startDate: "-0d",
                endDate: "+360d"
            }).on('changeDate', function (ev) {
                $(this).datepicker('hide');
            });

            $(document).on('change', '.btn-file :file', function () {
                var input = $(this),
                    label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
                input.trigger('fileselect', [label]);
            });

            $('.btn-file :file').on('fileselect', function (event, label) {
                var input = $(this).parents('.input-group').find(':text'),
                    log = label;
                if (input.length) {
                    input.val(log);
                } else {
                    if (log) alert(log);
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

            $("#imgInp").change(function () {
                readURL(this);
            });

        });
    </script>
@endsection
