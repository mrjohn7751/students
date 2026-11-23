<?php

return [
    'url' => env('HEMIS_API_URL', 'https://student.jdpu.uz/rest/v1'),
    'token' => env('HEMIS_API_TOKEN'),
    'page_size' => (int) env('HEMIS_PAGE_SIZE', 200),
    'timeout' => (int) env('HEMIS_TIMEOUT', 60),
    // So'rovlar orasidagi pauza (mikrosekund) — rate limitdan saqlanish uchun
    'throttle_us' => (int) env('HEMIS_THROTTLE_US', 250000),
];
