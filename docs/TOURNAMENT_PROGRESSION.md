# Tournament Progression Guide (LMS)

How a league advances from registration to champion — for every structure, plus what the
Organizer can control by hand.

> For the ANP2026 basketball tournament (brackets → crossover/pools → playoffs, forfeits,
> protests, eligibility rules) see **`ANP2026_SETUP.md`**.
> For the ANP2026 volleyball tournament (two pools, set scoring, F1/F2 finals, 30-minute
> protests) see **`ANP2026_VOLLEYBALL_SETUP.md`**.

> **Golden rule:** progression is driven by the **stage** a match belongs to
> (`tbl_schedule.stage_id` → `tbl_league_stage.stage_type`), **not** by the sport.
> Basketball, Volleyball, DOTA, Pickleball … all progress identically; the sport only
> affects roster/player limits and scoring rules.

---

## 1. Who does what

| Role | Responsibilities |
|---|---|
| **Organizer** (role 1) | Creates + publishes the league, generates the schedule, publishes each match (date / time / venue / assigned Committee), manages groups and progression marks. **Cannot submit results.** |
| **Committee** (role 4) | Assigned per match. Submits the official score — **the only action that advances a tournament**. |
| Team Rep / public | View published schedules and standings. |

Key tables: `tbl_league` (`stat`, `grouping_enabled`),
`tbl_league_stage` + `tbl_league_stage_group` + `tbl_league_stage_group_team`,
`tbl_team` (`status`, `progression_status`),
`tbl_schedule` (`status`, `published_at`, `winner`, `round`, `stage_id`,
`stage_group_id`), `tbl_scheduling_config`, `tbl_audit_log`, `tbl_notification`.

---

## 2. Lifecycle (same for every structure)

1. **Create league (Draft)** → visible only to the organizer.
2. **Publish League** → `tbl_league.stat` is recomputed (`leagueAction.php`).
   Values: `Upcoming` → `Open` → `Ongoing` → `Completed` (date-driven, see
   `config/leagueStatus.php`).
3. **Teams register**; organizer **Approves** them (`tbl_team.status = 'Approved'`,
   `progression_status = 'Undecided'`). Each approval recomputes `stat`.
   Only **approved** teams occupy a slot (`config/leagueStatus.php →
   countLeagueApprovedTeams()`); Pending / Correction Requested applications are
   queued for review and never fill the league, so registration stays Open until
   the organizer approves `max_team` teams (approving past the limit is refused).
4. **Registration closes** (window ends, slots fill, or organizer clicks *Close
   Registration*) → `stat = 'Ongoing'`.
   *Automatic schedule generation requires `stat = 'Ongoing'`; manual match creation
   only requires that registration is closed / slots are full.*
5. **Generate the fixtures** — a stage league draws them in **Setup → Matches**
   (preview, then generate); a league without a structure can use **Generate Schedule**
   (auto pairing) or **Add Match** on Schedule. Every fixture is created as a **Draft**;
   auto-paired matches get a date/time from `tbl_scheduling_config` (defaults: 60-min
   matches, 15-min rest, 08:00-22:00, max 10/day) plus conflict detection, while
   Setup-generated stage fixtures start with **no date, time, venue or Committee** —
   the organizer fills those in on Schedule.
6. **Publish each match** — requires date + time + venue + an **active Committee**.
   Sets `published_at = NOW()`, `status = 'Scheduled'`; notifies the Committee and the
   teams. A match containing an eliminated/ineligible team cannot be published.
7. **Committee submits the score** (one submission per match, published matches only):
   `winner` = higher score, `0` when drawn; `status = 'Completed'`; `published_at` set.
8. **The progression engine runs immediately** (`progressMatchResult()`, called from
   `app/committee/manage_match.php`) and branches on the match's stage type.
9. **Standings / KPI cards update.** Only rows with `published_at IS NOT NULL`,
   `LOWER(status) = 'completed'` and `is_archived = 0` are ever counted.
10. Notifications: round complete / group stage complete / tournament complete →
    organizer; match result → teams + organizer.

---

## 3. Structures

League creation no longer asks for a structure: the organizer applies one of the **five
structures** on the Setup page after the league is saved (Setup now / Set up later). The
chosen preset seeds the stage/group skeleton and can only be applied once — before the first
stage or match exists. There is no league "format" column or Admin format list any more.

