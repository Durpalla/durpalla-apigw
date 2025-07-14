@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content content-overlap">
        <div class="row">
            <div class="col-12">
                <!-- /.card-header -->
                <div class="col-md-7">
                    <div class="card-body">
                        <form role="form" action="{{ route('dashboard.routes.store') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label for="routeTypeVal">Service type</label>
                                        <select name="service_type"
                                                class="form-control @error('service_type') is-invalid @enderror"
                                                id="serviceTypes" required>
                                            <option value="">Select type</option>
                                            @foreach($service_list as $key => $value)
                                                <option value="{{ $key }}"
                                                        @if(old('service_type') == $key) selected @endif>{{ $value }}</option>
                                            @endforeach
                                        </select>
                                        @error('service_type')
                                        <div class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label for="routeNameVal">Route Name</label>
                                        <input type="text" id="routeNameVal" name="route_name"
                                               value="{{ old('route_name') }}"
                                               class="form-control disabled @error('route_name') is-invalid @enderror"
                                               required readonly>
                                        @error('route_name')
                                        <div class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Route No.</label>
                                        <input type="text" name="route_no" value="{{ old('route_no') }}"
                                               class="form-control @error('route_no') is-invalid @enderror" required>
                                        @error('route_no')
                                        <div class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Route Type</label>
                                        <select name="route_type"
                                                class="form-control @error('route_type') is-invalid @enderror" required>
                                            <option value="direct">Direct</option>
                                            <option value="local">Local</option>
                                        </select>
                                        @error('route_type')
                                        <div class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="clearfix"></div>
                            </div>
                            <hr>
                            <h4>Boarding points (Ghat) <span class="badge badge-success pull-right float-right"
                                                             id="addProperty"><i class="fa fa-plus"></i> Add new</span>
                            </h4>
                            <hr>
                            <div class="row">
                                <div class="col-sm-12" id="buildyourform">
                                    <div class="row fieldwrapper" id="field1">
                                        <div class="col-md-6 form-group">
                                            <div class="select2-purple">
                                                <select name="property_name[]" class="select2" id="starting"
                                                        data-placeholder="Select ghat"
                                                        data-dropdown-css-class="select2-purple" style="width: 100%;"
                                                        required>

                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <select class="fieldname form-control" name="property_type[]">
                                                <option value="start">Starting point</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 form-group">
                                            <input type="number" step="1" class="fieldname form-control"
                                                   name="property_position[]" value="1" readonly>
                                        </div>
                                    </div>
                                    <div class="row fieldwrapper" id="field2">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <div class="select2-purple">
                                                    <select name="property_name[]" class="select2" id="ending"
                                                            data-placeholder="Select ghat"
                                                            data-dropdown-css-class="select2-purple"
                                                            style="width: 100%;">

                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <select class="fieldname form-control" name="property_type[]">
                                                <option value="end">Ending point</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 form-group">
                                            <input type="number" step="1" id="endingPointPosition"
                                                   class="fieldname form-control"
                                                   name="property_position[]" min="2" max="20" minlength="1"
                                                   maxlength="2" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- <div class="row">
                                  <div class="col-md-12">
                                    <button id="" type="button" class="btn btn-lg btn-success pull-right float-right"><i class="fas fa-plus"></i> Add new boarding point</button>
                                  </div>
                                </div> -->
                            </div>
                            <hr>
                            <div class="form-group">
                                <button type="submit" class="btn btn-lg btn-primary">Create route</button>
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
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/AdminLte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <script>
        let service_type = 'launch';
        $(function () {
            let Url = "{{ route('dashboard.ghat.suggest') }}";

            //Initialize Select2 Elements
            $(".select2").each(function () {
                initializeSelect2(this);
            });

            $('#serviceTypes').change(function (e) {
                if ($(this).val() !== '') {
                    service_type = $(this).val();
                }
            });

            function initializeSelect2(select2) {
                $(select2).select2({
                    placeholder: "Select ghat",
                    allowClear: true,
                    cache: false,
                    theme: 'bootstrap4',
                    ajax: {
                        url: Url,
                        dataType: 'json',
                        type: "GET",
                        quietMillis: 50,
                        data: function (term) {
                            return {
                                term: term.term,
                                service_type: service_type
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
            };

            $('select#starting, select#ending').on("select2:select", function (e) {
                let starting = $('select#starting').val();
                let ending = $('select#ending').val();
                $.ajax({
                    url: "{{ route('dashboard.route.name') }}",
                    dataType: "json",
                    data: {starting: starting, ending: ending},
                    success: function (data) {
                        if (data.status == true) {
                            $('input#routeNameVal').val(data.route_name);
                        }
                    }
                });
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

                var field_name_wrapper = $('<div class="col-md-6 form-group">' +
                    '<div class="select2-purple">' +
                    '</div></div>');
                var select2 = $('<select name="property_name[]" class="select2" data-placeholder="Select ghat" data-dropdown-css-class="select2-purple" style="width: 100%;">' +
                    '</select>');

                var field_type_wrapper = $("<div class='col-md-3 form-group'>" +
                    "<select class='fieldname form-control' name='property_type[]'>" +
                    "<option value='via'>Via</option>" +
                    "</select>" +
                    "</div>");

                let field_position_wrapper = $("\n" +
                    "<div class='col-md-2 form-group'>\n" +
                    "<input type='number' step='1' class='fieldname form-control' name='property_position[]' min='2' max='20' minlength='1' maxlength='2' required>\n" +
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
                field_name_wrapper.append(select2);
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
