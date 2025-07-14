@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content content-overlap">
        <form method="post" action="{{ route('partner.update', $partner->id)}}" role="form" enctype="multipart/form-data">
            <div class="row">
                <!-- /.card-header -->
                <div class="col-md-7">
                    <div class="card-body">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="type" value="{{ \Jolzatra\Constants\AppConst::PARTNER_TYPE }}">
                        <input type="hidden" name="meta_id" value="{{ $partner->meta->id }}">
                        <input type="hidden" name="incentive_id" value="{{ $partner->incentive->id }}">
                        <div class="form-group">
                            <label for="name">Partner name</label>
                            <input type="text" name="name" id="name"
                                   class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $partner->name) }}"
                                   placeholder="Name" required="">
                            @error('name')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="text" name="email" id="email"
                                   class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $partner->email) }}"
                                   placeholder="Email" required="">
                            @error('email')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Mobile</label>
                            <input type="text" name="mobile" id="mobile"
                                   class="form-control @error('mobile') is-invalid @enderror"
                                   value="{{ old('mobile', $partner->mobile) }}" placeholder="Mobile" required="">
                            @error('mobile')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="address" id="address"
                                   class="form-control @error('address') is-invalid @enderror"
                                   value="{{ old('address', $partner->meta->address) }}" placeholder="Address" required="">
                            @error('address')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" id="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   value="{{ old('password') }}" placeholder="Password">
                            @error('password')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Password Confirm</label>
                            <input type="password_confirm" name="password_confirm" id="password_confirm"
                                   class="form-control @error('password_confirm') is-invalid @enderror"
                                   value="{{ old('password_confirm') }}" placeholder="Password confirm">
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
                        <label>Incentive amount</label>
                        <div class="input-group">
                            <input type="number" name="incentive" id="incentive" value="{{ old('incentive', $partner->incentive->incentive) }}"
                                   class="form-control @error('incentive') is-invalid @enderror">
                            <div class="input-group-append">
                                <select class="form-control" name="incentive_type">
                                    <option value="percent" @if($partner->incentive && $partner->incentive->incentive_type == 'percent') selected @endif>Percent</option>
                                    <option value="fixed" @if($partner->incentive && $partner->incentive->incentive_type == 'percent') selected @endif>Fixed</option>
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