| Structure | Stages created | Generation | Progression |
|---|---|---|---|
| **Round robin** | `League Stage` (group) + `Group A` | `round_robin_groups` from Setup step 3 (default draw + preview) | standings; optional seeded playoffs from Setup step 3 |
| **Round robin + playoffs** | `League Stage` (group) + `Group A`, then `Playoffs` (knockout, empty) | `round_robin_groups`, then `Create playoffs` → `seeded_bracket` | standings; top N promoted; QF/SF/Final advance automatically |
| **Single elimination** | `Bracket` (knockout) | `seeded_bracket` (seeds 1…N, byes) | winners advance; final → champion |
| **Groups → knockout** | `Group Stage` (2–8 groups) + `Knockout` | RR in each group, then promote top N and generate a knockout pattern | group tables; promotion; knockout advances |
| **Pools → crossover → playoffs** | Brackets A–D → Crossover/Pools → Playoffs | RR in brackets; crossover; full playoffs | auto-promotes 2 per bracket, then 4 per pool; auto champion |
| **Custom** | none | round-robin pairing assistant (no stages) | standings only |

**Every match belongs to a stage.** The engine reads `tbl_schedule.stage_id` and
`progressStageMatch()` branches on the stage type:

* `group` / `crossover` — update the table; report round/stage completion; when the
  stage has `qualifiers_per_group`, **auto-promote** its qualifiers into the next stage.
* `knockout` — eliminate the loser, fill the winner's exact next-round slot immediately,
  feed the Battle for Third; the final winner becomes `Qualified` (champion).
* `placement` — record the third-place result.

The **first stage's groups are seeded with the approved teams** on the first draw
(round-robin by team name), so the organizer only has to generate.

Fixture generation (round robins, brackets, crossover, playoffs and qualifier
advancement) lives **only on Setup**; the Schedule page owns dates, venues,
Committee assignment, publishing and results.

**Pools → crossover → playoffs** is fully wired: `qualifiers_per_group` is `2` on the
brackets stage and `4` on the pools stage. Declared pool sources (Pool 1 ← Bracket A + D,
Pool 2 ← Bracket B + C) are honoured and the promotion seeds drive the knockout pairings.
Manual assignments in the target stage always win over automatic promotion.

**Single elimination** uses the `seeded_bracket` pattern: teams are seeded 1…N, paired
`1 v N`, `2 v N-1` …, and byes advance automatically (bye rows are completed one-team
rows and never block a round). A completed winner is placed into its exact next-round
slot immediately; the bracket does not wait for the whole round to finish.

Double Elimination and Swiss System were retired with the format model; there is no
stage equivalent for them.

---

## 4. Automatic vs manual progression

**Automatic** — Committee submits a result → `progressMatchResult()` (per stage).
**Manual** — the Organizer uses the Standings **Progression Manager** and/or hand-built
matches. Both paths are always available; manual marks are what the schedule pickers and
the publish checks read.

⚠️ **Confirm Selection is a sledgehammer:** the ticked teams become `Qualified` and
**every other approved team becomes `Eliminated`**
(`bulkSetProgression`, `includes/tournamentProgression.php`). Eliminated teams can no
longer be scheduled/published and are dropped from generation. **Reset All** clears
everyone back to `Undecided`.

---

## 5. What the organizer can change by hand

| Control | Where | Endpoint | Rules |
|---|---|---|---|
| **Add a match** | Schedule → Add Match | `createMatch` | Registration closed / slots full. Both teams approved + not eliminated. Active venue; optional active Committee; optional Match Group (both teams must be in it). Saved as **Draft**. |
| **Publish a match** | Schedule → Publish | `publishMatch` | Date + time + venue + active Committee, no conflicts, both teams eligible. |
| **Reschedule / swap teams / reassign Committee** | Schedule → Edit | `updateMatch` | Blocked once `Completed`. |
| **Delete / void a match** | Schedule → Delete | `deleteMatch` | Soft delete: `is_archived=1`, `status='Cancelled'` (excluded from standings). |
| **Mark one team** ✓ / ⊘ / ↺ | Standings → row buttons | `setProgression` | `Qualified` / `Eliminated` / `Undecided` (or *Reset All*). |
| **Confirm / reset the cut** | Standings → Progression Manager | `confirmProgression`, `resetProgression` | Bulk as described in §4. |
| **Groups** | Standings → Group Setup | `addGroup`, `deleteGroup`, `assignGroup` | Unique per league, ≤ 80 chars; deleting a group unassigns its teams. |

**Not possible:** entering or editing official results — that is exclusively the assigned
Committee's (`updateResult` returns 403 by design), and a match's score cannot be changed
after it is completed.

---

## 6. Fully manual workflow (works with any structure)

1. Close registration → make sure the league shows **Ongoing**. If it was closed by
   mistake, **Reopen Registration** on the league overview restores it while the
   registration end date has not passed.
2. Approve teams; set up groups if the league is grouped.
3. Build round 1: *Generate Schedule*, or **Add Match** one by one.
4. For each match: set date / time / venue / Committee → **Publish**.
5. Committee submits each result.
6. On **Standings**, press **✓** for teams that advance and **⊘** for teams that are done
   (or *Get Suggestions → Confirm Selection* for the whole cut).
