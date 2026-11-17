<?php

namespace App\Modules\Communications\Enums;

enum SmsDeliveryRoute: string
{
    case Standard = 'standard';
    case Priority = 'priority';
    case Otp = 'otp';
}
