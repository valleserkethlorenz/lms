<?php
require_once __DIR__.'/../../config/dbconnect.php';
require_once __DIR__.'/../../config/auth_guard.php';
require_once __DIR__.'/../../includes/storageService.php';
require_once __DIR__.'/../../includes/stageHelper.php';
require_once __DIR__.'/../../includes/leagueStructure.php';
require_once __DIR__.'/../../includes/setupWizard.php';
require_once __DIR__.'/../../includes/setupProgress.php';
require_once __DIR__.'/../../includes/schedulingHelper.php';
require_once __DIR__.'/../../includes/tournamentProgression.php';
require_once __DIR__.'/../../includes/leagueSetupState.php';
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
requireRole([1]); requireActiveAccount($conn);
$userId=currentUserId(); $id=(int)($_GET['id'] ?? $_POST['league_id'] ?? 0);
if(!$id || !organizerOwnsLeague($conn,$id,$userId)){
    $_SESSION['league_flash'] = ['danger', 'That league belongs to another organizer.'];
    header('Location: league.php'); exit;
}

$leagueStmt=$conn->prepare("SELECT * FROM tbl_league WHERE id=? AND created_by=?");
$leagueStmt->bind_param('ii',$id,$userId); $leagueStmt->execute(); $league=$leagueStmt->get_result()->fetch_assoc(); $leagueStmt->close();

/* Generated matches are unscheduled drafts; make sure the legacy date/time
   columns can hold NULL before any generation runs inside a transaction. */
ensureStageMatchDraftColumns($conn);

