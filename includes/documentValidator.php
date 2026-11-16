<?php
require_once __DIR__ . '/documentStorage.php';
require_once __DIR__ . '/storageService.php';

if (!function_exists('sanitizeForLog')) {
    function sanitizeForLog($value) { return preg_replace('/[\r\n\t]/', ' ', (string)$value); }
}

function documentQualityResult(string $status, string $reason, array $details = []): array
{
    return ['status' => $status, 'reason' => $reason, 'details' => $details];
}

if (!function_exists('documentQualityConfig')) {
    /**
     * Policy knobs for the checker (see config/documentQuality.php).
     */
    function documentQualityConfig(): array
    {
        static $config = null;
        if (is_array($config)) return $config;
        $defaults = [
            'ocr' => 'auto',
            'block_when_unavailable' => false,
            'block_when_flagged' => false,
            'max_ocr_pages' => 2,
            'parallel_documents' => 1,
            'cache_verdicts' => true,
            'cache_ttl_days' => 30,
            'timeout_base' => 45,
            'timeout_per_extra_document' => 25,
            'timeout_max' => 300,
            'blocking_statuses' => ['blurry', 'unreadable', 'invalid'],
        ];
        $file = dirname(__DIR__) . '/config/documentQuality.php';
        $loaded = is_file($file) ? (array) require $file : [];
        $config = array_merge($defaults, $loaded);
        return $config;
    }
}

if (!function_exists('documentQualityBlocks')) {
    /**
     * True when the verdict must stop the registration.
     *
     * 'unavailable' is only blocking when the organizer opts in: rejecting every
     * upload while the checker is down would block the league entirely, but it
     * must never pass silently either (see validatePlayerDocuments()).
     */
    function documentQualityBlocks(string $status): bool
    {
        $config = documentQualityConfig();
        if (in_array($status, (array) $config['blocking_statuses'], true)) return true;
        if ($status === 'unavailable') return (bool) $config['block_when_unavailable'];
        if ($status === 'flagged') return (bool) $config['block_when_flagged'];
        return false;
    }
}

if (!function_exists('documentQualityMessage')) {
    /**
     * User-facing explanation for a rejected document.
     *
     * The checker reports *why* a page failed (blur, illegible text, wrong
     * format) so the form can point the user at the real problem instead of a
     * single catch-all sentence.
     */
    function documentQualityMessage(array $issue): string
    {
        return match ((string) ($issue['code'] ?? '')) {
            'blurry' => 'This document is blurry. Take a sharper photo or upload a scanned copy.',
            'unreadable' => 'The text on this document cannot be read. Upload a clearer photo or a scanned copy.',
            'invalid' => (string) ($issue['message'] ?? 'This document could not be read. Upload a PDF, JPG, PNG, BMP or WebP file.'),
            'unavailable' => 'The automatic document check is unavailable right now. Your document was kept for manual review.',
            default => (string) ($issue['message'] ?? 'This document could not be accepted.'),
        };
    }
}

if (!function_exists('documentQualityFlag')) {
    /** Organizer-facing note for documents that passed but need a human look. */
    function documentQualityFlag(string $label, string $dbStatus, string $details, bool $unavailable): ?string
    {
        if ($unavailable) {
            return $label . ': the automatic check was unavailable, please review this document manually.';
        }
        return $dbStatus === 'Flagged' ? $label . ': ' . $details : null;
    }
}

if (!function_exists('documentCheckerPythonCandidates')) {
    /**
     * Candidate Python interpreters, ordered from most to least specific.
     * Windows Store aliases under WindowsApps are excluded because they only
     * print an installation message instead of running Python.
     */
    function documentCheckerPythonCandidates(): array
    {
        $candidates = [];
        $configured = trim((string)getenv('LMS_PYTHON'));
        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $isWindows = PHP_OS_FAMILY === 'Windows';
        $lookupCommand = $isWindows ? 'where' : 'which';
        foreach (['python', 'python3'] as $name) {
            $found = [];
            @exec($lookupCommand . ' ' . escapeshellarg($name) . ' 2>' . ($isWindows ? 'nul' : '/dev/null'), $found);
            foreach ($found as $line) {
                $line = trim((string)$line);
                if ($line !== '') $candidates[] = $line;
            }
        }

        if ($isWindows) {
            $patterns = ['C:\\Python*\\python.exe'];
            foreach (['LOCALAPPDATA', 'APPDATA', 'ProgramFiles', 'ProgramFiles(x86)'] as $envName) {
                $base = (string)getenv($envName);
                if ($base === '') continue;
                $base = rtrim($base, '\\/');
                $patterns[] = $base . '\\Programs\\Python\\Python*\\python.exe';
                $patterns[] = $base . '\\Python\\pythoncore-*\\python.exe';
                $patterns[] = $base . '\\uv\\python\\*\\python.exe';
                $patterns[] = $base . '\\Python*\\python.exe';
            }
        } else {
            $patterns = ['/usr/bin/python3', '/usr/local/bin/python3', '/usr/bin/python'];
        }

        foreach ($patterns as $pattern) {
            if (strpbrk($pattern, '*?[') !== false) {
                foreach ((array)glob($pattern, GLOB_NOSORT) as $path) {
                    $candidates[] = $path;
                }
            } elseif (is_file($pattern)) {
                $candidates[] = $pattern;
            }
        }

        $seen = [];
        $result = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate, " \t\n\r\0\x0B\"");
            if ($candidate === '') continue;
            if ($isWindows) {
                if (stripos($candidate, '\\WindowsApps\\') !== false) continue;
                $candidate = str_replace('/', '\\', $candidate);
            }
            if (!is_file($candidate)) continue;
            $key = strtolower($candidate);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $result[] = $candidate;
        }
        return $result;
    }
}

