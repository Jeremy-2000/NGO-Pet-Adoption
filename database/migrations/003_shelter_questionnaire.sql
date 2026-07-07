CREATE TABLE IF NOT EXISTS shelter_questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shelter_id INT UNSIGNED NOT NULL,
  question_text VARCHAR(255) NOT NULL,
  answer_type ENUM('yes_no','free_text','choice') NOT NULL DEFAULT 'free_text',
  choice_options TEXT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE CASCADE,
  INDEX idx_shelter_questions_active (shelter_id, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adoption_application_answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  answer_text TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES adoption_applications(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES shelter_questions(id) ON DELETE CASCADE,
  UNIQUE KEY uq_application_question (application_id, question_id),
  INDEX idx_answers_application (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