if($_SERVER['REQUEST_METHOD']==='POST'){
    $token=(string)($_POST['csrf_token'] ?? '');
    if(!$token || !hash_equals(csrfToken(),$token)){ $_SESSION['setup_flash']=['danger','Form session expired.']; header("Location: setup.php?id=$id"); exit; }
    try {
        if(isset($_POST['applyStructure'])){
            /* Apply or replace the structure preset. The Format step only selects
               a card; this handler runs when Next is pressed. Allowed while the
               league has no matches; replacing deletes the current stages
               (cascading to their groups, pool sources and team placements) and
               builds the new skeleton. Adding playoffs to a plain round robin is
               additive and keeps its placements. */
            $structureKey=trim((string)($_POST['structure_key'] ?? ''));
            /* Editable preset settings from the Format step (top N per group /
               pool, Swiss rounds). Only the selected card's inputs are enabled,
               so these values always belong to $structureKey. */
            $postedStructureSettings=isset($_POST['settings'])&&is_array($_POST['settings'])?$_POST['settings']:null;
            $structureSettings=($structureKey!==''&&$postedStructureSettings!==null&&array_key_exists($structureKey,leagueStructurePresets()))
                ?leagueStructureSanitizeSettings($structureKey,$postedStructureSettings)
                :[];

            /* The Format step only selects a card; Next posts the selection here.
               Snapshot the current skeleton once: it decides whether there is
               anything to change and which step follows this one. */
            $existingStages=getLeagueStages($conn,$id);
            $existingStageCount=count($existingStages);
            $existingStructureKey=null;
            if($existingStageCount>0){
                $structureShape=[];
                foreach($existingStages as $structureStage){
                    $structureStage['groups']=getStageGroups($conn,(int)$structureStage['id']);
                    $structureShape[]=$structureStage;
                }
                $existingStructureKey=detectLeagueStructure($structureShape);
            }

            /* No selection (a hand-built skeleton) or the card that is already
               applied means "keep this format and continue" - never replace the
               skeleton from under the organizer. */
            if($existingStageCount>0 && ($structureKey==='' || $structureKey===$existingStructureKey)){
                /* The same preset is already applied. Qualifier/round edits are
                   saved in place. A group-count edit is structural, so it
                   rebuilds the skeleton and re-draws the approved teams. */
                if($structureKey!=='' && $postedStructureSettings!==null){
                    $settingsMatchCount=0;
                    if($settingsMatchStmt=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE league_id=? AND (is_archived=0 OR is_archived IS NULL)")){
                        $settingsMatchStmt->bind_param('i',$id); $settingsMatchStmt->execute();
                        $settingsMatchCount=(int)($settingsMatchStmt->get_result()->fetch_assoc()['c'] ?? 0); $settingsMatchStmt->close();
                    }
                    if($settingsMatchCount>0) throw new RuntimeException('Stage settings are locked while active matches exist.');

                    $groupCountChanged=false;
                    $currentGroupCount=0;
                    $newGroupCount=0;
                    $redrawnTeams=0;
                    $savedSettings=0;
                    $postedGroupCount=isset($postedStructureSettings[0]['groups']) ? (int)($structureSettings[0]['groups'] ?? 0) : null;
                    if($structureKey==='groups_knockout' && $postedGroupCount!==null && !empty($existingStages[0]['id'])){
                        $currentGroupCount=count(getStageGroups($conn,(int)$existingStages[0]['id']));
                        $groupCountChanged=$postedGroupCount!==$currentGroupCount;
                    }

                    $conn->begin_transaction();
                    try {
                        if($groupCountChanged){
                            $rebuiltStages=replaceLeagueStructure($conn,$id,$structureKey,$structureSettings);
                            if($rebuiltStages<1) throw new RuntimeException('The new group layout could not be created.');
                            $rebuiltStageRows=getLeagueStages($conn,$id);
                            $newGroupStageId=(int)($rebuiltStageRows[0]['id'] ?? 0);
                            $redrawnTeams=$newGroupStageId>0 ? seedStageGroupsFromApprovedTeams($conn,$id,$newGroupStageId) : 0;
                            $newGroupCount=$newGroupStageId>0 ? count(getStageGroups($conn,$newGroupStageId)) : 0;
                        } else {
                            $savedSettings=updateLeagueStructureSettings($conn,$id,$structureKey,$structureSettings);
                            if($savedSettings<1) throw new RuntimeException('The stage settings could not be saved.');
                            resetLeagueProgression($conn,$id);
                        }
                        $conn->commit();
                    } catch(Throwable $settingsFailure){
                        $conn->rollback();
                        throw $settingsFailure;
                    }

                    if($groupCountChanged){
                        auditLog($conn,'Rebuild League Groups',"Changed the groups_knockout layout from $currentGroupCount to $newGroupCount group/s and re-drew $redrawnTeams approved team/s (league ID $id)");
                        $_SESSION['setup_flash']=['info','Group layout changed to '.$newGroupCount.' groups. '.($redrawnTeams>0?$redrawnTeams.' approved team/s were re-drawn automatically.':'No approved teams to re-draw yet.').' No active matches existed, so no match history was affected. Team progression was reset.'];
                    } else {
                        auditLog($conn,'Update League Structure Settings',"Updated $savedSettings preset setting/s for the $structureKey structure (league ID $id)");
                        $_SESSION['setup_flash']=['success','Stage settings saved. The existing stages and team placements were kept. Team progression was reset.'];
                    }
                }
                $existingNextStep='matches';
                foreach($existingStages as $existingStage){
                    if(in_array((string)($existingStage['stage_type'] ?? ''),['group','crossover'],true)){
                        $existingNextStep='teams';
                        break;
                    }
                }
                header('Location: setup.php?id='.$id.'&step='.$existingNextStep.'#step-'.$existingNextStep);
                exit;
            }
            if($structureKey===''){
                throw new RuntimeException('Choose a format to continue.');
            }

            $existingMatchCount=0;
            if($structureMatchStmt=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE league_id=? AND (is_archived=0 OR is_archived IS NULL)")){
                $structureMatchStmt->bind_param('i',$id); $structureMatchStmt->execute();
                $existingMatchCount=(int)($structureMatchStmt->get_result()->fetch_assoc()['c'] ?? 0); $structureMatchStmt->close();
            }
            $applyError=structurePresetApplyError($structureKey,$existingMatchCount);
            if($applyError!==null) throw new RuntimeException($applyError);

            /* Snapshot what the replacement removes, for the confirmation flash. */
            $existingGroupCount=0; $existingPlacedCount=0;
            if($existingStageCount>0){
                if($groupCountStmt=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_league_stage_group g JOIN tbl_league_stage s ON s.id=g.stage_id WHERE s.league_id=?")){
                    $groupCountStmt->bind_param('i',$id); $groupCountStmt->execute();
                    $existingGroupCount=(int)($groupCountStmt->get_result()->fetch_assoc()['c'] ?? 0); $groupCountStmt->close();
                }
                if($placedCountStmt=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_league_stage_group_team t JOIN tbl_league_stage_group g ON g.id=t.stage_group_id JOIN tbl_league_stage s ON s.id=g.stage_id WHERE s.league_id=?")){
                    $placedCountStmt->bind_param('i',$id); $placedCountStmt->execute();
                    $existingPlacedCount=(int)($placedCountStmt->get_result()->fetch_assoc()['c'] ?? 0); $placedCountStmt->close();
                }
            }

            /* A plain round robin can be upgraded to round robin + playoffs
               without destroying its league stage or team placements. */
            $structureExtended=false;

            $createdStructureStages=0;
            $conn->begin_transaction();
            try {
                if($structureKey==='round_robin_playoffs' && $existingStructureKey==='round_robin'){
                    $addedPlayoffsStageId=addPlayoffsStageToRoundRobin($conn,$id);
                    if($addedPlayoffsStageId<1) throw new RuntimeException('Could not add the Playoffs stage to this round robin.');
                    updateLeagueStructureSettings($conn,$id,$structureKey,$structureSettings);
                    resetLeagueProgression($conn,$id);
                    $createdStructureStages=1;
                    $structureExtended=true;
                } else {
                    $createdStructureStages=replaceLeagueStructure($conn,$id,$structureKey,$structureSettings);
                    if($createdStructureStages<1) throw new RuntimeException('That structure has no stages to create.');
                }
                $conn->commit();
            } catch(Throwable $structureFailure){
                $conn->rollback();
                throw $structureFailure;
            }
            if($structureExtended){
                auditLog($conn,'Extend League Structure',"Added the Playoffs stage to the round-robin structure for league ID $id; existing stages and team placements kept");
                $_SESSION['setup_flash']=['success','Playoffs stage added — your League Stage, teams and team placements were kept. Team progression was reset. Generate the round robin; the configured top N advance automatically.'];
            } else {
                $structureAction=$existingStageCount>0?'Changed':'Applied';
                $createdStagesText=$createdStructureStages.' '.($createdStructureStages===1?'stage':'stages');
                auditLog($conn,'Apply League Structure',"$structureAction the $structureKey structure ($createdStagesText) for league ID $id".($existingStageCount>0?" — replaced $existingStageCount stage/s, $existingGroupCount group/s, $existingPlacedCount placement/s":''));
                $flashMessage="Structure $structureAction — $createdStagesText created.";
                if($existingStageCount>0){
                    $removedSummary=[$existingStageCount.' '.($existingStageCount===1?'stage':'stages')];
                    if($existingGroupCount>0) $removedSummary[]=$existingGroupCount.' '.($existingGroupCount===1?'group':'groups');
                    if($existingPlacedCount>0) $removedSummary[]=$existingPlacedCount.' team '.($existingPlacedCount===1?'placement':'placements');
                    $flashMessage.=' Removed the old skeleton ('.implode(', ',$removedSummary).'); team records and matches are untouched.';
                    if($existingPlacedCount>0) $flashMessage.=' Group placements were reset.';
                    $flashMessage.=' Team progression was reset.';
                }
                $flashStructureStages=leagueStructurePresetStages($structureKey) ?? [];
                $flashStructureHasGroups=false;
                foreach($flashStructureStages as $flashStructureStage){
                    if(!empty($flashStructureStage['groups'])){ $flashStructureHasGroups=true; break; }
                }
                $flashMessage.=$flashStructureHasGroups
                    ? ' Continue with Teams, then generate the matches.'
                    : ' Continue with the Matches step.';
                $_SESSION['setup_flash']=[$existingStageCount>0?'info':'success',$flashMessage];
            }
        } elseif(isset($_POST['addStageWithGroups'])){
            /* Wizard step: create one stage together with its groups. */
            $stageName=trim($_POST['stage_name'] ?? '');
            $stageType=(string)($_POST['stage_type'] ?? 'group');
            if($stageName==='' || mb_strlen($stageName,'UTF-8')>80) throw new RuntimeException('Give the stage a name (80 characters max).');
            if(!array_key_exists($stageType, stageTypeLabels())) throw new RuntimeException('Choose a valid stage type.');
            $stageFormat=(string)($_POST['stage_format'] ?? '');
            if($stageFormat!=='' && !array_key_exists($stageFormat,tournamentFormats())) throw new RuntimeException('Choose a valid competition format.');
            if($stageFormat!=='' && stageTypeForFormat($stageFormat)!==$stageType) $stageType=stageTypeForFormat($stageFormat);
            $stageGroupCount=max(0,min(8,(int)($_POST['group_count'] ?? 0)));
            $stageGroupNames=defaultStageGroupNames($stageGroupCount,$stageType);
            $stageQualifiers=(int)($_POST['qualifiers_per_group'] ?? 0);
            $conn->begin_transaction();
            try {
                $createdStageId=createStageWithGroups($conn,$id,$stageName,$stageType,$stageGroupNames,$stageQualifiers>0?$stageQualifiers:null,!empty($_POST['carries_previous_results']),$stageFormat!==''?$stageFormat:null);
                $conn->commit();
            } catch(Throwable $stageFailure){ $conn->rollback(); throw $stageFailure; }
            auditLog($conn,'Create League Stage',"Created stage $stageName ($stageType, ".(($stageFormat!=='')?$stageFormat:'default format').", ".count($stageGroupNames)." group/s) for league ID $id");
            $_SESSION['setup_flash']=['success','Stage created: '.$stageName.(count($stageGroupNames)>0?' with '.count($stageGroupNames).' group'.(count($stageGroupNames)===1?'':'s'):'').'.'];
        } elseif(isset($_POST['discardSetup'])){
            /* Start over: remove the competition skeleton and schedule, keep the
               league details and teams. The typed confirmation prevents a stray click. */
            if(strtoupper(trim((string)($_POST['discard_confirm'] ?? ''))) !== 'DISCARD'){
                throw new RuntimeException('Type DISCARD in the confirmation box to start over.');
            }
            $discardTableExists=static function(string $table) use ($conn): bool {
                $safe=$conn->real_escape_string($table);
                $exists=$conn->query("SHOW TABLES LIKE '$safe'");
                $found=$exists && $exists->num_rows>0;
                if($exists) $exists->free();
                return $found;
            };
            $conn->begin_transaction();
            try {
                $discardRun=function(string $sql) use ($conn,$id): void {
                    $statement=$conn->prepare($sql);
                    if(!$statement){
                        throw new RuntimeException('The reset query could not be prepared: '.$conn->error);
                    }
                    if(!$statement->bind_param('i',$id) || !$statement->execute()){
                        $error=$statement->error;
                        $statement->close();
                        throw new RuntimeException('The setup could not be discarded: '.$error);
                    }
                    $statement->close();
                };
                if($discardTableExists('tbl_progression_log')){
                    $discardRun("DELETE FROM tbl_progression_log WHERE league_id=?");
                }
                $discardRun("DELETE FROM tbl_schedule WHERE league_id=?");
                if($discardTableExists('tbl_league_stage')){
                    $discardRun("DELETE FROM tbl_league_stage WHERE league_id=?");
                }
                if($discardTableExists('tbl_league_group') && $discardTableExists('tbl_league_group_team')){
                    $discardRun("DELETE gt FROM tbl_league_group_team gt JOIN tbl_league_group g ON g.id=gt.group_id WHERE g.league_id=?");
                    $discardRun("DELETE FROM tbl_league_group WHERE league_id=?");
                }
                $discardRun("UPDATE tbl_league SET grouping_enabled=0 WHERE id=?");
                $discardCompletion=$conn->query("SHOW COLUMNS FROM tbl_league WHERE Field IN ('tournament_completed_at','tournament_completed_by')");
                if($discardCompletion && $discardCompletion->num_rows===2){
                    $discardCompletion->free();
                    $discardRun("UPDATE tbl_league SET tournament_completed_at=NULL, tournament_completed_by=NULL WHERE id=?");
                } elseif($discardCompletion){ $discardCompletion->free(); }
                $discardProgressColumns=$conn->query("SHOW COLUMNS FROM tbl_team LIKE 'progression_status'");
                if($discardProgressColumns && $discardProgressColumns->num_rows>0){
                    $discardProgressColumns->free();
                    $discardRun("UPDATE tbl_team SET progression_status='Undecided', progression_decided_by=NULL, progression_decided_at=NULL WHERE league_id=?");
                } elseif($discardProgressColumns){ $discardProgressColumns->free(); }
                $conn->commit();
            } catch(Throwable $discardFailure){
                $conn->rollback();
                throw $discardFailure;
            }
            auditLog($conn,'Discard League Setup',"Discarded stages, pools and matches for league ID $id (teams and league details kept)");
            $_SESSION['setup_flash']=['success','Setup discarded — stages, groups, pools and matches were removed. Teams and league details were kept.'];
        } elseif(isset($_POST['createPlayoffs'])){
            /* Normal progression: one round-robin group -> top N -> a standard
               seeded knockout (QF/SF/Final depending on N). */
            $playoffSourceId=(int)($_POST['source_stage_id'] ?? 0);
            $playoffTeams=max(2,min(64,(int)($_POST['playoff_teams'] ?? 4)));
            $playoffSource=getStage($conn,$playoffSourceId);
            if(!$playoffSource || (int)$playoffSource['league_id']!==$id) throw new RuntimeException('That stage does not belong to this league.');
            if(!in_array((string)$playoffSource['stage_type'],['group','crossover'],true)) throw new RuntimeException('Playoffs can only be created from a round-robin stage.');
            if(!isStageComplete($conn,$id,$playoffSourceId)) throw new RuntimeException('Finish every round-robin match before creating the playoffs.');
            $playoffSourceGroups=getStageGroups($conn,$playoffSourceId);
            if(count($playoffSourceGroups)!==1) throw new RuntimeException('This action is for a single round-robin group. Use Advance qualifiers in the Matches step for multi-group tournaments.');
            $playoffRanked=getStageGroupStandings($conn,$id,(int)$playoffSourceGroups[0]['id']);
            $playoffRankedCount=count($playoffRanked);
            if($playoffRankedCount<2) throw new RuntimeException('At least two teams need a standings row before the playoffs can be created.');
            if($playoffTeams>$playoffRankedCount) $playoffTeams=$playoffRankedCount;
            $playoffNextStage=nextLeagueStage($conn,$id,$playoffSourceId);
            $conn->begin_transaction();
            try {
                if($playoffNextStage){
                    if((string)$playoffNextStage['stage_type']!=='knockout') throw new RuntimeException('The next stage is not a knockout stage.');
                    $playoffStageId=(int)$playoffNextStage['id'];
                    $playoffGroups=getStageGroups($conn,$playoffStageId);
                    if(count($playoffGroups)>1) throw new RuntimeException('The next stage has multiple pools. Advance the teams in the Matches step instead.');
                    if(!$playoffGroups) createStageGroup($conn,$playoffStageId,'Playoffs',1);
                    $playoffMatchStmt=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE league_id=? AND stage_id=? AND (is_archived=0 OR is_archived IS NULL)");
                    $playoffMatchStmt->bind_param('ii',$id,$playoffStageId);
                    $playoffMatchStmt->execute();
                    $playoffMatchCount=(int)($playoffMatchStmt->get_result()->fetch_assoc()['c'] ?? 0);
                    $playoffMatchStmt->close();
                    if($playoffMatchCount>0) throw new RuntimeException('The playoff bracket already exists.');
                } else {
                    $playoffStageId=createStageWithGroups($conn,$id,'Playoffs','knockout',['Playoffs']);
                }
                $playoffPromotion=promoteStageQualifiers($conn,$id,$playoffSourceId,$playoffStageId,$playoffTeams,true,null);
                updateStage($conn,$playoffSourceId,(string)$playoffSource['name'],(string)$playoffSource['stage_type'],(int)$playoffSource['display_order'],$playoffTeams,!empty($playoffSource['carries_previous_results']));
                /* Draw the playoff bracket through the graph engine so it carries
                   bracket_side/node_key like every other preset bracket. */
                $playoffStageRow=getStage($conn,$playoffStageId) ?: $playoffNextStage;
                $playoffStageFormat=$playoffStageRow ? stageFormat($playoffStageRow) : 'single_elimination';
                $playoffStageTeams=[];
                foreach(stageTeamPool($conn,$id,$playoffStageId) as $playoffTeam) $playoffStageTeams[]=(int)$playoffTeam['team_id'];
                $playoffGenerated=createBracketForStage($conn,$id,$playoffStageId,$playoffStageTeams,$playoffStageFormat);
                if(!empty($playoffGenerated['errors'])) throw new RuntimeException(implode(' ',$playoffGenerated['errors']));
                if((int)$playoffGenerated['created']<1) throw new RuntimeException('The playoff bracket did not create any matches.');
                $conn->commit();
            } catch(Throwable $playoffFailure){
                $conn->rollback();
                throw $playoffFailure;
            }
            $playoffPromotedCount=0;
            foreach($playoffPromotion as $playoffPromotionRow) $playoffPromotedCount+=count($playoffPromotionRow['promoted'] ?? []);
            auditLog($conn,'Create Playoffs',"Created seeded playoffs for league ID $id: top $playoffTeams from stage ID $playoffSourceId, $playoffPromotedCount team/s promoted, {$playoffGenerated['created']} match/es generated");
            $_SESSION['setup_flash']=['success','Playoffs created — top '.$playoffTeams.' team'.($playoffTeams===1?'':'s').' advanced and the seeded bracket was generated as Drafts with no date, venue or Committee.'];
        } elseif(isset($_POST['advanceAndCreateNextStage'])){
            /* Wizard step: finish this stage, create the next one and carry the qualifiers over. */
            $nextSourceId=(int)($_POST['source_stage_id'] ?? 0);
            $nextName=trim($_POST['next_stage_name'] ?? '');
            $nextType=(string)($_POST['next_stage_type'] ?? 'knockout');
            $nextPerGroup=max(1,min(64,(int)($_POST['per_group'] ?? 2)));
            $nextSourceStage=getStage($conn,$nextSourceId);
            if(!$nextSourceStage || (int)$nextSourceStage['league_id']!==$id) throw new RuntimeException('That stage does not belong to this league.');
            if($nextName==='' || mb_strlen($nextName,'UTF-8')>80) throw new RuntimeException('Give the next stage a name (80 characters max).');
            if(!array_key_exists($nextType, stageTypeLabels())) throw new RuntimeException('Choose a valid stage type for the next stage.');
            if(nextLeagueStage($conn,$id,$nextSourceId)!==null) throw new RuntimeException('This stage already has a following stage — use Advance qualifiers in the Matches step instead.');
            if(!isStageComplete($conn,$id,$nextSourceId)) throw new RuntimeException('Finish every match in this stage before creating the next one.');
            $nextGroupNames=$nextType==='crossover'?['Pool 1','Pool 2']:($nextType==='group'?['Group A','Group B']:[]);
            $conn->begin_transaction();
            try {
                $nextStageId=createStageWithGroups($conn,$id,$nextName,$nextType,$nextGroupNames,null,false);
                $nextPromotion=promoteStageQualifiers($conn,$id,$nextSourceId,$nextStageId,$nextPerGroup,true,null);
                $conn->commit();
            } catch(Throwable $nextFailure){ $conn->rollback(); throw $nextFailure; }
            $nextPromotedCount=0;
            foreach($nextPromotion as $nextPromotionRow) $nextPromotedCount+=count($nextPromotionRow['promoted'] ?? []);
            auditLog($conn,'Create League Stage',"Created stage $nextName ($nextType) for league ID $id and advanced $nextPromotedCount team/s from stage ID $nextSourceId");
            $_SESSION['setup_flash']=['success',$nextName.' created — '.$nextPromotedCount.' team/s advanced from '.$nextSourceStage['name'].'.'];
        } elseif(isset($_POST['toggleGrouping'])){
            $enabled=(int)!empty($_POST['enabled']);
            $stmt=$conn->prepare("UPDATE tbl_league SET grouping_enabled=? WHERE id=? AND created_by=?"); $stmt->bind_param('iii',$enabled,$id,$userId); $stmt->execute(); $stmt->close();
            auditLog($conn,'Configure Team Grouping',($enabled?'Enabled':'Disabled')." grouping for league ID $id");
            $_SESSION['setup_flash']=['success','Optional grouping setting updated.'];
        } elseif(isset($_POST['addGroup'])){
            $name=trim($_POST['group_name'] ?? '');
            if($name==='' || mb_strlen($name,'UTF-8')>80) throw new RuntimeException('Group name is required and must not exceed 80 characters.');
            $order=max(1,(int)($_POST['display_order'] ?? 1));
            $stmt=$conn->prepare("INSERT INTO tbl_league_group(league_id,name,display_order) VALUES (?,?,?)"); $stmt->bind_param('isi',$id,$name,$order); $stmt->execute(); $stmt->close();
            auditLog($conn,'Create League Group',"Created $name for league ID $id"); $_SESSION['setup_flash']=['success','Group created.'];
        } elseif(isset($_POST['deleteGroup'])){
            $groupId=(int)$_POST['group_id'];
            $stmt=$conn->prepare("DELETE FROM tbl_league_group WHERE id=? AND league_id=?"); $stmt->bind_param('ii',$groupId,$id); $stmt->execute(); $stmt->close();
            auditLog($conn,'Delete League Group',"Removed group ID $groupId from league ID $id"); $_SESSION['setup_flash']=['success','Group removed; its teams are now ungrouped.'];
        } elseif(isset($_POST['addStage'])){
            $stageName=trim($_POST['stage_name'] ?? '');
            $stageType=(string)($_POST['stage_type'] ?? 'group');
            if($stageName==='' || mb_strlen($stageName,'UTF-8')>80) throw new RuntimeException('Stage name is required and must not exceed 80 characters.');
            if(!array_key_exists($stageType, stageTypeLabels())) throw new RuntimeException('Invalid stage type.');
            $qpg=(int)($_POST['qualifiers_per_group'] ?? 0);
            $stageFormat=(string)($_POST['stage_format'] ?? '');
            if($stageFormat!=='' && !array_key_exists($stageFormat,tournamentFormats())) throw new RuntimeException('Invalid competition format.');
            createStage($conn,$id,$stageName,$stageType,null,$qpg>0?$qpg:null,!empty($_POST['carries_previous_results']),$stageFormat!==''?$stageFormat:null);
            auditLog($conn,'Create League Stage',"Created stage $stageName ($stageType) for league ID $id");
            $_SESSION['setup_flash']=['success','Stage created.'];
        } elseif(isset($_POST['deleteStage'])){
            $stageId=(int)$_POST['stage_id'];
            $stage=getStage($conn,$stageId);
            if(!$stage || (int)$stage['league_id']!==$id) throw new RuntimeException('Stage not found in this league.');
            /* tbl_schedule.stage_id has no foreign key, so deleting a stage that
               still owns matches would leave those matches without a stage and
               silently drop them out of every stage table. */
            $stageMatchCount=0;
            if($stageMatchStmt=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE league_id=? AND stage_id=? AND (is_archived=0 OR is_archived IS NULL)")){
                $stageMatchStmt->bind_param('ii',$id,$stageId); $stageMatchStmt->execute();
                $stageMatchCount=(int)($stageMatchStmt->get_result()->fetch_assoc()['c'] ?? 0); $stageMatchStmt->close();
            }
            if($stageMatchCount>0){
                throw new RuntimeException('This stage still has '.$stageMatchCount.' match'.($stageMatchCount===1?'':'es').'. Delete those matches on the Schedule page first, then remove the stage — otherwise the matches would be left without a stage.');
            }
            deleteStage($conn,$id,$stageId);
            auditLog($conn,'Delete League Stage',"Removed stage ID $stageId from league ID $id");
            $_SESSION['setup_flash']=['success','Stage removed (its groups and assignments were removed too).'];
        } elseif(isset($_POST['addStageGroup'])){
            $stageId=(int)$_POST['stage_id'];
            $stageGroupName=trim($_POST['stage_group_name'] ?? '');
            $stage=getStage($conn,$stageId);
            if(!$stage || (int)$stage['league_id']!==$id) throw new RuntimeException('Stage not found in this league.');
            if($stageGroupName==='' || mb_strlen($stageGroupName,'UTF-8')>80) throw new RuntimeException('Group name is required and must not exceed 80 characters.');
            createStageGroup($conn,$stageId,$stageGroupName);
            auditLog($conn,'Create Stage Group',"Created $stageGroupName in stage ID $stageId (league ID $id)");
            $_SESSION['setup_flash']=['success','Stage group created.'];
        } elseif(isset($_POST['deleteStageGroup'])){
            $stageId=(int)$_POST['stage_id']; $stageGroupId=(int)$_POST['stage_group_id'];
            $stage=getStage($conn,$stageId); $stageGroup=getStageGroup($conn,$stageGroupId);
            if(!$stage || (int)$stage['league_id']!==$id || !$stageGroup || (int)$stageGroup['stage_id']!==$stageId) throw new RuntimeException('Stage group not found.');
            deleteStageGroup($conn,$stageId,$stageGroupId);
            auditLog($conn,'Delete Stage Group',"Removed stage group ID $stageGroupId from league ID $id");
            $_SESSION['setup_flash']=['success','Stage group removed.'];
        } elseif(isset($_POST['assignStageTeam'])){
            $stageGroupId=(int)$_POST['stage_group_id']; $stageTeamId=(int)$_POST['team_id'];
            $stageGroup=getStageGroup($conn,$stageGroupId);
            if(!$stageGroup || (int)$stageGroup['league_id']!==$id) throw new RuntimeException('Stage group not found in this league.');
            $teamStmt=$conn->prepare("SELECT 1 FROM tbl_team WHERE id=? AND league_id=? AND LOWER(status)='approved'"); $teamStmt->bind_param('ii',$stageTeamId,$id); $teamStmt->execute();
            $validStageTeam=(bool)$teamStmt->get_result()->fetch_row(); $teamStmt->close();
            if(!$validStageTeam) throw new RuntimeException('Approved team not found in this league.');
            if(!empty($_POST['unassign'])) { moveStageTeamToGroup($conn,(int)$stageGroup['stage_id'],$stageTeamId,null); }
            else { moveStageTeamToGroup($conn,(int)$stageGroup['stage_id'],$stageTeamId,$stageGroupId); }
            auditLog($conn,'Assign Stage Team',"Set team ID $stageTeamId in stage group ID $stageGroupId (league ID $id)");
            $_SESSION['setup_flash']=['success','Stage membership updated.'];
        } elseif(isset($_POST['promoteQualifiers'])){
            $sourceStageId=(int)$_POST['source_stage_id']; $targetStageId=(int)$_POST['target_stage_id'];
            $perGroup=max(1,(int)($_POST['per_group'] ?? 2));
            $sourceStage=getStage($conn,$sourceStageId); $targetStage=getStage($conn,$targetStageId);
            if(!$sourceStage || !$targetStage || (int)$sourceStage['league_id']!==$id || (int)$targetStage['league_id']!==$id) throw new RuntimeException('Stage not found in this league.');
            if($sourceStageId===$targetStageId) throw new RuntimeException('Choose two different stages — a stage cannot advance teams into itself.');
            if(!isStageComplete($conn,$id,$sourceStageId)) throw new RuntimeException('Finish every match in the source stage before advancing its qualifiers.');
            $targetRunning=0;
            if($targetRunningStmt=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE league_id=? AND stage_id=? AND (is_archived=0 OR is_archived IS NULL)")){
                $targetRunningStmt->bind_param('ii',$id,$targetStageId); $targetRunningStmt->execute();
                $targetRunning=(int)($targetRunningStmt->get_result()->fetch_assoc()['c'] ?? 0); $targetRunningStmt->close();
            }
            if($targetRunning>0) throw new RuntimeException('The target stage already has fixtures. Archive them on Schedule before changing its qualifiers.');

            $mapping=null;
            if(!empty($_POST['pool_mapping'])){
                // e.g. "0,3|1,2" = Pool 1 <- groups #0+#3, Pool 2 <- groups #1+#2
                $mapping=[];
                foreach(explode('|',(string)$_POST['pool_mapping']) as $pair){
                    $indexes=array_map('intval',array_filter(explode(',',$pair),'strlen'));
                    if($indexes) $mapping[]=$indexes;
                }
                if(!$mapping) $mapping=null;
            }
            /* Presets declare which earlier groups feed a pool (Pool 1 <- A+D).
               Translate those saved links into the index map the promoter wants. */
            if($mapping===null){
                $sourceIndexes=[];
                foreach(getStageGroups($conn,$sourceStageId) as $sourceIndex=>$sourceGroup){
                    $sourceIndexes[(int)$sourceGroup['id']]=$sourceIndex;
                }
                $targetGroups=getStageGroups($conn,$targetStageId);
                $candidate=[]; $hasSources=false;
                foreach($targetGroups as $targetIndex=>$targetGroup){
                    $declared=getStageGroupSources($conn,(int)$targetGroup['id']);
                    if(empty($declared)){ $candidate[]=[$targetIndex]; continue; }
                    $hasSources=true;
                    $indexes=[];
                    foreach($declared as $declaredId){
                        if(isset($sourceIndexes[(int)$declaredId])) $indexes[]=$sourceIndexes[(int)$declaredId];
                    }
                    $candidate[]=$indexes?:[$targetIndex];
                }
                if($hasSources) $mapping=$candidate;
            }

            $promoteSummary=promoteStageQualifiers($conn,$id,$sourceStageId,$targetStageId,$perGroup,!empty($_POST['eliminate_rest']),$mapping);
            if(!$promoteSummary) throw new RuntimeException('Nothing to promote — check the source stage groups.');
            auditLog($conn,'Promote Stage Qualifiers',"Promoted top $perGroup of stage ID $sourceStageId into stage ID $targetStageId (league ID $id)");
            $_SESSION['setup_flash']=['success','Promoted: '.implode('; ',array_map(fn($s)=>$s['source'].' → '.$s['target'].' ('.count($s['promoted']).' team/s)', $promoteSummary))];
        } elseif(isset($_POST['generateSwissRound'])){
            /* Swiss stages generate one round at a time; the engine locks the
               previous round and pairs the next one from the standings. */
            $swissStageId=(int)($_POST['stage_id'] ?? 0);
            $swissStage=getStage($conn,$swissStageId);
            if(!$swissStage || (int)$swissStage['league_id']!==$id) throw new RuntimeException('Stage not found in this league.');
            if(stageFormat($swissStage)!=='swiss') throw new RuntimeException('This stage is not a Swiss stage.');
            if((int)($swissStage['rounds_planned'] ?? 0)<=0){
                $swissTeams=stageTeamPool($conn,$id,$swissStageId);
                updateStagePlannedRounds($conn,$swissStageId,swissRoundCount(count($swissTeams)));
            }
            $swissResult=generateSwissRoundForStage($conn,$id,$swissStageId);
            if(!empty($swissResult['errors'])) throw new RuntimeException(implode(' ',$swissResult['errors']));
            auditLog($conn,'Generate Swiss Round',"Generated Swiss round {$swissResult['round']} ({$swissResult['created']} fixtures) for stage ID $swissStageId (league ID $id)");
            $_SESSION['setup_flash']=['success','Swiss round '.$swissResult['round'].' generated — '.$swissResult['created'].' fixture/s.'];
        } elseif(isset($_POST['generateSeededBracket'])){
            /* Straight seeded bracket from every team in the stage. Used by the
               Groups → knockout preset, where the qualifiers are containers in
               one or more promotion groups but the bracket itself is single. */
            $bracketStageId=(int)($_POST['stage_id'] ?? 0);
            $bracketStage=getStage($conn,$bracketStageId);
            if(!$bracketStage || (int)$bracketStage['league_id']!==$id) throw new RuntimeException('Stage not found in this league.');
            if((string)$bracketStage['stage_type']!=='knockout') throw new RuntimeException('This action is only for a knockout stage.');
            $bracketExisting=0;
            if($bracketCheck=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE league_id=? AND stage_id=? AND (is_archived=0 OR is_archived IS NULL)")){
                $bracketCheck->bind_param('ii',$id,$bracketStageId); $bracketCheck->execute();
                $bracketExisting=(int)($bracketCheck->get_result()->fetch_assoc()['c'] ?? 0); $bracketCheck->close();
            }
            if($bracketExisting>0) throw new RuntimeException('This stage already has fixtures. Archive them on Schedule before drawing the bracket again.');
            $bracketTeams=stageSeededTeamIds($conn,$id,$bracketStageId);
            if(count($bracketTeams)<2) throw new RuntimeException('Assign at least two teams to this stage first.');
            $bracketFormat=stageFormat($bracketStage);
            $bracketReset=$bracketFormat==='double_elimination' && !empty($_POST['bracket_reset']);
            $bracketResult=createBracketForStage($conn,$id,$bracketStageId,$bracketTeams,$bracketFormat,['bracket_reset'=>$bracketReset]);
            if(!empty($bracketResult['errors'])) throw new RuntimeException(implode(' ',$bracketResult['errors']));
            if((int)$bracketResult['created']<1) throw new RuntimeException('The bracket did not create any matches.');
            if($bracketFormat==='double_elimination' && function_exists('saveStageSettings')){
                $bracketOptions=stageSettings($bracketStage);
                $bracketOptions['bracket_reset']=$bracketReset?1:0;
                saveStageSettings($conn,$bracketStageId,$bracketOptions);
            }
            auditLog($conn,'Generate Seeded Bracket',"Generated a {$bracketResult['created']}-match bracket for stage ID $bracketStageId (league ID $id)");
            $_SESSION['setup_flash']=['success','Bracket generated — '.$bracketResult['created'].' match/es created as Drafts.'
                .($bracketReset?' Bracket reset final is enabled.':'')];
        } elseif(isset($_POST['updateStageDetails'])){
            /* Inline editor in step 2: rename, retype, reorder or change how many
               teams advance from each group. Nothing here deletes data. */
            $editStageId=(int)($_POST['stage_id'] ?? 0);
            $editStageName=trim($_POST['stage_name'] ?? '');
            $editStageType=(string)($_POST['stage_type'] ?? 'group');
            $editStageOrder=max(1,min(99,(int)($_POST['display_order'] ?? 1)));
            $editStageQpg=max(0,min(64,(int)($_POST['qualifiers_per_group'] ?? 0)));
            $editStage=getStage($conn,$editStageId);
            if(!$editStage || (int)$editStage['league_id']!==$id) throw new RuntimeException('Stage not found in this league.');
            if($editStageName==='' || mb_strlen($editStageName,'UTF-8')>80) throw new RuntimeException('Stage name is required and must not exceed 80 characters.');
            if(!array_key_exists($editStageType, stageTypeLabels())) throw new RuntimeException('Choose a valid stage type.');
            updateStage($conn,$editStageId,$editStageName,$editStageType,$editStageOrder,$editStageQpg>0?$editStageQpg:null,!empty($_POST['carries_previous_results']));
            auditLog($conn,'Update League Stage',"Updated stage ID $editStageId (league ID $id): $editStageName ($editStageType)");
            $_SESSION['setup_flash']=['success','Stage settings saved.'];
        } elseif(isset($_POST['moveStage'])){
            /* Reorder stages. All display orders are renumbered 1..n so a manually
               entered duplicate order cannot break the sequence. */
            $moveStageId=(int)($_POST['stage_id'] ?? 0);
            $moveDirection=(string)($_POST['direction'] ?? 'up');
            if(!in_array($moveDirection,['up','down'],true)) throw new RuntimeException('Choose a valid stage direction.');
            $moveStages=getLeagueStages($conn,$id);
            $moveIndex=null;
            foreach($moveStages as $movePosition=>$moveStageRow){
                if((int)$moveStageRow['id']===$moveStageId){ $moveIndex=$movePosition; break; }
            }
            if($moveIndex===null) throw new RuntimeException('Stage not found in this league.');
            $moveTarget=$moveDirection==='up'?$moveIndex-1:$moveIndex+1;
            if($moveTarget<0 || $moveTarget>=count($moveStages)) throw new RuntimeException('That stage is already at the edge of the sequence.');
            $moveOrderIds=array_map(static fn($stageRow)=>(int)$stageRow['id'],$moveStages);
            [$moveOrderIds[$moveIndex],$moveOrderIds[$moveTarget]]=[$moveOrderIds[$moveTarget],$moveOrderIds[$moveIndex]];
            $moveById=[]; foreach($moveStages as $moveStageRow) $moveById[(int)$moveStageRow['id']]=$moveStageRow;
            $conn->begin_transaction();
            try {
                foreach($moveOrderIds as $movePosition=>$moveOrderedId){
                    $moveRow=$moveById[$moveOrderedId];
                    updateStage($conn,$moveOrderedId,(string)$moveRow['name'],(string)$moveRow['stage_type'],$movePosition+1,$moveRow['qualifiers_per_group']!==null?(int)$moveRow['qualifiers_per_group']:null,!empty($moveRow['carries_previous_results']));
                }
                $conn->commit();
            } catch(Throwable $moveFailure){ $conn->rollback(); throw $moveFailure; }
            auditLog($conn,'Reorder League Stages',"Moved stage ID $moveStageId $moveDirection (league ID $id)");
            $_SESSION['setup_flash']=['success','Stage order updated.'];
        } elseif(isset($_POST['seedStageTeams'])){
            /* Step 3 shortcut: draw every approved team into the first stage's
               groups (round-robin by team name). The helper refuses later stages
               and groups that already contain teams. */
            $seedStageId=(int)($_POST['stage_id'] ?? 0);
            $seedStage=getStage($conn,$seedStageId);
            if(!$seedStage || (int)$seedStage['league_id']!==$id) throw new RuntimeException('Stage not found in this league.');
            $seededTeams=seedStageGroupsFromApprovedTeams($conn,$id,$seedStageId);
            if($seededTeams<1) throw new RuntimeException('Nothing to fill — the groups already contain teams, or this is not the first stage.');
            auditLog($conn,'Seed Stage Teams',"Placed $seededTeams approved team/s into stage ID $seedStageId (league ID $id)");
            $_SESSION['setup_flash']=['success',$seededTeams.' team'.($seededTeams===1?'':'s').' placed into '.$seedStage['name'].'.'];
        }
    } catch(Throwable $e){ $_SESSION['setup_flash']=['danger',$e->getCode()===1062?'That group already exists.':$e->getMessage()]; }
    /* Land back on the step that was edited instead of the top of the page. */
    $setupRedirectStep='';
    $setupRedirectHash='';
    foreach([
        'addStageWithGroups'=>['','#stages'],'advanceAndCreateNextStage'=>['matches','#step-matches'],
        'applyStructure'=>['teams','#step-teams'],
        'discardSetup'=>['format','#step-format'],
        'createPlayoffs'=>['matches','#step-matches'],
        'toggleGrouping'=>['','#pools'],'addGroup'=>['','#pools'],'deleteGroup'=>['','#pools'],
        'addStage'=>['','#stages'],'updateStageDetails'=>['','#stages'],'moveStage'=>['','#stages'],
        'deleteStage'=>['','#stages'],'addStageGroup'=>['','#stages'],'deleteStageGroup'=>['','#stages'],
        'seedStageTeams'=>['teams','#step-teams'],'assignStageTeam'=>['','#stages'],
        'promoteQualifiers'=>['matches','#step-matches'],
        'generateSwissRound'=>['matches','#step-matches'],
        'generateSeededBracket'=>['matches','#step-matches'],
    ] as $setupActionKey=>$setupActionTarget){
        if(isset($_POST[$setupActionKey])){
            $setupRedirectStep=(string)$setupActionTarget[0];
            $setupRedirectHash=(string)$setupActionTarget[1];
            break;
        }
    }
    header("Location: setup.php?id=$id".($setupRedirectStep!==''?'&step='.$setupRedirectStep:'').$setupRedirectHash); exit;
}

