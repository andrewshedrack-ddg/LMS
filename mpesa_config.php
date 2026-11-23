<?php
// ============================================================
// M-Pesa Daraja settings (STK Push).
//
// Fill these in from your Safaricom Daraja app at
// developer.safaricom.co.ke (create an app, then copy the
// Consumer Key, Consumer Secret and Passkey for your shortcode).
//
// These values are secret. Do NOT share them, and do NOT post
// this file anywhere public. Keep it on the server only.
// ============================================================

// Your Daraja app credentials.
define('MPESA_CONSUMER_KEY', 'G6IH49lXRIWExHKWBJtTkVwqPKHwroYmAwDnEzUVFnWwVCih');
define('MPESA_CONSUMER_SECRET', '5DfA3XMwhqXgj3crS0cXkhPRrgGkOLGzJGpZdHxLiW125OEtj5zqAg73fDAAhnvR');

// The passkey for your shortcode (from your Daraja app).
define('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');

// Your M-Pesa shortcode (till number or paybill).
// The Daraja sandbox test shortcode is 174379.
define('MPESA_SHORTCODE', '174379');

// For a paybill use 'CustomerPayBillOnline'.
// For a till number use 'CustomerBuyGoodsOnline'.
define('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline');

// True while testing with the Daraja sandbox, false for live M-Pesa.
define('MPESA_SANDBOX', true);

// The public address of mpesa_callback.php on the LIVE site.
// Safaricom calls this address after the customer pays, so it
// must start with https and be reachable from the internet.
// It will NOT work on localhost. Set it after the site is hosted.
define('MPESA_CALLBACK_URL', 'https://YOUR-LIVE-SITE/mpesa_callback.php');

// Base address for Daraja, sandbox or live.
function mpesa_base_url() {
    return MPESA_SANDBOX
        ? 'https://sandbox.safaricom.co.ke'
        : 'https://api.safaricom.co.ke';
}
