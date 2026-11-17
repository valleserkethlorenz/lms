# How a league flows (plain language)

This page explains the **order of operations** in the LMS from the organizer's point of
view. It exists because the Setup page used to look optional — that is correct, and here is
exactly why.

---

## 1. The one-sentence version

> The Setup page is a short wizard: Format → Teams → Matches. The Format step
> also edits the preset's settings (group count, teams advancing per group/pool,
> Swiss rounds), so the whole tournament is configured and driven from one page.

**How the page is laid out.** A compact header shows *Step N of M* with the clickable
steps. Structures that use a group draw have three steps (Format, Teams, Matches);
bracket-only structures such as **Single elimination** have two (Format, Matches),
because their teams are seeded at generation time. Exactly one step panel is visible at
a time; it ends with **Previous** / **Next**, and the header shows a thin progress bar.
There are no milestone cards, long explanations or pattern dropdowns in the main flow.

The normal journey is:

1. **Format** — select one of the structure cards (Round robin, Swiss system, Single elimination, Double elimination, Round robin + playoffs, Groups → knockout, or Pools → playoffs), adjust the preset settings (group count, teams advancing, Swiss rounds), then press **Next** to create its stages.
2. **Teams** — approve teams, then fill the opening group automatically.
3. **Matches** — generate the round-robin matches, enter results on Schedule, then choose how many teams advance and create a **seeded knockout bracket** (quarterfinals, semifinals, final).

The open step is stored in the URL (`?step=teams`), so refresh and shared links keep working.
The Format step stores the group count, the preset's cut-off settings (top N per
group/pool) and Swiss rounds on the stages, so the Matches step can advance qualifiers
automatically as each stage finishes.



A league is created without a tournament shape; right after saving it the organizer is asked
**Setup now** or **Set up later**, and the wizard picks up from there. Nothing on the page is
required before you can schedule: the Schedule page always reads whatever stages exist, and if
there are none it falls back to a simple pairing assistant.

---

## 2. Where the structure comes from

The **Create League** form (Organizer → League Management → *Create League*) no longer asks for
a structure. When the league is saved, a dialog offers **Setup now** (jumps to
`Setup → Choose your competition structure`) or **Set up later** (returns to the league list,
where the league carries a *Setup needed* chip until a structure is applied).

The structure preset is applied on the Setup page, inside a database transaction, and creates
the stage/group skeleton.

| Structure option (Setup page) | What the system creates for you |
|---|---|
| **Round robin** | 1 stage `League Stage` (group type) with 1 group `Group A` |
| **Round robin + playoffs** (recommended) | `League Stage` (`Group A`) + `Playoffs` (empty knockout stage); **Create playoffs** fills it later |
| **Single elimination** | 1 stage `Bracket` (knockout type), no groups |
| **Groups → knockout** | `Group Stage` with **2–8 groups** (recommended from the team count) + `Knockout` with one `Qualifiers` pool |
| **Pools → crossover → playoffs** | `Elimination (Brackets)` (Brackets A–D, top 2 advance) → `Crossover / Pools` (Pool 1 ← A+D, Pool 2 ← B+C, top 4 advance) → `Playoffs` |
| *Custom* (legacy) | An existing hand-built skeleton is read as-is; new leagues use a preset |

A structure can be applied **and changed** at any time **before the first match exists**: picking
another option replaces the current stages (their groups, pool links and team placements) and builds
the new skeleton — teams and matches are never deleted. Switching from plain **Round robin** to
**Round robin + playoffs** is the exception: it appends the Playoffs stage and keeps the league stage
and team placements. Once matches exist the picker is locked,
because `tbl_schedule.stage_id` has no foreign key and old matches would silently fall out of every
stage table. To change the structure after that, use **Discard setup & start over** first. The code
for the option is **not** stored on the league — the skeleton itself *is* the
structure, so Setup recognises it by comparing the stages it finds with these presets (that is how it
shows "Groups → knockout" or "Custom structure (stages changed by hand)").

---

## 3. The journey for each structure

```
Create league → [Setup now | Set up later] → apply structure → approve teams → generate matches → committee submits results → standings/progression
```

