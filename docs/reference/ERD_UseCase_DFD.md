# LMS ERD, Use Cases, and DFDs

## Overview
This document contains: ERD, Use Case descriptions, Level 0 DFD, and Level 1 DFD for the League Management System (LMS).

---

## Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    TBL_USER {
        int id PK
        varchar res_id
        varchar fname
        varchar lname
        varchar mname
        varchar suffix
        varchar phone
        varchar email
        varchar pass
        varchar role
        varchar stat
        int pass_stat
        datetime created
    }

    TBL_LEAGUE {
        int id PK
        varchar league_image
        varchar league_name
        varchar sport
        enum division
        varchar descrip
        enum category
        varchar format
        varchar start_date
        varchar end_date
        varchar regis_start
        varchar regis_end
        varchar venue
        varchar max_team
        varchar max_player
        varchar regis_fee
        varchar add_info
        varchar stat
        int min_age
        int max_age
        int min_male_players
        int min_female_players
        tinyint registration_window_open
        datetime created
        int created_by FK
        tinyint is_archived
        datetime archived_at
        varchar guideline_document
    }

    TBL_TEAM {
        int id PK
        varchar team_name
        varchar team_image
        varchar league_id FK*
        varchar bracket
        varchar team_repre
        varchar email
        varchar phone
        varchar status
        varchar user_id FK*
        datetime created
    }

    TBL_PLAYER {
        int id PK
        varchar team_id FK*
        varchar name
        varchar age
        varchar role
        varchar gender
        varchar jersey_number
        varchar document
        datetime created
    }

    TBL_SCHEDULE {
        int id PK
        varchar league_id FK*
        varchar team_a FK*
        varchar a_score
        varchar team_b FK*
        varchar b_score
        varchar date
        varchar time
        varchar status
        varchar winner FK*
        datetime created
        datetime updated_at
    }

    TBL_ANNOUNCEMENT {
        int id PK
        int league_id FK
        varchar title
        text content
        int created_by FK
        datetime created
        datetime updated
    }

    TBL_NOTIFICATION {
        int id PK
        int user_id FK
        varchar type
        varchar title
        text message
        int related_id
        tinyint is_read
        datetime created
    }

    TBL_GALLERY_POSTS {
        int id PK
        varchar title
        longtext description
        int uploaded_by FK*
        datetime created_at
        datetime updated_at
    }

    TBL_GALLERY_IMAGES {
        int id PK
        int post_id FK
        varchar filename
        int display_order
        datetime created_at
    }

    TBL_MATCH_HISTORY {
        int id PK
        int schedule_id FK
        int league_id FK
        int team_a_id FK
        int team_b_id FK
        int team_a_score
        int team_b_score
        int winner_id FK
        date match_date
        time match_time
        varchar venue
        enum status
        longtext notes
        datetime created_at
        datetime updated_at
    }

    TBL_SCHEDULING_CONFIG {
        int id PK
        int league_id FK
        int match_duration_minutes
        int rest_period_minutes
        time earliest_start_time
        time latest_end_time
        int max_matches_per_day
        tinyint auto_schedule_enabled
        tinyint conflict_check_enabled
        datetime created_at
        datetime updated_at
    }

    TBL_ATTEMPTS {
        int id PK
        varchar ip_address
        bigint try_time
    }

    TBL_USER ||--o{ TBL_LEAGUE : "creates/created_by"
    TBL_LEAGUE ||--o{ TBL_TEAM : "has"
    TBL_TEAM ||--o{ TBL_PLAYER : "has"
    TBL_LEAGUE ||--o{ TBL_SCHEDULE : "schedules"
    TBL_TEAM ||--o{ TBL_SCHEDULE : "team_a/team_b"
    TBL_LEAGUE ||--o{ TBL_ANNOUNCEMENT : "announcements"
    TBL_USER ||--o{ TBL_ANNOUNCEMENT : "authors"
    TBL_USER ||--o{ TBL_NOTIFICATION : "receives"
    TBL_GALLERY_POSTS ||--o{ TBL_GALLERY_IMAGES : "images"
    TBL_LEAGUE ||--o{ TBL_MATCH_HISTORY : "match_history"
    TBL_TEAM ||--o{ TBL_MATCH_HISTORY : "participants"
    TBL_LEAGUE ||--o{ TBL_SCHEDULING_CONFIG : "config"
```

---

## Use Cases

Actors:
- Organizer (creates leagues, announcements, configures scheduling)
- Team Representative (registers team, manages roster)
- System (automated scheduler)
- Viewer (views announcements, gallery, schedules)

Use Cases (high level):
- Create League: Organizer creates and configures a league (`tbl_league`).
- Register Team: Team Representative registers team and uploads team info (`tbl_team`).
- Manage Players: Team Representative adds/removes players (`tbl_player`).
- Post Announcement: Organizer posts announcements for a league (`tbl_announcement`).
- Notify Users: System sends notifications (`tbl_notification`) on events.
- Upload Gallery: Users upload posts and images (`tbl_gallery_posts`, `tbl_gallery_images`).
- Schedule Matches: Organizer or System schedules matches (`tbl_schedule`) using `tbl_scheduling_config` rules; results stored in `tbl_match_history`.
- Authentication: Users log in; attempts recorded in `tbl_attempts`.

Each use case can be expanded into step-by-step flows if required.

---

## Level 0 DFD (Context Diagram)

```mermaid
flowchart LR
    A[User (Organizer / Team Rep / Viewer)] -->|interacts with| LMS[(LMS System)]
    LMS -->|reads/writes| DB[(Database)]
    LMS -->|sends| Notifications[Notifications]
    LMS -->|produces| Gallery[Gallery]
    Note[External System: Email/SMS] -->|deliver| Notifications
```

Description: Single process `LMS` interacts with external actors and the central database.

---

## Level 1 DFD (Decomposed)

```mermaid
flowchart LR
    subgraph EXTERNAL
        U[User (Organizer/Team Rep/Viewer)]
        EXT[External Delivery: Email/SMS]
    end

    subgraph LMS_SYSTEM
        A1[Authentication]
        A2[League Management]
        A3[Team & Player Management]
        A4[Scheduling Engine]
        A5[Announcements & Notifications]
        A6[Gallery Management]
    end

    DB_USERS[(Users DB)]
    DB_LEAGUES[(Leagues DB)]
    DB_TEAMS[(Teams DB)]
    DB_SCHEDULES[(Schedules DB)]
    DB_HISTORY[(Match History DB)]
    DB_GALLERY[(Gallery DB)]
    DB_NOTIFS[(Notifications DB)]

    U --> A1
    A1 --> DB_USERS
    A2 --> DB_LEAGUES
    A3 --> DB_TEAMS
    A3 --> DB_USERS
    A4 --> DB_SCHEDULES
    A4 --> DB_LEAGUES
    A4 --> DB_TEAMS
    A4 --> DB_HISTORY
    A5 --> DB_NOTIFS
    A5 --> EXT
    A6 --> DB_GALLERY
    A6 --> DB_GALLERY

    A1 --> A2
    A2 --> A3
    A2 --> A4
    A4 --> A5
    A3 --> A4
```

Notes:
- Data stores correspond to SQL tables: `tbl_user`, `tbl_league`, `tbl_team`, `tbl_player`, `tbl_schedule`, `tbl_match_history`, `tbl_gallery_posts`, `tbl_gallery_images`, `tbl_notification`.
- Scheduling engine may be invoked manually by an Organizer or run automatically based on `tbl_scheduling_config`.

---

## Recommendations / Next Steps
- If you want a visual `.mb` (MindManager) or another binary format, tell me which target (MindManager, XMind, FreeMind) and I will export accordingly.
- I can also produce PNG/SVG exports of the diagrams.


Generated from `lms/ERD_Data_Dictionary.txt` and `lms/database/lms.sql`.
