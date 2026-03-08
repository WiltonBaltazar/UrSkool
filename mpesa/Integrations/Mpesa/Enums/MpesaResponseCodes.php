<?php

namespace App\Integrations\Mpesa\Enums;

enum MpesaResponseCodes: string
{
    case PROCESSED_SUCESSFULLY = 'INS-0';
    case INVALID_API_KEY = 'INS-2';
    case DUPLICATED_TRANSACTION = 'INS-10';
    case INVALID_CONTACT = 'INS-2051';
    case INVALID_AMOUNT = 'INS-15';
    case REQUEST_TIMEOUT = 'INS-9';
    case NOT_ENOUGH_BALANCE = 'INS-2006';
}