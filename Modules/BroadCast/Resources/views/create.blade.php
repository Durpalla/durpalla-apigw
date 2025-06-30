@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content content-overlap">
        <form method="post" action="{{ route('broadcast.store')}}" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <!-- /.card-header -->
                <div class="col-md-7">
                    <div class="card-body">
                        <div class="form-group">
                            <label for="name">Title / Subject</label>
                            <input type="text" name="title" id="name" value="{{old('title')}}"
                                   class="form-control @error('title') is-invalid @enderror" placeholder="Title"
                                   required="">
                            @error('title')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="broadcastType">Type</label>
                            <select class="form-control" name="type" id="broadcastType" required>
                                <option value="all" @if(old('type') === 'all') selected @endif>All Type</option>
                                @foreach(config('constants.broadcast_types') as $key => $type)
                                    <option value="{{ $key }}" @if(old('type') === $key) selected @endif>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('type')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group topicElem" style="@if(old('topic') != null) display:block; @else display:none; @endif">
                            <label for="topic">Select topic</label>
                            <select class="form-control" name="topic" id="topic">
                                <option value="">Select</option>
                                @foreach(config('constants.topics') as $key => $type)
                                    <option value="{{ $key }}" @if(old('topic') === $key) selected @endif>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('topic')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="discount_amount">Message</label>
                            <p><small>Dynamic variable {name}</small></p>
                            <textarea name="message" class="form-control @error('message') is-invalid @enderror" cols="10"
                                      style="height: 220px" placeholder="Dear {name}, Welcome to {{ getOption('company_website') }}"
                                      required>{{ old('message') }}</textarea>
                            @error('message')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">{{ __('Create broadcast') }}</button>
                        </div>
                    </div>
                </div>
                <div class="col-5 mt-4">
                    <div class="form-group">
                        <label for="group">Customers</label>
                        <select class="form-control" name="group" id="type" required>
                            <option value="all" @if(old('group') === 'all') selected @endif>All</option>
                            <option value="individual" @if(old('group') === 'individual') selected @endif>Individual
                            </option>
                        </select>
                        @error('group')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="customers">Select customers</label>
                        <select class="form-control select2-blue" id="customers"
                                placeholder="Select customers"></select>
                    </div>
                    <div class="form-group">
                        <label>Attachment</label>
                        <div class="input-group">
                            <div class="input-group-btn">
                                <div class="btn btn-default btn-file">
                                    Browse… <input type="file" name="attachment" id="imgInp">
                                </div>
                            </div>
                            <input type="text" class="form-control" readonly><br>
                        </div>
                        <img id='img-upload'/>
                        <span class="help-text">Please upload a standard image. which will be below 100Kb and size (460X340)px</span>
                    </div>
                </div>
            </div>
        </form>
    </section>

@endsection

@section('header')
    <style>
        #img-upload {
            width: 100%;
            height: auto;
            padding: 15px;
            background: #fff;
        }
    </style>
@endsection

@section('footer')
    <script>
        $(function () {
            $('#broadcastType').change(function(e) {
                let type = $(this).val();
                if(type == 'topic') {
                    $('.topicElem').show();
                }
            });
        });
    </script>
@endsection
