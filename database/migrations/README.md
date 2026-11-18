# LMS Database Migrations

Numbered in the order they were applied to the development database (by file
timestamp). **This folder is the upgrade path for an existing database** — a
brand-new install uses [`../install/`](../install/) instead and does **not**
need these files.

## Rules

1. Apply in numeric order. Never skip.
2. Files marked **⚠ one-shot** contain statements without `IF [NOT] EXISTS`
   (`ADD COLUMN`, `ADD KEY`, `DROP COLUMN`, `DROP INDEX`). Running one twice
   will fail. On a fresh install they are unnecessary — `install/01_schema.sql`
   already contains their result.
3. `037_remove_league_formats.sql` also **drops** the legacy `format` /
   `format_code` columns and the `league_format` utility rows. It has no
   `IF EXISTS` guard and cannot be re-run.
4. Always take a dump before upgrading:
   `mysqldump -u root lms > backup_before_upgrade.sql`

## Applying (existing database)

```bash
# Windows / XAMPP
for %f in (migrations\*.sql) do "C:\xampp\mysql\bin\mysql.exe" -u root lms < "%f"

# Linux / macOS
for f in migrations/*.sql; do mysql -u root lms < "$f"; done
```

Only apply the files **newer than your current database**. To know what has
already been applied, compare your schema against the dates below.

## Manifest

| # | File | Applied | Re-run safe | What it changes |
|---|---|---|---|---|
| 001 | `001_gallery_system.sql` | 2026-05-31 | yes | `tbl_gallery_posts`, `tbl_gallery_images` |
| 002 | `002_announcements.sql` | 2026-05-31 | yes | `tbl_announcement` |
| 003 | `003_add_guideline_document.sql` | 2026-05-31 | yes | league guideline document column |
| 004 | `004_add_player_document.sql` | 2026-05-31 | yes | player document column |
| 005 | `005_add_eligibility_requirements.sql` | 2026-05-31 | yes | min/max age, min male/female players, registration window |
| 006 | `006_notifications.sql` | 2026-05-31 | yes | `tbl_notification` |
| 007 | `007_add_created_by_league.sql` | 2026-05-31 | ⚠ one-shot | `tbl_league.created_by` |
| 008 | `008_add_archived_at.sql` | 2026-06-02 | yes | `tbl_league.archived_at` |
| 009 | `009_add_user_id_audit_log.sql` | 2026-06-03 | ⚠ one-shot | `tbl_audit_log.user_id` |
| 010 | `010_scheduling_system.sql` | 2026-06-03 | ⚠ one-shot | schedule columns, `tbl_scheduling_config`, indexes |
| 011 | `011_add_unique_league_constraint.sql` | 2026-06-03 | ⚠ one-shot | unique league-name constraint |
| 012 | `012_roles_committee_admin.sql` | 2026-08-03 | yes | roles 3 = Admin, 4 = Committee |
| 013 | `013_active_league_uniqueness.sql` | 2026-08-03 | ⚠ one-shot | one active league (`active_unique` + index) |
| 014 | `014_final_roles_foundation.sql` | 2026-08-03 | yes | `publication_status`, committee/livestream/result columns, `tbl_utility` |
| 015 | `015_configurable_league_rules.sql` | 2026-08-03 | ⚠ one-shot | points / tie-break rules on the league |
| 016 | `016_schedule_draft_publish.sql` | 2026-08-03 | yes | schedule draft → publish state |
| 017 | `017_optional_groups_progression.sql` | 2026-08-03 | ⚠ one-shot | optional groups, `qualifiers_per_group` |
| 018 | `018_multiple_league_guidelines.sql` | 2026-08-04 | yes | `tbl_league_guideline_document` |
| 019 | `019_gallery_archive_security.sql` | 2026-08-04 | ⚠ one-shot | gallery archive columns + indexes |
| 020 | `020_unique_active_team_registration.sql` | 2026-08-04 | ⚠ one-shot | one active registration per team per league |
| 021 | `021_player_birthdate.sql` | 2026-08-04 | ⚠ one-shot | `tbl_player.birthdate` |
| 022 | `022_configurable_minimum_players.sql` | 2026-08-04 | yes | configurable minimum players |
| 023 | `023_sport_roster_defaults.sql` | 2026-08-04 | yes | roster defaults per sport in `tbl_utility` |
| 024 | `024_player_eligibility_review.sql` | 2026-08-05 | ⚠ one-shot | player eligibility review columns |
| 025 | `025_team_correction_deadlines.sql` | 2026-08-11 | ⚠ one-shot | team correction deadline columns |
| 026 | `026_schema_improvements.sql` | 2026-08-29 | ⚠ one-shot | date column conversions + validation schema |
| 027 | `027_security_enhancements.sql` | 2026-08-29 | yes | `tbl_password_history`, `tbl_login_attempts`, `tbl_security_events` |
| 028 | `028_add_organizer_profile_fields.sql` | 2026-08-30 | yes | organizer profile fields |
| 029 | `029_remove_org_position_fields.sql` | 2026-08-30 | yes | drop organizer position fields |
| 030 | `030_fix_user_defaults.sql` | 2026-08-31 | yes | `tbl_user` defaults |
| 031 | `031_add_quality_metrics.sql` | 2026-08-31 | yes | match quality metrics |
| 032 | `032_document_type_scope.sql` | 2026-09-01 | yes | document scope (`player` / `team`) |
| 033 | `033_password_reset.sql` | 2026-09-15 | yes | `tbl_password_reset` |
| 034 | `034_anp2026_stages_and_rules.sql` | 2026-10-13 | yes | stage model + ANP2026 scoring rules |
| 035 | `035_volleyball_anp2026.sql` | 2026-10-13 | yes | volleyball sets/rules + seed rows |
| 036 | `036_forfeit_decision.sql` | 2026-10-14 | yes | forfeit decision record |
| 037 | `037_remove_league_formats.sql` | 2026-10-20 | ⚠ **one-shot, destructive** | drops the legacy `format` / `format_code` model |
| 038 | `038_tournament_completion_and_series.sql` | 2026-10-20 | yes | tournament completion columns, stage `final_best_of`, `result_unit = 'games'` |
| 039 | `039_notification_indexes.sql` | 2026-11-05 | yes | `tbl_notification` composite indexes (unread badge, bell list, daily de-dupe) |
| 040 | `040_schedule_draft_nulls.sql` | 2026-11-06 | yes | nullable `tbl_schedule.date` / `time` for unscheduled drafts |
| 041 | `041_tournament_structures.sql` | 2026-11-16 | yes | tournament stage/group structures |
| 042 | `042_email_verification.sql` | 2026-11-16 | yes | `tbl_email_verification` (registration OTP) |
| 043 | `043_report_indexes.sql` | 2026-11-19 | yes | reporting indexes on leagues, teams, schedules and players |

## Baseline note

Every migration above is already folded into `install/01_schema.sql`
(2026-11-19). A database built from `install/` is at migration **043** — do not
run this folder against it.

## Related documents

- `../README.md` — deployment guide (fresh install / restore / upgrade)
- `../DATA_DICTIONARY.txt` — table and column reference
- `../backups/` — dumps, never part of a deployment

