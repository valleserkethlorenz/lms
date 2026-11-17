<?php
require_once __DIR__.'/../config/dbconnect.php';
require_once __DIR__.'/schedulingHelper.php';
require_once __DIR__.'/stageHelper.php';
header('Content-Type: application/json');
$leagueId=(int)($_GET['league_id'] ?? $_POST['league_id'] ?? 0);
$status=trim($_GET['status'] ?? $_POST['status'] ?? '');
// Bracket views need the one-team BYE rows so every slot is visible. They are
// only returned on request; the public fixture list keeps hiding them.
$includeByes=filter_var($_GET['include_byes'] ?? $_POST['include_byes'] ?? false, FILTER_VALIDATE_BOOLEAN);
$includeBracket=filter_var($_GET['include_bracket'] ?? $_POST['include_bracket'] ?? false, FILTER_VALIDATE_BOOLEAN);
$stmt=$conn->prepare("SELECT 1 FROM tbl_league WHERE id=? AND publication_status='Published' AND (is_archived=0 OR is_archived IS NULL)");
$stmt->bind_param('i',$leagueId); $stmt->execute(); $available=(bool)$stmt->get_result()->fetch_row(); $stmt->close();
if(!$available){ http_response_code(404); echo json_encode(['status'=>404,'msg'=>'League not found.']); exit; }
$payload=['status'=>200,'schedule'=>getLeagueSchedule($conn,$leagueId,$status ?: null,true,$includeByes)];
if($includeBracket){
    // The bracket is the full draw (drafts included, unpublished details hidden)
    // while the fixture list above stays published-only.
    $payload['bracket']=getLeagueBracketSchedule($conn,$leagueId);
    // The stage catalog comes from the structure skeleton, not from the draw:
    // a preset's knockout stage must be visible before its fixtures exist.
    $payload['bracket_stages']=getLeagueBracketStageCatalog($conn,$leagueId);
}
echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);


