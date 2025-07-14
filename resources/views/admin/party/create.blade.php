@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content content-overlap">
        <form method="post" action="{{ route('parties.store')}}" role="form">
            @csrf
            <div class="row">
                <!-- /.card-header -->
                <div class="col-7">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Name of party</label>
                            <input type="text" class="form-control" value="{{ old('name') }}" name="name"
                                   placeholder="Name" required/>
                        </div>
                        <div class="form-group">
                            <label>Slug</label>
                            <input type="text" class="form-control" value="{{ old('slug') }}" name="slug"
                                   placeholder="Slug" required/>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description"
                                      style="min-height: 80px;">{{ old('description') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea class="form-control" name="address"
                                      style="min-height: 80px;">{{ old('address') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label>Domain name</label>
                            <input type="text" class="form-control" value="{{ old('domain_name') }}" name="domain_name"
                                   placeholder="Domain name" required/>
                        </div>
                        <div class="form-group">
                            <label>Mobile</label>
                            <input type="text" class="form-control" value="{{ old('mobile') }}" name="mobile"
                                   placeholder="Mobile" required/>
                        </div>
                    </div>
                </div>
                <div class="col-5">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" value="{{ old('email') }}" name="email"
                                   placeholder="Email" required/>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" name="password" placeholder="Password"
                                   required/>
                        </div>
                        <div class="form-group">
                            <label>Password confirm</label>
                            <input type="password" class="form-control" name="password_confirm"
                                   placeholder="Confirm password"
                                   required/>
                        </div>
                        <div class="form-group">
                            <label>Services</label><hr>
                            @foreach($service_dropdowns as $key => $value)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" name="services[]" type="checkbox" id="inlineCheckbox{{$key}}" value="{{$key}}" checked>
                                <label class="form-check-label" for="inlineCheckbox{{$key}}">{{ $value }}</label>
                            </div>
                            @endforeach
                        </div>
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

                <!-- <div class="row">
                  <div class="col-md-12">
                    <button id="" type="button" class="btn btn-lg btn-success pull-right float-right"><i class="fas fa-plus"></i> Add new boarding point</button>
                  </div>
                </div> -->
            </div>
            <hr>

            <div class="form-group">
                <input type="submit" name="Submit" value="Submit" class="btn btn-primary"/>
            </div>
        </form>
    </section>

@endsection

@section('header')

@endsection

@section('footer')
<script>
    jQuery(function($) {
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
