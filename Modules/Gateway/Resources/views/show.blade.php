@extends('metis::layouts.master')

@section('header')
@endsection

@section('content')
    <div id="content">
        <div class="outer">
            <div class="inner bg-light no-padding">
                <h3>Gateway: {{ $gateway->name }}
                    @if(\App\Helpers\CommonHelper::hasPermission(['gateway-update']) && auth()->id() == 1)
                        <a href="{{ route('gateway.edit', $gateway->id) }}" class="btn btn-default pull-right">
                            <i class="fa fa-edit"></i> Edit
                        </a>
                    @endif
                </h3>
                <div>
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs menuTab" role="tablist">
                        <li role="presentation"
                            class="@if(!request()->has('tab') || request()->input('tab') == 'info') active @endif">
                            <a href="#home" aria-controls="home" role="tab" data-toggle="tab"
                               aria-expanded="true">Info</a></li>
                        <li role="presentation"
                            class="@if(request()->has('tab') && request()->input('tab') == 'credential') active @endif">
                            <a href="#profile" aria-controls="profile" role="tab" data-toggle="tab">Credentials</a>
                        </li>
                        <li role="presentation"
                            class="@if(request()->has('tab') && request()->input('tab') == 'endpoint') active @endif">
                            <a href="#endpoints" aria-controls="endpoints" role="tab"
                               data-toggle="tab">Endpoints</a>
                        </li>
                    </ul>

                    <!-- Tab panes -->
                    <div class="tab-content">
                        <div role="tabpanel"
                             class="tab-pane fade @if(!request()->has('tab') || request()->input('tab') == 'info') active in @endif"
                             id="home">
                            <table class="table table-striped">
                                <tbody>
                                <tr>
                                    <th style="width: 30%">ID</th>
                                    <td>{{ $gateway->id }}</td>
                                </tr>
                                <tr>
                                    <th>Name</th>
                                    <td>{{ $gateway->name }}</td>
                                </tr>
                                <tr>
                                    <th>Namespace</th>
                                    <td>{{ $gateway->class_name }}</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>{{ $gateway->nice_status }}</td>
                                </tr>
                                <tr>
                                    <th>Created at</th>
                                    <td>{{ $gateway->created_at }}</td>
                                </tr>
                                <tr>
                                    <th>Last updated at</th>
                                    <td>{{ $gateway->updated_at }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <div role="tabpanel"
                             class="tab-pane fade @if(request()->has('tab') && request()->input('tab') == 'credential') active in @endif"
                             id="profile">
                            <div class="row">
                                <div class="col-md-8">
                                    <table class="table table-striped">
                                        <thead>
                                        <tr>
                                            <th style="width: 30%">Key</th>
                                            <th>Value</th>
                                            <th><i class="fa fa-cogs"></i></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($gateway->credentials as $credential)
                                            <tr>
                                                <td>{{ $credential->key }}</td>
                                                <td><span class="wrapText"
                                                          style="width: 320px; word-wrap: break-word;">{{ $credential->value }}</span>
                                                </td>
                                                <td>
                                                    <form method="POSt"
                                                          action="{{ route('gateway.credential.destroy', $credential->id) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-danger"><i class="fa fa-times"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-4">
                                    <h4><i class="fa fa-plus"></i> Add new credential</h4>
                                    <hr/>
                                    <form method="POST" action="{{ route('gateway.credential.store') }}">
                                        @csrf
                                        <input type="hidden" name="gateway_id" value="{{ $gateway->id }}">
                                        <div class="form-group">
                                            <label>Key (*)</label>
                                            <input type="text" name="key" class="form-control" placeholder="Key name">
                                        </div>
                                        <div class="form-group">
                                            <label>Value (*)</label>
                                            <textarea name="value" class="form-control" placeholder="Value"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary">Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div role="tabpanel"
                             class="tab-pane fade @if(request()->has('tab') && request()->input('tab') == 'endpoint') active in @endif"
                             id="endpoints">
                            <div class="row">
                                <div class="col-md-4">
                                    <h4><i class="fa fa-plus"></i> Add endpoint</h4>
                                    <hr/>
                                    <form method="POST" action="{{ route('gateway.endpoint.store') }}">
                                        @csrf
                                        <input type="hidden" name="gateway_id" value="{{ $gateway->id }}">
                                        <div class="form-group">
                                            <label>Key (*)</label>
                                            <select name="key" class="form-control" required>
                                                <option value="">Select key</option>
                                                @foreach($endpointTypes as $key => $value)
                                                    <option value="{{ $key }}"
                                                            @if(old('key') == $key) selected @endif>{{ $value }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Value (*)</label>
                                            <textarea name="value" class="form-control" placeholder="Value"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary">Save</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="col-md-8">
                                    <table class="table table-striped" id="endpoints">
                                        <thead>
                                        <tr>
                                            <th>Key</th>
                                            <th>Value</th>
                                            <th><i class="fa fa-cogs"></i></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($gateway->endpoints as $endpoint)
                                            <tr>
                                                <td>{{ $endpoint->key }}</td>
                                                <td>{{ $endpoint->value }}</td>
                                                <td>
                                                    <form method="POSt"
                                                          action="{{ route('gateway.endpoint.destroy', $endpoint->id) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-danger"><i class="fa fa-times"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <!-- /.inner -->
        </div>
        <!-- /.outer -->
    </div>
    <!-- /#content -->
@endsection

@section('footer')

@endsection
