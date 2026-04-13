<?php

return [

    'name' => env('COMPANY_NAME', 'SuGanta International.'),

    'address' => [
        'line1' => env('COMPANY_ADDRESS_LINE1', '4th Floor, 96A, Block- B, Pocket-10'),
        'line2' => env('COMPANY_ADDRESS_LINE2', 'Dwarka Sector -13'),
        'city' => env('COMPANY_ADDRESS_CITY', 'New Delhi'),
        'state' => env('COMPANY_ADDRESS_STATE', 'Delhi'),
        'pincode' => env('COMPANY_ADDRESS_PINCODE', '110078'),
        'country' => env('COMPANY_ADDRESS_COUNTRY', 'India'),
    ],

    'contact' => [
        'phone' => env('COMPANY_PHONE', '+91-9540763434'),
        'email' => env('COMPANY_EMAIL', 'support@suganta.com'),
        'website' => env('COMPANY_WEBSITE', 'https://www.suganta.com'),
        'notification_email' => env('COMPANY_NOTIFICATION_EMAIL', 'support@suganta.com'),
        'support_email' => env('COMPANY_SUPPORT_EMAIL', 'support@suganta.com'),
    ],

    'social' => [
        'facebook' => env('COMPANY_FACEBOOK', 'https://www.facebook.com/SuGantaIntl'),
        'twitter' => env('COMPANY_TWITTER', 'https://x.com/Sugantaintl'),
        'linkedin' => env('COMPANY_LINKEDIN', 'https://www.linkedin.com/company/sugantaintl/'),
        'instagram' => env('COMPANY_INSTAGRAM', 'https://www.instagram.com/sugantaintl?igsh=MTRjNGJlMmV4anV4Zw=='),
        'pinterest' => env('COMPANY_PINTEREST', 'https://in.pinterest.com/SuGantaIntl/'),
        'youtube' => env('COMPANY_YOUTUBE', 'https://www.youtube.com/channel/UCqwo0Ew6wHqy_ItvzpkJQ3g'),
        'whatsapp' => env('COMPANY_WHATSAPP', 'https://wa.me/919540763434'),
        'telegram' => env('COMPANY_TELEGRAM', 'https://t.me/SuGantaTutors'),
    ],

    'business_hours' => [
        'weekdays' => env('COMPANY_BUSINESS_HOURS_WEEKDAYS', 'Monday to Friday, 9 AM - 6 PM'),
        'weekend' => env('COMPANY_BUSINESS_HOURS_WEEKEND', 'Saturday, 9 AM - 2 PM'),
        'sunday' => env('COMPANY_BUSINESS_HOURS_SUNDAY', 'Sunday - Closed'),
    ],

];