if (!function_exists('documentCheckerCapabilities')) {
    /**
     * Run scripts/checker_probe.py and return its capability report, or null.
     *
     * @return array|null ['ok'=>bool,'python'=>..., 'cv2'=>..., 'numpy'=>..., 'ocr'=>...]
     */
    function documentCheckerCapabilities(string $python): ?array
    {
        $probe = realpath(dirname(__DIR__) . '/scripts/checker_probe.py');
        if (!$probe) return null;
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $pipes = [];
        $process = @proc_open([$python, $probe], $descriptors, $pipes);
        if (!is_resource($process)) return null;
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + 20;
        $timedOut = false;
        $exitCode = -1;
        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = (int)$status['exitcode'];
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process);
                $timedOut = true;
                break;
            }
            usleep(50000);
        }
        $stdout = (string)stream_get_contents($pipes[1]);
        @stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closedCode = proc_close($process);
        if ($exitCode < 0) $exitCode = $closedCode;
        if ($timedOut) return null;
        foreach (array_reverse(preg_split('/\R/', trim($stdout)) ?: []) as $line) {
            $decoded = json_decode(trim((string)$line), true);
            if (is_array($decoded) && array_key_exists('ok', $decoded)) {
                $decoded['exit_code'] = $exitCode;
                return $decoded;
            }
        }
        return null;
    }
}

if (!function_exists('documentCheckerPythonWorks')) {
    function documentCheckerPythonWorks(string $python): bool
    {
        $capabilities = documentCheckerCapabilities($python);
        return is_array($capabilities) && !empty($capabilities['ok']);
    }
}

if (!function_exists('documentCheckerCacheFile')) {
    /**
     * Path of the small cache that remembers which interpreter works.
     * Probing costs a full `import cv2` (~1s), so it must not run per request.
     */
    function documentCheckerCacheFile(): string
    {
        $dir = dirname(__DIR__) . '/storage/private/cache';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return '';
        }
        $guard = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\nDeny from all\n");
        }
        return $dir . DIRECTORY_SEPARATOR . 'python_interpreter.json';
    }
}

if (!function_exists('findDocumentCheckerPython')) {
    function findDocumentCheckerPython(): ?string
    {
        static $resolved = false;
        static $python = null;
        if ($resolved) return $python;
        $resolved = true;

        $cacheFile = documentCheckerCacheFile();
        if ($cacheFile !== '' && is_file($cacheFile)) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['checked_at'])) {
                $age  = time() - (int) $cached['checked_at'];
                $path = (string) ($cached['path'] ?? '');
                // A working interpreter is cached for a day; a miss only for 10 minutes
                // so a freshly installed Python is picked up quickly.
                if ($path !== '' && $age < 86400 && is_file($path)) {
                    $python = $path;
                    // Cache entries written by older builds have no capability
                    // report; probe once so the health panel is not blind.
                    if (!is_array($cached['capabilities'] ?? null)) {
                        $probe = documentCheckerCapabilities($path);
                        if (is_array($probe)) {
                            @file_put_contents($cacheFile, json_encode([
                                'path' => $path,
                                'checked_at' => time(),
                                'capabilities' => $probe,
                            ]), LOCK_EX);
                        }
                    }
                    return $python;
                }
                if ($path === '' && $age < 600) {
                    return null;
                }
            }
        }

        $capabilities = null;
        foreach (documentCheckerPythonCandidates() as $candidate) {
            $capabilities = documentCheckerCapabilities($candidate);
            if (is_array($capabilities) && !empty($capabilities['ok'])) {
                $python = $candidate;
                break;
            }
            $capabilities = null;
        }
        if ($python === null) {
            error_log('[DOCUMENT] No Python interpreter with OpenCV and NumPy was found; automatic document quality checking is unavailable.');
        } elseif (!empty($capabilities['ocr_available'])) {
            error_log('[DOCUMENT] Using Python interpreter: ' . $python . ' (OCR: ' . (string)$capabilities['ocr'] . ')');
        } else {
            error_log('[DOCUMENT] Using Python interpreter: ' . $python . ' (OCR unavailable: '
                . sanitizeForLog((string)($capabilities['ocr_detail'] ?? 'unknown')) . ')');
        }
        if ($cacheFile !== '') {
            @file_put_contents($cacheFile, json_encode([
                'path' => (string) ($python ?? ''),
                'checked_at' => time(),
                'capabilities' => $capabilities,
            ]), LOCK_EX);
        }
        return $python;
    }
}

if (!function_exists('documentCheckerCachedCapabilities')) {
    /**
     * Capability report for the cached interpreter (admin health panel).
     * Returns null when nothing has been probed yet.
     */
    function documentCheckerCachedCapabilities(): ?array
    {
        $cacheFile = documentCheckerCacheFile();
        if ($cacheFile === '' || !is_file($cacheFile)) return null;
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (!is_array($cached)) return null;
        $capabilities = $cached['capabilities'] ?? null;
        if (!is_array($capabilities)) return null;
        $capabilities['path'] = (string) ($cached['path'] ?? '');
        $capabilities['checked_at'] = (int) ($cached['checked_at'] ?? 0);
        return $capabilities;
    }
}

if (!function_exists('normalizeDocumentCheckerPath')) {
    function normalizeDocumentCheckerPath(string $path): string
    {
        return strtolower(str_replace('\\', '/', $path));
    }
}

if (!function_exists('documentCheckerSessionAllowed')) {
    /**
     * Warm checker process: one Python interpreter per PHP request.
     *
     * Starting Python, importing OpenCV and loading the OCR model costs 3-5 s, so
     * a submission with two document requirements used to pay that twice. The
     * warm process only changes *when* the interpreter starts, never what it
     * measures. Set LMS_DOCUMENT_SESSION=0 to fall back to one process per batch.
     */
    function documentCheckerSessionAllowed(): bool
    {
        return getenv('LMS_DOCUMENT_SESSION') !== '0';
    }
}

