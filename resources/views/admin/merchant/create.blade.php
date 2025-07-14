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
                                <a href="{{ route('dashboard.merchant.index') }}" class="btn btn-xs btn-primary"><i
                                        class="fa fa-angle-left"></i> back</a>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <form role="form" action="{{ route('dashboard.merchant.store') }}" method="POST"
                          enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-7">
                                <div class="card-body">

                                    @csrf
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <!-- text input -->
                                            <div class="form-group">
                                                <label>Merchant name</label>
                                                <input type="text" name="merchant_name"
                                                       class="form-control @error('merchant_name') is-invalid @enderror"
                                                       placeholder="Merchant name" value="{{ old('merchant_name') }}"
                                                       required>
                                                @error('merchant_name')
                                                <div class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Merchant Address</label>
                                                <input type="text" name="merchant_address"
                                                       class="form-control @error('merchant_address') is-invalid @enderror"
                                                       placeholder="Merchant address"
                                                       value="{{ old('merchant_address') }}">
                                                @error('merchant_address')
                                                <div class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Merchant registration No.</label>
                                                <input type="text" name="merchant_reg_no"
                                                       value="{{ old('merchant_reg_no') }}"
                                                       class="form-control @error('merchant_reg_no') is-invalid @enderror"
                                                       data-inputmask="'mask': ['999-999-99999', '017 762 73545']"
                                                       data-mask required>
                                                @error('merchant_reg_no')
                                                <div class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Trading License Expiry date</label>
                                                <div class="input-group">
                                                    <input type="text" name="merchant_reg_expiry_date"
                                                           value="{{ old('merchant_reg_expiry_date') }}"
                                                           class="form-control datepicker @error('merchant_reg_expiry_date', date('d/m/Y') ) is-invalid @enderror"
                                                           data-inputmask-alias="datetime"
                                                           data-inputmask-inputformat="dd/mm/yyyy" data-mask required>
                                                    <div class="input-group-addon">
                                                        <span class="glyphicon glyphicon-th"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <!-- textarea -->
                                            <div class="form-group">
                                                <label>Merchant Email</label>
                                                <input type="email" name="merchant_email"
                                                       class="form-control @error('merchant_email') is-invalid @enderror"
                                                       placeholder="Merchant email" value="{{ old('merchant_email') }}"
                                                       required>
                                                @error('merchant_email')
                                                <div class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Merchant Mobile</label>
                                                <input type="text" name="merchant_mobile"
                                                       value="{{ old('merchant_mobile') }}"
                                                       class="form-control @error('merchant_mobile') is-invalid @enderror"
                                                       data-inputmask="'mask': ['99999999999', '99999999999']" data-mask
                                                       required>
                                                @error('merchant_mobile')
                                                <div class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <!-- textarea -->
                                            <div class="form-group">
                                                <label>Merchant Phone</label>
                                                <input type="text" name="merchant_phone"
                                                       value="{{ old('merchant_phone') }}"
                                                       class="form-control @error('merchant_phone') is-invalid @enderror"
                                                       data-inputmask="'mask': ['99-999-9999', '99 999 9999']"
                                                       data-mask>
                                                @error('merchant_phone')
                                                <div class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Merchant Fax</label>
                                                <input type="text" name="merchant_fax" value="{{ old('merchant_fax') }}"
                                                       class="form-control @error('merchant_fax') is-invalid @enderror"
                                                       placeholder="Merchant fax">
                                                @error('merchant_fax')
                                                <div class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Password</label>
                                                <input type="password" name="merchant_password"
                                                       value="{{ old('merchant_password') }}"
                                                       class="form-control @error('merchant_password') is-invalid @enderror"
                                                       placeholder="Password" required>
                                                @error('merchant_password')
                                                <div class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <label>Confirm Password</label>
                                                <input type="password" name="merchant_password_confirm"
                                                       value="{{ old('merchant_password_confirm') }}"
                                                       class="form-control @error('merchant_password_confirm') is-invalid @enderror"
                                                       placeholder="Password" required>
                                                @error('merchant_password_confirm')
                                                <div class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-5 pr-4">
                                <div class="form-group">
                                    <label for="vat_visibility">Vat Visible? </label>
                                    <select name="vat_visibility" id="vat_applicable_to" class="form-control" required>
                                        <option value="">---Choose---</option>
                                        <option value="1" @if(old('vat_applicable_to') == '1' ) selected @endif>Yes
                                        </option>
                                        <option value="0" @if(old('vat_visibility') == '0' ) selected @endif>No</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="vat_applicable_to">Vat applicable to </label>
                                    <select name="vat_applicable_to" id="vat_applicable_to" class="form-control"
                                            required>
                                        <option value="">---Choose---</option>
                                        <option value="customer"
                                                @if(old('vat_applicable_to', getOption('vat_applicable_to')) == 'customer' ) selected @endif>
                                            Customer
                                        </option>
                                        <option value="merchant"
                                                @if(old('vat_applicable_to', getOption('vat_applicable_to')) == 'merchant' ) selected @endif>
                                            Merchant
                                        </option>
                                        <option value="vendor"
                                                @if(old('vat_applicable_to', getOption('vat_applicable_to')) == 'vendor' ) selected @endif>
                                            Vendor
                                        </option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="inputPassword5">Honorium Service charge</label>
                                    <div class="input-group">
                                        <input type="number" id="inputPassword5" name="honorium_service_charge"
                                               value="{{ old('honorium_service_charge', getOption('honorium_service_charge', 0))}}"
                                               class="form-control" aria-describedby="passwordHelpBlock" required>
                                        <div class="input-group-append">
                                            <div class="input-group-text p-0">
                                                <select class="form-control" name="honorium_type">
                                                    <option value="percent">Percent</option>
                                                    <option value="fixed">Fixed</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <small id="passwordHelpBlock" class="form-text text-muted">
                                        If company / Merchant book honorium items. then the charge will be applicable.
                                    </small>
                                </div>
                                <div class="form-group">
                                    <label>Upload Image</label>
                                    <div class="input-group">
                                        <div class="input-group-btn">
                                            <div class="btn btn-default btn-file">
                                                Browse… <input type="file" name="logo" id="imgInp">
                                            </div>
                                        </div>
                                        <input type="text" class="form-control" readonly><br>
                                    </div>
                                    <img id='img-upload'/>
                                    <span
                                        class="help-text">Please upload a standard logo. which will be below 100Kb</span>
                                </div>
                            </div>
                        </div>


                        <hr>
                        <div class="row">
                            <h4>Offices <span class="badge badge-success pull-right float-right" id="addProperty"><i class="fa fa-plus"></i> Add new</span></h4>
                            <div class="col-sm-12" id="buildyourform">
                                <div class="row fieldwrapper" id="field1">
                                    <div class="col-md-4 form-group">
                                        <div class="select2-purple">
                                            <input name="office_name[]" class="form-control" placeholder="Office name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <input class="fieldname form-control" name="office_address[]" placeholder="Address">
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <input type="text" step="1" class="fieldname form-control"
                                               name="office_lat[]" placeholder="Latitude">
                                    </div>
                                    <div class="col-md-2 form-group">
                                        <input type="text" step="1" class="fieldname form-control"
                                               name="office_lon[]" placeholder="Longitude">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="form-group">
                            <input type="submit" name="Submit" value="Submit" class="btn btn-primary"/>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('header')
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
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

        #img-upload {
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
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script>
        $(function () {

            //Initialize Select2 Elements
            $('.select2').select2()

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
            var intId = 2;
            $("#addProperty").click(function () {
                var lastField = $("#buildyourform div:last");
                intId = parseFloat(intId) + 1;
                let position = parseFloat($(".fieldwrapper").length) + 1;
                $('#endingPointPosition').val(position);
                console.log(intId);
                var fieldWrapper = $("<div class='row fieldwrapper' id='field" + intId + "'/>");
                fieldWrapper.data("idx", intId);

                var field_name_wrapper = $('<div class="col-md-4 form-group">' +
                    '<input name="office_name[]" class="form-control" placeholder="Office name">' +
                    '</div>');

                var field_address_wrapper = $("<div class='col-md-3 form-group'>" +
                    "<input class='fieldname form-control' type='text' name='office_address[]' placeholder='Address'>" +
                    "</div>");

                var field_type_wrapper = $("<div class='col-md-2 form-group'>" +
                    "<input class='fieldname form-control' type='text' name='office_lat[]' placeholder='Latitude'>" +
                    "</div>");

                let field_position_wrapper = $("\n" +
                    "<div class='col-md-2 form-group'>\n" +
                    "<input type='text' class='fieldname form-control' name='office_lon[]' placeholder='Longitude' required>\n" +
                    "</div>");

                var removeButton = $("<div class='col-md-1'>" +
                    "<button type='button' class='btn btn-danger remove'>" +
                    "<i class='fa fa-trash-o'></i>" +
                    "X</button>" +
                    "</div> </div>");

                removeButton.click(function (e) {
                    removeField(this);
                });
                fieldWrapper.append(field_name_wrapper);
                fieldWrapper.append(field_address_wrapper);
                fieldWrapper.append(field_type_wrapper);
                fieldWrapper.append(field_position_wrapper);
                fieldWrapper.append(removeButton);
                $("#buildyourform").append(fieldWrapper);
                initializeSelect2($(select2));
            });

            function removeField(_this) {
                var id = _this.getAttribute('data-id');
                if (id) {

                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    });
                    $.ajax({
                        url: '',
                        type: 'post',
                        data: {field_id: id},
                        success: function (data) {
                            if (data == 'true') {
                                var msg = '<div class="alert alert-success alert-dismissible">' +
                                    '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>' +
                                    '<strong>Successfully deleted field</strong>.' +
                                    '</div>';
                                $('#msg').html(msg);
                            }
                            //console.log(data);

                        }
                    });
                }
                var parent = $(_this).parents('.fieldwrapper');
                $(parent).remove();
                let position = parseFloat($(".fieldwrapper").length);
                $('#endingPointPosition').val(position);
            }
        });
    </script>
@endsection
