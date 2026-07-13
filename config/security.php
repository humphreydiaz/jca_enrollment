<?php

return [
    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV', 'production') === 'production'),
    'hsts' => [
        'enabled' => (bool) env('HSTS_ENABLED', env('APP_ENV', 'production') === 'production'),
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', true),
    ],
    'approval_gates' => [
        'retention' => (bool) env('JCA_RETENTION_POLICY_APPROVED', false),
        'finance' => (bool) env('JCA_FINANCE_POLICY_APPROVED', false),
        'academics' => (bool) env('JCA_ACADEMIC_CATALOG_APPROVED', false),
        'support' => (bool) env('JCA_SUPPORT_WORKFLOW_APPROVED', false),
        'sms' => (bool) env('JCA_SMS_POLICY_APPROVED', false),
        'reports' => (bool) env('JCA_REPORTS_APPROVED', false),
        'hosting_recovery' => (bool) env('JCA_HOSTING_RECOVERY_APPROVED', false),
        'access_uat' => (bool) env('JCA_ACCESS_UAT_APPROVED', false),
    ],
];
