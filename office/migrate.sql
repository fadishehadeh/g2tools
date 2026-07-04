-- ── Petty Cash ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS petty_cash_float (
  id          INT PRIMARY KEY DEFAULT 1,
  balance     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT IGNORE INTO petty_cash_float (id, balance) VALUES (1, 0.00);

CREATE TABLE IF NOT EXISTS petty_cash_requests (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  category        VARCHAR(50) NOT NULL,
  description     TEXT NOT NULL,
  receipt         VARCHAR(255) DEFAULT NULL,
  status          ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  admin_note      TEXT DEFAULT NULL,
  reviewed_by     INT DEFAULT NULL,
  reviewed_at     DATETIME DEFAULT NULL,
  paid_at         DATETIME DEFAULT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS petty_cash_log (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  type            ENUM('topup','disbursement','adjustment') NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  balance_after   DECIMAL(10,2) NOT NULL,
  reference       VARCHAR(100) DEFAULT NULL,
  notes           TEXT DEFAULT NULL,
  request_id      INT DEFAULT NULL,
  created_by      INT NOT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Pantry ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pantry_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  unit          VARCHAR(30)  NOT NULL DEFAULT 'pcs',
  category      VARCHAR(50)  DEFAULT 'General',
  current_stock DECIMAL(10,2) NOT NULL DEFAULT 0,
  par_level     DECIMAL(10,2) NOT NULL DEFAULT 5,
  reorder_qty   DECIMAL(10,2) NOT NULL DEFAULT 10,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pantry_movements (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  item_id     INT NOT NULL,
  type        ENUM('in','out','adjustment') NOT NULL,
  quantity    DECIMAL(10,2) NOT NULL,
  notes       VARCHAR(255) DEFAULT NULL,
  created_by  INT NOT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES pantry_items(id)
);

CREATE TABLE IF NOT EXISTS pantry_alert_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  item_id     INT NOT NULL,
  sent_at     DATETIME DEFAULT CURRENT_TIMESTAMP
);
