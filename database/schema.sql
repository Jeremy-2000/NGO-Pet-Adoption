CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('visitor','shelter','admin') NOT NULL DEFAULT 'visitor',
  status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shelters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  description TEXT NULL,
  logo_path VARCHAR(255) NULL,
  contact_email VARCHAR(190) NULL,
  contact_phone VARCHAR(50) NULL,
  website VARCHAR(255) NULL,
  facebook_url VARCHAR(255) NULL,
  instagram_url VARCHAR(255) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  country VARCHAR(120) NULL,
  status ENUM('applied','pending_review','approved','rejected') NOT NULL DEFAULT 'applied',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_shelters_status (status),
  INDEX idx_shelters_city_region_country (city, region, country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS animals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shelter_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  species VARCHAR(80) NOT NULL,
  breed VARCHAR(120) NULL,
  age VARCHAR(80) NULL,
  gender VARCHAR(20) NULL,
  size VARCHAR(30) NULL,
  color VARCHAR(80) NULL,
  status ENUM('available','reserved','adopted','medical_hold','archived','rejected') NOT NULL DEFAULT 'available',
  good_with_children TINYINT(1) NOT NULL DEFAULT 0,
  good_with_dogs TINYINT(1) NOT NULL DEFAULT 0,
  good_with_cats TINYINT(1) NOT NULL DEFAULT 0,
  energy_level VARCHAR(30) NULL,
  temperament VARCHAR(255) NULL,
  vaccinated TINYINT(1) NOT NULL DEFAULT 0,
  spayed_neutered TINYINT(1) NOT NULL DEFAULT 0,
  medical_conditions TEXT NULL,
  special_needs TEXT NULL,
  video_url VARCHAR(255) NULL,
  views_count INT UNSIGNED NOT NULL DEFAULT 0,
  favorites_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_senior TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE CASCADE,
  INDEX idx_animals_status_created (status, created_at),
  INDEX idx_animals_species_breed (species, breed),
  INDEX idx_animals_featured_status (is_featured, status),
  INDEX idx_animals_shelter_status (shelter_id, status),
  FULLTEXT KEY ft_animals_search (name, species, breed, temperament)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS animal_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  animal_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  thumbnail_path VARCHAR(255) NULL,
  mime_type VARCHAR(100) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  crop_focus ENUM('center','top','bottom','left','right') NOT NULL DEFAULT 'center',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  INDEX idx_animal_images_animal_sort (animal_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  animal_id INT UNSIGNED NULL,
  shelter_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(50) NULL,
  message TEXT NOT NULL,
  ip_hash CHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  source_page VARCHAR(255) NULL,
  status ENUM('new','contacted','viewing_scheduled','approved','declined','completed','closed') NOT NULL DEFAULT 'new',
  appointment_at DATETIME NULL,
  internal_notes TEXT NULL,
  notified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE SET NULL,
  FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_inquiries_shelter_status (shelter_id, status),
  INDEX idx_inquiries_animal (animal_id),
  INDEX idx_inquiries_created (created_at),
  INDEX idx_inquiries_ip_created (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS votes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  matchup_key CHAR(64) NOT NULL,
  animal_a_id INT UNSIGNED NOT NULL,
  animal_b_id INT UNSIGNED NOT NULL,
  winner_animal_id INT UNSIGNED NOT NULL,
  voter_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_a_id) REFERENCES animals(id) ON DELETE CASCADE,
  FOREIGN KEY (animal_b_id) REFERENCES animals(id) ON DELETE CASCADE,
  FOREIGN KEY (winner_animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  UNIQUE KEY uq_votes_matchup_voter (matchup_key, voter_hash),
  INDEX idx_votes_winner_created (winner_animal_id, created_at),
  INDEX idx_votes_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favorites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  animal_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  session_id VARCHAR(128) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_favorites_user_animal (user_id, animal_id),
  INDEX idx_favorites_session_animal (session_id, animal_id),
  INDEX idx_favorites_animal (animal_id)
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

CREATE TABLE IF NOT EXISTS reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  animal_id INT UNSIGNED NULL,
  shelter_id INT UNSIGNED NULL,
  reporter_name VARCHAR(150) NOT NULL,
  reporter_email VARCHAR(190) NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('open','reviewed','resolved') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE SET NULL,
  FOREIGN KEY (shelter_id) REFERENCES shelters(id) ON DELETE SET NULL,
  INDEX idx_reports_status_created (status, created_at),
  INDEX idx_reports_animal (animal_id),
  INDEX idx_reports_shelter (shelter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(80) NOT NULL,
  identity_hash CHAR(64) NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 1,
  window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rate_limits_action_identity (action, identity_hash),
  INDEX idx_rate_limits_expires (expires_at)
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

CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id INT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  target_type VARCHAR(80) NOT NULL,
  target_id INT UNSIGNED NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_activity_log_created (created_at),
  INDEX idx_activity_log_actor (actor_id, created_at),
  INDEX idx_activity_log_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