7. **Add Match** for the next round using only surviving teams (eliminated teams are
   hidden from the pickers) → publish → repeat until one team remains.
8. Mark the final winner **✓ Qualified** — that is also what the Schedule page's
   "Tournament complete / champion" panel reads.

---

## 7. Data model the engine relies on

| Column | Meaning for progression |
|---|---|
| `tbl_league_stage.stage_type` | Selects the progression branch (`group` / `crossover` / `knockout` / `placement`). |
| `tbl_league.stat` | Must be `Ongoing` for automatic schedule generation. |
| `tbl_league.grouping_enabled` | Turns group tables / the Match Group selector on. |
| `tbl_team.status` | Only `Approved` teams are scheduled. |
| `tbl_team.progression_status` | `Undecided` / `Qualified` / `Eliminated`; drives pickers and publishing. |
| `tbl_schedule.status` | `Draft` → `Scheduled` (published) → `Completed`; Setup-generated stage matches start as `Draft`, and bye rows auto-complete. |
| `tbl_schedule.published_at` | Only published rows count in standings/KPIs. |
| `tbl_schedule.winner` | Winning team id, `0` for a draw. |
| `tbl_schedule.round` | Round number used for round-complete checks. |
| `tbl_schedule.stage_id` | The stage a match belongs to; stage rows progress through `progressStageMatch()`. |
| `tbl_schedule.stage_group_id` | The stage group (pool / bracket) the match counts toward. |
| `tbl_schedule.bracket` | Display/label only now (`stage_id` drives progression). |
| `tbl_schedule.league_group_id` | Optional legacy `tbl_league_group` link used by the Standings group filter. |
| `tbl_schedule.is_archived` | Archived/void matches are ignored everywhere. |

---

## 8. Known limitations

1. **Draws in knockouts** remove both teams from the next round (`winner = 0` is filtered
   out of the winners list). Force a decisive result.
2. **The seeded single-elimination bracket accepts any team count from 2 upwards**;
   missing seeds become byes. Double Elimination and Swiss no longer exist.
3. **Cancelled / postponed matches block round completion** — the round-complete check
   compares "all playable rows" vs "completed rows". Void or reschedule them.
4. **`stat` can be stale.** It is only refreshed by league publish/archive/restore, team
   approval, or *Close Registration* / *Reopen Registration*. If schedule generation
   says "League must be Ongoing", re-save the league or toggle registration.
5. **Champion detection is loose:** `getProgressionSummary()` reports
   `tournament_complete` + a champion as soon as **any** team has
   `progression_status = 'Qualified'`. Confirming qualifiers early therefore makes the
   Schedule panel claim the league is finished.
6. **Manual matches must be tagged to a stage** (Stage / Bracket selector) to count in
   that stage's table/bracket. Untagged rows are standings-only. Table stages also
   require a stage group; bracket stages are drawn by the engine (manual entry is
   limited to a standalone two-team final) so the node/round links stay consistent.
7. **Round robin + grouping** uses the stage groups: assign teams in **Setup →
   Stages**, then generate the round robin inside those groups.
8. **The standings formula is generic** (`W = 3`, `D = 1`, raw points-for/against).
   Sport-specific scoring (sets, frames) is stored in
   `tbl_schedule.result_breakdown` (JSON) but is **not** used for ranking.

---

## 9. Stage-based leagues (ANP2026)

A league is driven by **stages**. Stages are created on the Setup page
(*Stages* card, after picking the *Multi-stage tournament* format) and generated
on the Setup page (*Matches* step, with the automatic default draw and preview).

| Stage type | Purpose | Auto-advance |
|---|---|---|
| `group` | Brackets / groups, round robin inside each | No — the table decides; the stage reports completion |
| `crossover` | Crossover games + pools; can **carry the previous stage's results** into the pool table | No — pools are ranked, then qualifiers are promoted |
| `knockout` | QF (1v4 / 2v3 per pool) → SF → Final | Yes — losers eliminated, next round filled, champion on the final |
| `placement` | Battle for Third, fed by the semifinal losers | Records the winner only |

* **A team belongs to a group per stage**, which is what allows *Bracket A* in stage 1 and
  *Pool 1* in stage 2.
* **Promote qualifiers** carries the top N of **each** source group into the target stage
  (optionally mapping several brackets into one pool, e.g. `0,3|1,2`).
* **Match generation applies the stage's default draw** — the first pattern in the engine's
  preference list — so the organizer is never asked to choose one. A *group* stage draws
  **`round_robin_groups`** (single round robin inside each group / pool), a *crossover*
  stage draws the crossover pairs or a pool round robin, a *knockout* stage with one pool
  draws **`seeded_bracket`**, and a *knockout* stage fed by two or more pools draws the
  league's configured **finals pattern** when the sport defines one (Volleyball: F1
  `semis_final_bronze` by default, via `tbl_league.finals_pattern`) or the pool
  quarterfinals otherwise. Nothing is written until Preview/Generate is confirmed (Swiss
  rounds and double-elimination brackets generate directly); seeds
  (`A1`, `D2`, `W SF1`, `L SF2`) are stored and shown on the match cards.
