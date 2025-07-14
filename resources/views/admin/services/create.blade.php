@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content content-overlap">
        <form method="post" action="{{ route('services.store')}}" role="form">
            @csrf
            <div class="row">
                <!-- /.card-header -->
                <div class="col-7">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Name of service</label>
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
                            <label>Status</label>
                            <select class="form-control" name="status">
                                <option value="1" @if(old('status') == '1') selected @endif>Enable</option>
                                <option value="0" @if(old('status') == '0') selected @endif>Disable</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="submit" name="Submit" value="Submit" class="btn btn-primary form-control"/>
                        </div>
                    </div>
                </div>
                <div class="col-5">
                </div>
            </div>
        </form>
    </section>

@endsection

@section('header')

@endsection

@section('footer')

@endsection
