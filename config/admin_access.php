<?php

$emails = static fn (?string $value): array => array_values(array_unique(array_filter(
    array_map(static fn (string $email): string => strtolower(trim($email)), explode(',', $value ?? '')),
)));

return [
    'admin_emails' => $emails(env('ADMIN_GOOGLE_EMAILS')),
    'super_admin_emails' => $emails(env('SUPER_ADMIN_GOOGLE_EMAILS')),
];
