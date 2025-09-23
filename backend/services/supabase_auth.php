<?php
// backend/services/supabase_auth.php

function requireSupabaseEnv(): void
{
    if (!extension_loaded('curl')) {
        throw new Exception('PHP cURL extension is required.');
    }
    if (!getenv('SUPABASE_URL') || !getenv('SUPABASE_SERVICE_ROLE_KEY')) {
        throw new Exception('Missing SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY env vars.');
    }
}

function createSupabaseAuthUser(string $email, string $password, array $userMeta = []): array
{
    requireSupabaseEnv();

    $url = rtrim(getenv('SUPABASE_URL'), '/') . '/auth/v1/admin/users';
    $svc = getenv('SUPABASE_SERVICE_ROLE_KEY');

    $payload = [
        'email'         => $email,
        'password'      => $password,   // GoTrue hashes this
        'email_confirm' => true,        // you already OTP-verified
        'user_metadata' => $userMeta,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "Authorization: Bearer $svc",
            "apikey: $svc",
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $res    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($res === false || $status >= 300) {
        throw new Exception("Auth create failed (HTTP $status): " . ($res ?: curl_error($ch)));
    }
    return json_decode($res, true);
}

function deleteSupabaseAuthUser(string $authUserId): void
{
    // handy for rollback if your DB insert fails after auth creation
    requireSupabaseEnv();

    $url = rtrim(getenv('SUPABASE_URL'), '/') . '/auth/v1/admin/users/' . $authUserId;
    $svc = getenv('SUPABASE_SERVICE_ROLE_KEY');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $svc",
            "apikey: $svc",
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    curl_exec($ch);
    // if this fails, log it; don’t throw to avoid masking original error
}
