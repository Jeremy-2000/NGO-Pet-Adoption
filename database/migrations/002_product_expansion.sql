ALTER TABLE animals
  MODIFY status ENUM('available','reserved','adopted','medical_hold','archived','rejected') NOT NULL DEFAULT 'available';

ALTER TABLE animal_images
  ADD COLUMN IF NOT EXISTS crop_focus ENUM('center','top','bottom','left','right') NOT NULL DEFAULT 'center' AFTER sort_order;

ALTER TABLE inquiries
  MODIFY status ENUM('new','reviewed','contacted','viewing_scheduled','approved','declined','completed','closed') NOT NULL DEFAULT 'new',
  ADD COLUMN IF NOT EXISTS appointment_at DATETIME NULL AFTER status,
  ADD COLUMN IF NOT EXISTS internal_notes TEXT NULL AFTER appointment_at;

UPDATE inquiries SET status = 'contacted' WHERE status = 'reviewed';

ALTER TABLE inquiries
  MODIFY status ENUM('new','contacted','viewing_scheduled','approved','declined','completed','closed') NOT NULL DEFAULT 'new';

CREATE TABLE IF NOT EXISTS adoption_applications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  animal_id INT UNSIGNED NOT NULL,
  shelter_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(50) NULL,
  home_type VARCHAR(80) NULL,
  lifestyle VARCHAR(80) NULL,
  has_children TINYINT(1) NOT NULL DEFAULT 0,
  has_pets TINYINT(1) NOT NULL DEFAULT 0,
  experience TEXT NULL,
  message TEXT NOT NULL,
  privacy_consent TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('new','reviewing','contacted','viewing_scheduled','approved','declined','completed','cancelled') NOT NULL DEFAULT 'new',
  appointment_at DATETIME NULL,
  outcome_notes TEXT NULL,
  ip_hash CHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  source_page VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_applications_user_animal_open (user_id, animal_id, status),
  INDEX idx_applications_shelter_status (shelter_id, status),
  INDEX idx_applications_user_created (user_id, created_at),
  INDEX idx_applications_animal (animal_id),
  INDEX idx_applications_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  adoption_application_id INT UNSIGNED NULL,
  inquiry_id INT UNSIGNED NULL,
  shelter_id INT UNSIGNED NOT NULL,
  animal_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  appointment_at DATETIME NOT NULL,
  status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (adoption_application_id) REFERENCES adoption_applications(id) ON DELETE CASCADE,
  FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE,
  FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE CASCADE,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_appointments_shelter_time (shelter_id, appointment_at),
  INDEX idx_appointments_user_time (user_id, appointment_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recently_viewed (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  animal_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  session_id VARCHAR(128) NULL,
  viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_recent_user_animal (user_id, animal_id),
  INDEX idx_recent_session_animal (session_id, animal_id),
  INDEX idx_recent_user_viewed (user_id, viewed_at),
  INDEX idx_recent_session_viewed (session_id, viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_preferences (
  user_id INT UNSIGNED PRIMARY KEY,
  lifestyle VARCHAR(80) NULL,
  home_type VARCHAR(80) NULL,
  has_children TINYINT(1) NOT NULL DEFAULT 0,
  has_pets TINYINT(1) NOT NULL DEFAULT 0,
  preferred_species VARCHAR(80) NULL,
  preferred_size VARCHAR(30) NULL,
  preferred_age VARCHAR(80) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_preferences_match (preferred_species, preferred_size)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS taxonomies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(60) NOT NULL,
  value VARCHAR(120) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_taxonomies_type_value (type, value),
  INDEX idx_taxonomies_type_active (type, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO taxonomies (type, value, sort_order) VALUES
('species', 'Dog', 10),
('species', 'Cat', 20),
('species', 'Rabbit', 30),
('species', 'Bird', 40),
('breed', 'Mixed breed', 10),
('size', 'Small', 10),
('size', 'Medium', 20),
('size', 'Large', 30),
('size', 'Extra large', 40),
('animal_status', 'Available', 10),
('animal_status', 'Reserved', 20),
('animal_status', 'Medical hold', 30),
('animal_status', 'Adopted', 40),
('animal_status', 'Archived', 50),
('application_status', 'New', 10),
('application_status', 'Reviewing', 20),
('application_status', 'Contacted', 30),
('application_status', 'Viewing scheduled', 40),
('application_status', 'Approved', 50),
('application_status', 'Declined', 60),
('application_status', 'Completed', 70);
