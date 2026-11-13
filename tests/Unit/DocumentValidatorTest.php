<?php
/**
 * Tests for the document quality checking pipeline
 * Covers: documentValidator.php (PHP-side logic)
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class DocumentValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/documentStorage.php';
        require_once __DIR__ . '/../../includes/documentValidator.php';
    }

    // ── documentQualityResult() structure tests ──

    public function testDocumentQualityResultReturnsCorrectStructure(): void
    {
        $result = documentQualityResult('clear', 'All good');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('details', $result);
        $this->assertSame('clear', $result['status']);
        $this->assertSame('All good', $result['reason']);
        $this->assertSame([], $result['details']);
    }

    public function testDocumentQualityResultWithDetails(): void
    {
        $details = ['pages' => [['page' => 1, 'laplacian_std' => 85.0]]];
        $result = documentQualityResult('flagged', 'Needs review', $details);
        $this->assertSame('flagged', $result['status']);
        $this->assertSame($details, $result['details']);
    }

    public function testDocumentQualityResultAllStatuses(): void
    {
        foreach (['clear', 'flagged', 'blurry', 'invalid', 'unavailable'] as $status) {
            $result = documentQualityResult($status, "Test $status");
            $this->assertSame($status, $result['status']);
        }
    }

    // ── documentStorageDirectory() tests ──

    public function testDocumentStorageDirectoryReturnsString(): void
    {
        $dir = documentStorageDirectory();
        $this->assertIsString($dir);
        $this->assertNotEmpty($dir);
    }

    public function testDocumentStorageDirectoryContainsDocuments(): void
    {
        $dir = documentStorageDirectory();
        $this->assertStringContainsString('documents', $dir);
    }

    // ── documentStoredPath() tests ──

    public function testDocumentStoredPathReturnsNullForEmptyName(): void
    {
        $this->assertNull(documentStoredPath(''));
    }

    public function testDocumentStoredPathReturnsNullForTraversal(): void
    {
        $this->assertNull(documentStoredPath('../../../etc/passwd'));
    }

    public function testDocumentStoredPathReturnsNullForNonexistent(): void
    {
        $this->assertNull(documentStoredPath('nonexistent_file_xyz.jpg'));
    }

    public function testDocumentStoredPathRejectsDirectoryComponents(): void
    {
        $this->assertNull(documentStoredPath('../secret/file.pdf'));
    }


    // ── checkDocumentQuality() integration tests ──

    public function testCheckDocumentQualityRejectsInvalidPath(): void
    {
        $result = checkDocumentQuality('/nonexistent/path/file.jpg');
        $this->assertSame('invalid', $result['status']);
    }

    public function testCheckDocumentQualityRejectsPathOutsideStorage(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'lms_test_');
        file_put_contents($tempFile, 'not an image');
        $result = checkDocumentQuality($tempFile);
        @unlink($tempFile);
        $this->assertSame('invalid', $result['status']);
    }

    // ── checkDocumentBlur() delegation test ──

    public function testCheckDocumentBlurFailsClosedForUncheckedDocuments(): void
    {
        // Regression: this used to return true for anything that was not literally
        // 'blurry', so an unavailable checker silently accepted every upload.
        $this->assertFalse(checkDocumentBlur('/nonexistent/file.jpg'));
    }

    // ── quality policy helpers ──

    public function testDocumentQualityConfigHasExpectedDefaults(): void
    {
        $config = documentQualityConfig();
        $this->assertArrayHasKey('ocr', $config);
        $this->assertArrayHasKey('blocking_statuses', $config);
        $this->assertContains('blurry', $config['blocking_statuses']);
        $this->assertContains('unreadable', $config['blocking_statuses']);
        $this->assertContains('invalid', $config['blocking_statuses']);
    }

    public function testDocumentQualityBlocksRejectsUnreadableAndBlurry(): void
    {
        $this->assertTrue(documentQualityBlocks('blurry'));
        $this->assertTrue(documentQualityBlocks('unreadable'));
        $this->assertTrue(documentQualityBlocks('invalid'));
    }

    public function testDocumentQualityBlocksAcceptsClearAndFlaggedByDefault(): void
    {
        $this->assertFalse(documentQualityBlocks('clear'));
        $this->assertFalse(documentQualityBlocks('flagged'));
        // 'unavailable' only blocks when the operator opts in, but it is always
        // surfaced to the organizer (see validatePlayerDocuments()).
        $this->assertFalse(documentQualityBlocks('unavailable'));
    }

    public function testDocumentQualityMessageExplainsEachVerdict(): void
    {
        $this->assertStringContainsString('blurry', strtolower(documentQualityMessage(['code' => 'blurry'])));
        $this->assertStringContainsString('cannot be read', documentQualityMessage(['code' => 'unreadable']));
        $this->assertStringContainsString('unavailable', strtolower(documentQualityMessage(['code' => 'unavailable'])));
        $this->assertSame('custom reason', documentQualityMessage(['code' => 'something_else', 'message' => 'custom reason']));
    }

    public function testDocumentQualityFlagExplainsUncheckedDocuments(): void
    {
        $this->assertNull(documentQualityFlag('Valid ID', 'Clear', 'fine', false));
        $this->assertStringContainsString('Valid ID', (string) documentQualityFlag('Valid ID', 'Flagged', 'slightly soft', false));
        $this->assertStringContainsString('unavailable', (string) documentQualityFlag('Valid ID', 'Flagged', '', true));
    }

    public function testValidatePlayerDocumentsExposesUnavailableDocuments(): void
    {
        $this->assertArrayHasKey('unavailable', validatePlayerDocuments(
            ['name' => [], 'error' => [], 'tmp_name' => [], 'size' => []], []
        ));
    }

    public function testDocumentQualityMetricsPayloadKeepsAStableSchema(): void
    {
        $quality = documentQualityResult('blurry', 'too soft', [
            'thresholds_version' => '2.0.0',
            'ocr' => ['engine' => 'rapidocr-onnxruntime', 'available' => true],
            'pages_checked' => 1,
            'pages' => [[
                'page' => 1, 'width' => 1000, 'height' => 1414,
                'frame_width' => 2000, 'frame_height' => 2828,
                'page_found' => true, 'page_bbox' => [10, 20, 990, 1400], 'page_area_ratio' => 0.62,
                'laplacian_std' => 12.5, 'local_variance' => 300.0, 'detail_ratio' => 0.05, 'entropy' => 6.4,
                'rel_sharpness' => 1.6, 'rel_sharp_p10' => 1.4, 'motion_score' => 0.2,
                'contrast' => 0.3, 'ink_ratio' => 0.08, 'text_height' => 22.0,
                'ocr' => ['chars' => 120, 'mean_confidence' => 88.0, 'matched_keywords' => ['birth'],
                          'missing_keywords' => ['certificate'], 'excerpt' => 'REPUBLIC OF THE PHILIPPINES', 'engine' => 'rapidocr'],
                'status' => 'blurry', 'reason' => 'too soft',
            ]],
        ]);

        $decoded = json_decode(documentQualityMetricsPayload($quality), true);
        $this->assertIsArray($decoded);
        $this->assertSame('blurry', $decoded['status']);
        $this->assertSame('2.0.0', $decoded['thresholds_version']);
        $this->assertTrue($decoded['ocr_available']);
        $page = $decoded['pages'][0];
        foreach (['rel_sharpness', 'rel_sharp_p10', 'contrast', 'ink_ratio', 'text_height',
                  'ocr_chars', 'ocr_confidence', 'ocr_missing_keywords', 'page_status', 'page_reason'] as $key) {
            $this->assertArrayHasKey($key, $page);
        }
        $this->assertSame(['certificate'], $page['ocr_missing_keywords']);
        $this->assertSame(120, $page['ocr_chars']);
        $this->assertLessThanOrEqual(500, strlen($page['ocr_excerpt']));
    }

    // ── validatePlayerDocuments() structure tests ──

    public function testValidatePlayerDocumentsReturnsCorrectStructure(): void
    {
        $result = validatePlayerDocuments(['name' => [], 'error' => [], 'tmp_name' => [], 'size' => []], []);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('blurry', $result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('quality', $result);
        $this->assertArrayHasKey('quality_details', $result);
        $this->assertArrayHasKey('quality_metrics', $result);
        $this->assertArrayHasKey('issues', $result);
    }

    public function testValidatePlayerDocumentsRejectsUploadErrors(): void
    {
        $files = [
            'name' => ['doc.pdf'],
            'error' => [UPLOAD_ERR_INI_SIZE],
            'tmp_name' => ['/tmp/phpXXXXXX'],
            'size' => [1024],
        ];
        $result = validatePlayerDocuments($files, ['Player One']);
        $this->assertNotEmpty($result['blurry']);
        $this->assertContains('Player One', $result['blurry']);
        $this->assertArrayHasKey(0, $result['issues']);
        $this->assertSame('upload_error', $result['issues'][0]['code']);
    }

    public function testValidatePlayerDocumentsRejectsOversizedFiles(): void
    {
        // Note: The size check occurs after is_uploaded_file(), so in a unit test
        // environment (without real uploads), we verify the structure returns issues
        // for non-existent tmp files. The size limit is validated in integration tests.
        $files = [
            'name' => ['large.pdf'],
            'error' => [UPLOAD_ERR_OK],
            'tmp_name' => ['/tmp/phpXXXXXX'],
            'size' => [11 * 1024 * 1024],
        ];
        $result = validatePlayerDocuments($files, ['Player One']);
        $this->assertNotEmpty($result['blurry']);
        // In unit test env, is_uploaded_file() fails first → invalid_upload
        $this->assertArrayHasKey(0, $result['issues']);
    }

    public function testValidatePlayerDocumentsSkipsEmptyNames(): void
    {
        $files = [
            'name' => [''],
            'error' => [UPLOAD_ERR_NO_FILE],
            'tmp_name' => [''],
            'size' => [0],
        ];
        $result = validatePlayerDocuments($files, ['Player One']);
        $this->assertEmpty($result['blurry']);
        $this->assertEmpty($result['valid']);
    }

    public function testValidatePlayerDocumentsRejectsEmptyTmpName(): void
    {
        $files = [
            'name' => ['doc.pdf'],
            'error' => [UPLOAD_ERR_OK],
            'tmp_name' => [''],
            'size' => [1024],
        ];
        $result = validatePlayerDocuments($files, ['Player One']);
        $this->assertNotEmpty($result['blurry']);
        $this->assertSame('invalid_upload', $result['issues'][0]['code']);
    }
}