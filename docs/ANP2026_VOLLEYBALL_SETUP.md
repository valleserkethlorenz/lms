# ANP2026 Volleyball Tournament â€” LMS Setup Runbook

How to run the *25th Araw ng Panabo* Volleyball Tournament (Volleyball Guidelines ANP2026)
in the LMS: three divisions, two pools, set-based scoring, the finals patterns, forfeits,
protests and eligibility.

> General progression rules: `TOURNAMENT_PROGRESSION.md` Â· Basketball runbook: `ANP2026_SETUP.md`.
> Schema/seed migration: `database/migrations/035_volleyball_anp2026.sql`.

---

## 0. What maps to what

| Manual | LMS |
|---|---|
| Â§6.1 Single Round Robin, **Pool A + Pool B**, all categories | Two groups on a *Group* stage + the **"Round robin inside each group / pool"** generator |
| Â§6.1 "pool winners advance to the Finals, losing teams play for Third" together with Â§4.1's semifinal round | **Finals pattern** (per league, F1 default): see Â§4 below |
| Â§4.1 BO3 from the Elimination Round to the Semifinals | `best_of = 3` on the pools stage (inherited by every match) |
| Â§4.2 Final is **BO5** | The championship match is generated with `best_of = 5` |
| Â§4.3/4.4 sets to 25, deciding set to 15, win by 2 | Set-score entry with automatic validation |
| Â§6.2 Win 2-0 = **3** pts, Win 2-1 = **2**, Loss 1-2 = **1**, Loss 0-2 = **0** | Set-line scoring in every standings table |
| Â§6.3 tie â†’ matches won | Tiebreak order `wins â†’ set ratio â†’ point ratio â†’ head-to-head` |
| Â§10 forfeit lines 0-2 (0-25, 0-25) / 0-3 (0-25 Ã—3), defaulting team 0 pts | Automatic forfeit line when the Committee records a *Forfeit* / *Walkover* result for a match |
| Â§7.2 written protest within **30 minutes** | Configurable protest window (Volleyball = 30 minutes) |
| Â§7.4 ineligible player â†’ **all his games forfeited** | Record each affected match as *Forfeit* from the Committee's result entry (name the player in the result note) |
| Â§5.4 "No Coach, No Play" | Not enforced at registration; organizers/committee may review team officials manually |
| Team composition: 14 players incl. 2 Liberos + officials | Role labels are free text; roster size is enforced by the league's min/max. Composition is reviewed manually |
| Â§5.5 jerseys 1â€“99 | Jersey range enforced at registration |
| Eligibility page (3 categories) | Three leagues: Inter-Barangay Men's, Inter-Barangay Women's, Inter-School (Open) |

---

## 1. Create the three leagues

Organizer â†’ **Create League** (repeat per category):

