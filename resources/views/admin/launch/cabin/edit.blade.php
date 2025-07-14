@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content content-overlap">
        <div class="row">
            <div class="col-12">
                <!-- /.card-header -->
                <div class="col-md-7">
                    <div class="card-body">
                        <form role="form" action="{{ route('dashboard.cabin.update', $cabin->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <input type="hidden" name="vehicle_id" value="{{ $cabin->vehicle_id }}">
                            <input type="hidden" name="tab" value="{{ $cabin->type }}">
                            <div class="form-group">
                                <label>Booking Ownership.</label>
                                <select name="ownership" class="form-control @error('ownership') is-invalid @enderror"
                                        required>
                                    <option value="">Select ownership</option>
                                    @foreach($party_dropdowns as $key => $value)
                                        <option value="{{ $key }}"
                                                @if(old('ownership', $cabin->ownership) == $key) selected @endif>{{ $value }}</option>
                                    @endforeach
                                </select>
                                @error('ownership')
                                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Belongs to (Counter)</label>
                                <select name="ghat_id" class="form-control @error('ghat_id') is-invalid @enderror">
                                    <option value="">Select counter</option>
                                    @foreach($ghat_dropdowns as $id => $value)
                                        <option value="{{ $id }}"
                                                @if(old('ghat_id', $cabin->ghat_id) == $id) selected @endif>{{ $value }}</option>
                                    @endforeach
                                </select>
                                @error('ownership')
                                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>{{ ucfirst( $cabin->type ) }} No.</label>
                                <input type="text" name="cabin_no"
                                       class="form-control @error('cabin_no') is-invalid @enderror"
                                       placeholder="Cabin number" value="{{ old('cabin_no', $cabin->cabin_no) }}"
                                       required>
                                @error('cabin_no')
                                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>{{ ucfirst( $cabin->type ) }} Type</label>
                                <select name="type_id" class="form-control @error('type_id') is-invalid @enderror"
                                        value="{{ old('type_id') }}" id="cabinTypes" required>
                                    <option value="">Select type</option>
                                    @if( $cabin_types )
                                        @foreach( $cabin_types as $type )
                                            <option value="{{ $type->id }}"
                                                    @if(old('type_id', $cabin->type_id) == $type->id) selected @endif>{{ $type->name }}
                                                ({{ ( $type->is_ac ) ? 'AC' : 'Non-Ac' }}) [{{ $type->capacity }}person]
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                <a href="#" class="text-primary" data-toggle="modal" data-target="#typeModal">Add new
                                    type</a>
                                @error('type_id')
                                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>@if($cabin->vehicle->vehicle_type == 'bus') Dekcker @else Floor @endif</label>
                                <select name="floor" class="form-control @error('floor') is-invalid @enderror" required>
                                    <option value="">Select @if($cabin->vehicle->vehicle_type == 'bus') dekcker @else
                                            floor @endif</option>
                                    @if($cabin->vehicle->vehicle_type === 'bus')
                                        <option value="1" @if(old('floor', $cabin->floor) == 1) selected @endif>Lower
                                        </option>
                                        @if($cabin->vehicle->number_of_floor > 1)
                                            <option value="2" @if(old('floor', $cabin->floor) == 2) selected @endif>
                                                Upper
                                            </option>
                                        @endif
                                    @elseif($cabin->vehicle->vehicle_type == 'launch')
                                        @for($i = 1; $i <= 10; $i++)
                                            <option value="{{ $i }}"
                                                    @if(old('floor', $cabin->floor) === $i) selected @endif>
                                                Floor {{ $i }}</option>
                                        @endfor
                                    @else
                                        <option value="1" @if(old('floor', $cabin->floor) == 1) selected @endif>Floor
                                            1
                                        </option>
                                    @endif
                                </select>
                                @error('floor')
                                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Adult Fare</label>
                                <input type="number" name="fare" min="0" max="5000" step="1"
                                       class="form-control @error('fare') is-invalid @enderror"
                                       value="{{ old('fare', $cabin->fare) }}" required>
                                @error('fare')
                                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Child Fare</label>
                                <input type="number" name="child_fare" min="0" max="5000" step="1"
                                       class="form-control @error('child_fare') is-invalid @enderror"
                                       value="{{ old('child_fare', $cabin->child_fare) }}" required>
                                @error('child_fare')
                                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Infant Fare</label>
                                <input type="number" name="infant_fare" min="0" max="5000" step="1"
                                       class="form-control @error('infant_fare') is-invalid @enderror"
                                       value="{{ old('child_fare', $cabin->infant_fare) }}" required>
                                @error('infant_fare')
                                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Service charge</label>
                                <input type="number" name="service_charge" min="0" max="5000" step="1"
                                       class="form-control @error('service_charge') is-invalid @enderror"
                                       value="{{ old('service_charge', $cabin->service_charge) }}" required>
                                @error('service_charge')
                                <div class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Row on layout</label>
                                <select name="cabin_row" class="form-control @error('cabin_row') is-invalid @enderror"
                                        value="{{ old('cabin_row') }}" required>
                                    <option value="1" @if(old('cabin_row', $cabin->cabin_row) == 1) selected @endif>Row
                                        1
                                    </option>
                                    <option value="2" @if(old('cabin_row', $cabin->cabin_row) == 2) selected @endif>Row
                                        2
                                    </option>
                                    <option value="3" @if(old('cabin_row', $cabin->cabin_row) == 3) selected @endif>Row
                                        3
                                    </option>
                                    <option value="4" @if(old('cabin_row', $cabin->cabin_row) == 4) selected @endif>Row
                                        4
                                    </option>
                                    <option value="5" @if(old('cabin_row', $cabin->cabin_row) == 5) selected @endif>Row
                                        5
                                    </option>
                                    @if( $cabin->type == 'seat')
                                        <option value="6" @if(old('cabin_row', $cabin->cabin_row) == 6) selected @endif>
                                            Row 6
                                        </option>
                                        <option value="7" @if(old('cabin_row', $cabin->cabin_row) == 7) selected @endif>
                                            Row 7
                                        </option>
                                        <option value="8" @if(old('cabin_row', $cabin->cabin_row) == 8) selected @endif>
                                            Row 8
                                        </option>
                                        <option value="9" @if(old('cabin_row', $cabin->cabin_row) == 9) selected @endif>
                                            Row 9
                                        </option>
                                        <option value="10"
                                                @if(old('cabin_row', $cabin->cabin_row) == 10) selected @endif>Row 10
                                        </option>
                                        <option value="11"
                                                @if(old('cabin_row', $cabin->cabin_row) == 11) selected @endif>Row 11
                                        </option>
                                        <option value="12"
                                                @if(old('cabin_row', $cabin->cabin_row) == 12) selected @endif>Row 12
                                        </option>
                                        <option value="13"
                                                @if(old('cabin_row', $cabin->cabin_row) == 13) selected @endif>Row 13
                                        </option>
                                        <option value="14"
                                                @if(old('cabin_row', $cabin->cabin_row) == 14) selected @endif>Row 14
                                        </option>
                                        <option value="15"
                                                @if(old('cabin_row', $cabin->cabin_row) == 15) selected @endif>Row 15
                                        </option>
                                        <option value="16"
                                                @if(old('cabin_row', $cabin->cabin_row) == 16) selected @endif>Row 16
                                        </option>
                                        <option value="17"
                                                @if(old('cabin_row', $cabin->cabin_row) == 17) selected @endif>Row 17
                                        </option>
                                        <option value="18"
                                                @if(old('cabin_row', $cabin->cabin_row) == 18) selected @endif>Row 18
                                        </option>
                                        <option value="19"
                                                @if(old('cabin_row', $cabin->cabin_row) == 19) selected @endif>Row 19
                                        </option>
                                        <option value="20"
                                                @if(old('cabin_row', $cabin->cabin_row) == 20) selected @endif>Row 20
                                        </option>
                                        <option value="21"
                                                @if(old('cabin_row', $cabin->cabin_row) == 21) selected @endif>Row 21
                                        </option>
                                        <option value="22"
                                                @if(old('cabin_row', $cabin->cabin_row) == 22) selected @endif>Row 22
                                        </option>
                                        <option value="23"
                                                @if(old('cabin_row', $cabin->cabin_row) == 23) selected @endif>Row 23
                                        </option>
                                        <option value="24"
                                                @if(old('cabin_row', $cabin->cabin_row) == 24) selected @endif>Row 24
                                        </option>
                                        <option value="25"
                                                @if(old('cabin_row', $cabin->cabin_row) == 25) selected @endif>Row 25
                                        </option>
                                        <option value="26"
                                                @if(old('cabin_row', $cabin->cabin_row) == 26) selected @endif>Row 26
                                        </option>
                                        <option value="27"
                                                @if(old('cabin_row', $cabin->cabin_row) == 27) selected @endif>Row 27
                                        </option>
                                        <option value="28"
                                                @if(old('cabin_row', $cabin->cabin_row) == 28) selected @endif>Row 28
                                        </option>
                                        <option value="29"
                                                @if(old('cabin_row', $cabin->cabin_row) == 29) selected @endif>Row 29
                                        </option>
                                        <option value="30"
                                                @if(old('cabin_row', $cabin->cabin_row) == 30) selected @endif>Row 30
                                        </option>
                                    @endif
                                </select>
                                @error('cabin_row')
                                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Position on Row</label>
                                <input type="number" min="0" max="100" step="1" name="cabin_position"
                                       class="form-control @error('cabin_position') is-invalid @enderror"
                                       value="{{ old('cabin_position', $cabin->cabin_position) }}" required>
                                @error('cabin_position')
                                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="checkboxPrimary">Keep reserved?</label>
                                <div class="icheck-primary">
                                    <input type="checkbox" class="cancel-item" id="checkboxPrimary" name="is_reserved"
                                           value="1" @if( $cabin->is_reserved ) checked @endif>
                                    <label for="checkboxPrimary">
                                        Yes
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <button class="btn btn-block btn-primary" type="submit">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-sm-5">
                </div>
            </div>
        </div>
    </section>
    <!-- Modal -->
    <div class="modal fade" id="typeModal" data-backdrop="static" tabindex="-1" role="dialog"
         aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('dashboard.cabintype.store') }}" id="typeForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">Add {{ $cabin->type }} type</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        @csrf
                        <input type="hidden" name="type" value="cabin">
                        <!-- text input -->
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   placeholder="Cabin type" value="{{ old('name') }}" required>
                            @error('name')
                            <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Type Letter.</label>
                            <input type="text" name="letter" value="{{ old('letter') }}"
                                   placeholder="Exp. (S = Single, D = Double, F = Family)"
                                   class="form-control @error('letter') is-invalid @enderror" required>
                            @error('letter')
                            <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Passenger capacity</label>
                            <select name="capacity" value="{{ old('capacity') }}"
                                    class="form-control @error('capacity') is-invalid @enderror" required>
                                <option>1</option>
                                <option>2</option>
                                <option>3</option>
                                <option>4</option>
                                <option>5</option>
                            </select>
                            @error('capacity')
                            <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <input type="checkbox" id="isAc" name="is_ac" value="1">
                            <label>AC available</label>
                            @error('is_ac')
                            <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                  </span>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('footer')

    <script type="text/javascript">
        $(function () {
            $('.openTypeModal').click(function (e) {
                e.defaultPrevented;
                var typeModal = $('#typeModal');
                $(typeModal).find('form#typeForm').trigger("reset");
                let type = $(this).attr('data-type');
                $(typeModal).find('.modal-title').html('Add ' + type + ' type');
                $(typeModal).find('[name="type"]').val($(this).attr('data-type'));
                $(typeModal).modal("show");
                return false;
            });

            $('#typeForm').submit(function (e) {
                e.defaultPrevented;
                var data = $(this).serialize();
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    type: "POST",
                    url: "{{route('dashboard.cabintype.store')}}",
                    data: data,
                    dataType: 'json',
                    success: function (data) {
                        if (data.status == true) {
                            $('#typeModal').modal('hide');
                            var $option = $("<option/>", {
                                value: data.item.id,
                                text: data.item.name,
                                selected: true
                            });
                            $('#cabinTypes').append($option);
                            $("#cabinTypes").val(data.item.id);
                        }

                        Toast.fire({
                            icon: data.label,
                            title: data.content
                        });
                    },
                    error: function (jqXHR, status, err) {
                        Toast.fire({
                            icon: data.label,
                            title: data.content
                        });
                    }
                });

                return false;
            });
        });
    </script>
@endsection
