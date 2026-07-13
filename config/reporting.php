<?php

return [
    'disk' => env('REPORTS_DISK', 'reports'),
    'export_ttl_hours' => (int) env('REPORT_EXPORT_TTL_HOURS', 24),
    'export_row_limit' => (int) env('REPORT_EXPORT_ROW_LIMIT', 10000),
    'print_row_limit' => (int) env('REPORT_PRINT_ROW_LIMIT', 1000),
];
