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
            <form name="item" id="customerForm" action="{{ route('dashboard.user.update', $user->id) }}" method="POST" role="form" enctype="multipart/form-data">
              @csrf
              @method('PUT')
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
                                      <input type="text" id="name" name="name" class="form-control @if($errors->has('name')) is-invalid @endif" value="{{ old('name', $user->name) }}" placeholder="First name">
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
                                      <input type="text" name="username" value="{{ old('username', $user->username) }}" class="form-control" placeholder="Username">
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
                                      <input type="email" name="email" class="form-control @if($errors->has('email')) is-invalid @endif" value="{{ old('email', $user->email) }}" placeholder="Email address">
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
                                      <input type="text" name="mobile" class="form-control @if($errors->has('mobile')) is-invalid @endif" value="{{ old('mobile', $user->mobile) }}" placeholder="Mobile">
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
                                        <option value="{{ $k }}" {{ ( $user->hasrole( $k ) ) ? 'selected' : '' }}>{{ $v }}</option>
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
                                      <label>Designation</label>
                                      <select class="form-control" name="designation_id" @if($errors->has('designation_id')) is-invalid @endif" >
                                        <option value="">Select</option>
                                        @foreach( $designations as $k => $v )
                                        <option value="{{ $k }}" {{ ($user->designation_id == $k) ? 'selected' : ''}}>{{ $v }}</option>
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
                                    Update user
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
                              <img src="{{ asset($user->profile_pic) }}" id='img-upload'/>
                              <span class="help-text">Please upload a standard picture. which will be below 100Kb in size minimum (100X100)px</span>
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
