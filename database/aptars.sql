-- ============================================================
-- APTARS - Academic Performance Tracking, Analysis & Reporting
-- Full schema + demo seed data
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `aptars_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `aptars_db`;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(50)  NOT NULL,
  `email`      VARCHAR(120) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','lecturer','student') NOT NULL,
  `status`     ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `last_login` DATETIME     DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email`    (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `code`        VARCHAR(20)  NOT NULL,
  `description` TEXT,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dept_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `programmes`;
CREATE TABLE `programmes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT UNSIGNED NOT NULL,
  `name`          VARCHAR(150) NOT NULL,
  `code`          VARCHAR(20)  NOT NULL,
  `level`         ENUM('Certificate','Diploma','Degree','Masters','PhD') NOT NULL DEFAULT 'Degree',
  `duration`      TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prog_code` (`code`),
  KEY `idx_prog_dept` (`department_id`),
  CONSTRAINT `fk_prog_dept` FOREIGN KEY (`department_id`)
    REFERENCES `departments`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `academic_years`;
CREATE TABLE `academic_years` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year_name`  VARCHAR(20) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date`   DATE NOT NULL,
  `status`     ENUM('active','inactive','closed') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_year_name` (`year_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `semesters`;
CREATE TABLE `semesters` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year_id`  INT UNSIGNED NOT NULL,
  `semester_name`     VARCHAR(50) NOT NULL,
  `start_date`        DATE NOT NULL,
  `end_date`          DATE NOT NULL,
  `status`            ENUM('active','inactive','closed') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_sem_year` (`academic_year_id`),
  CONSTRAINT `fk_sem_year` FOREIGN KEY (`academic_year_id`)
    REFERENCES `academic_years`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`             INT UNSIGNED DEFAULT NULL,
  `registration_number` VARCHAR(30) NOT NULL,
  `first_name`          VARCHAR(60) NOT NULL,
  `middle_name`         VARCHAR(60) DEFAULT NULL,
  `last_name`           VARCHAR(60) NOT NULL,
  `gender`              ENUM('Male','Female','Other') NOT NULL,
  `date_of_birth`       DATE DEFAULT NULL,
  `phone`               VARCHAR(20) DEFAULT NULL,
  `email`               VARCHAR(120) DEFAULT NULL,
  `programme_id`        INT UNSIGNED NOT NULL,
  `department_id`       INT UNSIGNED NOT NULL,
  `admission_year`      YEAR NOT NULL,
  `status`              ENUM('active','inactive','graduated','archived') NOT NULL DEFAULT 'active',
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_stud_reg`  (`registration_number`),
  KEY `idx_stud_prog` (`programme_id`),
  KEY `idx_stud_dept` (`department_id`),
  KEY `idx_stud_user` (`user_id`),
  CONSTRAINT `fk_stud_user`  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)        ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_stud_prog`  FOREIGN KEY (`programme_id`) REFERENCES `programmes`(`id`)   ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_stud_dept`  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `lecturers`;
