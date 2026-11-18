# Tournament structures (setup & scheduling)

This is the reference for the **four competition formats** the LMS uses and for the
setup / scheduling workflow built around them.

> For the ANP2026 guidelines see `ANP2026_SETUP.md` and
> `ANP2026_VOLLEYBALL_SETUP.md`. For the general progression rules see
> `TOURNAMENT_PROGRESSION.md`.

---

## 1. The four formats

| Format | `stage_format` | Stage type | Shape | How it decides a winner |
|---|---|---|---|---|
| Round robin | `round_robin` | `group` | One table (or one table per group) | Standings |
| Swiss system | `swiss` | `group` | One table, a fixed number of rounds | Standings after the last round |
| Single elimination | `single_elimination` | `knockout` | Winners bracket only | The final |
| Double elimination | `double_elimination` | `knockout` | Winners bracket + losers bracket + grand final | The grand final |

`stage_type` stays the coarse bucket the older pages use. **`stage_format` is the
exact format** — it is what the setup wizard, the scheduling views and the
progression engine read (`includes/tournamentEngine.php → stageFormat()`).

A structure is a **chain of stages**:

```
Tournament (tbl_league)
  └── Stage 1  round robin  → groups, fixtures, standings
  └── Stage 2  single elimination → bracket graph
  └── Stage 3  double elimination → winners + losers + grand final
```

---

## 2. Data model

Everything lives under the existing stage tables; migration
`database/migrations/041_tournament_structures.sql` adds the rest.

### Stage

`tbl_league_stage`

| Column | Meaning |
|---|---|
| `stage_format` | `round_robin` / `swiss` / `single_elimination` / `double_elimination` / `placement` |
| `stage_status` | `Pending` → `Active` → `Locked` → `Complete` |
| `rounds_planned` | Swiss round count (auto = `ceil(log2(teams))`, minimum 3) |
| `settings_json` | Per-stage options (currently `bracket_reset` for double elimination) |
| `activated_at`, `locked_at` | Lifecycle timestamps |

### Bracket graph

`tbl_schedule` gains one row per **node** (one match) with:

| Column | Meaning |
|---|---|
| `bracket_side` | `winners` / `losers` / `grand_final` / `consolation` |
| `node_key` | `W-R2-M1`, `L-R3-M2`, `GF-M1` … (unique per stage) |
| `next_node_key`, `next_node_slot` | Where the **winner** goes |
| `loser_next_node_key`, `loser_next_node_slot` | Where the **loser** goes (losers bracket) |
| `swiss_round` | Swiss round number |
| `locked`, `locked_at` | The round/board is closed |

`seed_a` / `seed_b` keep the human label (`S1`, `BYE`, `W R1M2`, `L R3M1`) so the
bracket can be read without joining the graph.

### Transitions and seeding

| Table | Purpose |
|---|---|
| `tbl_league_stage_transition` | Legacy cut-off rules (`top_per_group`, `top_overall`, `bottom_consolation`); Setup now writes `qualifiers_per_group` |
| `tbl_league_stage_slot` | The saved seeding board: one slot per advancing team (`G1-R1`, `O-R3` …) |

---

## 3. Setup: presets, progression and draws

`app/organizer/setup.php` is the single setup workflow: Format → Teams → Matches.
The Structure tab and `structure.php` were removed; everything below is handled by
the wizard and the existing tournament engine.

### 3.1 Presets and editable settings

A preset seeds the stage/group skeleton. Each Format card also shows a short
stage flow derived from the preset (e.g. `Table → Bracket`,
`Groups ×2 → Bracket`, `WB + LB + GF`). The Format step also exposes the preset's
editable knobs:

| Preset | Setting written to the stage |
|---|---|
| Round robin + playoffs | `qualifiers_per_group` on the league stage (top N advance) |
| Groups → knockout | `groups` (2–8) and `qualifiers_per_group` on the group stage; the knockout receives one `Qualifiers` pool fed by every group |
| Pools → playoffs | `qualifiers_per_group` on the bracket stage and on the crossover stage |
| Swiss system | `rounds_planned` on the Swiss stage |

