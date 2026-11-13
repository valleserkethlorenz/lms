<?php
/**
 * Tests for the in-app document preview / streaming helpers
 * Covers: includes/documentPreview.php, includes/documentStream.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class DocumentStreamTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/documentPreview.php';
        require_once __DIR__ . '/../../includes/documentStream.php';
    }

    // ── documentPreviewKind() ──

    public function testPreviewKindDetectsPdf(): void
    {
        $this->assertSame('pdf', documentPreviewKind('birth_certificate.pdf'));
        $this->assertSame('pdf', documentPreviewKind('BIRTH.PDF'));
    }

    public function testPreviewKindDetectsImages(): void
    {
        foreach (['photo.jpg', 'photo.JPEG', 'scan.png', 'graphic.gif', 'shot.webp', 'scan.bmp'] as $name) {
            $this->assertSame('image', documentPreviewKind($name), $name . ' should preview as an image');
        }
    }

    public function testPreviewKindReadsCloudinaryUrls(): void
    {
        $this->assertSame(
            'pdf',
            documentPreviewKind('https://res.cloudinary.com/demo/image/upload/v1/lms/player_documents/cert.pdf')
        );
        $this->assertSame(
            'image',
            documentPreviewKind('https://res.cloudinary.com/demo/raw/upload/v1/lms/player_documents/id.jpg?x=1')
        );
    }

    public function testPreviewKindFallsBackToOther(): void
    {
        $this->assertSame('other', documentPreviewKind('notes.docx'));
        $this->assertSame('other', documentPreviewKind('spreadsheet.xlsx'));
        $this->assertSame('other', documentPreviewKind(''));
    }

    // ── documentMimeFromName() ──

    public function testMimeFromNameUsesTheFileExtension(): void
    {
        $this->assertSame('application/pdf', documentMimeFromName('cert.pdf'));
        $this->assertSame('image/jpeg', documentMimeFromName('id.JPG'));
        $this->assertSame('image/png', documentMimeFromName('photo.png'));
        $this->assertSame('text/plain; charset=UTF-8', documentMimeFromName('notes.txt'));
    }

    public function testMimeFromNameFallsBackToRemoteType(): void
    {
        $this->assertSame('application/pdf', documentMimeFromName('unknown', 'application/pdf'));
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            documentMimeFromName('unknown', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=binary')
        );
    }

    public function testMimeFromNameRejectsUnsafeRemoteType(): void
    {
        $this->assertSame('application/octet-stream', documentMimeFromName('unknown', 'text/html'));
        $this->assertSame('application/octet-stream', documentMimeFromName('unknown'));
    }

    // ── documentDownloadName() ──

    public function testDownloadNameSanitisesDisplayName(): void
    {
        $this->assertSame(
            'birth certificate.pdf',
            documentDownloadName(['original_name' => 'birth certificate.pdf'])
        );
        $this->assertSame(
            'file.pdf',
            documentDownloadName(['original_name' => 'my/file.pdf'])
        );
    }

    public function testDownloadNameFallsBackToStoredNameThenDefault(): void
    {
        $this->assertSame(
            'abc123.jpg',
            documentDownloadName(['original_name' => '', 'stored_name' => 'abc123.jpg'])
        );
        $this->assertSame('document', documentDownloadName(['original_name' => '', 'stored_name' => '']));
    }
}
