@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content content-overlap">
        <form method="post" action="{{ route('agent.store')}}" role="form" enctype="multipart/form-data">
            <div class="row">
                <!-- /.card-header -->
                <div class="col-md-7">
                    <div class="card-body">
                        @csrf
                        <input type="hidden" name="type" value="{{ \Jolzatra\Constants\AppConst::AGENT_TYPE }}">
                        <div class="form-group">
                            <label for="name">Agent name</label>
                            <input type="text" name="name" id="name"
                                   class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}"
                                   placeholder="Name" required="">
                            @error('name')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="text" name="email" id="email"
                                   class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}"
                                   placeholder="Email" required="">
                            @error('email')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Mobile</label>
                            <input type="text" name="mobile" id="mobile"
                                   class="form-control @error('mobile') is-invalid @enderror"
                                   value="{{ old('mobile') }}" placeholder="Mobile" required="">
                            @error('mobile')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="address" id="address"
                                   class="form-control @error('address') is-invalid @enderror"
                                   value="{{ old('address') }}" placeholder="Address" required="">
                            @error('address')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" id="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   value="{{ old('password') }}" placeholder="Password" required>
                            @error('password')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Password Confirm</label>
                            <input type="password" name="password_confirm" id="password_confirm"
                                   class="form-control @error('password_confirm') is-invalid @enderror"
                                   value="{{ old('password_confirm') }}" placeholder="Password confirm" required>
                            @error('password_confirm')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <input type="submit" value="Submit" class="btn btn-primary form-control"/>
                        </div>
                    </div>
                </div>
                <div class="col-5 mt-4">
                    <div class="form-group">
                        <label>NID No.</label>
                        <input type="text" name="nid_no" id="nid_no"
                               class="form-control @error('nid_no') is-invalid @enderror"
                               value="{{ old('nid_no') }}" placeholder="NID" required>
                        @error('nid_no')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label>NID Photo</label>
                        <input type="file" name="nid_attachment" id="nid_attachment"
                               class="form-control @error('nid_attachment') is-invalid @enderror"
                               required>
                        @error('nid_attachment')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label>Trade License No.</label>
                        <input type="text" name="trade_license" id="trade_license"
                               class="form-control @error('trade_license') is-invalid @enderror"
                               value="{{ old('trade_license') }}" placeholder="Trade license">
                        @error('trade_license')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label>Trade license Photo</label>
                        <input type="file" name="trade_attachment" id="trade_attachment"
                               class="form-control @error('trade_attachment') is-invalid @enderror">
                        @error('trade_attachment')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label>Incentive amount</label>
                        <div class="input-group">
                            <input type="number" name="incentive" id="incentive" value="{{ old('incentive', 0) }}"
                               class="form-control @error('incentive') is-invalid @enderror">
                            <div class="input-group-append">
                                <select class="form-control" name="incentive_type">
                                    <option value="percent">Percent</option>
                                    <option value="fixed">Fixed</option>
                                </select>
                            </div>
                        </div>
                        @error('incentive')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                </div>
            </div>
        </form>
    </section>

@endsection

@section('header')

@endsection

@section('footer')

@endsection
