<?php
/**
 * Tests for stored-document asset helpers.
 *
 * Covers: includes/documentAssets.php -> storedDocumentCloudRef()
 *
 * Stored documents are either a bare local file name or a Cloudinary secure
 * URL (validatePlayerDocuments() uploads to Cloudinary when it is configured).
 * Deleting a replaced or rolled-back document must work for both shapes, and
 * the Cloudinary branch needs the public id + resource type that
 * StorageService::delete() expects.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/documentAssets.php';

class DocumentAssetsTest extends TestCase
{
    private function cloudUrl(string $path): string
    {
        return 'https://res.cloudinary.com/u3wlf2nk/' . $path;
    }

    public function testImageUrlYieldsPublicIdWithoutExtension(): void
    {
        $ref = storedDocumentCloudRef($this->cloudUrl('image/upload/v1729000000/lms/documents/abc123.jpg'));

        $this->assertSame(['public_id' => 'lms/documents/abc123', 'resource_type' => 'image'], $ref);
    }

    public function testRawUrlKeepsTheFileExtension(): void
    {
        $ref = storedDocumentCloudRef($this->cloudUrl('raw/upload/v1729000000/lms/documents/abc123.pdf'));

        $this->assertSame(['public_id' => 'lms/documents/abc123.pdf', 'resource_type' => 'raw'], $ref);
    }

    public function testUrlWithoutAVersionSegmentStillParses(): void
    {
        $ref = storedDocumentCloudRef($this->cloudUrl('image/upload/lms/documents/abc123.png'));

        $this->assertSame(['public_id' => 'lms/documents/abc123', 'resource_type' => 'image'], $ref);
    }

    public function testPlainLocalFileNameIsNotACloudReference(): void
    {
        $this->assertNull(storedDocumentCloudRef('abc123.pdf'));
        $this->assertNull(storedDocumentCloudRef(''));
    }

    public function testForeignHostIsRejected(): void
    {
        $this->assertNull(storedDocumentCloudRef('https://example.com/image/upload/v1/lms/documents/abc.jpg'));
    }

    public function testUnsupportedResourceTypeIsRejected(): void
    {
        $this->assertNull(storedDocumentCloudRef($this->cloudUrl('private/upload/v1/lms/documents/abc.jpg')));
    }

    public function testUrlWithoutPublicIdIsRejected(): void
    {
        $this->assertNull(storedDocumentCloudRef($this->cloudUrl('image/upload/v1729000000')));
    }
}