if (!function_exists('documentCheckerSessionStore')) {
    /**
     * Per-request state of the warm checker process.
     *
     * Call without an argument to read the state, or with an array to merge it
     * into the state. Keeping it in a static holder lets every caller in one
     * request share the process without turning the checker into a service.
     *
     * Windows cannot read a proc_open pipe without blocking (stream_set_blocking
     * fails on anonymous pipes), so the child writes its JSON lines to a file and
     * the parent polls it. 'offset'/'buffer' hold the read position between
     * batches so nothing is parsed twice.
     */
    function documentCheckerSessionStore(?array $update = null): array
    {
        static $state = [
            'process' => null,
            'pipes' => [],
            'output' => '',
            'error_log' => '',
            'offset' => 0,
            'buffer' => '',
            'python' => '',
            'script' => '',
            'requests' => 0,
        ];
        if ($update !== null) {
            $state = array_merge($state, $update);
        }
        return $state;
    }
}

if (!function_exists('documentCheckerSessionClose')) {
    /** Stop the warm checker process (also runs from the shutdown handler). */
    function documentCheckerSessionClose(): void
    {
        $state = documentCheckerSessionStore();
        $process = $state['process'] ?? null;
        if (is_resource($process)) {
            $pipes = is_array($state['pipes'] ?? null) ? $state['pipes'] : [];
            // Closing stdin ends the serve loop, so the process exits on its own.
            if (isset($pipes[0]) && is_resource($pipes[0])) @fclose($pipes[0]);
            @proc_terminate($process);
            @proc_close($process);
        }
        foreach (['output', 'error_log'] as $slot) {
            $path = (string) ($state[$slot] ?? '');
            if ($path !== '' && is_file($path)) @unlink($path);
        }
        documentCheckerSessionStore([
            'process' => null, 'pipes' => [], 'output' => '', 'error_log' => '',
            'offset' => 0, 'buffer' => '', 'requests' => 0,
        ]);
    }
}

if (!function_exists('documentCheckerSessionRead')) {
    /** New bytes written by the child since the last call (never blocks). */
    function documentCheckerSessionRead(array $state): string
    {
        $path = (string) ($state['output'] ?? '');
        if ($path === '') return '';
        clearstatcache(true, $path);
        $size = @filesize($path);
        if (!is_int($size) || $size <= (int) $state['offset']) return '';
        $handle = @fopen($path, 'rb');
        if (!$handle) return '';
        @fseek($handle, (int) $state['offset']);
        $chunk = @stream_get_contents($handle);
        @fclose($handle);
        return is_string($chunk) ? $chunk : '';
    }
}

if (!function_exists('documentCheckerSessionOpen')) {
    /** Start the warm checker process once per request. */
    function documentCheckerSessionOpen(string $python, string $script, array $config): bool
    {
        $state = documentCheckerSessionStore();
        if (is_resource($state['process'] ?? null)) {
            return $state['python'] === $python && $state['script'] === $script;
        }
        if (!documentCheckerSessionAllowed()) return false;

        $scratch = documentQualityCacheDir();
        if ($scratch === '') $scratch = sys_get_temp_dir();
        $token = bin2hex(random_bytes(8));
        $output = $scratch . DIRECTORY_SEPARATOR . 'session-' . $token . '.jsonl';
        $errorLog = $scratch . DIRECTORY_SEPARATOR . 'session-' . $token . '.log';
        @touch($output);

        $command = [$python, $script, '--serve', '--out=' . $output,
            '--ocr=' . (string) $config['ocr'],
            '--max-ocr-pages=' . (int) $config['max_ocr_pages'],
        ];
        // stdout/stderr go to a log file: an unread pipe would eventually block the
        // child once the OCR engine prints enough progress output.
        $pipes = [];
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['file', $errorLog, 'a'],
            2 => ['file', $errorLog, 'a'],
        ], $pipes);
        if (!is_resource($process)) {
            @unlink($output);
            @unlink($errorLog);
            return false;
        }

        documentCheckerSessionStore([
            'process' => $process,
            'pipes' => $pipes,
            'output' => $output,
            'error_log' => $errorLog,
            'offset' => 0,
            'buffer' => '',
            'python' => $python,
            'script' => $script,
            'requests' => 0,
        ]);
        register_shutdown_function('documentCheckerSessionClose');
        return true;
    }
}

