@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content">
                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                <li class="nav-item">
                                  <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Basic information</a>
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

@endsection

@section('footer')

@endsection
