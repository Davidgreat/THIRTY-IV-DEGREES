CREATE TABLE IF NOT EXISTS payment_gateway_context (
 payment_id INT PRIMARY KEY,
 mode ENUM('test','live') NOT NULL,
 secret_cipher TEXT NOT NULL,
 authorization_url VARCHAR(1000) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS account_reset_tokens (
 user_id INT PRIMARY KEY, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS request_rate_limits (
 bucket CHAR(64) PRIMARY KEY, hits INT NOT NULL DEFAULT 1, expires_at BIGINT NOT NULL,
 INDEX(expires_at)
) ENGINE=InnoDB;