if (!function_exists('documentCheckerSessionRun')) {
    /**
     * Hand one batch of documents to the warm process.
     *
     * @param array<int,array{path:string,expect?:string}> $jobs
     * @return array{state:string,payloads:array<string,array>} state is
     *         'ok' (payloads are usable), 'timeout' (the caller must not retry)
     *         or 'error' (the process died; a one-shot retry is cheap).
     */
    function documentCheckerSessionRun(array $jobs, int $timeout): array
    {
        $state = documentCheckerSessionStore();
        if (!is_resource($state['process'] ?? null) || !$jobs) {
            return ['state' => 'error', 'payloads' => []];
        }

        $request = json_encode(['files' => array_values($jobs)],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($request) || @fwrite($state['pipes'][0], $request . "\n") === false) {
            documentCheckerSessionClose();
            return ['state' => 'error', 'payloads' => []];
        }
        @fflush($state['pipes'][0]);

        $payloads = [];
        $buffer = (string) ($state['buffer'] ?? '');
        $offset = (int) ($state['offset'] ?? 0);
        $deadline = microtime(true) + max(15, $timeout);
        $timedOut = false;
        while (true) {
            $chunk = documentCheckerSessionRead(['output' => $state['output'], 'offset' => $offset]);
            if ($chunk !== '') {
                $buffer .= $chunk;
                $offset += strlen($chunk);
            }

            while (($position = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $position));
                $buffer = substr($buffer, $position + 1);
                if ($line === '') continue;
                $payload = json_decode($line, true);
                if (!is_array($payload)) continue;
                if (!empty($payload['batch_done'])) {
                    documentCheckerSessionStore([
                        'offset' => $offset,
                        'buffer' => $buffer,
                        'requests' => (int) $state['requests'] + 1,
                    ]);
                    return ['state' => 'ok', 'payloads' => $payloads];
                }
                $file = isset($payload['file']) ? normalizeDocumentCheckerPath((string) $payload['file']) : '';
                if ($file !== '' && !empty($payload['status'])) $payloads[$file] = $payload;
            }

            $status = @proc_get_status($state['process']);
            if (empty($status['running'])) break;
            if (microtime(true) >= $deadline) { $timedOut = true; break; }
            usleep(20000);
        }

        error_log('[DOCUMENT] Warm checker ' . ($timedOut ? 'timed out' : 'stopped unexpectedly') . '; restarting it for the next batch.');
        documentCheckerSessionClose();
        return ['state' => $timedOut ? 'timeout' : 'error', 'payloads' => $payloads];
    }
}

if (!function_exists('runDocumentQualityChecker')) {
    /**
     * Run the Python quality checker once for one or more documents.
     *
     * Starting Python and importing OpenCV costs about a second, so every file of
     * a single submission is handed to ONE process instead of one process per file.
     *
     * @param array<int|string,string> $realPaths Absolute paths inside the private document directory.
     * @param array<int|string,string> $expectations Optional document-type name per path
     *        (e.g. "Valid ID"); drives the OCR keyword expectations.
     * @return array<int|string,array> Results keyed like $realPaths.
     */
    function runDocumentQualityChecker(array $realPaths, array $expectations = []): array
    {
        if (!$realPaths) return [];

        $unavailable = static function (string $reason) use ($realPaths): array {
            $out = [];
            foreach ($realPaths as $key => $_) {
                $out[$key] = documentQualityResult('unavailable', $reason);
            }
            return $out;
        };

        $script = realpath(dirname(__DIR__) . '/scripts/check_blur.py');
        if (!$script) return $unavailable('The automated quality checker is not installed.');

        $python = findDocumentCheckerPython();
        if (!$python) return $unavailable('Python with OpenCV is not available for automatic document checking.');

        $config = documentQualityConfig();
        $parallel = max(1, min(4, (int) ($config['parallel_documents'] ?? 1)));

        // Warm process: the interpreter, OpenCV and the OCR model are paid once per
        // PHP request, so a submission with several document requirements (or a CLI
        // reprocess run over many files) no longer restarts Python per requirement.
        if (documentCheckerSessionAllowed() && documentCheckerSessionOpen($python, $script, $config)) {
            $jobs = [];
            foreach ($realPaths as $key => $path) {
                $job = ['path' => (string) $path];
                $documentType = trim((string) ($expectations[$key] ?? ''));
                if ($documentType !== '') $job['expect'] = $documentType;
                $jobs[] = $job;
            }
            $budget = min((int) $config['timeout_max'],
                (int) $config['timeout_base'] + (int) $config['timeout_per_extra_document'] * max(0, count($realPaths) - 1));
            $outcome = documentCheckerSessionRun($jobs, $budget);
            if ($outcome['state'] === 'ok') {
                $results = [];
                foreach ($realPaths as $key => $path) {
                    $payload = $outcome['payloads'][normalizeDocumentCheckerPath((string) $path)] ?? null;
                    if ($payload === null) {
                        $results[$key] = documentQualityResult('unavailable',
                            'The automatic checker returned an invalid response.');
                        continue;
                    }
                    $results[$key] = documentQualityResult((string) $payload['status'],
                        (string) ($payload['reason'] ?? ''), $payload);
                }
                return $results;
            }
            if ($outcome['state'] === 'timeout') {
                return $unavailable('The automatic quality check timed out.');
            }
            // The warm process died before answering: retry this batch one-shot.
        }

        // One document, or parallelism disabled: keep the single-process batch
        // (cheapest for small submissions because the interpreter is shared).
        if (count($realPaths) <= 1 || $parallel <= 1) {
            return documentCheckerRunSingle($python, $script, $realPaths, $expectations, $config, $unavailable);
        }

        return documentCheckerRunParallel($python, $script, $realPaths, $expectations, $config, $parallel);
    }
}

if (!function_exists('documentCheckerArguments')) {
    /**
     * Per-file CLI arguments for one checker run.
     *
     * @param array<int|string,string> $expectations
     * @return string[]
     */
    function documentCheckerArguments(array $config, string $path, string $documentType = ''): array
    {
        $arguments = [
            '--ocr=' . (string) $config['ocr'],
            '--max-ocr-pages=' . (int) $config['max_ocr_pages'],
        ];
        if (trim($documentType) !== '') {
            $arguments[] = '--expect';
            $arguments[] = trim($documentType);
        }
        $arguments[] = $path;
        return $arguments;
    }
}

if (!function_exists('documentCheckerParsePayloads')) {
    /**
     * Parse the JSON lines printed by check_blur.py, keyed by normalised path.
     *
     * @return array<string,array>
     */
    function documentCheckerParsePayloads(string $stdout): array
    {
        $byPath = [];
        foreach (array_filter(preg_split('/\R/', trim($stdout)) ?: []) as $line) {
            $payload = json_decode($line, true);
            if (!is_array($payload) || empty($payload['status'])) continue;
            $file = isset($payload['file']) ? normalizeDocumentCheckerPath((string) $payload['file']) : '';
            if ($file !== '') $byPath[$file] = $payload;
        }
        return $byPath;
    }
}

