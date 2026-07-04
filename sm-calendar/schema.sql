-- G2 SM Calendar Tool — schema (lives in g2_sm_calendar, separate from g2forms)
-- Staff identity is NOT duplicated here — see sm_user_access, which references g2forms.users.id

CREATE TABLE IF NOT EXISTS sm_user_access (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,                  -- references g2forms.users.id (no cross-db FK)
  sm_role     ENUM('admin','manager') NOT NULL DEFAULT 'manager',
  granted_by  INT NOT NULL,                  -- g2forms.users.id of the granter
  granted_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user (user_id)
);

CREATE TABLE IF NOT EXISTS clients (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(150) NOT NULL,
  email              VARCHAR(150) DEFAULT NULL,
  logo               VARCHAR(255) DEFAULT NULL,
  account_manager_id INT DEFAULT NULL,        -- g2forms.users.id
  connected_platforms VARCHAR(255) DEFAULT NULL, -- comma list of labels e.g. "Facebook,Instagram"
  archived           TINYINT(1) NOT NULL DEFAULT 0,
  created_at         DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS client_contacts (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  client_id           INT NOT NULL,
  name                VARCHAR(150) NOT NULL,
  email               VARCHAR(150) NOT NULL UNIQUE,
  avatar              VARCHAR(255) DEFAULT NULL,
  password_hash       VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS calendars (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  client_id   INT NOT NULL,
  name        VARCHAR(150) NOT NULL,
  month       CHAR(7) NOT NULL,               -- 'YYYY-MM'
  owner_id    INT DEFAULT NULL,               -- g2forms.users.id
  archived    TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS posts (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  calendar_id        INT NOT NULL,
  client_id          INT NOT NULL,
  title              VARCHAR(200) NOT NULL,
  caption            TEXT,
  notes              TEXT,
  hashtags           TEXT,
  platform           VARCHAR(50) DEFAULT NULL,    -- Facebook / Instagram / etc.
  format             VARCHAR(50) DEFAULT NULL,    -- Image / Video / Carousel / Reel / Story
  technical_specs    VARCHAR(255) DEFAULT NULL,
  status             ENUM('Draft','Brief Sent','Artwork Pending','In Review','Approved','Rejected','Published')
                     NOT NULL DEFAULT 'Draft',
  scheduled_at       DATETIME DEFAULT NULL,
  owner_id           INT DEFAULT NULL,            -- g2forms.users.id
  rejection_feedback TEXT DEFAULT NULL,
  archived           TINYINT(1) NOT NULL DEFAULT 0,
  created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (calendar_id) REFERENCES calendars(id) ON DELETE CASCADE,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS artwork_versions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  post_id       INT NOT NULL,
  asset_path    VARCHAR(255) NOT NULL,
  filename      VARCHAR(255) NOT NULL,
  mime_type     VARCHAR(100) DEFAULT NULL,
  file_size     INT DEFAULT NULL,
  version       INT NOT NULL DEFAULT 1,
  uploaded_by   INT DEFAULT NULL,             -- g2forms.users.id
  uploaded_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_approved   TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at    DATETIME DEFAULT NULL,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comments (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  post_id           INT NOT NULL,
  author_user_id    INT DEFAULT NULL,         -- g2forms.users.id, OR
  author_contact_id INT DEFAULT NULL,         -- client_contacts.id (exactly one of the two set)
  author_name       VARCHAR(150) NOT NULL,
  content           TEXT NOT NULL,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT DEFAULT NULL,               -- g2forms.users.id, NULL = global/broadcast
  title       VARCHAR(200) NOT NULL,
  body        VARCHAR(500) DEFAULT NULL,
  link        VARCHAR(255) DEFAULT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Sessions / Auth (kept fully separate: staff vs client) ──
CREATE TABLE IF NOT EXISTS staff_sessions (
  id           VARCHAR(64) PRIMARY KEY,
  user_id      INT NOT NULL,                  -- g2forms.users.id
  ip_address   VARCHAR(45) DEFAULT NULL,
  user_agent   VARCHAR(255) DEFAULT NULL,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS client_sessions (
  id           VARCHAR(64) PRIMARY KEY,
  contact_id   INT NOT NULL,
  ip_address   VARCHAR(45) DEFAULT NULL,
  user_agent   VARCHAR(255) DEFAULT NULL,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME NOT NULL,
  FOREIGN KEY (contact_id) REFERENCES client_contacts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS auth_otp_challenges (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  subject_type ENUM('staff','client') NOT NULL,
  subject_id   INT NOT NULL,                  -- g2forms.users.id OR client_contacts.id
  code_hash    VARCHAR(255) NOT NULL,
  purpose      VARCHAR(50) NOT NULL DEFAULT 'login',
  attempts     INT NOT NULL DEFAULT 0,
  expires_at   DATETIME NOT NULL,
  consumed_at  DATETIME DEFAULT NULL,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Meta / Publishing ──
CREATE TABLE IF NOT EXISTS social_oauth_states (
  id          VARCHAR(64) PRIMARY KEY,
  client_id   INT NOT NULL,
  created_by  INT NOT NULL,                   -- g2forms.users.id
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at  DATETIME NOT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS social_connections (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  client_id       INT NOT NULL,
  provider        VARCHAR(20) NOT NULL DEFAULT 'meta',
  access_token    TEXT NOT NULL,
  token_expires_at DATETIME DEFAULT NULL,
  connected_by    INT DEFAULT NULL,           -- g2forms.users.id
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS social_destinations (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  connection_id   INT NOT NULL,
  client_id       INT NOT NULL,
  destination_type ENUM('facebook_page','instagram_business') NOT NULL,
  external_id     VARCHAR(100) NOT NULL,
  name            VARCHAR(150) NOT NULL,
  access_token    TEXT DEFAULT NULL,           -- page-level token, separate from user token
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (connection_id) REFERENCES social_connections(id) ON DELETE CASCADE,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS publish_jobs (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  post_id         INT NOT NULL,
  destination_id  INT DEFAULT NULL,            -- NULL for manual-publish log entries
  mode            ENUM('direct','manual') NOT NULL DEFAULT 'direct',
  status          ENUM('queued','processing','success','failed','cancelled') NOT NULL DEFAULT 'queued',
  scheduled_at    DATETIME DEFAULT NULL,
  last_error      VARCHAR(500) DEFAULT NULL,
  created_by      INT DEFAULT NULL,            -- g2forms.users.id
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS publish_attempts (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  job_id      INT NOT NULL,
  attempt_no  INT NOT NULL DEFAULT 1,
  status      ENUM('success','failed') NOT NULL,
  response    TEXT DEFAULT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES publish_jobs(id) ON DELETE CASCADE
);
