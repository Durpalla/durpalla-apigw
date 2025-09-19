@extends('metis::layouts.master')

@section('header')
@endsection

@section('content')
    <div id="content">
        <div class="outer">
            <div class="inner bg-light no-padding">

                <h3>{{ $title ?? "User: {$user->name}" }}</h3>
                <div>
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs menuTab" role="tablist">
                        <li role="presentation"
                            class="@if(!request()->has('tab') || request()->input('tab') == 'info') active @endif">
                            <a href="#home" aria-controls="home" role="tab" data-toggle="tab"
                               aria-expanded="true">Info</a>
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
                                    <td>{{ $user->id }}</td>
                                </tr>
                                <tr>
                                    <th>Name</th>
                                    <td>{{ $user->name }}</td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td>{{ $user->email }}</td>
                                </tr>
                                <tr>
                                    <th>Role</th>
                                    <td>{{ $user->roles->first()->name ?? '---' }}</td>
                                </tr>
                                <tr>
                                    <th>Created at</th>
                                    <td>{{ $user->created_at }}</td>
                                </tr>
                                <tr>
                                    <th>Last updated at</th>
                                    <td>{{ $user->updated_at }}</td>
                                </tr>
                                </tbody>
                            </table>
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



