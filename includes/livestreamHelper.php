<?php
/**
 * Livestream Helper Functions
 * Converts YouTube and Facebook live URLs into embeddable iframe sources.
 * Used by the Committee portal (manage) and the public live viewer.
 */

/**
 * Parse a livestream URL and return structured metadata.
 *
 * @param string $url  The raw URL entered by the committee member.
 * @return array{platform:string, video_id:string, embed_url:string, original_url:string}|null
 *         Returns null when the URL is not a recognised YouTube/Facebook link.
 */
function parseLivestreamUrl(string $url): ?array {
    $url = trim($url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
        return null;
    }
    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return null;
    }

    // Match the host itself, never a substring of the URL: a link such as
    // https://notyoutube.com/watch?v=... must not be treated as YouTube.
    $host = strtolower($parts['host']);
    $host = preg_replace('~^www\.~', '', $host) ?? $host;
    $path = $parts['path'] ?? '/';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $isVideoId = static function ($id): bool {
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) === 1;
    };

    $youtubeResult = static function (string $videoId) use ($url): array {
        return [
            'platform'     => 'youtube',
            'video_id'     => $videoId,
            'embed_url'    => 'https://www.youtube.com/embed/' . $videoId . '?autoplay=1&rel=0',
            'original_url' => $url,
        ];
    };

    // ---- YouTube ----
    // Supported: youtu.be/ID, youtube.com/watch?v=ID, /live/ID, /embed/ID,
    //            /shorts/ID, /v/ID and a channel's live page /channel/UC.../live.
    // A channel page itself (@handle/live, /c/name/live) is not embeddable:
    // YouTube sends X-Frame-Options for those, so they are rejected here.
    if ($host === 'youtu.be') {
        $videoId = explode('/', trim($path, '/'))[0] ?? '';
        if ($isVideoId($videoId)) {
            return $youtubeResult($videoId);
        }
        return null;
    }

    if (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com'], true)) {
        $watchId = $query['v'] ?? null;
        if ($isVideoId($watchId)) {
            return $youtubeResult((string) $watchId);
        }
        if (preg_match('~^/(?:live|embed|shorts|v)/([A-Za-z0-9_-]{6,})(?:/|$)~', $path, $m)) {
            return $youtubeResult($m[1]);
        }
        // Channel live shortcut: keep following the channel while it streams.
        if (preg_match('~^/channel/(UC[A-Za-z0-9_-]{10,})/live/?$~', $path, $m)) {
            return [
                'platform'     => 'youtube',
                'video_id'     => $m[1],
                'embed_url'    => 'https://www.youtube.com/embed/live_stream?channel=' . $m[1] . '&autoplay=1&rel=0',
                'original_url' => $url,
            ];
        }
        return null;
    }

    // ---- Facebook ----
    // Supported: /USER/videos/ID (optionally /videos/slug/ID), /USER/live_videos/ID,
    //            /USER/live/, /watch/?v=ID, /watch/live/?v=ID, /reel/ID,
    //            /share/v/CODE, fb.watch/CODE.
    if (in_array($host, ['facebook.com', 'm.facebook.com', 'web.facebook.com', 'fb.watch', 'fb.me'], true)) {
        $videoId = null;

        $queryVideoId = $query['v'] ?? null;
        if (is_string($queryVideoId) && preg_match('/^\d+$/', $queryVideoId)) {
            $videoId = $queryVideoId;
        } elseif (preg_match('~/videos/(?:[^/]+/)?(\d+)~', $path, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('~/live_videos/(\d+)~', $path, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('~/reel/(\d+)~', $path, $m)) {
            $videoId = $m[1];
        } elseif (preg_match('~/share/(?:v|r)/([A-Za-z0-9_-]+)~', $path, $m)) {
            $videoId = $m[1];
        } elseif ($host === 'fb.watch' || $host === 'fb.me') {
            $videoId = explode('/', trim($path, '/'))[0] ?? '';
        } elseif (preg_match('~^/(?!watch(?:/|$))[^/]+/live/?$~', $path)) {
            // A page's "currently live" URL. The plugin resolves it while the
            // stream runs and shows the replay once it ends.
            $videoId = 'live';
        }

        if ($videoId !== null && $videoId !== '') {
            // The plugin expects the canonical desktop URL; mobile/share links
            // are rewritten so the same video can be embedded.
            $canonical = preg_replace('~^https?://(?:m|web|www)\.facebook\.com~i', 'https://www.facebook.com', $url) ?? $url;
            return [
                'platform'     => 'facebook',
                'video_id'     => $videoId,
                'embed_url'    => 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode($canonical) . '&show_text=0&autoplay=1',
                'original_url' => $url,
            ];
        }
    }

    return null; // unsupported
}

/**
 * Return a human-readable label for the platform.
 */
function livestreamPlatformLabel(string $platform): string {
    return match ($platform) {
        'youtube'  => 'YouTube',
        'facebook' => 'Facebook',
        default    => ucfirst($platform),
    };
}

/**
 * Return a Bootstrap icon class for the platform.
 */
function livestreamPlatformIcon(string $platform): string {
    return match ($platform) {
        'youtube'  => 'bi-youtube',
        'facebook' => 'bi-facebook',
        default    => 'bi-broadcast',
    };
}

/**
 * Build a responsive iframe HTML snippet for the embed.
 *
 * @param string $embedUrl  The sanitised embed URL from parseLivestreamUrl().
 * @param string $title     Accessibility title for the iframe.
 * @param int    $height    CSS height in px (default 480).
 */
function livestreamEmbedHtml(string $embedUrl, string $title = 'Live Stream', int $height = 480): string {
    $safeUrl   = htmlspecialchars($embedUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $height    = max(180, $height);
    // Cap the width, not the height: limiting the height of a Bootstrap .ratio
    // box clips the player and breaks its 16:9 geometry.
    $maxWidth  = (int) round($height * 16 / 9);
    return '<div class="lms-stream-embed ratio ratio-16x9" style="max-width:' . $maxWidth . 'px;">'
         . '<iframe src="' . $safeUrl . '" title="' . $safeTitle . '" '
         . 'frameborder="0" '
         . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" '
         . 'allowfullscreen referrerpolicy="strict-origin-when-cross-origin" '
         . 'style="border:0;"></iframe>'
         . '</div>';
}

/**
 * Return a CSS class suffix for the livestream status badge.
 */
function livestreamStatusBadgeClass(?string $status): string {
    return match (strtolower((string)$status)) {
        'live'      => 'bg-danger',
        'scheduled' => 'bg-warning text-dark',
        'ended'     => 'bg-secondary',
        default     => 'bg-light text-muted border',
    };
}

/**
 * Quick check: is this URL a supported livestream source?
 */
function isSupportedLivestreamUrl(string $url): bool {
    return parseLivestreamUrl($url) !== null;
}

/**
 * Resolve the status that should be stored for a livestream update.
 *
 * A committee member who pastes a stream link and leaves the status on the
 * empty "Select" option is starting that stream, so it is stored as Live
 * instead of leaving the match invisible (no LIVE badge, no "Watch live"
 * link, absent from the committee dashboard). An explicit status always wins.
 *
 * @return array{status:string, auto:bool} status = value to persist,
 *         auto = true when Live was chosen automatically.
 */
function resolveLivestreamStatus(?string $status, ?string $url): array {
    $status = trim((string) $status);
    $url    = trim((string) $url);

    if ($status === '' && $url !== '') {
        return ['status' => 'Live', 'auto' => true];
    }

    return ['status' => $status, 'auto' => false];
}
