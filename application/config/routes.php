<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'login';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// ---------------- REST API v1 (Android) ----------------
$route['api/v1/health']['get'] = 'api/v1/home/health';
$route['api/v1/home']['get'] = 'api/v1/home/index';

$route['api/v1/auth/register']['post'] = 'api/v1/auth/register';
$route['api/v1/auth/verify-otp']['post'] = 'api/v1/auth/verify_otp';
$route['api/v1/auth/resend-otp']['post'] = 'api/v1/auth/resend_otp';
$route['api/v1/auth/login']['post'] = 'api/v1/auth/login';
$route['api/v1/auth/logout']['post'] = 'api/v1/auth/logout';
$route['api/v1/auth/forgot-password']['post'] = 'api/v1/auth/forgot_password';
$route['api/v1/auth/reset-password']['post'] = 'api/v1/auth/reset_password';

$route['api/v1/profile']['get'] = 'api/v1/profile/show';
$route['api/v1/profile']['put'] = 'api/v1/profile/update';
$route['api/v1/profile']['post'] = 'api/v1/profile/update';
$route['api/v1/profile/password']['post'] = 'api/v1/profile/password';
$route['api/v1/device-token']['post'] = 'api/v1/profile/device_token';

$route['api/v1/categories']['get'] = 'api/v1/home/categories';
$route['api/v1/destinations']['get'] = 'api/v1/destinations/index';
$route['api/v1/destinations/(:num)']['get'] = 'api/v1/destinations/show/$1';
$route['api/v1/packages']['get'] = 'api/v1/packages/index';
$route['api/v1/packages/(:num)']['get'] = 'api/v1/packages/show/$1';
$route['api/v1/packages/(:num)/reviews']['get'] = 'api/v1/packages/reviews/$1';
$route['api/v1/partners/(:num)']['get'] = 'api/v1/packages/partner/$1';
$route['api/v1/articles']['get'] = 'api/v1/home/articles';
$route['api/v1/faqs']['get'] = 'api/v1/home/faqs';

$route['api/v1/bookings/preview']['post'] = 'api/v1/bookings/preview';
$route['api/v1/bookings']['post'] = 'api/v1/bookings/create';
$route['api/v1/bookings']['get'] = 'api/v1/bookings/index';
$route['api/v1/bookings/(:num)']['get'] = 'api/v1/bookings/show/$1';
$route['api/v1/bookings/(:num)/cancel']['post'] = 'api/v1/bookings/cancel/$1';
$route['api/v1/bookings/(:num)/payments']['post'] = 'api/v1/payments/create/$1';
$route['api/v1/bookings/(:num)/documents']['post'] = 'api/v1/bookings/upload_document/$1';

$route['api/v1/payment-methods']['get'] = 'api/v1/payments/methods';
$route['api/v1/payments/(:num)']['get'] = 'api/v1/payments/show/$1';
$route['api/v1/payments/(:num)/proof']['post'] = 'api/v1/payments/proof/$1';

$route['api/v1/files/payment-proof/(:num)']['get'] = 'api/v1/files/payment_proof/$1';
$route['api/v1/files/booking-document/(:num)']['get'] = 'api/v1/files/booking_document/$1';

$route['api/v1/wishlist']['get'] = 'api/v1/wishlist/index';
$route['api/v1/wishlist/toggle']['post'] = 'api/v1/wishlist/toggle';

$route['api/v1/reviews']['post'] = 'api/v1/reviews/create';

$route['api/v1/notifications']['get'] = 'api/v1/notifications/index';
$route['api/v1/notifications/read-all']['post'] = 'api/v1/notifications/read_all';
$route['api/v1/notifications/(:num)/read']['post'] = 'api/v1/notifications/read/$1';

// ---------------- Webhook ----------------
$route['webhook/xendit']['post'] = 'webhook/xendit/invoice';