$groups=[]; $groupStmt=$conn->prepare("SELECT id,name,display_order FROM tbl_league_group WHERE league_id=? ORDER BY display_order,name"); $groupStmt->bind_param('i',$id); $groupStmt->execute(); $groupRes=$groupStmt->get_result(); while($g=$groupRes->fetch_assoc())$groups[]=$g; $groupStmt->close();
$groupingOn = (int)($league['grouping_enabled'] ?? 0) === 1;
$groupCount = count($groups);
/* Stage model (ANP2026): Elimination (Brackets) -> Crossover/Pools ->
   Playoffs -> Battle for Third. A team belongs to a group PER STAGE. */
$stages=[];
foreach(getLeagueStages($conn,$id) as $dbStage){
    $dbGroups=[];
    foreach(getStageGroups($conn,(int)$dbStage['id']) as $dbGroup){
        $dbGroup['teams']=getStageGroupTeams($conn,(int)$dbGroup['id']);
        $dbGroup['sources']=getStageGroupSources($conn,(int)$dbGroup['id']);
        $dbGroup['standings']=getStageGroupStandings($conn,$id,(int)$dbGroup['id']);
        $dbGroups[]=$dbGroup;
    }
    $dbStage['groups']=$dbGroups;
    $dbStage['complete']=isStageComplete($conn,$id,(int)$dbStage['id']);
    if(stageFormat($dbStage)==='swiss'){
        $dbStage['current_round']=getStageMaxRound($conn,$id,(int)$dbStage['id']);
        $dbStage['rounds_total']=(int)($dbStage['rounds_planned'] ?? 0);
        if($dbStage['rounds_total']<=0){
            $swissStageTeams=0;
            foreach($dbGroups as $swissStageGroup) $swissStageTeams+=count($swissStageGroup['teams'] ?? []);
            $dbStage['rounds_total']=max(1,swissRoundCount($swissStageTeams));
        }
    }
    $stages[]=$dbStage;
}
$stageTypes=stageTypeLabels();
$leagueScoringRules=getLeagueScoringRules($conn,$id);
$stageTeamNames=[]; foreach(getApprovedTeams($conn,$id) as $stageTeam)$stageTeamNames[(int)$stageTeam['id']]=$stageTeam['team_name'];
$stageGroupNames=[]; foreach($stages as $stageNameRow) foreach($stageNameRow['groups'] as $stageNameGroup) $stageGroupNames[(int)$stageNameGroup['id']]=$stageNameGroup['name'];

