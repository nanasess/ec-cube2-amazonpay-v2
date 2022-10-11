<?php

use Dotenv\Dotenv;

Dotenv::createUnsafeMutable(__DIR__, '.env')->safeLoad();
if (getenv('AMAZON_SANDBOX') !== '1') {
    Dotenv::createUnsafeMutable(__DIR__, '.env.sandbox')->safeLoad();
} else {
    Dotenv::createImmutable(__DIR__, '.env.live')->safeLoad();
}
