@extends('default::layouts.master')

@section('header')
@endsection

@section('content')
    <x-default::toolbar title="{{ $title ?? 'User:' . $user->name }}"></x-default::toolbar>

    <div>
        <!-- Nav tabs -->
        <ul class="nav nav-tabs menuTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link @if(!request()->has('tab') || request()->input('tab') == 'info') active @endif"
                   id="home-tab" data-bs-toggle="tab" href="#home" role="tab"
                   aria-controls="home"
                   aria-selected="{{ !request()->has('tab') || request()->input('tab') == 'info' ? 'true' : 'false' }}">
                    Info
                </a>
            </li>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content">
            <div role="tabpanel"
                 class="tab-pane fade @if(!request()->has('tab') || request()->input('tab') == 'info') active show @endif"
                 id="home">
                <table class="table table-striped table-bordered mt-3">
                    <tbody>
                    <tr>
                        <th style="width: 30%">ID</th>
                        <td>{{ $user->id }}</td>
                    </tr>
                    <tr>
                        <th>Name</th>
                        <td>{{ $user->name }}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>{{ \App\Helpers\CommonHelper::maskEmail($user->email) }}</td>
                    </tr>
                    <tr>
                        <th>Role</th>
                        <td>{{ $user->roles->first()->name ?? '---' }}</td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td>{{ $user->created_at}}</td>
{{--                        <td>{{ \Carbon\Carbon::parse($user->created_at)->format('Y-m-d H:i:s') }}</td>--}}
                    </tr>
                    <tr>
                        <th>Last Updated At</th>
                        <td>{{ $user->updated_at }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection



