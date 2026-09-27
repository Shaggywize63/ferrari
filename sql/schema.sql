-- Ferrari Pit Lane Competition – Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+

CREATE DATABASE IF NOT EXISTS ferrari_competition CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ferrari_competition;

-- ─── Admins ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id            INT            NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)    NOT NULL,
    password_hash VARCHAR(255)   NOT NULL,
    name          VARCHAR(100)   NOT NULL,
    email         VARCHAR(100),
    role          ENUM('super_admin','admin','scanner') NOT NULL DEFAULT 'admin',
    created_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin: admin / Admin@123
INSERT INTO admins (username, password_hash, name, email, role) VALUES
('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'admin@ferrari.example.com', 'super_admin');

-- ─── Events ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS events (
    id          INT           NOT NULL AUTO_INCREMENT,
    name        VARCHAR(200)  NOT NULL,
    description TEXT,
    venue       VARCHAR(200),
    start_date  DATE,
    end_date    DATE,
    status      ENUM('upcoming','active','completed') NOT NULL DEFAULT 'upcoming',
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO events (name, description, venue, start_date, end_date, status) VALUES
('A Pit Lane of Ferrari – HP Challenge', 'Official Ferrari HP competition with multiple stages', 'Ferrari World', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'active');

-- ─── Rounds ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rounds (
    id           INT           NOT NULL AUTO_INCREMENT,
    event_id     INT           NOT NULL,
    round_number INT           NOT NULL,
    name         VARCHAR(200)  NOT NULL,
    description  TEXT,
    max_score    INT           NOT NULL DEFAULT 100,
    start_date   DATETIME,
    end_date     DATETIME,
    status       ENUM('upcoming','active','completed') NOT NULL DEFAULT 'upcoming',
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_round_number (event_id, round_number),
    CONSTRAINT fk_rounds_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO rounds (event_id, round_number, name, description, max_score, status) VALUES
(1, 1, 'Qualification Round',  'All registered participants compete in the first stage', 100, 'active'),
(1, 2, 'Semi-Finals',          'Top performers advance from Round 1',                   150, 'upcoming'),
(1, 3, 'Grand Finale',         'The best of the best compete for the Ferrari trophy',   200, 'upcoming');

-- ─── Participants ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS participants (
    id           INT           NOT NULL AUTO_INCREMENT,
    name         VARCHAR(100)  NOT NULL,
    email        VARCHAR(150)  NOT NULL,
    phone        VARCHAR(25),
    city         VARCHAR(100),
    dob          DATE,
    team_name    VARCHAR(100),
    qr_token     CHAR(32)      NOT NULL,
    qr_image     VARCHAR(255),
    status       ENUM('active','eliminated','winner') NOT NULL DEFAULT 'active',
    registered_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_participants_email    (email),
    UNIQUE KEY uq_participants_qr_token (qr_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Participant Round Participation ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS participant_rounds (
    id              INT            NOT NULL AUTO_INCREMENT,
    participant_id  INT            NOT NULL,
    round_id        INT            NOT NULL,
    score           DECIMAL(10,2)  NOT NULL DEFAULT 0,
    checked_in_at   TIMESTAMP      NULL,
    checked_in_by   INT            NULL,
    status          ENUM('registered','checked_in','scored','eliminated') NOT NULL DEFAULT 'registered',
    notes           TEXT,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_participant_round (participant_id, round_id),
    CONSTRAINT fk_pr_participant FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_round       FOREIGN KEY (round_id)      REFERENCES rounds(id)       ON DELETE CASCADE,
    CONSTRAINT fk_pr_admin       FOREIGN KEY (checked_in_by) REFERENCES admins(id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── QR Scan Logs ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS qr_scan_logs (
    id             INT          NOT NULL AUTO_INCREMENT,
    participant_id INT          NOT NULL,
    round_id       INT          NULL,
    scanned_by     INT          NULL,
    scan_type      ENUM('registration','check_in','verification') NOT NULL DEFAULT 'check_in',
    ip_address     VARCHAR(45),
    user_agent     VARCHAR(500),
    scanned_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_log_participant FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
    CONSTRAINT fk_log_round       FOREIGN KEY (round_id)      REFERENCES rounds(id)       ON DELETE SET NULL,
    CONSTRAINT fk_log_admin       FOREIGN KEY (scanned_by)    REFERENCES admins(id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Useful views ─────────────────────────────────────────────────────────────

-- Overall leaderboard view
CREATE OR REPLACE VIEW v_leaderboard AS
SELECT
    p.id,
    p.name,
    p.email,
    p.city,
    p.team_name,
    p.status,
    COALESCE(SUM(pr.score), 0)                                     AS total_score,
    COUNT(DISTINCT pr.round_id)                                    AS rounds_participated,
    MAX(r.round_number)                                            AS highest_round,
    RANK() OVER (ORDER BY COALESCE(SUM(pr.score), 0) DESC)        AS overall_rank
FROM participants p
LEFT JOIN participant_rounds pr ON pr.participant_id = p.id AND pr.status IN ('scored','checked_in')
LEFT JOIN rounds r              ON r.id = pr.round_id
GROUP BY p.id, p.name, p.email, p.city, p.team_name, p.status;

-- Per-round leaderboard
CREATE OR REPLACE VIEW v_round_leaderboard AS
SELECT
    r.id                        AS round_id,
    r.round_number,
    r.name                      AS round_name,
    p.id                        AS participant_id,
    p.name                      AS participant_name,
    p.city,
    p.team_name,
    pr.score,
    pr.status                   AS round_status,
    pr.checked_in_at,
    RANK() OVER (PARTITION BY r.id ORDER BY pr.score DESC) AS round_rank
FROM rounds r
JOIN participant_rounds pr ON pr.round_id = r.id
JOIN participants p         ON p.id = pr.participant_id;