/* Setup progress — read-only presentation data for the status strip and the
   structure detector. The POST handlers above are untouched. */
$stageCount=count($stages);
$stageGroupCount=0; $stageAssignedTeamIds=[];
foreach($stages as $progressStage){
    foreach($progressStage['groups'] as $progressGroup){
        $stageGroupCount++;
        foreach($progressGroup['teams'] as $progressMember) $stageAssignedTeamIds[(int)$progressMember['team_id']]=true;
    }
}
$stageAssignedTeamCount=count($stageAssignedTeamIds);
$approvedTeamCount=count($stageTeamNames);
$matchCount=0;
if($matchStmt=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE league_id=? AND (is_archived=0 OR is_archived IS NULL)")){
    $matchStmt->bind_param('i',$id); $matchStmt->execute();
    $matchCount=(int)($matchStmt->get_result()->fetch_assoc()['c'] ?? 0); $matchStmt->close();
}

/* Which structure preset (if any) produced the stage skeleton. The preset code is
   not stored on tbl_league — the skeleton itself is the structure — so it is
   recognised by comparing that skeleton with the presets. A skeleton changed by
   hand no longer matches any preset and is reported as custom. */
$setupStructureKey=detectLeagueStructure($stages);
$setupStructureOptions=structureOptions();
$setupStructureLabel=$setupStructureKey!==null
    ? $setupStructureOptions[$setupStructureKey]
    : 'Custom structure (stages changed by hand)';
