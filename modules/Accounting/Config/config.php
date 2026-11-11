<?php
return [
 'name'=>'Accounting',
 'release'=>env('ACCOUNTING_RELEASE','0.1.0-dev'),
 'demo'=>(bool)env('ACCOUNTING_DEMO_MODE',false),
 'isolated_tests'=>(bool)env('ACCOUNTING_ISOLATED_INSTANCE',false),
 'offline_hours'=>72,
 'workspace_max_bytes'=>100*1024*1024,
 'mfa_minutes'=>15,
 'private_storage'=>storage_path('app/accounting/private'),
 'frontend_path'=>'/contabilidad/',
];