CREATE TABLE `lecturers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED DEFAULT NULL,
  `staff_number`  VARCHAR(30) NOT NULL,
  `first_name`    VARCHAR(60) NOT NULL,
  `last_name`     VARCHAR(60) NOT NULL,
  `email`         VARCHAR(120) DEFAULT NULL,
  `phone`         VARCHAR(20)  DEFAULT NULL,
  `department_id` INT UNSIGNED NOT NULL,
  `status`        ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lect_staff` (`staff_number`),
  KEY `idx_lect_dept` (`department_id`),
  KEY `idx_lect_user` (`user_id`),
  CONSTRAINT `fk_lect_user` FOREIGN KEY (`user_id`)       REFERENCES `users`(`id`)        ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_lect_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)  ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `programme_id`  INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED NOT NULL,
  `code`          VARCHAR(20) NOT NULL,
  `name`          VARCHAR(150) NOT NULL,
  `credit_hours`  TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `semester`      ENUM('1','2','3') NOT NULL DEFAULT '1',
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_subj_code` (`code`),
  KEY `idx_subj_prog` (`programme_id`),
  KEY `idx_subj_dept` (`department_id`),
  CONSTRAINT `fk_subj_prog` FOREIGN KEY (`programme_id`)  REFERENCES `programmes`(`id`)   ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_subj_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)  ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `student_subjects`;
CREATE TABLE `student_subjects` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`        INT UNSIGNED NOT NULL,
  `subject_id`        INT UNSIGNED NOT NULL,
  `semester_id`       INT UNSIGNED NOT NULL,
  `registration_date` DATE NOT NULL,
  `status`            ENUM('registered','completed','dropped') NOT NULL DEFAULT 'registered',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_stud_subj_sem` (`student_id`,`subject_id`,`semester_id`),
  KEY `idx_ss_subj` (`subject_id`),
  KEY `idx_ss_sem`  (`semester_id`),
  CONSTRAINT `fk_ss_stud` FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`)   ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ss_subj` FOREIGN KEY (`subject_id`)  REFERENCES `subjects`(`id`)   ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ss_sem`  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`)  ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `lecturer_subjects`;
CREATE TABLE `lecturer_subjects` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lecturer_id`      INT UNSIGNED NOT NULL,
  `subject_id`       INT UNSIGNED NOT NULL,
  `semester_id`      INT UNSIGNED NOT NULL,
  `academic_year_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lec_subj_sem` (`lecturer_id`,`subject_id`,`semester_id`),
  KEY `idx_ls_subj` (`subject_id`),
  KEY `idx_ls_year` (`academic_year_id`),
  CONSTRAINT `fk_ls_lect` FOREIGN KEY (`lecturer_id`)      REFERENCES `lecturers`(`id`)      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ls_subj` FOREIGN KEY (`subject_id`)       REFERENCES `subjects`(`id`)       ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ls_sem`  FOREIGN KEY (`semester_id`)      REFERENCES `semesters`(`id`)      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ls_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `assessments`;
CREATE TABLE `assessments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_id`  INT UNSIGNED NOT NULL,
  `semester_id` INT UNSIGNED NOT NULL,
  `name`        VARCHAR(60) NOT NULL,
  `type`        ENUM('CAT','Assignment','Practical','Exam','Other') NOT NULL DEFAULT 'CAT',
  `max_marks`   DECIMAL(6,2) NOT NULL DEFAULT 100,
  `weight`      DECIMAL(5,2) NOT NULL DEFAULT 0,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_ass_subj` (`subject_id`),
  KEY `idx_ass_sem`  (`semester_id`),
  CONSTRAINT `fk_ass_subj` FOREIGN KEY (`subject_id`)  REFERENCES `subjects`(`id`)  ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ass_sem`  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `results`;
