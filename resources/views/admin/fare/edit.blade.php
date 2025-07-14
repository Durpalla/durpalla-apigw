@extends('layouts.master')

@section('content')
<!-- Main content -->
<section class="content content-overlap">
  <div class="row">
    <div class="col-12">
      <!-- /.card-header -->
      <div class="col-md-7">
        <div class="card-body">
          <form action="{{ route('dashboard.deckfare.update', $fare->id) }}" method="POST">
              @csrf
              @method('PUT')
              <input type="hidden" name="route_id" value="{{ $fare->route_id }}">
              <input type="hidden" name="vehicle_id" value="{{ $fare->vehicle_id }}">
              <input type="hidden" name="merchant_id" value="{{ $fare->merchant_id }}">
              <input type="hidden" name="tab" value="deck">
              <div class="form-group">
                <label>From</label>
                <select name="departure_from" class="form-control @error('departure_from') is-invalid @enderror" value="{{ old('departure_from') }}" required>
                  <option value="">Start from</option>
                  <option value="{{ $fare->route['startingPoint']['id'] }}" @if( old('departure_from', $fare->departure_from == $fare->route['startingPoint']['id'] )) ? selected @endif>{{ $fare->route['startingPoint']['ghat']['name'] }}</option>
                  @if( $fare->route['boardingVias'] )
                    @foreach( $fare->route['boardingVias'] as $via )
                      <option value="{{ $via['id'] }}" @if(old('departure_from', $fare->departure_from) == $via['id']) ? selected @endif>{{ $via['ghat']['name'] }}</option>
                    @endforeach
                  @endif
                  <option value="{{ $fare->route['endingPoint']['id'] }}" @if( old('departure_from', $fare->departure_from == $fare->route['endingPoint']['id'] )) ? selected @endif>{{ $fare->route['endingPoint']['ghat']['name'] }}</option>
                </select>
                @error('departure_from')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <label>To</label>
                <select name="departure_to" class="form-control @error('departure_to') is-invalid @enderror" value="{{ old('departure_to') }}" required>
                  <option value="">Select to</option>
                  <option value="{{ $fare->route['startingPoint']['id'] }}" @if( old('departure_to', $fare->departure_to == $fare->route['startingPoint']['id'] )) ? selected @endif>{{ $fare->route['startingPoint']['ghat']['name'] }}</option>
                  @if( $fare->route['boardingVias'] )
                    @foreach( $fare->route['boardingVias'] as $via )
                      <option value="{{ $via['id'] }}" @if(old('departure_to', $fare->departure_to) == $via['id']) ? selected @endif>{{ $via['ghat']['name'] }}</option>
                    @endforeach
                  @endif
                  <option value="{{ $fare->route['endingPoint']['id'] }}" @if( old('departure_to', $fare->departure_to == $fare->route['endingPoint']['id'] )) ? selected @endif>{{ $fare->route['endingPoint']['ghat']['name'] }}</option>
                </select>
                @error('departure_to')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <label>Deck Fare</label>
                <input type="number" name="deck_fare" min="10" max="5000" step="10" class="form-control @error('deck_fare') is-invalid @enderror" value="{{ old('deck_fare', $fare->fare) }}" required>
                @error('deck_fare')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <label>Reverse Fare</label>
                <input type="number" name="reverse_fare" min="10" max="5000" step="10" class="form-control @error('reverse_fare') is-invalid @enderror" value="{{ old('reverse_fare', $fare->reverse_fare) }}" required>
                @error('reverse_fare')
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
                </span>
                @enderror
              </div>
              <div class="form-group">
                <button class="btn btn-block btn-primary" type="submit">Save</button>
              </div>
            </form>
        </div>
      </div>
      <div class="col-sm-5">
      </div>
    </div>
  </div>
</section>
@endsection

@section('footer')
@endsection
