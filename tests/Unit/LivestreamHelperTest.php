<?php
/**
 * Tests for includes/livestreamHelper.php
 *
 * The committee portal stores a raw YouTube/Facebook link and every committee
 * member + spectator page has to turn it into an embeddable iframe. The parser
 * therefore has to accept the URL shapes people actually copy out of those
 * apps (watch/live/shorts links, Facebook live and reel links), reject
 * look-alike hosts, and the iframe builder has to escape once and keep the
 * player's 16:9 geometry.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/livestreamHelper.php';

final class LivestreamHelperTest extends TestCase
{
    public function testYoutubeWatchLinkIsConvertedToEmbed(): void
    {
        // Shape of the link currently saved in tbl_schedule (radio/playlist
        // parameters included) — the video ID must win over the playlist.
        $url = 'https://www.youtube.com/watch?v=Ia4RPXAh4J0&list=RDGMEMCMFH2exzjBeE_zAHHJOdxgVMIa4RPXAh4J0&start_radio=1';

        $parsed = parseLivestreamUrl($url);

        $this->assertNotNull($parsed);
        $this->assertSame('youtube', $parsed['platform']);
        $this->assertSame('Ia4RPXAh4J0', $parsed['video_id']);
        $this->assertSame('https://www.youtube.com/embed/Ia4RPXAh4J0?autoplay=1&rel=0', $parsed['embed_url']);
        $this->assertSame($url, $parsed['original_url']);
    }

    public function testEveryYoutubeVideoUrlShapeIsConverted(): void
    {
        $urls = [
            'https://www.youtube.com/live/abc123XYZ_-',
            'https://www.youtube.com/live/abc123XYZ_-?feature=share',
            'https://youtu.be/abc123XYZ_-',
            'https://youtu.be/abc123XYZ_-?si=tracking',
            'https://www.youtube.com/embed/abc123XYZ_-',
            'https://www.youtube.com/shorts/abc123XYZ_-',
            'https://m.youtube.com/watch?v=abc123XYZ_-',
            'https://www.youtube.com/watch?list=PL123&v=abc123XYZ_-',
        ];

        foreach ($urls as $url) {
            $parsed = parseLivestreamUrl($url);

            $this->assertNotNull($parsed, $url . ' must be supported');
            $this->assertSame('youtube', $parsed['platform'], $url);
            $this->assertSame('abc123XYZ_-', $parsed['video_id'], $url);
            $this->assertSame('https://www.youtube.com/embed/abc123XYZ_-?autoplay=1&rel=0', $parsed['embed_url'], $url);
        }
    }

    public function testYoutubeChannelLiveUsesTheChannelLiveStreamEmbed(): void
    {
        $parsed = parseLivestreamUrl('https://www.youtube.com/channel/UCabc123def456/live');

        $this->assertNotNull($parsed);
        $this->assertSame('youtube', $parsed['platform']);
        $this->assertSame('UCabc123def456', $parsed['video_id']);
        $this->assertSame(
            'https://www.youtube.com/embed/live_stream?channel=UCabc123def456&autoplay=1&rel=0',
            $parsed['embed_url']
        );
    }

    public function testYoutubeChannelPageLiveLinkIsRejectedBecauseItCannotBeFramed(): void
    {
        $this->assertNull(parseLivestreamUrl('https://www.youtube.com/@SomeChannel/live'));
        $this->assertNull(parseLivestreamUrl('https://www.youtube.com/c/SomeChannel/live'));
    }

    public function testFacebookVideoAndLiveLinksAreConverted(): void
    {
        $cases = [
            'https://www.facebook.com/SomePage/videos/1234567890'                   => '1234567890',
            'https://www.facebook.com/SomePage/videos/some-video-slug/1234567890/'  => '1234567890',
            'https://www.facebook.com/SomePage/live_videos/1234567890'              => '1234567890',
            'https://www.facebook.com/watch/?v=1234567890'                          => '1234567890',
            'https://www.facebook.com/watch/live/?ref=watch_permalink&v=1234567890' => '1234567890',
            'https://www.facebook.com/SomePage/live/'                               => 'live',
            'https://www.facebook.com/reel/1234567890'                              => '1234567890',
            'https://www.facebook.com/share/v/1AbCdEfGhIjK/'                        => '1AbCdEfGhIjK',
            'https://fb.watch/abcDEF123/'                                           => 'abcDEF123',
        ];

        foreach ($cases as $url => $expectedId) {
            $parsed = parseLivestreamUrl($url);

            $this->assertNotNull($parsed, $url . ' must be supported');
            $this->assertSame('facebook', $parsed['platform'], $url);
            $this->assertSame($expectedId, $parsed['video_id'], $url);
            $this->assertStringStartsWith('https://www.facebook.com/plugins/video.php?href=', $parsed['embed_url'], $url);
            $this->assertStringEndsWith('&show_text=0&autoplay=1', $parsed['embed_url'], $url);
            $this->assertSame($url, $parsed['original_url'], $url);
        }
    }

    public function testFacebookEmbedUrlEncodesTheWholeOriginalLink(): void
    {
        $url    = 'https://www.facebook.com/SomePage/videos/1234567890';
        $parsed = parseLivestreamUrl($url);

        $this->assertNotNull($parsed);
        $this->assertStringContainsString(rawurlencode($url), $parsed['embed_url']);
        $this->assertStringNotContainsString('https://www.facebook.com/SomePage/videos/1234567890&', $parsed['embed_url']);
    }

    public function testMobileFacebookLinksAreNormalisedForThePlugin(): void
    {
        $parsed = parseLivestreamUrl('https://m.facebook.com/SomePage/videos/1234567890');

        $this->assertNotNull($parsed);
        $this->assertStringContainsString(
            rawurlencode('https://www.facebook.com/SomePage/videos/1234567890'),
            $parsed['embed_url']
        );
    }

    public function testLookAlikeHostsAreRejected(): void
    {
        $urls = [
            'https://notyoutube.com/watch?v=abc123XYZ_-',
            'https://youtube.com.evil.example/watch?v=abc123XYZ_-',
            'https://evil.example/?next=https://www.youtube.com/watch?v=abc123XYZ_-',
            'https://fakefacebook.com/SomePage/videos/1234567890',
        ];

        foreach ($urls as $url) {
            $this->assertNull(parseLivestreamUrl($url), $url . ' must not be treated as a supported platform');
        }
    }

    public function testUnsupportedAndMalformedUrlsReturnNull(): void
    {
        $urls = [
            '',
            '   ',
            'not a url',
            'www.youtube.com/watch?v=abc123XYZ_-',
            'ftp://www.youtube.com/watch?v=abc123XYZ_-',
            'javascript://www.youtube.com/%0Aalert(1)',
            'https://vimeo.com/123456789',
            'https://www.youtube.com/watch?v=short',
            'https://www.youtube.com/',
        ];

        foreach ($urls as $url) {
            $this->assertNull(parseLivestreamUrl($url), var_export($url, true) . ' must be rejected');
        }
    }

    public function testIsSupportedLivestreamUrlMatchesTheParser(): void
    {
        $this->assertTrue(isSupportedLivestreamUrl('https://youtu.be/abc123XYZ_-'));
        $this->assertTrue(isSupportedLivestreamUrl('https://www.facebook.com/SomePage/live/'));
        $this->assertFalse(isSupportedLivestreamUrl('https://www.youtube.com/@SomeChannel/live'));
        $this->assertFalse(isSupportedLivestreamUrl(''));
    }

    public function testEmbedHtmlEscapesTheUrlAndTitleExactlyOnce(): void
    {
        $parsed = parseLivestreamUrl('https://youtu.be/abc123XYZ_-');
        $this->assertNotNull($parsed);

        $html = livestreamEmbedHtml($parsed['embed_url'], 'Team A & Team B', 320);

        $this->assertStringContainsString('src="https://www.youtube.com/embed/abc123XYZ_-?autoplay=1&amp;rel=0"', $html);
        $this->assertStringContainsString('title="Team A &amp; Team B"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html, 'the title must not be double escaped');
        $this->assertStringContainsString('allowfullscreen', $html);
        $this->assertStringContainsString('ratio-16x9', $html);
    }

    public function testEmbedHtmlCapsWidthSoThePlayerKeepsItsAspectRatio(): void
    {
        $html = livestreamEmbedHtml('https://www.youtube.com/embed/abc123XYZ_-', 'Live', 320);

        // 320px tall at 16:9 is 568.9px wide.
        $this->assertStringContainsString('max-width:569px', $html);
        $this->assertStringNotContainsString('max-height', $html, 'clamping the height breaks the aspect ratio');
    }

    public function testEmbedHtmlNeverProducesAnUnescapedUrl(): void
    {
        $html = livestreamEmbedHtml('https://www.youtube.com/embed/x"><script>alert(1)</script>', 'Live', 320);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $html);
    }

    public function testStatusIsAutomaticallyLiveOnlyWhenAUrlIsSavedWithoutOne(): void
    {
        $this->assertSame(
            ['status' => 'Live', 'auto' => true],
            resolveLivestreamStatus('', 'https://youtu.be/abc123XYZ_-')
        );
        $this->assertSame(
            ['status' => 'Live', 'auto' => true],
            resolveLivestreamStatus(null, '  https://youtu.be/abc123XYZ_-  '),
            'a missing field and surrounding whitespace count as empty'
        );
    }

    public function testExplicitStatusAlwaysWinsOverTheAutomaticDefault(): void
    {
        foreach (['Scheduled', 'Live', 'Ended'] as $status) {
            $this->assertSame(
                ['status' => $status, 'auto' => false],
                resolveLivestreamStatus($status, 'https://youtu.be/abc123XYZ_-'),
                $status . ' must be stored as chosen'
            );
        }
    }

    public function testNoAutomaticStatusWithoutAStreamUrl(): void
    {
        $this->assertSame(['status' => '', 'auto' => false], resolveLivestreamStatus('', ''));
        $this->assertSame(['status' => '', 'auto' => false], resolveLivestreamStatus('   ', '   '));
        $this->assertSame(['status' => 'Ended', 'auto' => false], resolveLivestreamStatus('Ended', ''));
    }
}