| League | Category | Division | Notes |
|---|---|---|---|
| Inter-Barangay Volleyball (Men's) | Male | Open / Inter-Barangay | barangay endorsement + Voter's Certification or National ID |
| Inter-Barangay Volleyball (Women's) | Female | Open / Inter-Barangay | same documents |
| Inter-School Volleyball (Open) | Male | Open | **School Principal endorsement + valid School ID**; the barangay field carries the **school** |

Common settings: **Sport: Volleyball** (roster defaults 12â€“14 fill in automatically),
**Structure: Groups â†’ knockout** (two pools + a finals stage; pick F1/F2 when
generating the finals), min players 12 / max players 14, published when final.

Document requirements (Admin â†’ Utilities â†’ Document Types) then attach per league:
Barangay Captain / SK Chairman **or** School Principal endorsement, Voter's Certification /
National ID, School ID.

---

## 2. Eligibility & roster rules (checked at registration)

Set **Min players 12 / Max players 14** when creating each volleyball league. The
per-league Eligibility & Roster Rules panel was removed; the remaining rules are
enforced automatically or verified manually by the organizer.

Automatically enforced at registration:

* **Roster size** â€” every submitted roster row counts toward the league's configured
  min/max.
* **Jersey range** â€” jerseys **1â€“99** for Volleyball.
* **Double entry** â€” the same player (name + birthdate) in another active team of the
  same sport is rejected.

Checked manually by the organizer before approving:

* **Team composition** â€” Libero count, regular players and team officials. Role labels
  are free text and are not used to block registration.
* **Barangay / school supplied** (residency, Â§2).
* **One team per barangay** where the guidelines require it
  (Inter-Barangay: your call Â· Inter-School: not required).

A coach is not required at registration; the team representative may be a captain,
coach, or any representative.

---

## 3. Stage 1 â€” Elimination Round (Pool A, Pool B)

**Setup â†’ Stages** â†’ **+ Add stage**

1. **Add stage:** `Elimination (Pools)`, type *Group / Bracket stage*.
2. **Add groups:** `Pool A`, `Pool B`; assign the teams to each pool.
3. **Setup → Matches** → the pools draw applies
   **"Round robin inside each group / pool (single round robin)"** â†’ **Preview** â†’ **Generate**.
   Each pool plays a full single round robin (4 teams â†’ 6 games, 6 teams â†’ 15 games).
4. Set date / time / venue / Committee per match and **Publish**; the Committee then submits
   the **set scores** (see Â§5).

The pool tables rank by the Volleyball rules automatically.

---

## 4. Stage 2 â€” Finals (F1 by default, F2 as a league setting)

Volleyball defaults to **F1**. The Setup wizard applies the league's configured
`tbl_league.finals_pattern` automatically when the finals bracket is generated; F2
(`direct_final_bronze`) remains available as a league-level setting.

| | **F1 â€” `semis_final_bronze`** (default) | **F2 â€” `direct_final_bronze`** |
|---|---|---|
| Generated matches | Semifinal 1 `A1 v B2`, Semifinal 2 `B1 v A2` (BO3) then Championship `W SF1 v W SF2` (**BO5**) and Bronze `L SF1 v L SF2` (BO3) | Championship `A1 v B1` (**BO5**) and Bronze `A2 v B2` (BO3) |
| Reading of Â§6.1 | "losing teams compete for Third Place" = the semifinal losers | "pool winners advance to the Finals" read literally |

Setup: create a **Knockout** stage named `Finals` with **two groups**
(`Pool A Qualifiers`, `Pool B Qualifiers`), point each group at its source pool
(Stages â†’ *Fed by*), promote the **top 2** of each pool
(*Advance qualifiers*: from `Elimination (Pools)` into `Finals`, teams taken from each group `2`),
then generate the bracket; the league's configured finals pattern is applied. The bronze match is created in the placement stage
and filled automatically from the semifinal losers; the champion is declared only when
the BO5 final is completed.

---

## 5. Recording a volleyball result (Committee)

**Committee â†’ My Assigned Matches â†’ the match.** The form shows one row per set
(Set 1â€“2 at 25 points for BO3, the deciding set at 15; the BO5 final shows five rows):

1. Enter only the sets actually played â€” e.g. `25-20, 22-25, 25-18`; leave the rest blank.
2. The system validates: no level set, winner reaches the target, minimum two-point lead,
   and the match ends as soon as a team wins 2 (BO3) or 3 (BO5) sets. Extra sets after the
   match was decided are rejected.
3. **Result type** for anything that is not played on the court:
   * *Win by forfeit* / *Walkover* â€” the official line is written automatically
     (0â€“2 with 0â€“25 sets for BO3, 0â€“3 with 0â€“25 sets for BO5).
   * *Double forfeit* â€” no winner and no automatic advance; the Organizer decides.
   * *Win by decision* â€” a protest ruling; pick the winner.
4. Submitting advances the bracket immediately (semifinal winners â†’ BO5 final,
   losers â†’ bronze match).

The saved score line `2 - 1` always means **sets won**; the per-set detail appears next to it.

---

## 6. Standings rules in use

* **Match points (Â§6.2):** Win 2-0 = **3**, Win 2-1 = **2**, Loss 1-2 = **1**, Loss 0-2 = **0**,
  forfeit/default/withdrawal = **0**.
* **Tiebreak (Â§6.3):** **matches won** first, then **set ratio**, then **point ratio**,
  then head-to-head, then team name.
* The pools table shows `Pts`, `SW` (sets won), `SL`, `Set Ratio`, `PW/PL` (points won/lost).
* The same numbers feed the organizer table, the public standings page and the stage tables â€”
  one shared ranking engine.

---

## 7. Defaults, forfeits and withdrawals (Â§9, Â§10)

1. A team not on court **15 minutes** after the scheduled time is declared in default;
   the game is awarded to the opponent (record *Win by forfeit* / *Walkover*).
2. The defaulting team receives **0 match points**; the awarded line is
   `0â€“2 (0â€“25, 0â€“25)` for BO3 and `0â€“3 (0â€“25, 0â€“25, 0â€“25)` for BO5.
3. When the Technical Committee decides under Â§10 that earlier matches must also be
   forfeited, record each affected match from the Committee's result entry with the result
   type *Forfeit* (or *Walkover*) and name the decision in the result note. The official
   line above is applied to the match, and the audit log keeps the record.
4. A team may be excluded from the succeeding rounds: use the existing progression tools
   (the team is marked *Eliminated* and can no longer be scheduled).

---

## 8. Protests and ineligible players (Â§7)

1. A protest must be filed **in writing within 30 minutes** of the game, signed by the
   Captain **and** Coach, with evidence. Eligibility protests go to the Screening/Eligibility
   Committee, technical protests to the Technical Manager.
2. **Schedule â†’ the match â†’ flag button** â†’ *Log protest / reopen result*:
   **Uphold** reverses that result's progression (bracket rows removed, placeholders emptied)
   so the Committee can submit the corrected line; **Deny** leaves it standing; **Log only**
   records it for later.
3. If a protest is upheld because a player is ineligible, Â§7.4 requires **all games in which
   the player participated** to be forfeited in favour of the opponents. Record each affected
   match from the Committee's result entry as *Forfeit*, naming the player and the Technical
   Committee decision in the result note. The audit log keeps the full trail for
   suspensions/disqualification.

---

## 9. Day-of-operations checklist

1. Publish each match with date, time, venue and an active Committee â€” otherwise no result can be recorded.
2. Committee submits sets; confirm the score line (e.g. `3 - 2`) looks right on the match card.
3. Watch the **Setup → Matches** step for round completion; the final + bronze fill themselves after the semifinals.
4. Log defaults the same day (0â€“25 lines are applied automatically) and file protests within 30 minutes.
5. Publish the BO5 championship so the champion is declared on court.
6. Check the standings page after every game â€” the pool table drives who advances.

---

## 10. Known limitations to plan around

* **No rally-by-rally tracking** â€” the set scores are the record; the signed scoresheet stays authoritative.
* **No substitution/libero tracking during play** â€” the roster holds the 1â€“2 Liberos; the unlimited
  replacement rule and the "Libero as a regular player" allowance remain on-court decisions.
* **Set targets are uniform per sport profile** (25/15, win by 2). If a category ever plays
  different targets, they are league columns (`tbl_league` / `tbl_utility`) that can be changed without code.
* **Net height, attire, bench officials and awards** (MVP, Best Spiker, â€¦) are not stored â€” the
  awards module is not part of this build.
* **Pool structure**: the finals pattern handles both readings of Â§6.1; confirm with the Technical
  Manager which one applies before generating the bracket.