if (!function_exists('documentCheckerRunSingle')) {
    /**
     * Batch every document through ONE Python process.
     *
     * @param array<int|string,string> $realPaths
     * @param array<int|string,string> $expectations
     * @param callable(string):array $unavailable
     * @return array<int|string,array>
     */
    function documentCheckerRunSingle(string $python, string $script, array $realPaths,
                                       array $expectations, array $config, callable $unavailable): array
    {
        $command = [$python, $script,
            '--ocr=' . (string) $config['ocr'],
            '--max-ocr-pages=' . (int) $config['max_ocr_pages'],
        ];
        foreach ($realPaths as $key => $path) {
            $documentType = trim((string) ($expectations[$key] ?? ''));
            if ($documentType !== '') {
                $command[] = '--expect';
                $command[] = $documentType;
            }
            $command[] = $path;
        }

        $pipes = [];
        $process = @proc_open($command, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (!is_resource($process)) return $unavailable('The automatic checker could not be started.');
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $budget = min((int) $config['timeout_max'],
            (int) $config['timeout_base'] + (int) $config['timeout_per_extra_document'] * max(0, count($realPaths) - 1));
        $deadline = microtime(true) + max(15, $budget);
        $stdout = '';
        $stderr = '';
        $timedOut = false;
        do {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            $state = proc_get_status($process);
            if (!$state['running']) break;
            if (microtime(true) >= $deadline) { proc_terminate($process); $timedOut = true; break; }
            usleep(50000);
        } while (true);
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $byPath = documentCheckerParsePayloads($stdout);
        if (!$byPath) {
            error_log('[DOCUMENT] Invalid checker response: ' . sanitizeForLog($stdout . ' ' . $stderr));
        }

        $singleMode = count($realPaths) === 1;
        $results = [];
        foreach ($realPaths as $key => $realPath) {
            $payload = $byPath[normalizeDocumentCheckerPath($realPath)] ?? null;
            // Older checker builds do not echo the file name; a single-file run can
            // still fall back to the only payload that was printed.
            if ($payload === null && $singleMode && count($byPath) === 1) {
                $payload = reset($byPath);
            }
            if ($payload === null) {
                $results[$key] = documentQualityResult(
                    'unavailable',
                    $timedOut ? 'The automatic quality check timed out.' : 'The automatic checker returned an invalid response.'
                );
                continue;
            }
            $results[$key] = documentQualityResult((string) $payload['status'], (string) ($payload['reason'] ?? ''), $payload);
        }
        return $results;
    }
}

if (!function_exists('documentCheckerRunParallel')) {
    /**
     * Run documents in parallel waves (bounded concurrency).
     *
     * OCR is CPU bound, so two or three processes cut multi-document submissions
     * roughly in half. Each process re-imports OpenCV and the OCR model (~1 s),
     * which is why waves are used for batches but never for a single file.
     *
     * @param array<int|string,string> $realPaths
     * @param array<int|string,string> $expectations
     * @return array<int|string,array>
     */
    function documentCheckerRunParallel(string $python, string $script, array $realPaths,
                                        array $expectations, array $config, int $parallel): array
    {
        $results = [];
        $queue = array_keys($realPaths);
        $running = [];
        $perProcessBudget = max(20, (int) $config['timeout_base']);
        $failure = 'The automatic checker returned an invalid response.';

        while ($queue || $running) {
            while ($queue && count($running) < $parallel) {
                $key = array_shift($queue);
                $arguments = documentCheckerArguments($config, (string) $realPaths[$key],
                    (string) ($expectations[$key] ?? ''));
                $pipes = [];
                $process = @proc_open(array_merge([$python, $script], $arguments),
                    [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
                if (!is_resource($process)) {
                    $results[$key] = documentQualityResult('unavailable', 'The automatic checker could not be started.');
                    continue;
                }
                fclose($pipes[0]);
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                $running[$key] = [
                    'process' => $process, 'pipes' => $pipes, 'stdout' => '', 'stderr' => '',
                    'deadline' => microtime(true) + $perProcessBudget,
                ];
            }
            if (!$running) break;
            usleep(25000);

            foreach ($running as $key => $job) {
                $running[$key]['stdout'] .= (string) stream_get_contents($job['pipes'][1]);
                $running[$key]['stderr'] .= (string) stream_get_contents($job['pipes'][2]);
                $state = proc_get_status($job['process']);
                $timedOut = false;
                if ($state['running']) {
                    if (microtime(true) < $job['deadline']) continue;
                    proc_terminate($job['process']);
                    $timedOut = true;
                }
                $running[$key]['stdout'] .= (string) stream_get_contents($job['pipes'][1]);
                @fclose($job['pipes'][1]);
                @fclose($job['pipes'][2]);
                proc_close($job['process']);
                $stdout = $running[$key]['stdout'];
                $stderr = $running[$key]['stderr'];
                unset($running[$key]);

                $payloads = documentCheckerParsePayloads($stdout);
                $payload = null;
                foreach ($payloads as $candidatePath => $candidate) {
                    if ($candidatePath === normalizeDocumentCheckerPath((string) $realPaths[$key])) {
                        $payload = $candidate;
                        break;
                    }
                }
                if ($payload === null && count($payloads) === 1) {
                    $payload = reset($payloads);
                }
                if ($payload === null) {
                    error_log('[DOCUMENT] Invalid checker response: ' . sanitizeForLog($stdout . ' ' . $stderr));
                    $results[$key] = documentQualityResult('unavailable',
                        $timedOut ? 'The automatic quality check timed out.' : $failure);
                    continue;
                }
                $results[$key] = documentQualityResult((string) $payload['status'],
                    (string) ($payload['reason'] ?? ''), $payload);
            }
        }
        return $results;
    }
}

if (!function_exists('documentQualityCacheDir')) {
    /** Directory for cached verdicts (inside the existing private cache tree). */
    function documentQualityCacheDir(): string
    {
        $probeFile = documentCheckerCacheFile();
        $base = $probeFile !== '' ? dirname($probeFile) : (dirname(__DIR__) . '/storage/private/cache');
        $dir = $base . DIRECTORY_SEPARATOR . 'quality';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return '';
        }
        $guard = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\nDeny from all\n");
        }
        return $dir;
    }
}

if (!function_exists('documentQualityCacheKey')) {
    /**
     * Cache key for a byte-identical upload.
     *
     * Includes the document type, the OCR mode and the thresholds version, so a
     * recalibration or a different requirement never reuses a stale verdict.
     */
    function documentQualityCacheKey(string $realPath, string $documentType, array $config): ?string
    {
        $hash = @hash_file('sha256', $realPath);
        if (!is_string($hash) || $hash === '') return null;
        return hash('sha256', implode('|', [
            $hash,
            strtolower(trim($documentType)),
            (string) $config['ocr'],
            (int) $config['max_ocr_pages'],
            (string) (qualityThresholdsVersion() ?? ''),
        ]));
    }
}

if (!function_exists('qualityThresholdsVersion')) {
    /** Thresholds version of the checker, read once from the Python module. */
    function qualityThresholdsVersion(): ?string
    {
        static $version = false;
        if ($version !== false) return $version;
        $version = null;
        $file = dirname(__DIR__) . '/scripts/quality/thresholds.py';
        if (is_file($file)) {
            $contents = (string) @file_get_contents($file, false, null, 0, 2048);
            if (preg_match('/THRESHOLDS_VERSION\s*=\s*["\']([^"\']+)/', $contents, $matches)) {
                $version = $matches[1];
            }
        }
        return $version;
    }
}

if (!function_exists('documentQualityCacheRead')) {
    /** @return array|null Cached checker payload. */
    function documentQualityCacheRead(?string $key, array $config): ?array
    {
        if ($key === null || empty($config['cache_verdicts'])) return null;
        $dir = documentQualityCacheDir();
        if ($dir === '') return null;
        $file = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        if (!is_file($file)) return null;
        $ttl = max(1, (int) $config['cache_ttl_days']) * 86400;
        if (time() - (int) @filemtime($file) > $ttl) {
            @unlink($file);
            return null;
        }
        $payload = json_decode((string) @file_get_contents($file), true);
        return is_array($payload) && !empty($payload['status']) ? $payload : null;
    }
}

if (!function_exists('documentQualityCacheWrite')) {
    /** Store a verdict; never cache 'unavailable' (that must be retried). */
    function documentQualityCacheWrite(?string $key, ?array $payload, array $config): void
    {
        if ($key === null || !is_array($payload) || empty($config['cache_verdicts'])) return;
        if (($payload['status'] ?? '') === 'unavailable') return;
        $dir = documentQualityCacheDir();
        if ($dir === '') return;
        @file_put_contents($dir . DIRECTORY_SEPARATOR . $key . '.json',
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        // Opportunistic cleanup keeps the directory bounded without a cron job.
        if (random_int(1, 50) === 1) {
            $ttl = max(1, (int) $config['cache_ttl_days']) * 86400;
            foreach ((array) glob($dir . DIRECTORY_SEPARATOR . '*.json') as $file) {
                if (time() - (int) @filemtime($file) > $ttl) @unlink($file);
            }
        }
    }
}

function checkDocumentQuality(string $filePath, string $documentType = ''): array
{
    $realPath = realpath($filePath);
    $privateDir = realpath(documentStorageDirectory());
    if (!$realPath || !$privateDir || !str_starts_with(strtolower($realPath), strtolower($privateDir . DIRECTORY_SEPARATOR))) {
        return documentQualityResult('invalid', 'The stored document path is invalid.');
    }
    // Goes through the batch helper so byte-identical uploads hit the verdict cache.
    $results = checkDocumentQualityBatch([0 => $realPath], [0 => $documentType]);
    return $results[0] ?? documentQualityResult('unavailable', 'The automatic checker returned an invalid response.');
}

if (!function_exists('checkDocumentQualityBatch')) {
    /**
     * Check several documents with a single Python run.
     *
     * @param array<int|string,string> $filePaths Paths of already-stored uploads.
     * @param array<int|string,string> $documentTypes Optional document-type name per path.
     * @return array<int|string,array> Quality results keyed like $filePaths.
     */
    function checkDocumentQualityBatch(array $filePaths, array $documentTypes = []): array
    {
        $results = [];
        $paths = [];
        $expectations = [];
        $cacheKeys = [];
        $seenKeys = [];
        $duplicates = [];
        $config = documentQualityConfig();
        $privateDir = realpath(documentStorageDirectory());
        foreach ($filePaths as $key => $filePath) {
            $realPath = realpath((string) $filePath);
            if (!$realPath || !$privateDir || !str_starts_with(strtolower($realPath), strtolower($privateDir . DIRECTORY_SEPARATOR))) {
                $results[$key] = documentQualityResult('invalid', 'The stored document path is invalid.');
                continue;
            }
            $documentType = (string) ($documentTypes[$key] ?? '');
            $cacheKey = documentQualityCacheKey($realPath, $documentType, $config);
            $cached = documentQualityCacheRead($cacheKey, $config);
            if ($cached !== null) {
                // Byte-identical resubmission: reuse the verdict instead of re-running OCR.
                $cached['cache'] = 'hit';
                $results[$key] = documentQualityResult((string) $cached['status'],
                    (string) ($cached['reason'] ?? ''), $cached);
                continue;
            }
            // The same photo attached twice (e.g. one file for two requirements) has
            // the same bytes, type and thresholds, so one analysis serves both.
            if ($cacheKey !== null && isset($seenKeys[$cacheKey])) {
                $duplicates[$key] = ['source' => $seenKeys[$cacheKey], 'path' => $realPath];
                continue;
            }
            if ($cacheKey !== null) $seenKeys[$cacheKey] = $key;
            $paths[$key] = $realPath;
            $expectations[$key] = $documentType;
            $cacheKeys[$key] = $cacheKey;
        }
        if ($paths) {
            foreach (runDocumentQualityChecker($paths, $expectations) as $key => $quality) {
                $results[$key] = $quality;
                if (in_array((string) $quality['status'], ['clear', 'flagged', 'blurry', 'unreadable', 'invalid'], true)) {
                    documentQualityCacheWrite($cacheKeys[$key] ?? null,
                        is_array($quality['details']) ? $quality['details'] : null, $config);
                }
            }
        }
        foreach ($duplicates as $key => $duplicate) {
            $source = $results[$duplicate['source']] ?? null;
            if (!is_array($source)) {
                $results[$key] = documentQualityResult('unavailable', 'The automatic checker returned an invalid response.');
                continue;
            }
            $details = is_array($source['details'] ?? null) ? $source['details'] : [];
            if (isset($details['file'])) $details['file'] = $duplicate['path'];
            $results[$key] = documentQualityResult((string) $source['status'],
                (string) ($source['reason'] ?? ''), $details);
        }
        return $results;
    }
}

if (!function_exists('documentQualityMetricsPayload')) {
    /**
     * Serialise one checker verdict into the JSON stored in
     * tbl_submitted_document.quality_metrics.
     *
     * Shared by the registration flow and scripts/reprocess_quality_metrics.php so
     * both write the same schema. Only a short OCR excerpt is kept: these files are
     * IDs, birth certificates and medical records.
     */
    function documentQualityMetricsPayload(array $quality): string
    {
        $pages = $quality['details']['pages'] ?? [];
        return (string) json_encode([
            'status' => (string) ($quality['status'] ?? 'unavailable'),
            'thresholds_version' => (string) ($quality['details']['thresholds_version'] ?? ''),
            'ocr_engine' => (string) ($quality['details']['ocr']['engine'] ?? ''),
            'ocr_available' => (bool) ($quality['details']['ocr']['available'] ?? false),
            'ocr_pages' => (int) ($quality['details']['ocr_pages'] ?? 0),
            'timings' => [
                'total' => (float) ($quality['details']['timings']['total'] ?? 0),
                'ocr' => (float) ($quality['details']['timings']['ocr'] ?? 0),
            ],
            'pages_checked' => (int) ($quality['details']['pages_checked'] ?? 0),
            'pages' => array_map(static function ($p) {
                $ocr = is_array($p['ocr'] ?? null) ? $p['ocr'] : [];
                return [
                    'page' => (int) ($p['page'] ?? 0),
                    'width' => (int) ($p['width'] ?? 0),
                    'height' => (int) ($p['height'] ?? 0),
                    'frame_width' => (int) ($p['frame_width'] ?? 0),
                    'frame_height' => (int) ($p['frame_height'] ?? 0),
                    'page_found' => (bool) ($p['page_found'] ?? false),
                    'page_bbox' => array_map('intval', is_array($p['page_bbox'] ?? null) ? $p['page_bbox'] : []),
                    'page_area_ratio' => (float) ($p['page_area_ratio'] ?? 0),
                    'laplacian_std' => (float) ($p['laplacian_std'] ?? 0),
                    'local_variance' => (float) ($p['local_variance'] ?? 0),
                    'detail_ratio' => (float) ($p['detail_ratio'] ?? 0),
                    'entropy' => (float) ($p['entropy'] ?? 0),
                    'rel_sharpness' => (float) ($p['rel_sharpness'] ?? 0),
                    'rel_sharp_p10' => (float) ($p['rel_sharp_p10'] ?? 0),
                    'motion_score' => (float) ($p['motion_score'] ?? 0),
                    'contrast' => (float) ($p['contrast'] ?? 0),
                    'ink_ratio' => (float) ($p['ink_ratio'] ?? 0),
                    'text_height' => isset($p['text_height']) ? (float) $p['text_height'] : null,
                    'ocr_chars' => (int) ($ocr['chars'] ?? 0),
                    'ocr_confidence' => (float) ($ocr['mean_confidence'] ?? 0),
                    'ocr_matched_keywords' => array_values((array) ($ocr['matched_keywords'] ?? [])),
                    'ocr_missing_keywords' => array_values((array) ($ocr['missing_keywords'] ?? [])),
                    'ocr_excerpt' => mb_substr((string) ($ocr['excerpt'] ?? ''), 0, 500),
                    'page_status' => $p['status'] ?? 'unknown',
                    'page_reason' => mb_substr((string) ($p['reason'] ?? ''), 0, 300),
                ];
            }, is_array($pages) ? $pages : []),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

function checkDocumentBlur($filePath): bool
{
    // Fail closed: a document that could not be checked is not "not blurry".
    $status = checkDocumentQuality((string)$filePath)['status'];
    return in_array($status, ['clear', 'flagged'], true);
}

function validatePlayerDocuments($filesArray, $playerNamesArray, array $documentTypes = []): array
{
    $result = ['blurry'=>[], 'valid'=>[], 'quality'=>[], 'quality_details'=>[], 'quality_metrics'=>[],
               'issues'=>[], 'unavailable'=>[]];
    $names = array_values((array)$playerNamesArray);
    $allowed = [
        'pdf'=>'application/pdf', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'png'=>'image/png',
        'bmp'=>'image/bmp', 'webp'=>'image/webp'
    ];
    $directory = documentStorageDirectory();
    $labels = [];
    $pending = [];

    // Pass 1 — cheap upload checks, then move each accepted file into protected storage.
    foreach ((array)($filesArray['name'] ?? []) as $key => $originalName) {
        if (trim((string)$originalName) === '') continue;
        $label = $names[$key] ?? 'Document ' . ($key + 1);
        $labels[$key] = $label;
        $error = (int)($filesArray['error'][$key] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string)($filesArray['tmp_name'][$key] ?? '');
        $size = (int)($filesArray['size'][$key] ?? 0);
        $reject = function(string $code, string $message) use (&$result, $key, $label) {
            $result['blurry'][] = $label;
            $result['issues'][$key] = ['code'=>$code, 'message'=>$message];
        };
        if ($error !== UPLOAD_ERR_OK) { $reject('upload_error', 'The upload did not complete successfully.'); continue; }
        if (!$tmp || !is_uploaded_file($tmp)) { $reject('invalid_upload', 'The uploaded file is invalid.'); continue; }
        if ($size <= 0 || $size > 10 * 1024 * 1024) { $reject('file_size', 'Documents must be between 1 byte and 10 MB.'); continue; }
        $extension = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset($allowed[$extension]) || $allowed[$extension] !== $mime) {
            $reject('file_type', 'Use a valid PDF, JPG, PNG, BMP, or WebP document.'); continue;
        }
        if ($mime !== 'application/pdf') {
            if (function_exists('getimagesize')) {
                $dimensions = @getimagesize($tmp);
                if (!$dimensions) { $reject('corrupt', 'The image is corrupt or unreadable.'); continue; }
                if ($dimensions[0] > 12000 || $dimensions[1] > 12000 || $dimensions[0] * $dimensions[1] > 40000000) {
                    $reject('dimensions', 'The image dimensions are too large to process safely.'); continue;
                }
            }
        }
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $path = $directory . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($tmp, $path)) { $reject('storage', 'The document could not be stored.'); continue; }
        @chmod($path, 0640);
        $pending[$key] = ['path' => $path, 'stored' => $storedName];
    }

    // Pass 2 — ONE Python/OpenCV run for every file of this submission. Starting the
    // interpreter once per document was the main reason resubmissions felt slow.
    // The document-type name is handed over so OCR can verify the expected content.
    $qualities = [];
    if ($pending) {
        $paths = [];
        $types = [];
        foreach ($pending as $key => $file) {
            $paths[$key] = $file['path'];
            $types[$key] = (string) ($documentTypes[$key] ?? '');
        }
        $qualities = checkDocumentQualityBatch($paths, $types);
    }
    $ocrAvailable = null;

    // Pass 3 — drop unacceptable files, then upload only the readable ones.
    foreach ($pending as $key => $file) {
        $label = $labels[$key] ?? ('Document ' . ($key + 1));
        $reject = function(string $code, string $message) use (&$result, $key, $label) {
            $result['blurry'][] = $label;
            $result['issues'][$key] = ['code'=>$code, 'message'=>$message];
        };
        $quality = $qualities[$key] ?? documentQualityResult('unavailable', 'The automatic checker returned an invalid response.');
        $status = (string) $quality['status'];
        if ($ocrAvailable === null && isset($quality['details']['ocr']['available'])) {
            $ocrAvailable = (bool) $quality['details']['ocr']['available'];
            if (!$ocrAvailable) {
                error_log('[DOCUMENT] Legibility check ran without OCR; documents are accepted on sharpness/readability only.');
            }
        }
        if (documentQualityBlocks($status)) {
            @unlink($file['path']); $reject($status, $quality['reason']); continue;
        }
        $storedName = $file['stored'];
        // Upload to Cloudinary if enabled, otherwise keep local file
        if (StorageService::isEnabled()) {
            try {
                $cloudDoc = StorageService::upload($file['path'], 'player_documents', [
                    'public_id' => pathinfo($storedName, PATHINFO_FILENAME),
                ]);
                @unlink($file['path']); // remove local copy after successful cloud upload
                $storedName = $cloudDoc['secure_url'];
            } catch (\Throwable $e) {
                // Never block a registration because the cloud is unreachable.
                // The validated local copy stays available for the organizer to
                // review while this instance lives, and the failure is logged so
                // the deployment problem is not silent.
                error_log('[DOCUMENT] Cloudinary upload failed; keeping the local copy for manual review: '
                    . sanitizeForLog($e->getMessage()));
            }
        }
        if ($status === 'unavailable') {
            // Never let a broken checker look like a passed check: the organizer
            // still gets the document, but with an explicit "not checked" flag.
            $result['unavailable'][$key] = $quality['reason'];
            error_log('[DOCUMENT] Quality check unavailable for ' . $label . ': ' . sanitizeForLog($quality['reason']));
        }
        $dbStatus = $status === 'clear' ? 'Clear' : 'Flagged';
        $result['valid'][$key] = $storedName;
        $result['quality'][$key] = $dbStatus;
        $result['quality_details'][$key] = mb_substr(($status === 'unavailable' ? 'Automatic check unavailable: ' : '') . $quality['reason'], 0, 500);
        // Persist raw quality metrics for auditing and threshold recalibration
        $result['quality_metrics'][$key] = documentQualityMetricsPayload($quality);
    }
    return $result;
}
