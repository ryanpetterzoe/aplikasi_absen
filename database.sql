-- USERS
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('ADMIN','KEPSEK','YAYASAN','GURU','SISWA') NOT NULL,
  status ENUM('PENDING','ACTIVE','REJECTED') NOT NULL DEFAULT 'PENDING',
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,

  full_name VARCHAR(120) NOT NULL,
  phone_wa VARCHAR(30) NULL,
  address TEXT NULL,

  employee_no VARCHAR(50) NULL,
  nisn VARCHAR(50) NULL,

  class_id INT NULL,
  academic_year_id INT NULL,
  is_alumni TINYINT(1) NOT NULL DEFAULT 0,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- SCHOOL PROFILE (single row)
CREATE TABLE IF NOT EXISTS school_profile (
  id INT PRIMARY KEY,
  school_name VARCHAR(200) NOT NULL DEFAULT 'Sekolah',
  school_address TEXT NULL,
  school_phone VARCHAR(100) NULL,
  school_email VARCHAR(120) NULL,
  city VARCHAR(100) NULL,
  logo_path VARCHAR(255) NULL,
  geo_lat DECIMAL(10,7) NULL,
  geo_lng DECIMAL(10,7) NULL,
  geo_radius_m INT NULL DEFAULT 150
);
INSERT INTO school_profile (id, school_name) VALUES (1,'Sekolah') 
  ON DUPLICATE KEY UPDATE school_name=school_name;

-- ACADEMIC YEARS
CREATE TABLE IF NOT EXISTS academic_years (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0
);

-- MAJORS
CREATE TABLE IF NOT EXISTS majors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL
);

-- CLASSES
CREATE TABLE IF NOT EXISTS classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  grade INT NOT NULL, -- 10/11/12
  major_id INT NULL,
  homeroom_teacher_id INT NULL,
  academic_year_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (major_id) REFERENCES majors(id) ON DELETE SET NULL,
  FOREIGN KEY (homeroom_teacher_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL
);

-- WORK RULES (per active academic year)
CREATE TABLE IF NOT EXISTS work_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT NULL,
  checkin_time TIME NOT NULL DEFAULT '07:00:00',
  checkout_time TIME NOT NULL DEFAULT '15:00:00',
  late_tolerance_min INT NOT NULL DEFAULT 10,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL
);


-- WORK SCHEDULE (PER HARI)
CREATE TABLE IF NOT EXISTS work_schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT NOT NULL,
  day_of_week TINYINT NOT NULL, -- 1=Senin ... 7=Minggu (ISO-8601)
  is_workday TINYINT NOT NULL DEFAULT 1,
  checkin_time TIME NOT NULL,
  checkout_time TIME NOT NULL,
  UNIQUE KEY uniq_year_day (academic_year_id, day_of_week),
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
);

-- HOLIDAYS / LIBUR
CREATE TABLE IF NOT EXISTS holidays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT NULL,
  holiday_date DATE NOT NULL,
  name VARCHAR(120) NOT NULL,
  UNIQUE KEY uniq_hdate_year (holiday_date, academic_year_id),
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
);

-- ATTENDANCE
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  att_date DATE NOT NULL,
  checkin_at DATETIME NULL,
  checkin_lat DECIMAL(10,7) NULL,
  checkin_lng DECIMAL(10,7) NULL,
  checkin_photo_path VARCHAR(255) NULL,
  status_in ENUM('ONTIME','LATE') NULL,

  checkout_at DATETIME NULL,
  checkout_lat DECIMAL(10,7) NULL,
  checkout_lng DECIMAL(10,7) NULL,
  checkout_photo_path VARCHAR(255) NULL,
  status_out ENUM('NORMAL','EARLY') NULL,
  note_out VARCHAR(255) NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_date (user_id, att_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- LEAVE / IJIN
CREATE TABLE IF NOT EXISTS leave_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  leave_date DATE NOT NULL,
  reason VARCHAR(50) NOT NULL,
  description VARCHAR(255) NULL,

  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  approver_id INT NULL,
  decided_at DATETIME NULL,
  decision_note VARCHAR(255) NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_leave_user_date (user_id, leave_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE SET NULL
);


-- Seed admin
INSERT INTO users (role,status,username,password_hash,full_name,must_change_password)
VALUES ('ADMIN','ACTIVE','admin', '$2y$10$J/UKnhxptYjwG8u.Hb/RhezJzfXpgynI6H1YQT6qtisDk7NN7Ghem', 'Administrator', 0)
ON DUPLICATE KEY UPDATE username=username;

-- Password hash above = admin123 (generated with password_hash)


CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(120) NOT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- App settings
CREATE TABLE IF NOT EXISTS app_settings (
  `key` VARCHAR(60) PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO app_settings (`key`,`value`) VALUES
('app_name','Absensi Sekolah'),
('app_logo',''),
('footer_text','© '),
('marquee_enabled','0'),
('marquee_text','')
ON DUPLICATE KEY UPDATE value=VALUES(value);
