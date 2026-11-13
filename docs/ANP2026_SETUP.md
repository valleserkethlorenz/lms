# ANP2026 Basketball Tournament â€” LMS Setup Runbook

How to run the *Araw ng Panabo 2026* Basketball Tournament (Basketball Guidelines ANP2026)
in the LMS: four division leagues, bracket â†’ crossover/pools â†’ playoffs, forfeits,
protests and eligibility.

> Progression rules in general are documented in `TOURNAMENT_PROGRESSION.md`.
> The volleyball equivalent of this document is **`ANP2026_VOLLEYBALL_SETUP.md`**.
> This file is the tournament-specific runbook for the CSDP staff.

---

## 0. What maps to what

| Manual | LMS |
|---|---|
| 4 divisions (Inter-Barangay I, II, SK, SHS 17-under) | **4 separate leagues** (each has its own registration, standings and bracket) |
| Elimination Round in Brackets Aâ€“D | **Stage 1** â€” `Group / Bracket` stage with 4 groups (round robin inside each) |
| Crossover (A1vD2, A2vD1, B1vC2, B2vC1) + pools | **Stage 2** â€” `Crossover / Pool` stage that *carries previous results* |
| Quarterfinals 1v4 / 2v3 per pool, Semifinals, Final | **Stage 3** â€” `Knockout` stage (QF â†’ SF â†’ Final) |
| Battle for Third | **Stage 4** â€” `Placement` stage (created automatically) |
| 12â€“15 players + optional officials | Min players 12 / max players 15 at league creation; every roster row counts as a player. A team representative may be a captain, coach, or representative; officials are optional and reviewed manually |
| One team per barangay, no double entry | Barangay is captured at registration and verified manually; the duplicate-player block (same name + birthdate in the same sport) is automatic |
| Forfeits (15-min grace), double forfeit, 10-point dress-code award | Result types on the Committee result form |
| Written protest within 24 hours, Technical Committee decision | Protest / reopen result (Schedule page flag button) |

---

## 1. Create the four division leagues

Organizer â†’ **League Management â†’ Create League** (repeat per division):

* **Sport:** Basketball Â· **Category:** Male
* **Division:** e.g. `Inter-Barangay Division I`
* **Structure:** `Pools â†’ crossover â†’ playoffs` â€” this pre-builds Stage 1
  (Brackets Aâ€“D), Stage 2 (Crossover / Pools) and Stage 3 (Playoffs). *(The league
  format model was removed; Structure is the only choice.)*
* **Maximum teams:** your entry count Â· **Min players 12 / Max players 15**
* Dates: registration window + league dates (these drive the automatic status: Upcoming â†’ Open â†’ Ongoing)
* Publish the league when the details are final.

> **Automation:** on the first draw the bracket groups are seeded with the approved
> teams, and each completed stage **auto-promotes** its qualifiers â€” top 2 of each
> bracket feed the pools, top 4 of each pool feed the playoffs. The manual
> promotion steps in sections 4â€“6 remain for custom layouts or re-seeding.

---

## 2. Document requirements

**Admin â†’ Utilities â†’ Document Types** â€” add and activate:

1. Barangay / SK / School endorsement
2. Voter's Certification **or** National ID
3. PSA Birth Certificate
4. School ID (SHS division)

Then attach them as *required* per league (league creation/edit â†’ document requirements, `applies_to` = player or team).

---

## 3. Eligibility & roster rules (checked at registration)

Set **Min players 12 / Max players 15** when creating each division league. The
per-league Eligibility & Roster Rules panel was removed, so the remaining rules are
either enforced automatically or verified manually by the organizer.

Automatically enforced at registration:

* **Roster size** â€” every submitted roster row counts toward the league's configured
  min/max. Team officials are optional; the team representative may be a captain,
  coach, or any representative.
* **Age** â€” the division's min/max age bracket is enforced.
* **Double entry** â€” the same player (name + birthdate) in another active team of the
  same sport is rejected, which enforces the manual's cross-division ban.

Checked manually by the organizer before approving:

| Rule | Div I / II | SK Division | SHS Division |
|---|---|---|---|
| Birth year (from each player's birthdate) | free | **2004â€“2008** | **2009 or later** |
| Age | free | 18 / 22 | 0 / 17 |
| Team barangay / school supplied | âœ… | âœ… | âœ… (school) |
| Only one team per barangay | âœ… | âœ… | â€” (school-based) |

---

## 4. Stage 1 â€” Elimination Round (Brackets Aâ€“D)

**Setup â†’ Stages** â†’ **+ Add stage**

1. **Add stage:** name `Elimination (Brackets)`, type *Group / Bracket stage*, qualifiers per group `2`.
2. **Add groups:** `Bracket A`, `Bracket B`, `Bracket C`, `Bracket D`.
3. **Assign teams** to each bracket (the team dropdown under each group).
4. Approve the registrations as they arrive; the per-bracket tables fill as results come in.

Round-robin fixtures are generated in **Setup → Matches** (preview, then generate). For ad-hoc
fixtures use **Schedule → Add Match** (tag the *Stage / Bracket*) or *Generate Draft Pairings*,
then set **date / time / venue / Committee** and **Publish** so the assigned Committee can submit
the result. The **Setup → Matches** step shows the stage as complete when all fixtures are played.

---

## 5. Stage 2 â€” Crossover / Pools

**Setup â†’ Stages** â†’ **+ Add stage**

1. **Add stage:** name `Crossover / Pools`, type *Crossover / Pool stage*, teams that advance per group `4`,
   tick **This stage's table also counts the earlier stages' results**.
2. **Advance qualifiers:** *From stage* `Elimination (Brackets)` â†’ *Into stage* `Crossover / Pools`,
   **Teams taken from each group** `2`, open **Advanced: combine groups into new pools** and pick *Crossover* (mapping `0,3|1,2`), keep *mark non-qualifiers eliminated* ticked â†’ **Advance qualifiers**.
   * Pool 1 = top 2 of Bracket A + top 2 of Bracket D
   * Pool 2 = top 2 of Bracket B + top 2 of Bracket C
   * Everyone else is marked eliminated and can no longer be scheduled.

**Setup → Matches** → the *Crossover / Pools* draw →
`Crossover â€” A1 v D2, A2 v D1, B1 v C2, B2 v C1` â†’ **Preview** â†’ **Generate**.

Publish and play those four games. The pool tables now show the bracket results **plus** the
crossover game (carry-over), which is exactly how the manual ranks the pools.

---

## 6. Stage 3 â€” Playoffs (QF â†’ SF â†’ Final) and Battle for Third

**Setup â†’ Stages** â†’ **+ Add stage**

1. **Add stage:** name `Playoffs`, type *Knockout stage*, qualifiers per group `4`.
2. **Advance qualifiers:** *From stage* `Crossover / Pools` â†’ *Into stage* `Playoffs`, **Teams taken from each group 4**
   (the top 4 of each pool advance; nobody is eliminated here).

**Setup → Matches** → the *Playoffs* draw →
`Full playoffs â€” QF, semifinals, final and Battle for Third` â†’ **Preview** â†’ **Generate**.

The generator creates the quarterfinals with real teams and seeds (`1 v 4`, `2 v 3` per pool),
the semifinals and the final as placeholders, and the **Battle for Third** in a new *placement*
stage fed by the two semifinal losers.

Then, round by round: set **date / time / venue / Committee** â†’ **Publish** â†’ the Committee submits
results. The engine advances automatically:

* QF complete â†’ semifinals filled
* SF complete â†’ **Final + Battle for Third filled**
* Final complete â†’ **champion marked Qualified**, everyone else eliminated

---

## 7. Forfeits, walkovers, double forfeits and penalties

On the Committee result form choose the **Result type**:

| Situation | Result type | Effect |
|---|---|---|
| Team absent 15 min after the grace period, or no authorised official present | **Win by forfeit** | pick the winner; stored as 20â€“0 with the note; the forfeiting team gets 0 points |
| Opponent did not appear (walkover) | **Walkover** | same as forfeit |
| Both teams at fault | **Double forfeit** | no winner and nobody advances â€” **the Tournament Organizer decides** how the pairing proceeds (the engine flags it and waits) |
| Protest upheld by the Technical Committee | **Win by decision** | pick the winner and keep the real score |
| Dress-code violation (automatic +10) | any type | put **+10** in the offending team's opponent *Adjustment* box â€” it is added to that team's points-for |

---

## 8. Protests and result corrections

1. The coach/captain informs the panel immediately and signs the scoresheet; the written protest
   must reach the Organizer **within 24 hours** (the system enforces the window for team/committee
   filings â€” the Organizer may still act later).
2. **Schedule â†’ the match â†’ flag button** â†’ *Log protest / reopen result*:
   * **Uphold** â€” restores the affected teams' status, removes the bracket rows that result created,
     empties the placeholders it had filled, and reopens the match for a corrected result.
   * **Deny** â€” the official result stands.
   * **Log only** â€” record it and decide later (the match shows *Protested*).
3. The match returns to `Scheduled` with the previous result cleared, so the Committee can submit the
   corrected score. Every step is audit-logged.

---

## 9. Standings rules in use

* **Points:** win = **2**, loss = **1**, forfeit = **0** (basketball defaults applied by the migration).
* **Tiebreak order:** **head-to-head â†’ quotient (PF Ã· PA) â†’ point difference â†’ points for â†’ team name**.
* **One ranking engine:** the organizer table, the public standings page, the stage tables and the
  reports all read the same shared provider, so the configured points and tiebreak order apply
  everywhere (previously only the stage tables honoured them).
* `PF`/`PA` include point adjustments in every table, so the +10 dress-code award is reflected everywhere.

---

## 10. Day-of-operations checklist

1. Confirm the match is **published** and has a **Committee** assigned â€” otherwise no result can be recorded.
2. The Committee submits the score/result type; the bracket advances immediately.
3. Watch the Schedule page *Tournament Progression* panel and the **Setup → Matches** step for round completion.
4. Log forfeits and protests the same day (the 24-hour window starts at the result).
5. Publish the Final with its date and venue so the **champion** is decided on the court.

---

## 11. Known limitations to plan around

* **No best-of series** â€” every matchup is one game. If a BO series is ever used, record the series
  score (e.g. 2â€“1) in the single row.
* **Per-game detail** (quarter scores) is not stored; the signed scoresheet remains the record.
* **Quarter lengths** (4Ã—8, 8/8/8+10, 4Ã—10) are not stored; the scheduler uses the league's match
  duration for slot spacing.
* **Barangay / school** is a free-text field typed at registration and verified against the submitted documents.
* Livestream links, roster checks and disciplinary records stay with the Committee/Organizer.

