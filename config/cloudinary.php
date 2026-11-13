<?php
/**
 * Cloudinary Configuration
 *
 * Replace the placeholder values with your actual Cloudinary credentials.
 * Get them from: https://console.cloudinary.com/pm/developer-dashboard
 *
 * CLOUD_NAME  – appears in every delivery URL (e.g. https://res.cloudinary.com/<cloud_name>/…)
 * API_KEY     – public key used for signed uploads
 * API_SECRET  – private key (never expose to the client)
 */

return [
    'cloud_name' => 'u3wlf2nk',
    'api_key'    => '236569973581668',
    'api_secret' => 'h_hFH69RZQZQQghTjz_GaHsqsxU',

    // Folder prefixes inside Cloudinary (logical organisation)
    'folders' => [
        'league_images'     => 'lms/leagues',
        'gallery'           => 'lms/gallery',
        'profiles'          => 'lms/profiles',
        'team_images'       => 'lms/teams',
        'player_documents'  => 'lms/documents',
        'guideline_documents' => 'lms/guidelines',
    ],
];