CREATE TABLE `results` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`       INT UNSIGNED NOT NULL,
  `subject_id`       INT UNSIGNED NOT NULL,
  `assessment_id`    INT UNSIGNED NOT NULL,
  `semester_id`      INT UNSIGNED NOT NULL,
  `academic_year_id` INT UNSIGNED NOT NULL,
  `marks`            DECIMAL(6,2) NOT NULL,
  `max_marks`        DECIMAL(6,2) NOT NULL,
  `percentage`       DECIMAL(5,2) NOT NULL,
  `grade`            VARCHAR(3) NOT NULL,
  `grade_point`      DECIMAL(3,2) NOT NULL DEFAULT 0,
  `entered_by`       INT UNSIGNED DEFAULT NULL,
  `status`           ENUM('draft','published') NOT NULL DEFAULT 'published',
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_res_stud_ass` (`student_id`,`assessment_id`,`semester_id`),
  KEY `idx_res_subj`  (`subject_id`),
  KEY `idx_res_sem`   (`semester_id`),
  KEY `idx_res_year`  (`academic_year_id`),
  CONSTRAINT `fk_res_stud` FOREIGN KEY (`student_id`)       REFERENCES `students`(`id`)       ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_res_subj` FOREIGN KEY (`subject_id`)       REFERENCES `subjects`(`id`)       ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_res_ass`  FOREIGN KEY (`assessment_id`)    REFERENCES `assessments`(`id`)    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_res_sem`  FOREIGN KEY (`semester_id`)      REFERENCES `semesters`(`id`)      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_res_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_res_user` FOREIGN KEY (`entered_by`)       REFERENCES `users`(`id`)          ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`      INT UNSIGNED NOT NULL,
  `subject_id`      INT UNSIGNED NOT NULL,
  `lecturer_id`     INT UNSIGNED DEFAULT NULL,
  `semester_id`     INT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status`          ENUM('Present','Absent','Late','Excused') NOT NULL,
  `remarks`         VARCHAR(255) DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_att_stud_subj_date` (`student_id`,`subject_id`,`attendance_date`),
  KEY `idx_att_subj` (`subject_id`),
  KEY `idx_att_sem`  (`semester_id`),
  CONSTRAINT `fk_att_stud` FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`)  ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_att_subj` FOREIGN KEY (`subject_id`)  REFERENCES `subjects`(`id`)  ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_att_lect` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers`(`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_att_sem`  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `influencing_factors`;
CREATE TABLE `influencing_factors` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `code`          VARCHAR(30)  NOT NULL,
  `description`   TEXT,
  `scale_min`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `scale_max`     TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `scale_labels`  JSON DEFAULT NULL,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_factor_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `student_factors`;
CREATE TABLE `student_factors` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`       INT UNSIGNED NOT NULL,
  `factor_id`        INT UNSIGNED NOT NULL,
  `semester_id`      INT UNSIGNED NOT NULL,
  `academic_year_id` INT UNSIGNED NOT NULL,
  `rating`           TINYINT UNSIGNED NOT NULL,
  `recorded_by`      INT UNSIGNED DEFAULT NULL,
  `recorded_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sf_stud_fac_sem` (`student_id`,`factor_id`,`semester_id`),
  KEY `idx_sf_fac` (`factor_id`),
  KEY `idx_sf_sem` (`semester_id`),
  CONSTRAINT `fk_sf_stud` FOREIGN KEY (`student_id`)       REFERENCES `students`(`id`)              ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_sf_fac`  FOREIGN KEY (`factor_id`)        REFERENCES `influencing_factors`(`id`)   ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_sf_sem`  FOREIGN KEY (`semester_id`)      REFERENCES `semesters`(`id`)             ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_sf_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`)        ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_sf_user` FOREIGN KEY (`recorded_by`)      REFERENCES `users`(`id`)                 ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `grading_scales`;