| Structure | Organizer work after applying it | Where matches come from |
|---|---|---|
| Round robin | Approve teams, generate in **Step 3** | Step 3 → **Generate round-robin matches** |
| Single elimination | Approve teams, generate in **Step 3** | Step 3 → **Generate bracket** |
| Round robin + playoffs | Generate the league round robin; the configured top N advance automatically | Step 3 → generate the league stage, then the bracket |
| Groups → knockout | Generate the group round robin; the configured top N per group advance automatically | Step 3 → generate the group stage, then **Generate bracket** |
| Pools → crossover → playoffs | Generate each stage; the preset's qualifier counts and pool sources advance the teams automatically | Step 3 → each stage's generation form |
| No structure (deferred / legacy) | Everything by hand | **Optional Pairing Assistant** on Schedule, or **Add Match** |

**Teams are placed for you on the first draw.** When you generate the first stage's round
robin, the system fills that stage's groups with the approved teams (alphabetical, round
robin across the groups). So you go from "teams approved" to "matches generated" without
touching team assignments by hand.

---

## 4. The three wizard steps

The page shows one step at a time. The open step is stored in the URL (`?step=format`,
`?step=teams`, `?step=matches`), so refresh and shared links keep working.

| Step | What you see | What you do |
|---|---|---|
| **1. Format** | The structure cards + **Format settings** | Select a card — the applied one stays selected — adjust the group count and how many teams advance (or the Swiss rounds), then press **Next**. This creates the stages. You can change it until the first match exists. |
| **2. Teams** | Approved / placed summary | **Fill the group automatically**. |
| **3. Matches** | The current stage, its progress and one main action | Generate the matches. When a stage finishes, the configured qualifiers advance automatically; the same step then shows the next stage's generation form. Results are entered on Schedule. |

**Setup builds the tournament; Schedule runs it.** Fixture creation (round robins, brackets,
playoffs and qualifier advancement) lives **only** on Setup, with the stage's automatic
default draw and a preview modal where the format supports it. The Schedule page owns dates,
times, venues, Committee assignment, publishing and results, plus the manual match /
auto-pairing tools for leagues without a structure. There is no second generator on Schedule.

**The normal round-robin → playoffs progression**

1. Finish every round-robin match. Results are entered by the Committee on Schedule.
2. The configured top N teams advance automatically when the stage completes.
3. The rest are marked `Eliminated`.
4. Step 3 shows the seeded knockout generation form:
   - 8 teams → quarterfinals, semifinals, final
   - 4 teams → semifinals, final
   - 2 teams → final
   - other numbers get byes
5. Semifinal losers feed the **Battle for Third**; the final winner becomes champion.

If qualifiers were not configured (a legacy league), Step 3 shows an **Advance qualifiers**
form instead; choose N there and the same progression continues.

The quarterfinals, semifinals and final are **rounds inside one Playoffs stage** — do not
create three separate stages for them.

**Generated matches are Drafts.** Whether they come from the round-robin generator or the
seeded bracket, they are created with **no date, no venue and no Committee**. The organizer
sets those on the Schedule page and only then publishes the match. This keeps fixture
generation separate from scheduling.

**Discard setup & start over** is at the bottom of the page. It deletes the stages, stage
groups, standings pools and every match for the league (including results), resets team
progression and the tournament-completed flag, but **keeps the league details and teams**.
The organizer must type `DISCARD` to confirm.