The **Groups → knockout** group count is recommended from the team count (approved
teams first, otherwise the league's team limit): the algorithm prefers balanced groups
of 4–6, top 2 per group and fewer bracket byes. The organizer can override both values.
Changing the group count rebuilds the skeleton and re-draws the approved teams; matches
already lock structural changes.

The values can be edited while the league has no matches; **Save stage settings**
updates the existing stages without deleting groups or team placements.

### 3.2 Automatic advancement

`autoPromoteStageQualifiers()` reads `qualifiers_per_group` when a group or crossover
stage completes. It promotes the top N into the next stage, respects the preset's
`tbl_league_stage_group_source` links (Pool 1 ← Bracket A + D) and marks the rest
`Eliminated`. Teams already placed manually are never overwritten.

For legacy stages without a configured count, the Matches step shows an **Advance
qualifiers** form. It posts to the same promoter and derives the pool mapping from the
saved group sources.

### 3.3 Stage draws

The Matches step follows `setupNextTask()` and shows one action at a time:

* **Preview** lists the actual matchups, grouped by round/bracket side. Double
  elimination previews the winners, losers and grand-final plan without writing
  anything.
* generate the current stage's fixtures (round robin, seeded bracket, playoff patterns),
* generate a straight seeded bracket for a multi-group → knockout preset,
* generate the next Swiss round after the current round is complete,
* advance qualifiers when a finished stage feeds an empty stage,
* wait for results on Schedule when the stage is in progress.

### 3.4 Manual fixtures

Manual fixture creation stays on the Schedule page's **Manual match** form. The old
manual matrix, stage timeline editing, cut-off variants and seeding-board overrides are
no longer part of the interface.

A manual fixture must choose a stage. Table and pool stages also require one of their
groups, and both teams must already belong to the selected stage/group. Bracket stages
are drawn by the tournament engine: a generated bracket rejects manual rows, while an
empty stage accepts only a standalone two-team final. Placement matches are filled from
the semifinal losers.

---

## 4. Scheduling

`app/organizer/schedule.php` keeps the table / calendar / bracket views.

### 4.1 Brackets

* The bracket tab is fed by the **structure skeleton**, not only by generated
  rows. A preset's knockout stage is selectable as **“not drawn yet”** before
  its fixtures exist and is rendered as a **TBD skeleton** (winners, losers and
  grand final for double elimination). The organizer state points to
  **Setup → Matches**, while the public view simply says the draw has not been
  published yet.
* The Bracket toggle is only shown when the structure has a bracket stage;
  Round robin and Swiss hide it.
* **Single elimination** — one clean round-by-round tree. Seeded draws use the
  graph engine (`bracket_side` / `node_key`); legacy rows without those columns
  still render through the same table fallback.
* Legacy stage-less rows are grouped by their `stage`/`bracket` tag, so both the
  organizer and public views can draw a pre-stage bracket.
* **Double elimination** — separate **Winners bracket**, **Losers bracket** and
  **Grand final** sections (plus **Consolation** when present). Round labels are
  side-aware: `Winners Final`, `Losers Round n`, `Losers Final`, `Grand Final`.
  When the reset option is enabled and the losers-bracket entrant wins the first
  final, a `Grand Final reset` column appears with both teams.
* **Byes** are normalised to `team_a` with `seed_b = 'BYE'`; any completed
  one-team row counts as a bye, including rows collapsed by an older engine.
* When a round mixes pools, each card shows its stage group, so
  Pools → playoffs quarterfinals stay attributable to their pool.

Committee scoring does not change: the assigned Committee submits the result, and
the engine moves the teams.

### 4.2 Round robin / Swiss

Fixtures are grouped by round. A round-robin stage is generated across its real
rounds (circle method) instead of one flat "Round 1". A Swiss stage exposes
**Next Swiss round**, which:

1. refuses while the previous round has unplayed matches,
2. locks the previous round (`locked = 1`),
3. recalculates match points,
4. pairs equal records, floats one team down from an odd group, gives the bye to
   the lowest team that has not had one, avoids rematches,
5. writes the next batch of fixtures as drafts.

Standings are computed **on read** from the completed matches
(`standingsProvider.php`), so saving a score instantly changes the table — there is
no stale cache to rebuild.

### 4.3 Standings surfaces

Both `standing.php` (public) and `app/organizer/standing.php` read
`includes/standingsSections.php → getLeagueStandingsSections()`. The builder turns
the stage structure into display sections:

| Stage format | Section |
|---|---|
| `round_robin` / `swiss` | One table per stage group, or one stage-wide table |
| `single_elimination` / `double_elimination` | Winners / losers / grand-final bracket columns |
| `placement` | Placement match cards |
| No stages | Legacy group tables or one overall table |

- Table rows still come from `standingsProvider.php` (`getLeagueStandings()`,
  `getStageGroupStandings()` and `getStageStandings()`), so points, tiebreaks,
  forfeits and set scoring are identical to every other surface.
- Group tables honour carry-over sources (`tbl_league_stage_group_source`).
- Bracket cards include byes and unpublished later rounds; unpublished details
  (date, score, winner) stay hidden.
- Published results that are not tagged to a stage appear in a separate
  **Overall standings — untagged results** section.
- The organizer page can move a team between groups of the same stage
  (`assignStageGroup` → `moveStageTeamToGroup()`); a team stays in exactly one
  group per stage.

---

## 5. Progression rules

`includes/tournamentProgression.php → progressMatchResult()` routes every match
that has a `node_key` to `progressBracketStageMatch()`.

| Match | Winner | Loser |
|---|---|---|
| Winners bracket (single elim) | Next node | Eliminated |
| Winners bracket (double elim) | Next winners node | **Drops** into the losers node |
| Losers bracket | Next losers node | Eliminated |
| Grand final | Champion (`tournament_complete`) | Eliminated |
| Grand final GF-M1 with reset enabled, losers-side win | **GF-M2 reset** (both stay alive) | Nothing yet |
| Grand final reset GF-M2 | Champion (`tournament_complete`) | Eliminated |
| Swiss / round robin | Nothing — the table decides | Nothing |

Byes are collapsed automatically: a node with one team and an unreachable other
side is completed, its team moves on, and the pass repeats so a bye can cascade
through several rounds. A bye never blocks a round.

Corrected results still run through `reverseProgression()`; the graph is re-synced
from the corrected row.

---

## 6. Engine API

| Function | Purpose |
|---|---|
| `singleEliminationPlan()` / `doubleEliminationPlan()` | Pure node graphs (rounds, links, slots) |
| `bracketSeedPairs()` | Seeded first round with byes (1 v N, 2 v N-1 …) |
| `swissPairings()`, `swissFirstRound()`, `swissRoundCount()` | Pure Swiss pairing |
| `roundRobinRounds()` | Pure circle-method rounds |
| `createBracketForStage()` | Persist a plan + resolve byes |
| `leagueBracketStageCatalog()` / `getLeagueBracketStageCatalog()` | Bracket stage summaries for the views (empty stages included) |
| `stageSettings()` / `saveStageSettings()` | Per-stage options such as `bracket_reset` |
| `ensureBracketResetFinal()` / `clearBracketResetFinal()` | Create or remove GF-M2 when a reset final is / is no longer needed |
| `progressBracketStageMatch()` | Winner forward, loser down, champion detection |
| `generateSwissRoundForStage()` | Lock previous round + pair + create fixtures |
| `stageSlotPlan()`, `saveStageSlots()` | Saved seeding-board helpers (legacy; Setup uses automatic promotion) |
| `activateLeagueStage()` | Activate a stage, lock the previous one |

Unit coverage: `tests/Unit/TournamentEngineTest.php`.

### Scheduling endpoints (`action/schedulingAction.php`)

| POST key | Effect |
|---|---|
| `generateFormatBracket` | Draw the whole winners/losers/final graph |
| `generateSwissRound` | Generate (and lock) the next Swiss round |
| `lockStageRound` | Lock one stage round |

---

## 7. Applying the migration

```
mysql -u <user> -p <database> < database/migrations/041_tournament_structures.sql
```

The engine also calls `ensureTournamentEngineSchema()` at runtime: it adds any
missing column/table from migration 041 so a copied deployment keeps working. It
never drops or rewrites data. Old stages are backfilled from `stage_type`
(`knockout` → `single_elimination`, `placement` → `placement`, everything else →
`round_robin`).

---

## 8. Known limits

* A bracket supports **2 – 128 teams**; missing seeds become byes.
* Double elimination can opt into a **bracket-reset grand final** when the draw is
  generated. If the losers-bracket entrant wins the first grand final, a second
  grand final (`GF-M2`) is created on demand with both grand-finalists; a
  winners-side win in the first final ends the tournament with no reset row.
  If GF-M1 is corrected after GF-M2 has been played, reopen GF-M2 first so its
  result is reversed before the first final is corrected.
* `bottom_consolation` saves the rule and shows in the board, but the consolation
  stage must be added as a stage of its own (the wizard does not create it
  automatically yet).
* Swiss pairings avoid rematches with a best-effort fold/shift choice; with very
  small pools every option can be a rematch, in which case the least-bad pairing
  is used.