CREATE TABLE `grading_scales` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grade`          VARCHAR(3) NOT NULL,
  `min_percentage` DECIMAL(5,2) NOT NULL,
  `max_percentage` DECIMAL(5,2) NOT NULL,
  `grade_point`    DECIMAL(3,2) NOT NULL,
  `description`    VARCHAR(60) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grade` (`grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `title`      VARCHAR(120) NOT NULL,
  `message`    TEXT NOT NULL,
  `type`       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(60) NOT NULL,
  `setting_value` VARCHAR(255) DEFAULT NULL,
  `description`   VARCHAR(255) DEFAULT NULL,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `action`      VARCHAR(60) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_user` (`user_id`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA - all demo accounts use password: "password"
-- ============================================================

INSERT INTO `users` (`id`,`username`,`email`,`password`,`role`,`status`) VALUES
(1,'admin',   'admin@aptars.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin','active'),
(2,'lecturer1','lect1@aptars.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','lecturer','active'),
(3,'lecturer2','lect2@aptars.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','lecturer','active'),
(4,'student1', 'stud1@aptars.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','active'),
(5,'student2', 'stud2@aptars.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','active'),
(6,'student3', 'stud3@aptars.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','active'),
(7,'student4', 'stud4@aptars.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','active'),
(8,'student5', 'stud5@aptars.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','student','active');

INSERT INTO `departments` (`id`,`name`,`code`,`description`) VALUES
(1,'Computer Science','CS','Department of Computer Science and IT'),
(2,'Mathematics','MATH','Department of Mathematics and Statistics');

INSERT INTO `programmes` (`id`,`department_id`,`name`,`code`,`level`,`duration`) VALUES
(1,1,'BSc Computer Science','BSCS','Degree',4),
(2,2,'BSc Applied Mathematics','BSAM','Degree',4);

INSERT INTO `academic_years` (`id`,`year_name`,`start_date`,`end_date`,`status`) VALUES
(1,'2024/2025','2024-09-01','2025-06-30','active');

INSERT INTO `semesters` (`id`,`academic_year_id`,`semester_name`,`start_date`,`end_date`,`status`) VALUES
(1,1,'Semester 1','2024-09-01','2024-12-20','active'),
(2,1,'Semester 2','2025-01-13','2025-06-27','active');

INSERT INTO `students`
(`id`,`user_id`,`registration_number`,`first_name`,`middle_name`,`last_name`,`gender`,`date_of_birth`,`phone`,`email`,`programme_id`,`department_id`,`admission_year`) VALUES
(1,4,'CS/2024/001','Alice','Wanjiru','Kariuki','Female','2004-03-12','0700000001','alice@aptars.test',1,1,2024),
(2,5,'CS/2024/002','Brian','Otieno','Mwangi','Male','2003-11-05','0700000002','brian@aptars.test',1,1,2024),
(3,6,'CS/2024/003','Cynthia','Naliaka','Wafula','Female','2004-07-21','0700000003','cynthia@aptars.test',1,1,2024),
(4,7,'MA/2024/001','David','Kimani','Njoroge','Male','2003-09-30','0700000004','david@aptars.test',2,2,2024),
(5,8,'MA/2024/002','Esther','Achieng','Ouma','Female','2004-01-18','0700000005','esther@aptars.test',2,2,2024);

INSERT INTO `lecturers`
(`id`,`user_id`,`staff_number`,`first_name`,`last_name`,`email`,`phone`,`department_id`) VALUES
(1,2,'STF/CS/001','John','Kiprop','jkiprop@aptars.test','0710000001',1),
(2,3,'STF/MA/001','Mary','Njeri','mnjeri@aptars.test','0710000002',2);

INSERT INTO `subjects` (`id`,`programme_id`,`department_id`,`code`,`name`,`credit_hours`,`semester`) VALUES
(1,1,1,'CS101','Introduction to Programming',3,'1'),
(2,1,1,'CS102','Discrete Mathematics',3,'1'),
(3,1,1,'CS103','Computer Organisation',3,'1'),
(4,2,2,'MA101','Calculus I',3,'1'),
(5,2,2,'MA102','Linear Algebra',3,'1');

INSERT INTO `student_subjects` (`student_id`,`subject_id`,`semester_id`,`registration_date`) VALUES
(1,1,1,'2024-09-05'),(1,2,1,'2024-09-05'),(1,3,1,'2024-09-05'),
(2,1,1,'2024-09-05'),(2,2,1,'2024-09-05'),(2,3,1,'2024-09-05'),
(3,1,1,'2024-09-06'),(3,2,1,'2024-09-06'),(3,3,1,'2024-09-06'),
(4,4,1,'2024-09-06'),(4,5,1,'2024-09-06'),
(5,4,1,'2024-09-06'),(5,5,1,'2024-09-06');

INSERT INTO `lecturer_subjects` (`lecturer_id`,`subject_id`,`semester_id`,`academic_year_id`) VALUES
(1,1,1,1),(1,2,1,1),(1,3,1,1),
(2,4,1,1),(2,5,1,1);

INSERT INTO `assessments` (`id`,`subject_id`,`semester_id`,`name`,`type`,`max_marks`,`weight`) VALUES
(1,1,1,'CAT 1','CAT',30,10),
(2,1,1,'CAT 2','CAT',30,10),
(3,1,1,'Assignment','Assignment',20,10),
(4,1,1,'Final Exam','Exam',70,70),
(5,2,1,'CAT 1','CAT',30,10),
(6,2,1,'Final Exam','Exam',70,90);

INSERT INTO `results`
(`student_id`,`subject_id`,`assessment_id`,`semester_id`,`academic_year_id`,`marks`,`max_marks`,`percentage`,`grade`,`grade_point`,`entered_by`) VALUES
(1,1,1,1,1,24,30,80.00,'A',4.00,2),
(1,1,2,1,1,22,30,73.33,'A',4.00,2),
(1,1,3,1,1,17,20,85.00,'A',4.00,2),
(1,1,4,1,1,58,70,82.86,'A',4.00,2),
(1,2,5,1,1,21,30,70.00,'A',4.00,2),
(2,1,1,1,1,18,30,60.00,'B',3.00,2),
(2,1,2,1,1,17,30,56.67,'C',2.00,2),
(2,1,3,1,1,13,20,65.00,'B',3.00,2),
(2,1,4,1,1,44,70,62.86,'B',3.00,2),
(3,1,1,1,1,12,30,40.00,'D',1.00,2),
(3,1,2,1,1,14,30,46.67,'D',1.00,2),
(3,1,3,1,1,10,20,50.00,'C',2.00,2),
(3,1,4,1,1,30,70,42.86,'D',1.00,2);

INSERT INTO `attendance`
(`student_id`,`subject_id`,`lecturer_id`,`semester_id`,`attendance_date`,`status`) VALUES
(1,1,1,1,'2024-09-10','Present'),
(1,1,1,1,'2024-09-12','Present'),
(1,1,1,1,'2024-09-17','Present'),
(2,1,1,1,'2024-09-10','Present'),
(2,1,1,1,'2024-09-12','Absent'),
(2,1,1,1,'2024-09-17','Late'),
(3,1,1,1,'2024-09-10','Absent'),
(3,1,1,1,'2024-09-12','Absent'),
(3,1,1,1,'2024-09-17','Present');

INSERT INTO `influencing_factors`
(`id`,`name`,`code`,`description`,`scale_min`,`scale_max`,`scale_labels`) VALUES
(1,'Learning Facilities Availability','FACILITIES','Availability and adequacy of learning facilities such as labs, classrooms, and equipment.',1,5,
 JSON_OBJECT('1','Very Poor','2','Poor','3','Average','4','Good','5','Excellent')),
(2,'Teacher Effectiveness','TEACHER','Perceived effectiveness of the lecturer in delivering the subject.',1,5,
 JSON_OBJECT('1','Very Low','2','Low','3','Average','4','High','5','Very High')),
(3,'Access to Learning Resources','RESOURCES','Access to books, e-resources, and reference material.',1,5,
 JSON_OBJECT('1','Very Poor','2','Poor','3','Average','4','Good','5','Excellent')),
(4,'Study Environment','STUDY_ENV','Conduciveness of the study environment on campus.',1,5,
 JSON_OBJECT('1','Very Poor','2','Poor','3','Average','4','Good','5','Excellent'));

INSERT INTO `student_factors`
(`student_id`,`factor_id`,`semester_id`,`academic_year_id`,`rating`,`recorded_by`) VALUES
(1,1,1,1,4,1),(1,2,1,1,5,1),(1,3,1,1,4,1),(1,4,1,1,4,1),
(2,1,1,1,3,1),(2,2,1,1,3,1),(2,3,1,1,3,1),(2,4,1,1,3,1),
(3,1,1,1,2,1),(3,2,1,1,2,1),(3,3,1,1,2,1),(3,4,1,1,2,1);

INSERT INTO `grading_scales`
(`grade`,`min_percentage`,`max_percentage`,`grade_point`,`description`) VALUES
('A',70.00,100.00,4.00,'Excellent'),
('B',60.00,69.99,3.00,'Good'),
('C',50.00,59.99,2.00,'Satisfactory'),
('D',40.00,49.99,1.00,'Pass'),
('E',0.00,39.99,0.00,'Fail');

INSERT INTO `settings` (`setting_key`,`setting_value`,`description`) VALUES
('institution_name','Demo University','Displayed on reports and page header.'),
('system_title','APTARS','Short name of the system.'),
('current_academic_year_id','1','Active academic year id.'),
('current_semester_id','1','Active semester id.'),
('attendance_threshold','75','Minimum attendance percentage before attention flag.'),
('performance_threshold','50','Minimum average mark before attention flag.'),
('failed_subjects_threshold','2','Number of failed subjects that triggers attention flag.');

INSERT INTO `notifications` (`user_id`,`title`,`message`,`type`) VALUES
(1,'Welcome to APTARS','Phase 1 foundation installed successfully.','success');