**Format settings are part of the wizard.** The Format step stores the preset's
advancement counts (top N per group/pool), the **Groups → knockout** group count and
the Swiss round count directly on the stages. They can be edited while the league has
no matches; the **Save stage settings** button updates the existing stages without
deleting the groups or team placements. Changing the group count is structural: the
skeleton is rebuilt and the approved teams are re-drawn evenly into the new groups.
For **Groups → knockout**, the wizard recommends a balanced layout from the approved
team count (or the league's team limit), e.g. 16 teams → 4 groups of 4, top 2 advance →
an 8-team knockout. The recommendation is only a suggestion; both the group count and
the cut-off can be overridden.
Larger manual edits — adding arbitrary stages, renaming/reordering stages, bottom-N
consolation rules and seeding-board overrides — are no longer part of the interface.
Manual fixtures remain available on Schedule.

Everything else — publishing matches, results, standings — happens on other pages.


---

## 5. The two different "groups" (this is the usual source of confusion)

The system has **two unrelated grouping models**, and they live on different pages:

| | **Standings pools** (Standings page) | **Stage groups** (Setup) |
|---|---|---|
| Table | `tbl_league_group` + `tbl_league_group_team` | `tbl_league_stage_group` + `..._group_team` |
| Visible on | **Standings** page (splits the table into Group A / Group B) | **Setup** Matches step stage tables |
| Teams are placed | On the **Standings** page, per team dropdown | **Teams** step (automatic draw), or **Advance qualifiers** in Matches |
| Matches count toward it | Only when a match is tagged with that pool | A match belongs to a stage automatically when generated |
| Needed for | Leagues ranked in separate pools on one standings page | Any bracket/crossover/playoff progression |
| Default | **Off** | Created by the structure preset (if it has stages) |

Rule of thumb: **stage groups drive the tournament; standings pools only change how the
standings page is displayed.** Most leagues never turn pools on.

---

## 6. Does the UI change with the structure?

Yes — but it *reads* your data instead of asking you to pick again:

| What the Setup page shows | Driven by |
|---|---|
| Progress bar + step indicator (2 or 3 steps) | `includes/leagueSetupState.php` (built on the stage / team / match counters); nothing is stored |
| Which step is open | `?step=format`, `?step=teams` or `?step=matches`; otherwise the first unfinished step |
| No stages | Step 1 shows the structure cards: Round robin, Swiss system, Single elimination, Double elimination, Round robin + playoffs, Groups → knockout, Pools → playoffs |
| Round robin complete | Step 3 advances the configured top N and shows the knockout generation form |
| Single elimination | The Teams step is skipped — the wizard is Format → Matches, and Step 2 generates the bracket |
| Round robin + playoffs complete | Step 3 shows the tournament is complete and links to Standings |
| Standings pools (Standings page) | `grouping_enabled` on the league (Off / On) |
| Change overall format (Step 1) | Only while the league has no matches (`choose_structure`) |
| Edit qualifier counts / Swiss rounds | Only while the league has no matches (`choose_structure`) |
| Advance teams (Matches step) | Available when a finished stage feeds a stage with no teams (`promote`) |

The page's own capabilities come from **one** place — `includes/leagueSetupState.php` —
so the header and the step panels can no longer disagree about what is done or what is
allowed. Nothing on the page changes *what you are allowed to do*; it only surfaces the parts
of the model that your league actually uses. The Setup page also carries the qualifier
advancement and the later-stage draws, so no separate structure page is needed.

---

## 7. Common questions

**"Can I change the format?"**
Yes — while the league has **no matches**. Step 1 offers all seven structures, and its
**Format settings** panel edits the qualifier counts or Swiss rounds without replacing the
skeleton. Picking a different structure replaces the stages (groups and team placements go
with them; teams and matches are kept). Once the first match exists both the format and its
settings are locked.

**"I chose *Set up later* — how do I find it again?"**
The league register shows a **Setup needed** chip next to that league's name; it links
straight to Step 1. Schedule shows a reminder too. Nothing is blocked in the meantime:
the league uses the manual pairing assistant.

**"Do I have to assign teams to groups before generating?"**
No. For a round robin, **Fill the group automatically** puts the approved teams into the opening
group. Assign by hand only when you want specific seeds or a different split.

**"How do playoffs work?"**
Choose the top N in the Format step's settings before applying **Round robin + playoffs**.
When the league stage completes, those teams advance automatically and Step 3 shows the
seeded knockout generator. The bracket creates quarterfinals, semifinals and the final
automatically (byes are used when the number is not a power of two).

**"Where do results come from?"**
The assigned Committee submits them per published match. The organizer cannot edit scores
(by design). Standings and progression update automatically from those results.

**"How many groups should I use?"**
Open the Format step's **Format settings** for **Groups → knockout**. The wizard shows a
recommendation based on the approved team count (or the league's team limit), e.g.
16 teams → 4 groups of 4, top 2 per group → an 8-team knockout. Press **Use recommendation**
or set the group count yourself (2–8). Changing the group count rebuilds the group stage
and re-draws the approved teams; no match data exists at that point.

**"Where do I advance qualifiers now?"**
The **Matches** step. Presets with promotion counts advance the teams automatically when a
stage completes. If a legacy league has no configured count, the step shows an
**Advance qualifiers** form; it clears the target stage's groups and refills them from the
source stage's ranking. For multi-group and pool formats, the preset's saved pool sources
are respected.

**"Why does a round-robin league show a stage?"**
Because the *Round robin* format is implemented as one group stage called `League Stage`
with `Group A`. It is one stage that holds the single table.