$setupStructureIsPreset=$setupStructureKey!==null && $setupStructureKey!=='custom';

/* Matches per stage — drives the wizard's "what to do next" decision. Mirrors
   isStageComplete(): only fixtures with both teams and not archived count. */
$stageStats=[];
if($stageStatsStmt=$conn->prepare("SELECT stage_id, COUNT(*) AS total, SUM(CASE WHEN LOWER(status)='completed' THEN 1 ELSE 0 END) AS completed
                                   FROM tbl_schedule
                                   WHERE league_id=? AND stage_id IS NOT NULL AND stage_id>0
                                     AND team_a>0 AND team_b>0 AND (is_archived=0 OR is_archived IS NULL)
                                   GROUP BY stage_id")){
    $stageStatsStmt->bind_param('i',$id); $stageStatsStmt->execute();
    $stageStatsRes=$stageStatsStmt->get_result();
    while($stageStatsRow=$stageStatsRes->fetch_assoc()){
        $statsStageId=(int)$stageStatsRow['stage_id'];
        $statsTotal=(int)$stageStatsRow['total'];
        $statsCompleted=(int)$stageStatsRow['completed'];
        $stageStats[$statsStageId]=['matches'=>$statsTotal,'completed'=>$statsCompleted,'complete'=>$statsTotal>0 && $statsCompleted>=$statsTotal];
    }
    $stageStatsStmt->close();
}
/* One state source: the wizard header, the three step panels and the manual
   editor all read this array. Nothing below re-derives the step flags. */
$setupState=leagueSetupState([
    'league_id'=>$id,
    'stages'=>$stages,
    'stage_stats'=>$stageStats,
    'approved_teams'=>$approvedTeamCount,
    'assigned_teams'=>$stageAssignedTeamCount,
    'matches'=>$matchCount,
    'structure_key'=>$setupStructureKey,
    'structure_label'=>$setupStructureLabel,
    'structure_is_preset'=>$setupStructureIsPreset,
    'requested_step'=>is_string($_GET['step'] ?? null)?(string)$_GET['step']:'',
]);
$setupStepKeys=$setupState['step_keys'];
$setupStepDone=$setupState['done'];
$setupActiveStep=$setupState['active_step'];
$setupActiveStepIndex=$setupState['active_index'];
$setupSimplePercent=$setupState['percent'];
$setupMatchesDone=$setupState['finish_done'];
$setupStructureCards=$setupState['structure']['cards'];



$flash=$_SESSION['setup_flash'] ?? null; unset($_SESSION['setup_flash']); $csrf=csrfToken();
$leagueHeroApprovedCount = count($stageTeamNames);
include 'includes/header.php';
?>
<body id="page-top" class="body"><?php include 'includes/bar.php'; ?>
<div class="container-fluid lp-page lsp-page" style="padding-top:20px;min-height:800px;"><div class="row p-3"><div class="card cards" style="background:transparent;box-shadow:none;border:none;"><div class="card-body p-0">
<?php include 'includes/dashboard-styles.php'; include 'includes/league-view-styles.php'; include 'includes/league-setup-styles.php'; ?>
<nav class="lp-crumbs dash-animate" aria-label="Breadcrumb">
    <a href="league.php"><i class="bi bi-arrow-left"></i> League Management</a>
    <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
    <span class="lp-crumb-current"><?= htmlspecialchars((string) $league['league_name']) ?></span>
</nav>
<?php $leagueHeroData = $league; include 'includes/league-hero.php'; ?>
<div class="lp-tabs dash-animate dash-animate-d1">
<?php $leagueTabLeagueId=$id; include 'includes/league_tabs.php'; ?>
</div>
<style>
<?php include 'includes/league-standing-styles.php'; ?>
</style>
<?php if($flash): $flashType = in_array($flash[0], ['success','danger'], true) ? $flash[0] : 'info';
      $flashIcon = $flashType === 'success' ? 'bi-check-circle-fill' : ($flashType === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-info-circle-fill'); ?>
<div class="lp-flash lp-flash-<?= $flashType ?> dash-animate" role="alert"><i class="bi <?= $flashIcon ?>"></i><span><?= htmlspecialchars($flash[1]) ?></span></div>
<?php endif; ?>

<?php include 'includes/league-setup-progress.php'; ?>
<?php include 'includes/league-setup-steps.php'; ?>

<div class="lsp-danger-zone">
    <details class="lsp-danger">
        <summary><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Discard setup &amp; start over</summary>
        <div class="lsp-danger-body">
            <p><b>This cannot be undone.</b> It deletes all stages, groups, standings pools and matches — including results. Teams and league details are kept.</p>
            <form method="post" class="lsp-danger-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
                <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                <input type="hidden" name="discardSetup" value="1">
                <div>
                    <label class="form-label" for="discardConfirm">Type <b>DISCARD</b> to confirm</label>
                    <input id="discardConfirm" name="discard_confirm" class="form-control" autocomplete="off" required>
                </div>
                <button type="submit" class="btn btn-danger">Discard setup</button>
            </form>
        </div>
    </details>
</div>
</div></div></div></div><?php include 'includes/footer.php'; ?>
<script>
(function () {
    'use strict';

    /* 1. Confirm overwriting / destructive actions (buttons carry
          data-lsp-confirm). Capture phase so the submit can be cancelled. */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-lsp-confirm]');
        if (!trigger) return;
        var message = trigger.getAttribute('data-lsp-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    /* 2. Horizontal wizard: one step at a time with Previous / Next.
          Every panel is rendered server-side, so the wizard still works without
          this script (the ?step= links reload the page). */
    var stepKeys = <?= json_encode($setupStepKeys, JSON_UNESCAPED_UNICODE) ?>;
    var stepPanels = Array.prototype.slice.call(document.querySelectorAll('[data-step-panel]'));
    var stepMilestones = Array.prototype.slice.call(document.querySelectorAll('[data-step-milestone]'));
    var stepCounter = document.querySelector('[data-lsp-step-counter]');
    var stepsAnchor = document.getElementById('setup-steps');
    var initialStep = <?= json_encode($setupActiveStep, JSON_UNESCAPED_UNICODE) ?>;

    function showStep(stepKey, options) {
        var stepIndex = stepKeys.indexOf(stepKey);
        if (stepIndex === -1) return false;

        stepPanels.forEach(function (panel) {
            var isActive = panel.getAttribute('data-step-panel') === stepKey;
            panel.classList.toggle('is-active', isActive);
            panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });
        var activeMilestone = null;
        stepMilestones.forEach(function (milestone) {
            var isActive = milestone.getAttribute('data-step-milestone') === stepKey;
            milestone.classList.toggle('is-active', isActive);
            var link = milestone.querySelector('a');
            if (link) {
                if (isActive) link.setAttribute('aria-current', 'step');
                else link.removeAttribute('aria-current');
            }
            if (isActive) activeMilestone = milestone;
        });
        var wizardTitle = document.querySelector('[data-lsp-wizard-title]');
        var wizardHint = document.querySelector('[data-lsp-wizard-hint]');
        if (activeMilestone) {
            if (wizardTitle) wizardTitle.textContent = activeMilestone.getAttribute('data-step-title') || '';
            if (wizardHint) wizardHint.textContent = activeMilestone.getAttribute('data-step-hint') || '';
        }
        if (stepCounter) stepCounter.textContent = 'Step ' + (stepIndex + 1) + ' of ' + stepKeys.length;

        if (!options || options.updateUrl !== false) {
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('step', stepKey);
                url.hash = 'step-' + stepKey;
                if (options && options.push && window.history.pushState) {
                    window.history.pushState(null, '', url.toString());
                } else {
                    window.history.replaceState(null, '', url.toString());
                }
            } catch (urlError) {
                window.location.hash = 'step-' + stepKey;
            }
        }

        if (options && options.scroll) {
            window.setTimeout(function () {
                var target = stepsAnchor || document.getElementById('step-' + stepKey);
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 50);
        }
        return true;
    }

    document.querySelectorAll('[data-lsp-step-go]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            var stepKey = trigger.getAttribute('data-lsp-step-go');
            if (showStep(stepKey, { scroll: true, push: true })) event.preventDefault();
        });
    });

    /* 3. Format step: a card only selects the format; Next applies it. When the
          selection is already the applied format (or a hand-built skeleton has
          no card checked), Next simply opens the next step. */
    var formatForm = document.getElementById('formatStructureForm');
    var formatNext = document.querySelector('[data-format-next]');
    if (formatForm && formatNext) {
        var formatCurrentKey = formatForm.getAttribute('data-current-key') || '';
        var formatHasStages = formatForm.getAttribute('data-has-stages') === '1';
        var formatCurrentGroups = parseInt(formatForm.getAttribute('data-current-groups') || '0', 10);
        var formatGroupRebuildConfirm = formatForm.getAttribute('data-group-rebuild-confirm') || '';
        var formatNextStep = formatNext.getAttribute('data-next-step') || '';
        var formatReplaceConfirm = formatForm.getAttribute('data-replace-confirm') || '';
        var formatExtendConfirm = formatForm.getAttribute('data-extend-confirm') || '';
        var formatRadios = Array.prototype.slice.call(formatForm.querySelectorAll('input[name="structure_key"]'));
        var formatSettingsPanels = Array.prototype.slice.call(formatForm.querySelectorAll('[data-format-settings]'));
        var formatSettingsDirty = false;

        var selectedFormatKey = function () {
            var checked = formatForm.querySelector('input[name="structure_key"]:checked');
            return checked ? checked.value : '';
        };

        var formatConfirmMessage = function (selected) {
            if (!formatHasStages || selected === '' || selected === formatCurrentKey) return '';
            return (formatCurrentKey === 'round_robin' && selected === 'round_robin_playoffs')
                ? formatExtendConfirm
                : formatReplaceConfirm;
        };

        var syncFormatCards = function () {
            formatRadios.forEach(function (radio) {
                var card = radio.closest('.lsp-format-card');
                if (card) card.classList.toggle('is-selected', radio.checked);
            });
        };

        var syncFormatSettings = function () {
            var selected = selectedFormatKey();
            formatSettingsPanels.forEach(function (panel) {
                var active = panel.getAttribute('data-format-settings') === selected;
                panel.classList.toggle('is-active', active);
                panel.querySelectorAll('input, select, textarea').forEach(function (input) {
                    input.disabled = !active;
                });
            });
        };

        formatForm.querySelectorAll('[data-format-setting]').forEach(function (input) {
            var markDirty = function () { formatSettingsDirty = true; };
            input.addEventListener('change', markDirty);
            input.addEventListener('input', markDirty);
        });

        formatForm.querySelectorAll('[data-use-group-recommendation]').forEach(function (button) {
            button.addEventListener('click', function () {
                var panel = button.closest('[data-format-settings]');
                var recommendation = button.closest('[data-group-recommendation]');
                if (!panel || !recommendation) return;

                var groupsInput = panel.querySelector('input[name="settings[0][groups]"]');
                var qualifiersInput = panel.querySelector('input[name="settings[0][qualifiers]"]');
                var groups = recommendation.getAttribute('data-groups') || '';
                var qualifiers = recommendation.getAttribute('data-qualifiers') || '';
                var notify = function (input, value) {
                    if (!input || value === '') return;
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                };

                notify(groupsInput, groups);
                notify(qualifiersInput, qualifiers);
            });
        });

        formatRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                syncFormatCards();
                syncFormatSettings();
            });
        });
        syncFormatCards();
        syncFormatSettings();

        formatForm.addEventListener('submit', function (event) {
            var selected = selectedFormatKey();
            var message = formatConfirmMessage(selected);
            if (message) {
                if (!window.confirm(message)) event.preventDefault();
                return;
            }

            if (selected === formatCurrentKey && selected === 'groups_knockout' && formatCurrentGroups > 0) {
                var groupsInput = formatForm.querySelector('input[name="settings[0][groups]"]');
                if (groupsInput && parseInt(groupsInput.value, 10) !== formatCurrentGroups) {
                    if (!window.confirm(formatGroupRebuildConfirm)) event.preventDefault();
                }
            }
        });

        formatNext.addEventListener('click', function (event) {
            var selected = selectedFormatKey();
            if (formatHasStages && !formatSettingsDirty && (selected === '' || selected === formatCurrentKey)) {
                if (formatNextStep && showStep(formatNextStep, { scroll: true, push: true })) {
                    event.preventDefault();
                }
            }
        });
    }

    /* 4. Open the step the URL points at. Old wizard links used #status / #wizard. */
    var legacyHashes = {
        '#status': '#setup-progress',
        '#wizard': '#setup-steps'
    };
    var currentHash = legacyHashes[window.location.hash] || window.location.hash;
    if (currentHash && currentHash.indexOf('#step-') === 0) {
        showStep(currentHash.slice(6), { scroll: true });
    } else {
        showStep(initialStep, { updateUrl: false });
        if (window.location.hash) {
            var hashTarget = null;
            try {
                hashTarget = document.querySelector(window.location.hash);
            } catch (hashError) {
                hashTarget = document.getElementById(window.location.hash.slice(1));
            }
            if (hashTarget) window.setTimeout(function () {
                hashTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 60);
        }
    }

    window.addEventListener('popstate', function () {
        var params = new URLSearchParams(window.location.search);
        var stepKey = params.get('step');
        if (stepKey && stepKeys.indexOf(stepKey) !== -1) {
            showStep(stepKey, { updateUrl: false });
        } else {
            showStep(initialStep, { updateUrl: false });
        }
    });

    /* 5. Wizard: preview (when the format has one) / generate the matches of the
          current stage using the same endpoint the Schedule page uses. The draw
          pattern is a hidden default, so the JS reads whatever field carries it. */
    var wizardForm = document.querySelector('.js-wizard-generate');
    if (wizardForm) {
        var wizardLeagueId = <?= (int) $id ?>;
        var wizardCsrf = <?= json_encode(csrfToken()) ?>;
        var wizardStageId = Number(wizardForm.getAttribute('data-stage-id'));
        var wizardPattern = wizardForm.querySelector('[name="pattern"]');
        var wizardReset = wizardForm.querySelector('[name="bracket_reset"]');

        var wizardPost = function (action, onDone) {
            var payload = {
                league_id: wizardLeagueId,
                stage_id: wizardStageId,
                pattern: wizardPattern ? wizardPattern.value : '',
                csrf_token: wizardCsrf
            };
            if (wizardReset) payload.bracket_reset = wizardReset.checked ? 1 : 0;
            payload[action] = true;
            $.ajax({
                url: 'action/schedulingAction.php',
                type: 'POST',
                data: payload,
                dataType: 'json',
                success: onDone,
                error: function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.msg) || 'Request failed.';
                    Swal.fire('Error', msg, 'error');
                }
            });
        };

        var wizardEscapeHtml = function (value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/[&<>"']/g, function (character) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character];
                });
        };

        var wizardPreviewHtml = function (matches, errors) {
            var groups = {};
            var order = [];
            matches.forEach(function (match) {
                var key = String(match.label || 'Draw').trim() || 'Draw';
                if (match.bracket_side === 'winners') key = 'Winners ' + key;
                else if (match.bracket_side === 'losers') key = 'Losers Round ' + (match.round || '');
                if (!groups[key]) { groups[key] = []; order.push(key); }
                groups[key].push(match);
            });

            var html = '<p class="lsp-preview-count"><strong>' + matches.length + '</strong> match'
                + (matches.length === 1 ? '' : 'es') + ' would be created.</p>';
            html += '<div class="lsp-preview-list">';
            order.forEach(function (key) {
                html += '<div class="lsp-preview-group"><b>' + wizardEscapeHtml(key) + '</b>';
                groups[key].forEach(function (match) {
                    var seedA = match.seed_a ? '<span>' + wizardEscapeHtml(match.seed_a) + '</span>' : '';
                    var seedB = match.seed_b ? '<span>' + wizardEscapeHtml(match.seed_b) + '</span>' : '';
                    html += '<div class="lsp-preview-match">'
                        + '<span class="lsp-preview-side">' + seedA + wizardEscapeHtml(match.team_a || 'TBD') + '</span>'
                        + '<em>vs</em>'
                        + '<span class="lsp-preview-side">' + seedB + wizardEscapeHtml(match.team_b || 'TBD') + '</span>'
                        + '</div>';
                });
                html += '</div>';
            });
            html += '</div>';

            if (errors && errors.length) {
                html += '<p class="lsp-preview-warning">' + wizardEscapeHtml(errors.join(' ')) + '</p>';
            }
            return html;
        };

        var wizardPreview = wizardForm.querySelector('.js-wizard-preview');
        if (wizardPreview) {
            wizardPreview.addEventListener('click', function () {
                wizardPost('previewStageMatches', function (res) {
                    if (res.status !== 200) { Swal.fire('Error', res.msg || 'Preview failed.', 'error'); return; }
                    if (!res.matches || !res.matches.length) {
                        Swal.fire('Nothing to preview', (res.errors && res.errors[0]) || 'No teams are available for this draw yet.', 'warning');
                        return;
                    }
                    var resetNote = wizardReset && wizardReset.checked
                        ? '<p class="lsp-preview-note">Bracket reset enabled — a second grand final is created only if the losers-bracket team wins the first.</p>'
                        : '';
                    Swal.fire({
                        title: 'Draw preview',
                        html: wizardPreviewHtml(res.matches, Array.isArray(res.errors) ? res.errors : []) + resetNote,
                        width: 680,
                        showCancelButton: true,
                        confirmButtonText: 'Generate',
                        cancelButtonText: 'Close'
                    }).then(function (r) {
                        if (r.isConfirmed) wizardForm.querySelector('.js-wizard-generate-btn').click();
                    });
                });
            });
        }

        wizardForm.querySelector('.js-wizard-generate-btn').addEventListener('click', function () {
            var generateButton = this;
            generateButton.disabled = true;
            wizardPost('generateStageMatches', function (res) {
                if (res.status !== 201) {
                    generateButton.disabled = false;
                    Swal.fire('Error', res.msg || 'Generation failed.', 'error');
                    return;
                }
                Swal.fire('Matches created', res.msg, 'success').then(function () { window.location.reload(); });
            });
        });
    }
    /* 6. Teams step: manual group draw.
          Drag a team card into a group (or back to Unassigned) and the move is
          saved immediately through the scheduling endpoint. Each card's
          "Move to" menu runs the same save, so touch and keyboard users are
          not left out. */
    var drawRoot = document.querySelector('[data-lsp-draw]');
    if (drawRoot) {
        var drawLocked = drawRoot.getAttribute('data-locked') === '1';
        var drawStageId = parseInt(drawRoot.getAttribute('data-stage-id') || '0', 10);
        var drawLeagueId = <?= (int) $id ?>;
        var drawCsrf = <?= json_encode(csrfToken()) ?>;
        var drawApproved = <?= (int) $approvedTeamCount ?>;
        var drawStatus = drawRoot.querySelector('[data-lsp-draw-status]');
        var drawContainers = Array.prototype.slice.call(
            drawRoot.querySelectorAll('[data-lsp-draw-group], [data-lsp-draw-pool]')
        );

        var drawSetStatus = function (message, tone) {
            if (!drawStatus) return;
            drawStatus.textContent = message || '';
            drawStatus.classList.toggle('is-ok', tone === 'ok');
            drawStatus.classList.toggle('is-error', tone === 'error');
        };

        var drawListOf = function (container) {
            return container ? container.querySelector('[data-lsp-draw-list]') : null;
        };

        var drawRefreshEmpty = function (container) {
            var list = drawListOf(container);
            if (!list) return;
            var empty = list.querySelector('[data-lsp-draw-empty]');
            if (empty) empty.hidden = list.querySelector('[data-lsp-team-card]') !== null;
        };

        var drawCardsIn = function (container) {
            var list = drawListOf(container);
            return list ? Array.prototype.slice.call(list.querySelectorAll('[data-lsp-team-card]')) : [];
        };

        var drawGroupChoices = function () {
            var choices = [];
            drawContainers.forEach(function (container) {
                var groupId = container.getAttribute('data-stage-group-id') || '0';
                var nameEl = container.querySelector('.lsp-draw-group-head b');
                choices.push({
                    id: groupId,
                    name: groupId === '0' ? 'Unassigned' : (nameEl ? nameEl.textContent : 'Group #' + groupId)
                });
            });
            return choices;
        };

        var drawSyncMoveMenus = function () {
            var choices = drawGroupChoices();
            drawRoot.querySelectorAll('[data-lsp-team-card]').forEach(function (card) {
                var details = card.querySelector('.lsp-team-move');
                var menu = details ? details.querySelector('.lsp-team-move-menu') : null;
                if (!details || !menu) return;
                var container = card.closest('[data-lsp-draw-group], [data-lsp-draw-pool]');
                var currentGroupId = container ? (container.getAttribute('data-stage-group-id') || '0') : '0';
                var teamId = card.getAttribute('data-team-id') || '';
                menu.innerHTML = '';
                var visible = 0;
                choices.forEach(function (choice) {
                    if (choice.id === currentGroupId) return;
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.setAttribute('data-lsp-move-team', teamId);
                    button.setAttribute('data-lsp-move-target', choice.id);
                    button.textContent = choice.name;
                    menu.appendChild(button);
                    visible++;
                });
                details.hidden = visible === 0;
            });
        };

        var drawRefreshCounters = function () {
            var placedIds = {};
            drawContainers.forEach(function (container) {
                var cards = drawCardsIn(container);
                var groupId = container.getAttribute('data-stage-group-id') || '0';
                var badge = container.querySelector('[data-lsp-group-count]');
                if (badge) badge.textContent = cards.length;
                if (groupId !== '0') {
                    cards.forEach(function (card) {
                        var teamId = card.getAttribute('data-team-id');
                        if (teamId) placedIds[teamId] = true;
                    });
                }
                drawRefreshEmpty(container);
            });

            var placed = Object.keys(placedIds).length;
            var percent = drawApproved > 0 ? Math.round(Math.min(1, placed / drawApproved) * 100) : 0;
            document.querySelectorAll('[data-lsp-draw-summary]').forEach(function (el) {
                el.textContent = placed + ' of ' + drawApproved + ' teams placed';
            });
            document.querySelectorAll('[data-lsp-draw-hint]').forEach(function (el) {
                el.textContent = placed >= drawApproved
                    ? 'Teams are ready.'
                    : (drawLocked ? 'The draw is locked.' : 'Drag a team into a group below.');
            });
            document.querySelectorAll('[data-lsp-draw-percent]').forEach(function (el) {
                el.textContent = percent + '%';
            });
            var progress = document.querySelector('[data-lsp-draw-progress]');
            if (progress) progress.style.width = percent + '%';
            var progressWrap = document.querySelector('[data-lsp-draw-progress-wrap]');
            if (progressWrap) progressWrap.setAttribute('aria-label', placed + ' of ' + drawApproved + ' teams placed');
            var detail = document.querySelector('[data-lsp-draw-detail]');
            if (detail) detail.textContent = placed + ' of ' + drawApproved + ' placed';
            drawSyncMoveMenus();
        };

        var drawRemoveDuplicates = function (teamId, keepCard) {
            drawRoot.querySelectorAll('[data-lsp-team-card]').forEach(function (card) {
                if (card === keepCard || card.getAttribute('data-team-id') !== teamId) return;
                var container = card.closest('[data-lsp-draw-group], [data-lsp-draw-pool]');
                if (card.parentNode) card.parentNode.removeChild(card);
                if (container) drawRefreshEmpty(container);
            });
        };

        var drawRevert = function (card, originList, originNext, originContainer, targetContainer) {
            if (originNext && originNext.parentNode === originList) {
                originList.insertBefore(card, originNext);
            } else {
                originList.appendChild(card);
            }
            card.classList.remove('is-saving');
            drawRefreshEmpty(originContainer);
            drawRefreshEmpty(targetContainer);
            drawRefreshCounters();
        };

        var drawMoveCard = function (card, targetContainer) {
            if (!card || !targetContainer || drawLocked) return;
            var originContainer = card.closest('[data-lsp-draw-group], [data-lsp-draw-pool]');
            if (!originContainer || originContainer === targetContainer) return;
            if (card.classList.contains('is-saving')) return;

            var originList = drawListOf(originContainer);
            var targetList = drawListOf(targetContainer);
            if (!originList || !targetList) return;

            var originNext = card.nextSibling;
            var teamId = card.getAttribute('data-team-id') || '';
            var targetGroupId = targetContainer.getAttribute('data-stage-group-id') || '0';

            targetList.appendChild(card);
            card.classList.add('is-saving');
            drawRefreshEmpty(originContainer);
            drawRefreshEmpty(targetContainer);
            drawRefreshCounters();
            drawSetStatus('Saving…', '');

            $.ajax({
                url: 'action/schedulingAction.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    moveStageTeam: 1,
                    league_id: drawLeagueId,
                    stage_id: drawStageId,
                    team_id: teamId,
                    stage_group_id: targetGroupId,
                    csrf_token: drawCsrf
                },
                success: function (res) {
                    if (!res || res.status !== 200) {
                        drawRevert(card, originList, originNext, originContainer, targetContainer);
                        drawSetStatus((res && res.msg) || 'The team could not be moved.', 'error');
                        return;
                    }
                    card.classList.remove('is-saving');
                    drawRemoveDuplicates(teamId, card);
                    drawRefreshEmpty(originContainer);
                    drawRefreshEmpty(targetContainer);
                    drawRefreshCounters();
                    drawSetStatus('Saved', 'ok');
                    window.setTimeout(function () {
                        if (drawStatus && drawStatus.textContent === 'Saved') drawSetStatus('', '');
                    }, 1800);
                },
                error: function (xhr) {
                    drawRevert(card, originList, originNext, originContainer, targetContainer);
                    var message = (xhr.responseJSON && xhr.responseJSON.msg)
                        || 'The team could not be moved. Refresh the page and try again.';
                    drawSetStatus(message, 'error');
                }
            });
        };

        if (!drawLocked) {
            drawRoot.addEventListener('dragstart', function (event) {
                var card = event.target.closest('[data-lsp-team-card]');
                if (!card || card.classList.contains('is-saving')) {
                    event.preventDefault();
                    return;
                }
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', card.getAttribute('data-team-id') || '');
                }
                card.classList.add('is-dragging');
            });

            drawRoot.addEventListener('dragend', function (event) {
                var card = event.target.closest('[data-lsp-team-card]');
                if (card) card.classList.remove('is-dragging');
                drawContainers.forEach(function (container) {
                    container.classList.remove('is-drop-target');
                });
            });

            drawContainers.forEach(function (container) {
                container.addEventListener('dragover', function (event) {
                    event.preventDefault();
                    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
                    container.classList.add('is-drop-target');
                });
                container.addEventListener('dragleave', function (event) {
                    if (!container.contains(event.relatedTarget)) {
                        container.classList.remove('is-drop-target');
                    }
                });
                container.addEventListener('drop', function (event) {
                    event.preventDefault();
                    container.classList.remove('is-drop-target');
                    var card = drawRoot.querySelector('[data-lsp-team-card].is-dragging');
                    if (!card && event.dataTransfer) {
                        var draggedId = event.dataTransfer.getData('text/plain');
                        if (draggedId) {
                            card = drawRoot.querySelector('[data-lsp-team-card][data-team-id="' + draggedId + '"]');
                        }
                    }
                    if (card) drawMoveCard(card, container);
                });
            });
        }

        drawRoot.addEventListener('click', function (event) {
            var moveButton = event.target.closest('[data-lsp-move-target]');
            if (!moveButton) return;
            event.preventDefault();
            var card = moveButton.closest('[data-lsp-team-card]');
            var targetId = moveButton.getAttribute('data-lsp-move-target') || '0';
            var targetContainer = null;
            drawContainers.forEach(function (container) {
                if (!targetContainer && container.getAttribute('data-stage-group-id') === targetId) {
                    targetContainer = container;
                }
            });
            var details = moveButton.closest('details');
            if (details) details.open = false;
            if (card && targetContainer) drawMoveCard(card, targetContainer);
        });

        document.addEventListener('click', function (event) {
            if (event.target.closest('.lsp-team-move')) return;
            drawRoot.querySelectorAll('.lsp-team-move[open]').forEach(function (details) {
                details.open = false;
            });
        });

        drawRefreshCounters();
    }
})();
</script>
</body></html>
