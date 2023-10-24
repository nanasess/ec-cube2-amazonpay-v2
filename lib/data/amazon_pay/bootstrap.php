<?php

use Dotenv\Dotenv;

Dotenv::createUnsafeMutable(__DIR__, '.env')->safeLoad();

if (getenv('AMAZONPAY_SANDBOX') === '1') {

    Dotenv::createUnsafeMutable(__DIR__, '.env.sandbox')->safeLoad();
} else {

    Dotenv::createUnsafeMutable(__DIR__, '.env.live')->safeLoad();
}
