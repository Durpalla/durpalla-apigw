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
                  <a href="{{ route('dashboard.user.index') }}" class="btn btn-xs btn-primary"><i class="fa fa-angle-left"></i> back</a>
                </div>
              </div>
            </div>
            <!-- /.card-header -->
            <form name="item" id="customerForm" action="{{ route('dashboard.user.store')}}" method="POST" role="form" enctype="multipart/form-data">
              @csrf
              <div class="row">
                <div class="col-md-7">
                            @if(count(array_intersect($errors->all(), ['first_name', 'mobile', 'username', 'email'])) > 0)
                              error found
                            @endif
                            <div class="card-body" style="border:1px solid #eee;">
                              <div class="row">
                                  <div class="col-sm-6">
                                    <div class="form-group">
                                      <label for="name">Name</label>
                                      <input type="text" id="name" name="name" class="form-control @if($errors->has('name')) is-invalid @endif" value="{{ old('name') }}" placeholder="First name">
                                      @if($errors->has('name'))
                                      <div class="invalid-feedback" style="display:block;">
                                        {{ $errors->first('name') }}
                                      </div>
                                      @endif
                                    </div>
                                  </div>
                                  <div class="col-sm-6">
                                    <div class="form-group">
                                      <label for="username">Username</label>
                                      <input type="text" name="username" class="form-control" value="{{ old('username') }}" placeholder="Username">
                                      <!-- <select class="form-control" name="gender" @if($errors->has('gender')) is-invalid @endif" >
                                        <option value="">Select</option>
                                        <option value="male" {{ ( old('gender') == 'male') ? 'selected':'' }}>Male</option>
                                        <option value="female" {{ ( old('gender') == 'female') ? 'selected':'' }}>Female</option>
                                        <option value="other" {{ ( old('gender') == 'other') ? 'selected':'' }}>Other</option>
                                      </select> -->
                                      @if($errors->has('username'))
                                      <div class="invalid-feedback" style="display:block;">
                                        {{ $errors->first('username') }}
                                      </div>
                                      @endif
                                    </div>
                                  </div>
                                  <div class="clearfix"></div>
                                  <div class="col-sm-6">
                                    <div class="form-group">
                                      <label>Email</label>
                                      <input type="email" name="email" class="form-control @if($errors->has('email')) is-invalid @endif" value="{{ old('email') }}" placeholder="Email address">
                                      @if($errors->has('email'))
                                      <div class="invalid-feedback" style="display:block;">
                                        {{ $errors->first('email') }}
                                      </div>
                                      @endif
                                    </div>
                                  </div>
                                  <div class="col-sm-6">
                                    <div class="form-group">
                                      <label>Mobile</label>
                                      <input type="text" name="mobile" class="form-control @if($errors->has('mobile')) is-invalid @endif" value="{{ old('mobile') }}" placeholder="Mobile">
                                      @if($errors->has('mobile'))
                                      <div class="invalid-feedback" style="display:block;">
                                        {{ $errors->first('mobile') }}
                                      </div>
                                      @endif
                                    </div>
                                  </div>
                                  <div class="clearfix"></div>
                                  <div class="col-sm-6">
                                    <div class="form-group">
                                      <label>Role</label>
                                      <select class="form-control" id="selectRole" name="role" @if($errors->has('role')) is-invalid @endif" >
                                        <option value="">Select</option>
                                        @foreach( $roles as $k => $v )
                                        <option value="{{ $k }}" @if(old('role') == $k) selected @endif>{{ $v }}</option>
                                        @endforeach
                                      </select>
                                      @if($errors->has('role'))
                                      <div class="invalid-feedback" style="display:block;">
                                        {{ $errors->first('role') }}
                                      </div>
                                      @endif
                                    </div>
                                  </div>
                                  <div class="col-sm-6">
                                    <div class="form-group">
                                      <label>Designation</label>  <a href="{{ route('dashboard.designation.create') }}" data-toggle="modal" data-target="#routeModal"><i class="fas fa-plus"></i> add new</a>
                                      <select class="form-control" id="designationIds" name="designation_id" @if($errors->has('designation_id')) is-invalid @endif" >
                                        <option value="">Select</option>
                                        @foreach( $designations as $k => $v )
                                        <option value="{{ $k }}" @if(old('designation_id') == $k) selected @endif>{{ $v }}</option>
                                        @endforeach
                                      </select>
                                      @if($errors->has('designation_id'))
                                      <div class="invalid-feedback" style="display:block;">
                                        {{ $errors->first('designation_id') }}
                                      </div>
                                      @endif
                                    </div>
                                  </div>
                                  <div class="clearfix"></div>
                                  <div class="col-6">
                                      <div class="form-group d-none" id="counterDIV">
                                          <label>Select Counter</label>
                                          <select class="form-control" id="counterID" name="counter_id" @if($errors->has('counter_id')) is-invalid @endif" >
                                          <option value="">Select</option>
                                          @foreach( $ghat_dropdowns as $k => $v )
                                              <option value="{{ $k }}" @if(old('counter_id') == $k) selected @endif>{{ $v }}</option>
                                              @endforeach
                                              </select>
                                              @if($errors->has('counter_id'))
                                                  <div class="invalid-feedback" style="display:block;">
                                                      {{ $errors->first('counter_id') }}
                                                  </div>
                                              @endif
                                      </div>
                                  </div>
                                  <div class="col-6"></div>
                                  <div class="clearfix"></div>
                                  <div class="col-sm-6">
                                    <div class="form-group">
                                      <label>Password</label>
                                      <input type="password" name="password" class="form-control @if($errors->has('password')) is-invalid @endif" value="{{ old('password') }}" placeholder="Password">
                                      @if($errors->has('password'))
                                      <div class="invalid-feedback" style="display:block;">
                                        {{ $errors->first('password') }}
                                      </div>
                                      @endif
                                    </div>
                                  </div>
                                  <div class="col-sm-6">
                                    <div class="form-group">
                                      <label>Password confirm</label>
                                      <input type="password" name="password_confirm" class="form-control @if($errors->has('password_confirm')) is-invalid @endif" value="{{ old('password_confirm') }}" placeholder="Password">
                                      @if($errors->has('password_confirm'))
                                      <div class="invalid-feedback" style="display:block;">
                                        {{ $errors->first('password_confirm') }}
                                      </div>
                                      @endif
                                    </div>
                                  </div>
                                  <div class="clearfix"></div>
                                </div>
                                <div class="card-footer">
                                  <button class="btn btn-success" type="submit" style="color: #fff;">
                                    Create user
                                  </button>
                                </div>
                            </div>
                          </div>
                          <div class="col-sm-5">

                            <div class="form-group">
                              <label>Profile picture</label>
                              <div class="input-group">
                                <span class="input-group-btn">
                                  <span class="btn btn-default btn-file">
                                    Browse… <input type="file" name="avatar" id="imgInp">
                                  </span>
                                </span>
                                <input type="text" class="form-control" readonly><br>
                              </div>
                              <img id='img-upload'/>
                              <span class="help-text">Please upload a standard picture. which will be below 100Kb in size minimum (100X100)px</span>
                            </div>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </section>
             <!-- Modal -->
              <div class="modal fade" id="routeModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog card" role="document">
                  <div class="modal-content">
                    <div class="modal-header pull-right">
                    <h4 class="modal-title" id="myModalLabel"><i class="fa fa-plus"></i> Add new designation</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                    </div>
                    <div class="card-body">
                      <form id="routeForm" method="POST" action="{{ route('dashboard.designation.store')}}">
                        @csrf
                          <!-- text input -->
                          <div class="form-group">
                            <label>Designation name</label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="Designation name" class="form-control" required>
                              @error('name')
                            <span class="invalid-feedback" role="alert">
                              <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                          </div>
                          <div class="form-group">
                            <button type="submit" class="btn btn-lg btn-primary">Create designation</button>
                          </div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Modal End -->
@endsection

@section('header')

@endsection

@section('footer')
<script type="text/javascript">
  jQuery(function($) {
    $('#selectRole').change(function(e) {
        e.defaultPrevented;
        let selected = $(this).val();
        if(parseInt(selected) === 8) {
            $('#counterDIV').removeClass('d-none');
        } else {
            $('#counterDIV').addClass('d-none');
        }
    });
    $('#routeForm').submit( function(event){
         event.defaultPrevented;
          var data = $(this).serialize();
          $.ajaxSetup({
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
          });

          $.ajax({
            url: "{{ route('dashboard.designation.store') }}",
            type:"POST",
            data: data,
            success:function(response){
              if( response.status == true ) {
                var $option = $("<option/>", {
                  value: response.item.id,
                  text: response.item.name,
                  selected: true
                });
                $('#designationIds').append($option);
                $("#designationIds").val(response.item.id);
                $('#routeModal').modal('hide');
              }
              Toast.fire({
                icon: response.label,
                title: response.content
              });
            },
           });
          return false;
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