* **The finals pattern comes from the league, not from a picker:** a *group* stage draws
  `round_robin_groups` (Volleyball's Pool A/B), a *knockout* stage uses the league's
  configured finals pattern, and a *placement* stage receives the bronze match from it.
  A double-elimination stage always uses the graph engine (winners, losers and grand
  final), and its generation form has no preview because the legacy preview only models
  single-elimination brackets.
* **Result types** (see §8.5 below) and **protests/corrections** work the same in stage leagues;
  a corrected result restores team statuses and removes/empties the rows that result created.

### 9.5 Result types, corrections and eligibility

| Feature | Where | Notes |
|---|---|---|
| Result type | Committee result form | `Regular` · `Forfeit` · `Walkover` · `DoubleForfeit` · `Decision`, plus a note and per-team point **adjustments** (e.g. the +10 dress-code award) |
| Forfeit rules | Standings | a forfeit win still counts as a win; the team that forfeits receives `scoring_forfeit_points` (0 for basketball) |
| Double forfeit | Engine | no winner and **no automatic advance** — the organizer decides how the bracket proceeds |
| Protests | Schedule → flag button | 24-hour window for team/committee filings; *uphold* reopens the result and reverses its progression effects, *deny* leaves it standing, *log only* records it |
| Standings rules | `tbl_league` (`scoring_*`, `tiebreak_order`) | win/draw/loss/forfeit points and tiebreak order incl. **head-to-head** and **quotient** |
| Eligibility rules | Registration (automatic) + organizer review | roster size + jersey range + **double-entry** block are enforced automatically; sport roster composition (Libero / official), birth-year band, residency and one team per barangay are checked manually by the organizer |

## 10. Fixes applied (2026-10-13)

| Fix | Where |
|---|---|
| **Format model removed** — the Setup page applies the seven structure presets (creation only saves the league); `format` / `format_code`, the Admin League Formats list, the format switch and the Double/Swiss generators are gone | `includes/leagueStructure.php`, `app/organizer/setup.php`, `app/organizer/action/leagueAction.php`, `includes/tournamentProgression.php`, `includes/schedulingHelper.php`, `admin/utility.php`, migration `migrations/037_remove_league_formats.sql` |
| Group tagging unified on `tbl_league_group` (groups created/assigned/linked on generation; per-group suggestions) | `schedulingHelper.php`, `tournamentProgression.php` |
| **Reset All** in the Progression Manager resets to `Undecided` instead of eliminating everyone; empty bulk confirms are rejected | `tournamentProgressionAction.php`, `includes/tournamentProgression.php`, `js/progression-manager.js` |
| Standings ranking no longer respects stale group assignments when grouping is off; KPI counts read published matches only | `app/organizer/standing.php`, `standing.php` |
| **Stage model** (brackets → crossover/pools → playoffs → battle for third) with stage-scoped team membership, carry-over pool standings and per-stage tables | `includes/stageHelper.php`, migration `migrations/034_anp2026_stages_and_rules.sql` |
| **Seeded bracket generation** with preview/confirm: crossover A1vD2 … B2vC1, QF 1v4 / 2v3, SF, Final and the Battle for Third | `includes/stageHelper.php`, `action/schedulingAction.php`, `schedule.php` |
| **Stage-aware progression**: group/crossover complete, knockout winners advance, SF losers fill the 3rd-place game, champion only when the final is decided | `includes/tournamentProgression.php` |
| **Configurable standings**: win/draw/loss/forfeit points + tiebreak order (head-to-head, quotient, difference, points for) | `config/scoringRules.php` |
| **Result types & penalties**: forfeit / walkover / double forfeit / decision, notes and point adjustments; double forfeit never auto-advances | `app/committee/manage_match.php`, `tournamentProgression.php`, `schedule.php` |
| **Protests & result correction**: 24-hour window, uphold/deny/log, progression log + safe reversal of eliminations, filled placeholders and created rows | `action/schedulingAction.php`, `tournamentProgression.php` |
| **Forfeit results** (§10 / §7.4): recorded per match from the Committee's result entry with the official set line; the result note carries the Technical Committee decision reference, and double forfeits never auto-advance | `app/committee/manage_match.php`, `includes/tournamentProgression.php`, `config/scoringRules.php` |
| **Eligibility rules**: league-configured roster size, jersey range and the cross-division duplicate-player block; role labels are stored as free text and sport roster composition (Libero / official) is reviewed manually; barangay captured at registration for manual verification | `includes/eligibilityValidator.php`, `includes/registerTeam.php` |


