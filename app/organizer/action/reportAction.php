<?php
session_start();
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/reportQuery.php';
require_once __DIR__ . '/../../../config/apiSecurity.php';
requireJsonRole([1]);
requireActiveAccount($conn, true);
enforceRequestSizeLimit(1 * 1024 * 1024); // 1MB
addApiSecurityHeaders();
header('Content-Type: application/json');

$userId=currentUserId();
$type=trim($_GET['report_type']??'registrations');
$leagueId=(int)($_GET['league_id']??0);
$sport=trim($_GET['sport']??'');
$division=trim($_GET['division']??'');
$category=trim($_GET['category']??'');
$leagueStatus=trim($_GET['league_status']??'');
$from=trim($_GET['from_date']??'');
$to=trim($_GET['to_date']??'');

try {
    $report = buildReportQuery($conn, $userId, $type, $leagueId, $sport, $division, $category, $leagueStatus, $from, $to);
} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'msg' => $e->getMessage()]);
    exit;
} catch (\RuntimeException $e) {
    http_response_code(403);
    echo json_encode(['status' => 403, 'msg' => $e->getMessage()]);
    exit;
}

auditLog($conn,'Generate League Report','Generated '.$report['title'].($leagueId?" for league ID $leagueId":' for all owned leagues'));
echo json_encode(['status'=>200,'title'=>$report['title'],'filters'=>$report['filters'],'generated_at'=>$report['generated_at'],'rows'=>$report['rows']]);
