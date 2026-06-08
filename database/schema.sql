CREATE TABLE IF NOT EXISTS users (
    id              INT             NOT NULL AUTO_INCREMENT,
    email           VARCHAR(255)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    role            ENUM('volunteer', 'organizer', 'company') NOT NULL,
    first_name      VARCHAR(100)    NOT NULL,
    last_name       VARCHAR(100)    NOT NULL,
    bio             TEXT            DEFAULT NULL,
    virtual_balance DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,
    email_verified  TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
    id              INT             NOT NULL AUTO_INCREMENT,
    organizer_id    INT             NOT NULL,
    title           VARCHAR(255)    NOT NULL,
    description     TEXT            NOT NULL,
    campaign_type   VARCHAR(50)     NOT NULL,
    goal_amount     DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,
    current_amount  DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,
    deadline        DATETIME        NOT NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'draft',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_campaigns_organizer (organizer_id),
    KEY idx_campaigns_status (status),
    KEY idx_campaigns_deadline (deadline),
    CONSTRAINT fk_campaigns_organizer FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_items (
    id                  INT             NOT NULL AUTO_INCREMENT,
    campaign_id         INT             NOT NULL,
    producer_id         INT             DEFAULT NULL,
    name                VARCHAR(255)    NOT NULL,
    description         TEXT            DEFAULT NULL,
    item_type           VARCHAR(20)     NOT NULL DEFAULT 'good',
    production_cost     DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,
    donation_amount     DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,
    quantity_available  INT             NOT NULL DEFAULT 1,
    quantity_sold       INT             NOT NULL DEFAULT 0,
    status              VARCHAR(20)     NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_campaign_items_campaign (campaign_id),
    KEY idx_campaign_items_status (status),
    KEY idx_campaign_items_producer (producer_id),
    CONSTRAINT fk_campaign_items_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_items_producer FOREIGN KEY (producer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_offers (
    id                          INT             NOT NULL AUTO_INCREMENT,
    campaign_id                 INT             NOT NULL,
    producer_id                 INT             NOT NULL,
    name                        VARCHAR(255)    NOT NULL,
    description                 TEXT            DEFAULT NULL,
    item_type                   VARCHAR(20)     NOT NULL DEFAULT 'good',
    proposed_production_cost    DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,
    proposed_donation_amount    DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,
    quantity_offered            INT             NOT NULL DEFAULT 1,
    status                      VARCHAR(20)     NOT NULL DEFAULT 'pending',
    organizer_note              TEXT            DEFAULT NULL,
    accepted_item_id            INT             DEFAULT NULL,
    decided_at                  DATETIME        DEFAULT NULL,
    created_at                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_offers_campaign (campaign_id),
    KEY idx_offers_producer (producer_id),
    KEY idx_offers_status (status),
    CONSTRAINT fk_offers_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_offers_producer FOREIGN KEY (producer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_offers_accepted_item FOREIGN KEY (accepted_item_id) REFERENCES campaign_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contributions (
    id                      INT             NOT NULL AUTO_INCREMENT,
    campaign_id             INT             NOT NULL,
    contributor_id          INT             NOT NULL,
    type                    VARCHAR(20)     NOT NULL,
    amount                  DECIMAL(10, 2)  DEFAULT NULL,
    hours_count             DECIMAL(6, 2)   DEFAULT NULL,
    goods_description       TEXT            DEFAULT NULL,
    goods_estimated_value   DECIMAL(10, 2)  DEFAULT NULL,
    note                    TEXT            DEFAULT NULL,
    status                  VARCHAR(20)     NOT NULL DEFAULT 'pending',
    created_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contributions_campaign (campaign_id),
    KEY idx_contributions_contributor (contributor_id),
    KEY idx_contributions_type (type),
    KEY idx_contributions_status (status),
    CONSTRAINT fk_contributions_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_contributions_contributor FOREIGN KEY (contributor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchases (
    id              INT             NOT NULL AUTO_INCREMENT,
    item_id         INT             NOT NULL,
    buyer_id        INT             NOT NULL,
    campaign_id     INT             NOT NULL,
    quantity        INT             NOT NULL,
    unit_cost       DECIMAL(10, 2)  NOT NULL,
    unit_donation   DECIMAL(10, 2)  NOT NULL,
    total_paid      DECIMAL(10, 2)  NOT NULL,
    total_donation  DECIMAL(10, 2)  NOT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_purchases_buyer (buyer_id),
    KEY idx_purchases_item (item_id),
    KEY idx_purchases_campaign (campaign_id),
    CONSTRAINT fk_purchases_item FOREIGN KEY (item_id) REFERENCES campaign_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_purchases_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_purchases_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deposits (
    id              INT             NOT NULL AUTO_INCREMENT,
    user_id         INT             NOT NULL,
    amount          DECIMAL(10, 2)  NOT NULL,
    balance_before  DECIMAL(10, 2)  NOT NULL,
    balance_after   DECIMAL(10, 2)  NOT NULL,
    payment_method  VARCHAR(20)     NOT NULL DEFAULT 'mock_card',
    card_last4      VARCHAR(4)      DEFAULT NULL,
    card_holder     VARCHAR(100)    DEFAULT NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'completed',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_deposits_user (user_id),
    KEY idx_deposits_created (created_at),
    CONSTRAINT fk_deposits_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS revoked_tokens (
    jti         CHAR(32)    NOT NULL,
    user_id     INT         NOT NULL,
    revoked_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  TIMESTAMP   NOT NULL,
    PRIMARY KEY (jti),
    KEY idx_revoked_expires (expires_at),
    CONSTRAINT fk_revoked_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED: demo accounts (password for all three is "Password123!")
INSERT INTO users (email, password_hash, role, first_name, last_name, virtual_balance) VALUES
  ('volunteer@example.com', '$2y$12$g8QZB4DsuQS6y/X4rtPMJOe1Z2UYhgjdQzbRzWxm9kGGvlVOPXnVe', 'volunteer', 'Vera',  'Volunteer', 100.00),
  ('organizer@example.com', '$2y$12$ijOOowP/I9naEPe74mgnjeJG20./FkKOKTOuJpxO292EYbKqSLdtu', 'organizer', 'Olive', 'Organizer', 0.00),
  ('company@example.com',   '$2y$12$x6Zn7jlP/sUegCDWEtJsHO5UVLze2oGEWuLAoD28lPdboOh/uJlDm', 'company',   'Carl',  'Company',   500.00);