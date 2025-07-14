@extends('layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'general') ? 'active': ''; @endphp"
                   id="general-tab" data-toggle="tab" href="#general" role="tab" aria-controls="general"
                   aria-selected="true">General</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'vatcharge') ? 'active': ''; @endphp"
                   id="customer-tab" data-toggle="tab" href="#vatcharge" role="tab" aria-controls="vatcharge"
                   aria-selected="false">VAT & Charge</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'booking') ? 'active': ''; @endphp"
                   id="booking-tab" data-toggle="tab" href="#booking" role="tab" aria-controls="booking"
                   aria-selected="false">Booking policy</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'cancellation') ? 'active': ''; @endphp"
                   id="booking-cancellations-tab" data-toggle="tab" href="#booking-cancellations" role="tab"
                   aria-controls="booking-cancellations" aria-selected="false">Cancellation policy</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'merchant') ? 'active': ''; @endphp"
                   id="merchant-tab" data-toggle="tab" href="#merchant" role="tab" aria-controls="merchant"
                   aria-selected="false">Merchant</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'customer') ? 'active': ''; @endphp"
                   id="customer-tab" data-toggle="tab" href="#customer" role="tab" aria-controls="customer"
                   aria-selected="false">Customer</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'payment') ? 'active': ''; @endphp"
                   id="payment-tab" data-toggle="tab" href="#payment" role="tab" aria-controls="payment"
                   aria-selected="false">Payment</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'withdrawal') ? 'active': ''; @endphp"
                   id="withdrawal-tab" data-toggle="tab" href="#withdrawal" role="tab" aria-controls="withdrawal"
                   aria-selected="false">Withdraw</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'sms') ? 'active': ''; @endphp"
                   id="sms-tab" data-toggle="tab" href="#sms" role="tab" aria-controls="sms"
                   aria-selected="false">SMS</a>
            </li>
            <li class="nav-item">
                <a class="nav-link @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'facts') ? 'active': ''; @endphp"
                   id="facts-tab" data-toggle="tab" href="#facts" role="tab" aria-controls="sms" aria-selected="false">Some
                    facts</a>
            </li>
        </ul>
        <div class="tab-content" id="myTabContent" style="padding: 15px; background: #fff;">
            <div
                class="tab-pane fade show @php echo ( !isset( $_GET['tab'] ) || $_GET['tab'] == 'general') ? 'active show': ''; @endphp"
                id="general" role="tabpanel" aria-labelledby="general-tab">
                <form action="{{ route('dashboard.option.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="tab" value="general">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="inputPassword5">Company name</label>
                                <input type="text" id="inputPassword5" name="company_name"
                                       value="{{ old('company_name', getOption('company_name'))}}" class="form-control"
                                       aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Your company name
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Company Email</label>
                                <input type="email" id="inputPassword5" name="company_email"
                                       value="{{ old('company_email', getOption('company_email'))}}"
                                       class="form-control" aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Your company email address
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Company Phone</label>
                                <input type="text" id="inputPassword5" name="company_phone"
                                       value="{{ old('company_phone', getOption('company_phone'))}}"
                                       class="form-control" aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Your company Phone
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Hotline number</label>
                                <input type="text" id="inputPassword5" name="company_hotline"
                                       value="{{ old('company_hotline', getOption('company_hotline'))}}"
                                       class="form-control" aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Your company Hotline number
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Hotline number (Short code)</label>
                                <input type="text" id="inputPassword5" name="company_hotline_code"
                                       value="{{ old('company_hotline_code', getOption('company_hotline_code'))}}"
                                       class="form-control" aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Your company Hotline number (Short code)
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Company Fax</label>
                                <input type="text" id="inputPassword5" name="company_fax"
                                       value="{{ old('company_fax', getOption('company_fax'))}}" class="form-control"
                                       aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Your company Fax number
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Company Website</label>
                                <input type="text" id="inputPassword5" name="company_website"
                                       value="{{ old('company_website', getOption('company_website'))}}"
                                       class="form-control" aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Write down your company Website
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Company address</label>
                                <input type="text" id="inputPassword5" name="company_address"
                                       value="{{ old('company_address', getOption('company_address'))}}"
                                       class="form-control" aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Write down your company address
                                </small>
                            </div>
                            <h3>Social settings</h3>

                            <div class="form-group">
                                <label for="inputPassword5">Facebook</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text">https://fb.com/</div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="social_facebook"
                                           value="{{ old('social_facebook', getOption('social_facebook'))}}"
                                           class="form-control" placeholder="exp. jolzan"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Write down your company facebook ID
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Twitter</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text">https://twitter.com/</div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="social_twitter"
                                           value="{{ old('social_twitter', getOption('social_twitter'))}}"
                                           class="form-control" placeholder="exp. jolzan"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Write down your company Twitter ID
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Linkedin</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text">https://linkedin.com/in/</div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="social_linkedin"
                                           value="{{ old('social_linkedin', getOption('social_linkedin'))}}"
                                           class="form-control" placeholder="exp. jolzan"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Write down your company Linkedin ID
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Skype</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-skype"></i></div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="social_linkedin"
                                           value="{{ old('social_skype', getOption('social_skype'))}}"
                                           class="form-control" placeholder="exp. jolzan"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Write down your company Skype ID
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Google Play</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-google-play"></i></div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="google_play"
                                           value="{{ old('google_play', getOption('google_play'))}}"
                                           class="form-control" placeholder="Play download link"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Write down your google play download link
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Google Play (Short link)</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-google-play"></i></div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="google_play_short"
                                           value="{{ old('google_play_short', getOption('google_play_short'))}}"
                                           class="form-control" placeholder="Play download link"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Write down your google play download link (shortlink)
                                </small>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-lg">Save</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="inputPassword5">Slogan (English)</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-skype"></i></div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="site_slogan"
                                           value="{{ old('site_slogan', getOption('site_slogan', 'One stop booking solutions'))}}"
                                           class="form-control" placeholder="Slogan"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Slogan show upper serch form in frontend
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Slogan (Bangla)</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-skype"></i></div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="site_slogan_bn"
                                           value="{{ old('site_slogan_bn', getOption('site_slogan_bn', 'বুকিং এর সহজ সমাধান'))}}"
                                           class="form-control" placeholder="Slogan"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Slogan show upper serch form in frontend
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Enlisted company text (English)</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-skype"></i></div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="enlisted_slogan"
                                           value="{{ old('enlisted_slogan', getOption('enlisted_slogan', 'বুকিং এর সহজ সমাধান'))}}"
                                           class="form-control" placeholder="Slogan"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Showing below enlisted company section title in the frontend
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Enlisted Company text (Bangla)</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-skype"></i></div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="enlisted_slogan_bn"
                                           value="{{ old('enlisted_slogan_bn', getOption('enlisted_slogan_bn', 'বুকিং এর সহজ সমাধান'))}}"
                                           class="form-control" placeholder="Slogan"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Showing below enlisted company section title in the frontend
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Sponsorship title (English)</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-skype"></i></div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="sponsor_title"
                                           value="{{ old('sponsor_title', getOption('sponsor_title', 'Sponsor'))}}"
                                           class="form-control" placeholder="Slogan"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Sponsorship section title
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Sponsorship title (Bangla)</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-skype"></i></div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="sponsor_title_bn"
                                           value="{{ old('sponsor_title_bn', getOption('sponsor_title_bn', 'Sponsor'))}}"
                                           class="form-control" placeholder="Slogan"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Sponsorship section title
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Offers title (English)</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-bars"></i></div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="offers_title"
                                           value="{{ old('offers_title', getOption('offers_title', 'Latest offer'))}}"
                                           class="form-control" placeholder="Offer title"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Latest offer section title
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Offers title (Bangla)</label>
                                <div class="input-group">
                                    <div class="input-group-append">
                                        <div class="input-group-text"><i class="fa fa-bars"></i></div>
                                    </div>
                                    <input type="text" id="inputPassword5" name="offers_title_bn"
                                           value="{{ old('offers_title_bn', getOption('offers_title_bn', 'Offers'))}}"
                                           class="form-control" placeholder="Offers title"
                                           aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Offers section title
                                </small>
                            </div>

                        </div>
                    </div>
                </form>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'cancellation') ? 'active show': ''; @endphp"
                id="booking-cancellations" role="tabpanel" aria-labelledby="booking-tab">
                <form action="{{ route('dashboard.option.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="tab" value="cancellation">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="vatRefundable">Customer can cancel booking?</label>
                                <select name="is_cancellation_enabled" class="form-control">
                                    <option value="1" @if(getOption('is_cancellation_enabled') == '1') selected @endif>
                                        Yes
                                    </option>
                                    <option value="0" @if(getOption('is_cancellation_enabled') == '0') selected @endif>
                                        No
                                    </option>
                                </select>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Is cancellation enabled?
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="vatRefundable">Message for customer if cancellation disabled.</label>
                                <textarea name="cancellation_disable_note" class="form-control"
                                          placeholder="Write a not for customer...">{{ getOption('cancellation_disable_note')}}</textarea>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Booking cancellation disable note.
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="vatRefundable">Vat refundable?</label>
                                <select name="is_vat_refundable" class="form-control">
                                    <option value="1" @if(getOption('is_vat_refundable') == '1') selected @endif>Yes
                                    </option>
                                    <option value="0" @if(getOption('is_vat_refundable') == '0') selected @endif>No
                                    </option>
                                </select>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Is vat refundable?
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="serviceChargeRefundable">Service charge refundable?</label>
                                <select name="is_charge_refundable" class="form-control">
                                    <option value="1" @if(getOption('is_charge_refundable') == '1') selected @endif>
                                        Yes
                                    </option>
                                    <option value="0" @if(getOption('is_charge_refundable') == '0') selected @endif>No
                                    </option>
                                </select>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Is service charge refundable?
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="vatRefundable">Booking cancellation policy</label>
                                <textarea name="cancellation_policy" class="form-control" style="height:320px"
                                          placeholder="Write a not for customer...">{{ getOption('cancellation_policy')}}</textarea>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    List down booking cancellation policy for customer, like the example below

                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-lg">Save</button>
                    </div>
                </form>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'booking') ? 'active show': ''; @endphp"
                id="booking" role="tabpanel" aria-labelledby="booking-tab">
                <form action="{{ route('dashboard.option.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="tab" value="booking">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="inputPassword5">Cart lock (Minutes)</label>
                                <input type="number" id="inputPassword5" name="cart_lock_period"
                                       value="{{ old('cart_lock_period', getOption('cart_lock_period', 30))}}"
                                       class="form-control" aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Customer item will lock for this specefic period of minutes
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Payment pending period (Minutes)</label>
                                <input type="number" id="inputPassword5" name="payment_lock_period"
                                       value="{{ old('payment_lock_period', getOption('payment_lock_period', 60))}}"
                                       class="form-control" aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Payment will lock for payment process for period of minutes
                                </small>
                            </div>
                            <div class="form-group">
                                <label>Frontend Invoice Note (Emergency message)</label>
                                <textarea name="invoice_note" class="form-control"
                                          aria-describedby="passwordHelpBlock">{{ old('invoice_note', getOption('invoice_note'))}}</textarea>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    This message will show left side of invoice qr code empty space
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="vatRefundable">Booking policy (Terms & Conditions)</label>
                                <textarea name="booking_policy" class="form-control" style="height:320px"
                                          placeholder="Write a not for customer...">{{ getOption('booking_policy')}}</textarea>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    This agreement will show on booking modal of frontend
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-lg">Save</button>
                    </div>
                </form>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'merchant') ? 'active show': ''; @endphp"
                id="merchant" role="tabpanel" aria-labelledby="merchant-tab">

            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'customer') ? 'active show': ''; @endphp"
                id="customer" role="tabpanel" aria-labelledby="customer-tab">
                <form action="{{ route('dashboard.option.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="tab" value="customer">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="inputPassword5">Maximum cabin booking</label>
                                <div class="input-group">
                                    <input type="number" min="0" max="10" maxlength="1" id="inputPassword5"
                                           name="max_cabin_booking"
                                           value="{{ old('max_cabin_booking', getOption('max_cabin_booking', 2))}}"
                                           class="form-control" aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    How many cabin customer can book per day
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Maximum seat booking</label>
                                <div class="input-group">
                                    <input type="number" min="0" max="10" maxlength="1" id="inputPassword5"
                                           name="max_seat_booking"
                                           value="{{ old('max_seat_booking', getOption('max_seat_booking', 2))}}"
                                           class="form-control" aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    How many seat customer can book per day
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="inputPassword5">Maximum deck ticket booking</label>
                                <div class="input-group">
                                    <input type="number" min="0" max="10" maxlength="1" id="inputPassword5"
                                           name="max_deck_booking"
                                           value="{{ old('max_deck_booking', getOption('max_deck_booking', 2))}}"
                                           class="form-control" aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    How many deck ticket customer can book per day
                                </small>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-success">Save</button>
                            </div>
                        </div>
                        <div class="col-6"></div>
                    </div>
                </form>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'vatcharge') ? 'active show': ''; @endphp"
                id="vatcharge" role="tabpanel" aria-labelledby="vatcharge-tab">
                <form action="{{ route('dashboard.option.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="tab" value="vatcharge">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="inputPassword5">Vat Amount</label>
                                <div class="input-group">
                                    <input type="text" id="inputPassword5" name="vat_amount"
                                           value="{{ old('vat_amount', getOption('vat_amount', 0))}}"
                                           class="form-control" aria-describedby="passwordHelpBlock">
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            %
                                        </div>
                                    </div>
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Vat on every booking of your web / android / counter for both option
                                </small>
                            </div>

                            <h4>Service charges</h4>
                            <hr/>
                            <div class="form-group">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="customRadioInline1" name="service_charge_platform"
                                           value="global" class="custom-control-input" checked>
                                    <label class="custom-control-label" for="customRadioInline1">Global charge</label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="customRadioInline2" name="service_charge_platform" value="local"
                                           class="custom-control-input">
                                    <label class="custom-control-label" for="customRadioInline2">Item wize
                                        charge</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Service charge type</label>
                                <select name="service_charge_type" class="form-control">
                                    <option value="percent" @if(old(getOption('service_charge_type')) === 'percent') selected @endif>Percent</option>
                                    <option value="fixed" @if(old(getOption('service_charge_type')) === 'fixed') selected @endif>Fixed</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Service charge (Web)</label>
                                <div class="input-group">
                                    <input type="text" id="inputPassword5" name="service_charge_web"
                                           value="{{ old('service_charge_web', getOption('service_charge_web', 0))}}"
                                           class="form-control" aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Service charges will be calculate with this condtions (Percent / Fixed ) amount
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Service charge (Mobile app)</label>
                                <div class="input-group">
                                    <input type="text" id="inputPassword5" name="service_charge_mobile"
                                           value="{{ old('service_charge_mobile', getOption('service_charge_mobile', 0))}}"
                                           class="form-control" aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    This charges will be applicable when your customer order from mobile application
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Service charge (Counter)</label>
                                <div class="input-group">
                                    <!-- <div class="input-group-append">
                                      <div class="input-group-text">
                                        BDT
                                      </div>
                                    </div> -->
                                    <input type="text" id="inputPassword5" name="service_charge_counter"
                                           value="{{ old('service_charge_counter', getOption('service_charge_counter', 0))}}"
                                           class="form-control" aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    This charges will be applicable when your customer order from counter
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Bank Charge (SSLCommerz)</label>
                                <div class="input-group">
                                    <input type="text" id="inputPassword5" name="service_charge_bank"
                                           value="{{ old('service_charge_bank', getOption('service_charge_bank', '2.5'))}}"
                                           class="form-control" aria-describedby="passwordHelpBlock">
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    This charges will showing an calculation purpose only
                                </small>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-lg">Save</button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="inputPassword5">Calculation type</label>
                                <div class="input-group">
                                    <select class="form-control" name="number_format">
                                        <option value="actual"
                                                @if(getOption('number_format') == 'actual') selected @endif>Actual
                                        </option>
                                        <option value="ceil" @if(getOption('number_format') == 'ceil') selected @endif>
                                            Ceil
                                        </option>
                                        <option value="floor"
                                                @if(getOption('number_format') == 'floor') selected @endif>Floor
                                        </option>
                                    </select>
                                </div>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    This charges will showing an calculation purpose only
                                </small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'payment') ? 'active show': ''; @endphp"
                id="payment" role="tabpanel" aria-labelledby="payment-tab">
                <h4>Gateway Settings</h4>
                <hr>
                <form action="{{ route('dashboard.option.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="tab" value="payment">
                    <div class="row">
                        <div class="col-3">
                            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist"
                                 aria-orientation="vertical">
                                @foreach($gateway_list as $key => $gateway)
                                    <a class="nav-link @if($key === 0) active @endif"
                                       id="v-pills-{{$gateway->name}}-tab" data-toggle="pill"
                                       href="#v-pills-{{$gateway->name}}" role="tab"
                                       aria-controls="v-pills-{{$gateway->name}}"
                                       aria-selected="true">{{$gateway->name}}</a>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-9">
                            <div class="tab-content" id="v-pills-tabContent">
                                @foreach($gateway_list as $key => $gateway)
                                    <div class="tab-pane fade show @if($key === 0) active @endif"
                                         id="v-pills-{{$gateway->name}}" role="tabpanel"
                                         aria-labelledby="v-pills-{{$gateway->name}}-tab">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <label>Base URL</label>
                                                    <input type="text" name="{{$gateway->name}}_gateway_url"
                                                           class="form-control"
                                                           value="{{ old($gateway->name . '_gateway_url', getOption($gateway->name . '_gateway_url')) }}"
                                                           placeholder="Base url" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Sandbox?</label>
                                                    <select name="{{$gateway->name}}_gateway_sandbox"
                                                            class="form-control">
                                                        <option value="0"
                                                                @if(getOption($gateway->name . '_gateway_sandbox', 0) == 0) selected @endif>
                                                            No
                                                        </option>
                                                        <option value="1"
                                                                @if(getOption($gateway->name . '_gateway_sandbox', 0) == 1) selected @endif>
                                                            Yes
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Store username</label>
                                                    <input type="text" name="{{ $gateway->name }}_app_username"
                                                           class="form-control"
                                                           value="{{ old($gateway->name . '_app_username', getOption($gateway->name . '_app_username')) }}"
                                                           placeholder="Store username">
                                                </div>
                                                <div class="form-group">
                                                    <label>Store password</label>
                                                    <input type="text" name="{{$gateway->name}}_store_password"
                                                           class="form-control"
                                                           value="{{ old($gateway->name . '_store_password', getOption($gateway->name . '_store_password')) }}"
                                                           placeholder="Store password">
                                                </div>
                                                <div class="form-group">
                                                    <label>Store ID</label>
                                                    <input type="text" name="{{ $gateway->name }}_app_id"
                                                           class="form-control"
                                                           value="{{ old($gateway->name . '_app_id', getOption($gateway->name . '_app_id')) }}"
                                                           placeholder="Store ID">
                                                </div>
                                                <div class="form-group">
                                                    <label>Secret key</label>
                                                    <input type="text" name="{{$gateway->name}}_secret_key"
                                                           class="form-control"
                                                           value="{{ old($gateway->name . '_secret_key', getOption($gateway->name . '_secret_key')) }}"
                                                           placeholder="Secret key">
                                                </div>
                                                <div class="form-group">
                                                    <button type="submit" class="btn btn-lg btn-primary">Save</button>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-group">
                                                    <label>Public key</label>
                                                    <textarea type="text" name="{{$gateway->name}}_public_key"
                                                              class="form-control"
                                                              placeholder="Secret key">{{ old($gateway->name . '_public_key') }}</textarea>
                                                </div>
                                                <div class="form-group">
                                                    <label>Private key</label>
                                                    <textarea type="text" name="{{$gateway->name}}_private_key"
                                                              class="form-control"
                                                              placeholder="Secret key">{{ old($gateway->name . '_private_key') }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'withdrawal') ? 'active show': ''; @endphp"
                id="withdrawal" role="tabpanel" aria-labelledby="withdrawal-tab">
                <h4>Withdrawal Settings</h4>
                <hr>
                <form action="{{ route('dashboard.option.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="tab" value="withdrawal">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label>Withdrawal Limit (Agent)</label>
                                <input type="number" name="withdrawal_limit_agent" class="form-control" value="{{ getOption('withdrawal_limit_agent', 100) }}">
                            </div>
                            <div class="form-group">
                                <label>Withdrawal Limit (Supervisor)</label>
                                <input type="number" name="withdrawal_limit_partner" class="form-control" value="{{ getOption('withdrawal_limit_supervisor', 500) }}">
                            </div>
                            <div class="form-group">
                                <label>Withdrawal Limit (Partner)</label>
                                <input type="number" name="withdrawal_limit_partner" class="form-control" value="{{ getOption('withdrawal_limit_partner', 500) }}">
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'sms') ? 'active show': ''; @endphp"
                id="sms" role="tabpanel" aria-labelledby="sms-tab">
                <form action="{{ route('dashboard.option.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="tab" value="facts">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>SMS Enable?</label>
                                <select name="sms_enabled" class="form-control">
                                    <option value="0" @if(getOption('sms_enabled', 0) == 0) selected @endif>No</option>
                                    <option value="1" @if(getOption('sms_enabled', 0) == 1) selected @endif>Yes</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Api Url</label>
                                <input name="sms_api_url" class="form-control"
                                       value="{{ old('sms_api_url', getOption('sms_api_url')) }}" placeholder="Api Url">
                            </div>
                            <div class="form-group">
                                <label>Api Username</label>
                                <div class="input-group">
                                    <input name="sms_api_username" class="form-control"
                                           value="{{ old('sms_api_username', getOption('sms_api_username')) }}"
                                           placeholder="Api username">
                                    <div class="input-group-append">
                                        <input type="text" name="sms_api_username_key"
                                               value="{{ old('sms_api_username_key', getOption('sms_api_username_key')) }}"
                                               placeholder="Username param">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Api Password</label>
                                <div class="input-group">
                                    <input name="sms_api_password" class="form-control"
                                           value="{{ old('sms_api_password', getOption('sms_api_password')) }}"
                                           placeholder="Api password">
                                    <div class="input-group-append">
                                        <input type="text" name="sms_api_password_key"
                                               value="{{ old('sms_api_password_key', getOption('sms_api_password_key')) }}"
                                               placeholder="Password param">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Api Secret</label>
                                <div class="input-group">
                                    <input name="sms_api_secret" class="form-control"
                                           value="{{ old('sms_api_secret', getOption('sms_api_secret')) }}"
                                           placeholder="Api Secret">
                                    <div class="input-group-append">
                                        <input type="text" name="sms_api_secret_key"
                                               value="{{ old('sms_api_secret_key', getOption('sms_api_secret_key')) }}"
                                               placeholder="Secret param">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Api Sender (Masking / From)</label>
                                <div class="input-group">
                                    <input name="sms_api_sender" class="form-control"
                                           value="{{ old('sms_api_sender', getOption('sms_api_sender')) }}"
                                           placeholder="Api Sender">
                                    <div class="input-group-append">
                                        <input type="text" name="sms_api_sender_key"
                                               value="{{ old('sms_api_sender_key', getOption('sms_api_sender_key')) }}"
                                               placeholder="Sender param">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Number (To) Param</label>
                                <div class="input-group">
                                    <input name="sms_api_number_key" class="form-control"
                                           value="{{ old('sms_api_number_key', getOption('sms_api_number_key')) }}"
                                           placeholder="Number param">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Message Param</label>
                                <div class="input-group">
                                    <input name="sms_api_message_key" class="form-control"
                                           value="{{ old('sms_api_message_key', getOption('sms_api_message_key')) }}"
                                           placeholder="Message param">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Extra 1?</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="sms_api_extra_1"
                                           value="{{ old('sms_api_extra_1', getOption('sms_api_extra_1')) }}"
                                           placeholder="Extra 1 (value)">
                                    <div class="input-group-append">
                                        <input name="sms_api_extra_key_1" class="form-control"
                                               value="{{ old('sms_api_extra_key_1', getOption('sms_api_extra_key_1')) }}"
                                               placeholder="Extra 1 (Key)">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Extra 2?</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="sms_api_extra_2"
                                           value="{{ old('sms_api_extra_2', getOption('sms_api_extra_2')) }}"
                                           placeholder="Extra 2 (value)">
                                    <div class="input-group-append">
                                        <input name="sms_api_extra_key_2" class="form-control"
                                               value="{{ old('sms_api_extra_key_2', getOption('sms_api_extra_key_2')) }}"
                                               placeholder="Extra 2 (Key)">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Extra 3?</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="sms_api_extra_3"
                                           value="{{ old('sms_api_extra_3', getOption('sms_api_extra_3')) }}"
                                           placeholder="Extra 3 (value)">
                                    <div class="input-group-append">
                                        <input name="sms_api_extra_key_3" class="form-control"
                                               value="{{ old('sms_api_extra_key_3', getOption('sms_api_extra_key_3')) }}"
                                               placeholder="Extra 3 (Key)">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Extra 4?</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="sms_api_extra_4"
                                           value="{{ old('sms_api_extra_4', getOption('sms_api_extra_4')) }}"
                                           placeholder="Extra 4 (value)">
                                    <div class="input-group-append">
                                        <input name="sms_api_extra_key_4" class="form-control"
                                               value="{{ old('sms_api_extra_key_4', getOption('sms_api_extra_key_4')) }}"
                                               placeholder="Extra 4 (Key)">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>OTP message template</label>
                                <small>Variable: {code}</small>
                                <div class="input-group">
                                    <textarea name="sms_otp_template"
                                              class="form-control">{{ old('sms_otp_template', 'Dear user, {code} is your verification code') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-lg">Save</button>
                    </div>
                </form>
            </div>
            <div
                class="tab-pane fade @php echo ( isset( $_GET['tab'] ) && $_GET['tab'] == 'facts') ? 'active show': ''; @endphp"
                id="facts" role="tabpanel" aria-labelledby="facts-tab">
                <form action="{{ route('dashboard.option.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="tab" value="facts">
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="inputPassword5">Ticket available per day</label>
                                <input type="number" id="inputPassword5" name="ticket_available_perday"
                                       value="{{ old('ticket_available_perday', getOption('ticket_available_perday', 10000))}}"
                                       class="form-control" aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Ticket availble per day stat on frontend some fact section
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="inputPassword5">Happy customers</label>
                                <input type="number" id="inputPassword5" name="happy_customers"
                                       value="{{ old('happy_customers', getOption('happy_customers', 5000))}}"
                                       class="form-control" aria-describedby="passwordHelpBlock">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Happy customers stats on frontend
                                </small>
                            </div>
                            <div class="form-group">
                                <label>Available routes</label>
                                <input type="number" name="available_routes" class="form-control"
                                       aria-describedby="passwordHelpBlock"
                                       value="{{ old('available_routes', getOption('available_routes', 100))}}">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Available routes stat
                                </small>
                            </div>
                            <div class="form-group">
                                <label for="vatRefundable">Available vehicles</label>
                                <input type="number" name="available_vehicles" class="form-control"
                                       value="{{ getOption('available_vehicles', 25)}}">
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Available vehicles of frontend
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-lg">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
    <!-- /.content -->
@endsection

@section('header')
    <link rel="stylesheet"
          href="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
    <link href="{{ asset('assets/plugins/fullcalendar/assets/css/fullcalendar.css') }}" rel='stylesheet'/>
    <link href="{{ asset('assets/plugins/fullcalendar/assets/css/fullcalendar.print.css') }}" rel='stylesheet'
          media='print'/>
    <style type="text/css">
        /***
      User Profile Sidebar by @keenthemes
      A component of Metronic Theme - #1 Selling Bootstrap 3 Admin Theme in Themeforest: https://j.mp/metronictheme
      Licensed under MIT
      ***/

        body {
            background: #F1F3FA;
        }

        .nav-tabs .nav-item {
            margin-right: 8px;
        }

        .nav-tabs .nav-link {
            border-top-left-radius: .25rem;
            border-top-right-radius: .25rem;
            border: 1px solid #eee;
            background: #e4e2e2;
            color: #000;
        }


    </style>
@endsection

@section('footer')
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/datatables-bs4/js/dataTables.bootstrap4.js') }}"></script>
    <script
        src="{{ asset('assets/plugins/AdminLte/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/AdminLte/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/fullcalendar/assets/js/fullcalendar.js') }}" type="text/javascript"></script>
    <script>
        $(function () {


        });
    </script>
@endsection
