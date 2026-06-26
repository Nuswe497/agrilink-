-- Run this SQL once to add the password_resets table
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (token),
  INDEX (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
