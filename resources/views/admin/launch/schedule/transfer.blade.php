@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="card" style="background-color: none;">
                    <div class="card-header">
                        <h3 class="card-title">Quota transfer</h3>
                        <div class="card-tools">
                            <div class="btn-group" role="group" aria-label="Basic example">
                                <a href="{{ route('dashboard.schedule.show', $schedule->id) }}"
                                   class="btn btn-default"><i
                                        class="fa fa-arrow-alt-circle-left"></i> Back</a>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <form action="{{ route('dashboard.schedule.quotatransfer', $schedule->id) }}" method="POST">
                            @method('PUT')
                            @csrf
                            <div class="row">
                                <div class="col-8">
                                    <div class="card">
                                        <div class="card-header border-transparent">
                                            <h3 class="card-title">Available quota</h3>

                                            <div class="card-tools">
                                            </div>
                                        </div>
                                        <!-- /.card-header -->
                                        <div class="card-body">
                                            <table class="table" id="quotaTransferTable">
                                                <tr>
                                                    <th><input type="checkbox" id="checkedAll" value=""></th>
                                                    <th>Type</th>
                                                    <th>Cabin / Seat No.</th>
                                                    <th>Fare</th>
                                                </tr>
                                                @foreach($schedule->mappings as $mapping)
                                                    <tr>
                                                        <td><input type="checkbox" class="selectedItem" name="ids[]" value="{{$mapping->id}}"></td>
                                                        <td>
                                                            {{ ucfirst($mapping->type) }} - {{$mapping->id}}
                                                        </td>
                                                        <td>{{ ($mapping->cabin['cabinType']) ? $mapping->cabin['cabinType']['letter'] . '-' : '' }}{{ $mapping->cabin['cabin_no'] }}</td>
                                                        <td>{{ $mapping->cabin['fare'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-4">
                                    <!-- TABLE: LATEST ORDERS -->
                                    <div class="card">
                                        <div class="card-header border-transparent">
                                            <h3 class="card-title">Transfer to</h3>
                                            <div class="card-tools">
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            @if($schedule->mappings->count())
                                                <input type="hidden" name="tab" value="info">
                                                <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                                            <div class="form-group">
                                                <label for="operation_hour">Owner</label>
                                                <select name="quota_owner" class="form-control" id="ownershipChanged" required>
                                                    <option value="">Select owner</option>
                                                    @foreach($party_dropdowns as $key => $value)
                                                    <option value="{{ $key }}">{{ $value }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group d-none" id="counterGhat">
                                                <label for="operation_hour">Counter</label>
                                                <select name="quota_counter" class="form-control">
                                                    <option value="">Select counter</option>
                                                    @foreach($schedule->route['boardingPoints'] as $item)
                                                        @if($item['ghat_id'] != auth()->user()->counter_id)
                                                        <option
                                                            value="{{ $item['ghat_id'] }}">{{ $item['ghat']['name'] }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <button type="submit" class="btn btn-primary">Transfer now</button>
                                            </div>
                                            @else
                                                <p>No quota found</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
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
        $('#checkedAll').click(function (e) {
            e.defaultPrevented;
            var parent = $(this).parents('table');
            if ($(this).is(":checked")) {
                $(parent).find(".selectedItem").each(function () {
                    $(this).prop('checked', true);
                });
            } else {
                $(parent).find(".selectedItem").each(function () {
                    $(this).prop('checked', false);
                });
                ;
            }
        });
        $('#ownershipChanged').change(function(e) {
            e.defaultPrevented;
            if($(this).val() == 'merchant') {
                $('#counterGhat').removeClass('d-none');
            } else {
                $('#counterGhat').addClass('d-none');
            }
        })
    });
</script>
@endsection
