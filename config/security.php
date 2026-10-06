<?php

return [
    // Email one-time-code (2-step) for STAFF login. Needs working SMTP (MAIL_* in
    // .env). Leave false until mail is configured, otherwise nobody can log in.
    'otp_enabled'  => env('AUTH_OTP_ENABLED', false),
    'otp_ttl'      => 10,   // minutes the code stays valid
    'otp_attempts' => 5,    // wrong codes allowed before the code is burned

    // Role-switcher / tester shortcuts (DevController). OFF by default.
    'dev_tools'    => env('DEV_TOOLS_ENABLED', false),

    // Only these email domains may be used for staff accounts / OTP delivery.
    // Comma separated, empty = no restriction.
    'staff_email_domains' => array_filter(array_map('trim', explode(',', env('STAFF_EMAIL_DOMAINS', '')))),
];