@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content content-overlap">
        <form action="{{ route('dashboard.vehicle.store') }}" method="POST" enctype="multipart/form-data">
            <div class="row">
                <!-- /.card-header -->
                <div class="col-md-7">
                    <div class="card-body">
                        @csrf
                        <input type="hidden" name="tab" value="vehicle">
                        <div class="form-group">
                            <label for="routeTypeVal">Service type</label>
                            <select name="vehicle_type" class="form-control @error('vehicle_type') is-invalid @enderror" id="serviceTypes" required>
                                <option value="">Select type</option>
                                @foreach($service_list as $key => $value)
                                    <option value="{{ $key }}" @if(old('vehicle_type', $type) == $key) selected @endif>{{ $value }}</option>
                                @endforeach
                            </select>
                            @error('vehicle_type')
                            <div class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Merchant</label>
                            <select name="merchant_id" class="form-control @error('merchant_id') is-invalid @enderror"
                                    value="{{ old('merchant_id') }}" required>
                                @if( !$id && $merchant_dropdowns->count() > 1)
                                    <option value="">Merchant select</option>
                                @endif
                                @foreach( $merchant_dropdowns as $key => $value )
                                    @if($id && $key == $id)
                                        <option value="{{ old('merchant_id', $key) }}"
                                                @if(old('merchant_id') == $key) selected @endif>{{ $value }}</option>
                                    @else
                                        <option value="{{ old('merchant_id', $key) }}"
                                                @if(old('merchant_id') == $key) selected @endif>{{ $value }}</option>
                                    @endif
                                @endforeach
                            </select>

                            @error('merchant_id')
                            <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Route</label> @canany(['route-create', 'route-add'])<a
                                href="{{ route('dashboard.routes.create') }}" data-toggle="modal"
                                data-target="#routeModal"><i class="fas fa-plus"></i> add new</a>@endcanany
                            <select name="route_id" id="route_id"
                                    class="form-control select2Route @error('route_id') is-invalid @enderror"
                                    value="{{ old('route_id') }}" required>
                                <option value="">Select Route</option>
                            </select>

                            @error('route_id')
                            <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>vehicle Name</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   placeholder="vehicle name" value="{{ old('name') }}" required>
                            @error('name')
                            <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Reg. No.</label>
                            <input type="text" name="registration_no"
                                   class="form-control @error('registration_no') is-invalid @enderror"
                                   placeholder="Registration no." value="{{ old('registration_no') }}" required>
                            @error('registration_no')
                            <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Registration expiration date</label>
                            <div class="input-group">
                                <input type="text" name="registration_expiry_date"
                                       value="{{ old('registration_expiry_date') }}"
                                       class="form-control datepicker @error('registration_expiry_date') is-invalid @enderror"
                                       data-inputmask-alias="datetime" data-inputmask-inputformat="dd/mm/yyyy" data-mask
                                       required>
                                <div class="input-group-addon">
                                    <span class="glyphicon glyphicon-th"></span>
                                </div>
                            </div>
                            @error('registration_expiry_date')
                            <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Fitness expiry date</label>
                            <div class="input-group">
                                <input type="text" name="fitness_expiry_date" value="{{ old('fitness_expiry_date') }}"
                                       class="form-control datepicker @error('fitness_expiry_date') is-invalid @enderror"
                                       data-inputmask-alias="datetime" data-inputmask-inputformat="dd/mm/yyyy" data-mask
                                       required>
                                <div class="input-group-addon">
                                    <span class="glyphicon glyphicon-th"></span>
                                </div>
                            </div>
                            @error('fitness_expiry_date')
                            <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                            @enderror
                        </div>
                        <div class="form-group @if($type !== 'launch') d-none @endif" id="deckFareParent">
                            <label>Deck Passengers capacity</label>
                            <input type="number" min="0" max="5000" name="passengers_capacity"
                                   class="form-control @error('passengers_capacity') is-invalid @enderror"
                                   value="{{ old('passengers_capacity', 0) }}">
                            @error('passengers_capacity')
                            <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <button class="btn btn-block btn-primary" type="submit">Save</button>
                        </div>
                    </div>
                </div>
                <div class="col-sm-5">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Number of Floors?</label>
                            <select class="form-control" name="number_of_floor">
                                @for($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}" @if(old('number_of_floor') === $i) selected @endif>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Default Floor?</label>
                            <select class="form-control" name="default_floor">
                                @for($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}" @if(old('default_floor') === $i) selected @endif>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Default tab?</label>
                            <select class="form-control" name="default_tab">
                                <option value="cabin" @if(old('default_tab') == 'cabin') selected @endif>Cabin</option>
                                <option value="seat" @if(old('default_tab') == 'seat') selected @endif>Seat</option>
                                <option value="deck" @if(old('default_tab') == 'deck') selected @endif>Deck</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>AC Available?</label>
                            <select class="form-control" name="ac_available">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>NID Verification required?</label>
                            <select class="form-control" name="nid_verification_check">
                                <option value="0" @if(old('nid_verification_check') == 0) selected @endif>No</option>
                                <option value="1" @if(old('nid_verification_check') == 1) selected @endif>Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Upload photo</label>
                            <input type="file" name="photo" class="form-control-file">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </section>
    <!-- Modal -->
    <div class="modal fade" id="routeModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
         aria-hidden="true">
        <div class="modal-dialog card" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">Add new route</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="card-body">

                    <form id="routeForm" method="POST" action="{{ route('dashboard.routes.store')}}">
                        @csrf
                        <div class="row">
                            <div class="col-sm-4">
                                <!-- text input -->
                                <div class="form-group">
                                    <label>Route name</label>
                                    <input type="text" id="routeNameVal" name="route_name" id="route_name"
                                           class="form-control @error('route_name') is-invalid @enderror"
                                           placeholder="Route name" required readonly>
                                    @error('route_name')
                                    <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                              </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label>Route No.</label>
                                    <input type="text" name="route_no" id="route_no"
                                           class="form-control @error('route_no') is-invalid @enderror" required>
                                    @error('route_no')
                                    <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                              </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-sm-4">
                                <div class="form-group">
                                    <label>Route Type</label>
                                    <select name="route_type" id="route_type"
                                            class="form-control  @error('route_type') is-invalid @enderror" required>
                                        <option value="direct">Direct</option>
                                        <option value="local">Local</option>
                                    </select>
                                    @error('route_type')
                                    <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                              </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                        <hr>
                        <h4>Boarding points (Ghat) <span class="badge badge-success pull-right float-right"
                                                         id="addProperty"><i class="fa fa-plus"></i> Add new</span></h4>
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
                                    <div class="col-md-6 form-group">
                                        <select class="fieldname form-control" name="property_type[]">
                                            <option value="start">Starting point</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row fieldwrapper" id="field2">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="select2-purple">
                                                <select name="property_name[]" class="select2" id="ending"
                                                        data-placeholder="Select ghat"
                                                        data-dropdown-css-class="select2-purple" style="width: 100%;">

                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <select class="fieldname form-control" name="property_type[]">
                                            <option value="end">Ending point</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="form-group">
                            <button type="submit" class="btn btn-lg btn-primary">Create route</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal End -->
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
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/moment/moment.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/inputmask/min/jquery.inputmask.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/daterangepicker/daterangepicker.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <!-- Tempusdominus Bootstrap 4 -->
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <script>
        let service_type = "{{ $type }}";
        $(function () {
            $('#serviceTypes').change(function() {
                if($(this).val() !== '') {
                    service_type = $(this).val();
                }

                if(service_type === 'launch') {
                    $('#deckFareParent').removeClass('d-none');
                } else {
                    $('#deckFareParent').addClass('d-none');
                }
            });
            let Url = "{{ route('dashboard.ghat.suggest') }}";

            //Initialize Select2 Elements
            $(".select2").each(function () {
                initializeSelect2(this);
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

            $('#route_id').select2({
                placeholder: "Select route",
                allowClear: true,
                cache: false,
                theme: 'bootstrap4',
                ajax: {
                    url: "{{ route('dashboard.routes.suggest') }}",
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
                console.log(intId);
                var fieldWrapper = $("<div class='row fieldwrapper' id='field" + intId + "'/>");
                fieldWrapper.data("idx", intId);

                var field_name_wrapper = $('<div class="col-md-6 form-group">' +
                    '<div class="select2-purple">' +
                    '</div></div>');
                var select2 = $('<select name="property_name[]" class="select2" data-placeholder="Select ghat" data-dropdown-css-class="select2-purple" style="width: 100%;">' +
                    '</select>');

                var field_paginate = $("<div class='col-md-5 form-group'>" +
                    "<select class='fieldname form-control' name='property_type[]'>" +
                    "<option value='via'>Via</option>" +
                    "</select>" +
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
                fieldWrapper.append(field_paginate);
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
            }

            $('#routeForm').submit(function (event) {
                event.defaultPrevented;
                var data = $(this).serialize();
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                $.ajax({
                    url: "{{ route('dashboard.routes.store') }}",
                    type: "POST",
                    data: data,
                    success: function (response) {
                        if (response.status == true) {
                            var $option = $("<option/>", {
                                value: response.route.id,
                                text: response.route.name,
                                selected: true
                            });
                            $('#route_id').append($option);
                            $("#route_id").val(response.route.id);
                            $('#routeModal').modal('hide');
                        } else {
                            alert(response.content)
                        }
                    },
                });
                return false;
            });

            $('.datepicker').datepicker({
                format: 'dd/mm/yyyy',
                todayHighlight: 'TRUE',
                autoclose: true,
                startDate: "-360d"
            }).on('changeDate', function (ev) {
                $(this).datepicker('hide');
            });
            // const date = new Date();
            // date.setDate(date.getDate() + 7);
            // $('.datepicker').datepicker('setStartDate', date);
            //Timepicker
            $('#timepicker').datetimepicker({
                format: 'LT'
            });

            //Datemask dd/mm/yyyy
            $('#datemask').inputmask('dd/mm/yyyy', {'placeholder': 'dd/mm/yyyy'});
            //Datemask2 mm/dd/yyyy
            $('#datemask2').inputmask('mm/dd/yyyy', {'placeholder': 'mm/dd/yyyy'});
            //Money Euro
            $('[data-mask]').inputmask();
            //Timepicker
        });
    </script>
@endsection
