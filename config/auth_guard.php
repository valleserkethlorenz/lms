<?php
/**
 * Auth Guard Helper
 * Usage: require_once __DIR__ . '/path/to/auth_guard.php';
 * Then call: requireRole([3]) for Admin-only, requireRole([1]) for Organizer, etc.
 *
 * Roles: 1=Organizer, 2=Team Representative, 3=Admin, 4=Committee
 */
// Integrate secure session handler if available
if (file_exists(__DIR__ . '/sessionHandler.php')) {
    require_once __DIR__ . '/sessionHandler.php';
}

// Fix session name mismatch: if a session was started with the default name
// (PHPSESSID) before auth_guard was included, close it and restart with
// the correct session name (LMS_SESSION) so session data is read properly.
$expectedSessionName = 'LMS_SESSION';
if (session_status() === PHP_SESSION_ACTIVE && session_name() !== $expectedSessionName) {
    session_write_close();
    session_name($expectedSessionName);
}

if (function_exists('initializeSecureSession')) {
    initializeSecureSession();
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($expectedSessionName);
    session_start();
}

// Include security logger if available
if (file_exists(__DIR__ . '/securityLogger.php')) {
    require_once __DIR__ . '/securityLogger.php';
}

/**
 * Compute the project base URL path (e.g., /lms-20260528T041457Z-3-001/lms)
 * Uses DOCUMENT_ROOT to derive the web path from the filesystem path.
 */
function getProjectBasePath(): string {
    $projectRoot = realpath(__DIR__ . '/..');
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $scriptDir = str_replace('\\', '/', $projectRoot);
    return rtrim(substr($scriptDir, strlen($docRoot)), '/') ?: '';
}

function requireRole(array $allowedRoles, ?string $redirectTo = null): void {
    $role = $_SESSION['lms_role'] ?? null;
    if (!$role || !in_array((int)$role, $allowedRoles)) {
        if (class_exists('SecurityLogger')) {
            SecurityLogger::unauthorizedAccess("Unauthorized role access attempt (role: " . ($role ?? 'none') . ", required: " . implode(',', $allowedRoles) . ")");
        }
        if ($redirectTo === null) {
            $redirectTo = getProjectBasePath() . '/index.php';
        }
        header('Location: ' . $redirectTo);
        exit;
    }
}

function requireJsonRole(array $allowedRoles): void {
    $role = (int)($_SESSION['lms_role'] ?? 0);
    if (!$role || !in_array($role, $allowedRoles, true)) {
        if (class_exists('SecurityLogger')) {
            SecurityLogger::unauthorizedAccess("Unauthorized JSON role access (role: $role, required: " . implode(',', $allowedRoles) . ")");
        }
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 403, 'msg' => 'Unauthorized.']);
        exit;
    }
}

/**
 * JSON guard: ensure the authenticated Organizer owns the given league.
 * Emits a 403 JSON response and exits when the league is missing, archived
 * (unless explicitly allowed) or belongs to another organizer.
 */
function requireJsonLeagueOwnership(mysqli $conn, int $leagueId, bool $includeArchived = false): void {
    if ($leagueId > 0 && organizerOwnsLeague($conn, $leagueId, null, $includeArchived)) {
        return;
    }
    if (class_exists('SecurityLogger')) {
        SecurityLogger::unauthorizedAccess("Organizer attempted to access league ID $leagueId without ownership");
    }
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 403, 'msg' => 'You may manage only your own leagues.']);
    exit;
}

function requireActiveAccount(mysqli $conn, bool $json = false): void {
    $userId = currentUserId();
    $stmt = $conn->prepare("SELECT role, stat FROM tbl_user WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$account || (string)$account['stat'] !== '1' || (int)$account['role'] !== currentRole()) {
        $_SESSION = [];
        session_destroy();
        if ($json) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 401, 'msg' => 'Account is inactive or the session is no longer valid.']);
        } else {
            header('Location: ' . getProjectBasePath() . '/login.php');
        }
        exit;
    }
}

function organizerOwnsLeague(mysqli $conn, int $leagueId, ?int $organizerId = null, bool $includeArchived = false): bool {
    $organizerId ??= currentUserId();
    $archiveSql = $includeArchived ? '' : ' AND (is_archived=0 OR is_archived IS NULL)';
    $stmt = $conn->prepare("SELECT 1 FROM tbl_league WHERE id=? AND created_by=?" . $archiveSql);
    $stmt->bind_param('ii', $leagueId, $organizerId);
    $stmt->execute();
    $owns = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $owns;
}

function organizerOwnsTeam(mysqli $conn, int $teamId, ?int $organizerId = null): bool {
    $organizerId ??= currentUserId();
    $stmt = $conn->prepare("SELECT 1 FROM tbl_team t JOIN tbl_league l ON l.id=t.league_id WHERE t.id=? AND l.created_by=?");
    $stmt->bind_param('ii', $teamId, $organizerId);
    $stmt->execute();
    $owns = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $owns;
}

function organizerOwnsSchedule(mysqli $conn, int $scheduleId, ?int $organizerId = null): bool {
    $organizerId ??= currentUserId();
    $stmt = $conn->prepare("SELECT 1 FROM tbl_schedule s JOIN tbl_league l ON l.id=s.league_id WHERE s.id=? AND l.created_by=?");
    $stmt->bind_param('ii', $scheduleId, $organizerId);
    $stmt->execute();
    $owns = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $owns;
}

function currentUserId(): int {
    return (int)($_SESSION['lms_id'] ?? 0);
}

function currentUserName(): string {
    return $_SESSION['lms_username'] ?? '';
}

function currentRole(): int {
    return (int)($_SESSION['lms_role'] ?? 0);
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token.
 */
function validateCsrfToken(string $submitted): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($expected) || $expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
        if (class_exists('SecurityLogger')) {
            SecurityLogger::csrfViolation('Invalid CSRF token submitted');
        }
        return false;
    }
    return true;
}

function requireJsonCsrf(): void {
    $submitted = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!validateCsrfToken($submitted)) {
        http_response_code(419);
        header('Content-Type: application/json');
        echo json_encode(['status'=>419, 'msg'=>'Your form session expired. Refresh the page and try again.']);
        exit;
    }
}

function roleName(int $role): string {
    return match($role) {
        1 => 'Organizer',
        2 => 'Team Representative',
        3 => 'Admin',
        4 => 'Committee',
        default => 'Unknown'
    };
}

/**
 * Write an audit log entry.
 */
function auditLog(mysqli $conn, string $action, string $details): void {
    $userId   = currentUserId();
    $userName = currentUserName();
    $userRole = roleName(currentRole());
    $stmt = $conn->prepare(
        "INSERT INTO tbl_audit_log (user_name, user_id, user_role, action, details) VALUES (?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        $stmt->bind_param('sisss', $userName, $userId, $userRole, $action, $details);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Validate password complexity - requires 8+ chars with uppercase, lowercase, number, and special char
 */
function validatePasswordComplexity(string $password): array {
    if (strlen($password) < 8) return ['valid' => false, 'msg' => 'Password must be at least 8 characters long'];
    if (!preg_match('/[A-Z]/', $password)) return ['valid' => false, 'msg' => 'Password must contain at least one uppercase letter'];
    if (!preg_match('/[a-z]/', $password)) return ['valid' => false, 'msg' => 'Password must contain at least one lowercase letter'];
    if (!preg_match('/[0-9]/', $password)) return ['valid' => false, 'msg' => 'Password must contain at least one number'];
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return ['valid' => false, 'msg' => 'Password must contain at least one special character'];
    return ['valid' => true];
}
