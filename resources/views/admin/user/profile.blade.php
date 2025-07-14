@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content">
                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                <li class="nav-item">
                                  <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Basic information</a>
                                </li>
                                <li class="nav-item">
                                  <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Profile photo</a>
                                </li>
                                <li class="nav-item">
                                  <a class="nav-link" id="password-tab" data-toggle="tab" href="#password" role="tab" aria-controls="password" aria-selected="false">Password change</a>
                                </li>
                                <li class="nav-item">
                                  <a class="nav-link" id="contact-tab" data-toggle="tab" href="#contact" role="tab" aria-controls="contact" aria-selected="false">Activities</a>
                                </li>
                            </ul>
                             <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
                                <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                                  <table class="table table-striped">
                                    <tr>
                                      <th style="width:25%;">Name</th>
                                      <td>{{ $user->name }}</td>
                                    </tr>
                                    <tr>
                                      <th>Username</th>
                                      <td>{{ $user->username }}</td>
                                    </tr>
                                    <tr>
                                      <th>Email</th>
                                      <td>{{ $user->email }}</td>
                                    </tr>
                                    <tr>
                                      <th>Mobile</th>
                                      <td>{{ $user->mobile }}</td>
                                    </tr>
                                    <tr>
                                      <th>Role</th>
                                      <td>{{ $user->roles[0]->role_name }}</td>
                                    </tr>
                                    <tr>
                                      <th>Designation</th>
                                      <td>{{ $user->name }}</td>
                                    </tr>
                                    <tr>
                                      <th>Address</th>
                                      <td>{{ $user->name }}</td>
                                    </tr>
                                    <tr>
                                      <th>Status</th>
                                      <td>{{ ( $user->status == 1 ) ? 'Active' : (($user->status == 0) ? 'Pending' : 'Inactive') }}</td>
                                    </tr>
                                  </table>
                                </div>
                                <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                                  <div class="row">
                                      <div class="col-sm-8">
                                          <form action="{{ route('dashboard.user.upload') }}" method="POST" enctype="multipart/form-data" accept-charset="utf-8">
                                              @csrf
                                              <fieldset>
                                                  <legend>Upload profile:</legend>
                                                  <div class="form-group">
                                                      <input type="file" name="avatar" class="form-control-file" placeholder="Choose profile picture">
                                                  </div>
                                                  <div class="form-group">
                                                      <button type="submit" class="btn btn-success">Upload</button>
                                                  </div>
                                              </fieldset>
                                          </form>
                                      </div>
                                      <div class="col-sm-4">
                                          @php
                                            $profile_pic = ($user->profile_pic) ? asset($user->profile_pic) : asset('default/avatar.png');
                                          @endphp
                                          <img src="{{$profile_pic}}" style="width: 100%;height: auto;">
                                      </div>
                                  </div>
                                </div>
                                <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                                  <div class="col-md-7">
                                    <form method="post" action="{{ route('dashboard.user.password')}}">
                                      @csrf
                                        <input type="hidden" name="tab" value="upload">
                                        <div class="form-group">
                                            <input type="password" name="password" class="form-control" placeholder="Password">
                                        </div>
                                        <div class="form-group">
                                            <input type="password" name="old_password" class="form-control" placeholder="Old password">
                                        </div>
                                      <div class="form-group">
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Password confirm">
                                      </div>
                                      <div class="form-group">
                                        <button class="btn btn-primary btn-lg" type="submit">Change password</button>
                                      </div>
                                    </form>
                                  </div>
                                </div>
                                <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                                    <table class="table table-bordered table-striped">
                                        <tr>
                                            <th>Time</th>
                                            <th>Subject</th>
                                            <th>URL</th>
                                            <th>Method</th>
                                            <th>Ip</th>
                                            <th width="300px">User Agent</th>
                                            <th>Action</th>
                                        </tr>
                                        @if($user->logs->count())
                                            @foreach($user->logs as $key => $log)
                                                <tr>
                                                    <td>{{ date('d/m/Y h:i a', strtotime($log->created_at)) }}</td>
                                                    <td>{{ $log->subject }}</td>
                                                    <td class="text-success">{{ $log->url }}</td>
                                                    <td><label class="label label-info">{{ $log->method }}</label></td>
                                                    <td class="text-warning">{{ $log->ip }}</td>
                                                    <td class="text-danger">{{ $log->agent }}</td>
                                                    <td><button class="btn btn-danger btn-sm">Delete</button></td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="7">No activities found.</td>
                                            </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                          </section>
@endsection

@section('header')
<style>
    legend.scheduler-border {
        width:inherit; /* Or auto */
        padding:0 10px; /* To give a bit of padding on the left and right */
        border-bottom:none;
    }
</style>
@endsection

@section('footer')

@endsection
