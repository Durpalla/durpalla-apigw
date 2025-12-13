<?php
namespace App\Constants;


class AppConst
{
    const BOOKING_COMPLETE = 'COMPLETE';
    const BOOKING_PENDING = 'PENDING';
    const BOOKING_CANCELLED = 'CANCELLED';
    const BOOKING_REJECTED = 'REJECTED';
    const BOOKING_FAILED = 'FAILED';
    const BOOKING_ADVANCE = 'ADVANCE';
    const BOOKING_ITEM_PENDING = 0;
    const BOOKING_ITEM_ACTIVE = 1;
    const BOOKING_ITEM_CANCELLED = 2;
    const BOOKING_ITEM_FAILED = 9;
    const CANCELLATION_PENDING = 0;
    const CANCELLATION_APPROVED = 1;
    const CANCELLATION_PROCESSING = 2;
    const CANCELLATION_REFUNDED = 3;
    const CANCELLATION_REJECTED = 9;
    const SCHEDULE_ACTIVE = 'ACTIVE';
    const SCHEDULE_PAUSED = 'PAUSE';
    const SCHEDULE_COMPLETE = 'COMPLETE';
    const SCHEDULE_RESCHEDULE = 'RESCHEDULE';
    const SCHEDULE_CANCEL = 'CANCEL';
    const LAUNCH_ACTIVE = 1;
    const LAUNCH_INACTIVE = 2;
    const PAYMENT_SUCCESS = 'success';
    const PAYMENT_CANCELLED = 'cancelled';
    const AGENT_TYPE = 'agent';
    const AGENT_ROLE = 'agent';
    const USER_ACTIVE = 1;
    const AGENT_ACTIVE = 1;
    const AGENT_INACTIVE = 2;
    const WITHDRAWAl_PENDING = 0;
    const WITHDRAWAl_COMPLETE = 1;
    const WITHDRAWAl_CANCELLED = 2;
    const GATEWAY_ACTIVE = 1;
    const GATEWAY_PENDING = 0;
    const SUPERVISOR_ROLE = 'supervisor';
    const TYPE_MERCHANT = 'merchant';
    const USER_TYPE_CUSTOMER = 'customer';
    const TYPE_JOLZAN = 'admin';
    const PARTNER_ROLE = 'partner';
    const PARTNER_TYPE = 'partner';
    const PARTY_JOLZAN = 'jolzan';
    const PARTY_MARCHANT = 'merchant';
    const CUSTOMER_ACTIVE = 1;
    const VEHICLE_INACTIVE = 2;
    const OWNER = 'jolzan';
    const BKASH_PAYMENT_COMPLETED = 'Completed';
    const GATEWAY_TYPE_REQUEST = 'request';
    const GATEWAY_TYPE_CALLBACK = 'callback';
    const GATEWAY_TYPE_RESPONSE = 'response';
    const GATEWAY_TYPE_VERIFY = 'verify';
    const PAYMENT_METHOD_NAGAD = 'Nagad';
    const PAYMENT_METHOD_BKASH = 'Bkash';
    const DEFAULT_SERVICE_CHARGE_TYPE = 'percent';
}
