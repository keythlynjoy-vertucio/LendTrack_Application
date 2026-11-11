<?php

return [
    'roles' => [
        'admin' => 'admin',
        'staff' => 'staff',
    ],

    'permissions' => [
        'admin' => [
            'view_dashboard',
            'view_payments',
            'view_borrowers',
            'view_loans',
            'view_reports',
            'view_analytics',
            'view_discrepancy',
            'view_activity_logs',
            'manage_borrowers',
            'manage_loans',
            'manage_payments',
            'export_reports',
            'view_deleted_records',
        ],
        'staff' => [
            'view_dashboard',
            'view_payments',
            'view_borrowers',
            'view_loans',
            'record_payments',
            'create_loans',
        ],
    ],
];
