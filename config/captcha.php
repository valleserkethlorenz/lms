<?php
/**
 * Google reCAPTCHA v2 Configuration & Verification
 * 
 * Site Key:   Used in frontend HTML/JS (safe to expose)
 * Secret Key: Used in backend PHP verification (never share publicly)
 */

define('RECAPTCHA_SITE_KEY', '6LdzCKMtAAAAALZOBzK__d-Ow6iuZAWNZtjBMUDT');
define('RECAPTCHA_SECRET_KEY', '6LdzCKMtAAAAAFFqlF58G5qaiVpWk877jkgvu4jB');

/**
 * Verify a reCAPTCHA response token with Google's API.
 *
 * @param string $token The g-recaptcha-response token from the client
 * @return bool True if verification passed, false otherwise
 */
function verifyRecaptcha(string $token): bool
{
    if (empty($token)) {
        return false;
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 10,
        ],
    ];

    $context  = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log('reCAPTCHA verification request failed: could not reach Google API');
        return false;
    }

    $result = json_decode($response, true);

    return isset($result['success']) && $result['success'] === true;
}