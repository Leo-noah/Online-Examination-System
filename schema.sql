-- Online Examination System: empty database schema
-- Import into an EMPTY uit_exam_system database.
-- No accounts, credentials, questions, submissions, or personal data.
-- This file creates tables; it does not migrate an existing database.

SET NAMES utf8mb4;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stu_id` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `year_level` int DEFAULT NULL,
  `tr_year_level` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tr_id` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'student',
  `country` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `course` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `profile_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `login_attempts` int DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `email_2` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role` enum('teacher','student') COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_name` (`role`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_name` varchar(255) NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `semester` int DEFAULT NULL,
  `major_name` varchar(50) DEFAULT 'Core',
  PRIMARY KEY (`id`),
  KEY `fk_major` (`major_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `majors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `major_name` varchar(100) NOT NULL,
  `short_name` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `exams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int DEFAULT NULL,
  `teacher_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `course` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `exam_date` datetime DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `time_limit` int NOT NULL,
  `show_details` tinyint(1) DEFAULT '0',
  `tr_year_level` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_posted` tinyint(1) DEFAULT '0',
  `allow_retake` tinyint(1) DEFAULT '0',
  `course_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `password_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `identity` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `request_type` varchar(50) DEFAULT 'change',
  `status` enum('pending','approved','rejected','messaged') DEFAULT 'pending',
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_2` (`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `question_ranges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `start_q` int NOT NULL,
  `end_q` int NOT NULL,
  `q_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `marks_per_q` decimal(5,2) DEFAULT '1.00',
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `question_ranges_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `q_number` int NOT NULL,
  `q_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `question_text` text COLLATE utf8mb4_general_ci NOT NULL,
  `correct_answer` text COLLATE utf8mb4_general_ci,
  `options` longtext COLLATE utf8mb4_general_ci,
  `question_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `student_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `student_id` int NOT NULL,
  `student_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `answers_json` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `teacher_highlights_json` longtext COLLATE utf8mb4_general_ci,
  `marks_json` longtext COLLATE utf8mb4_general_ci,
  `teacher_recommendations_json` longtext COLLATE utf8mb4_general_ci,
  `total_score` decimal(5,2) DEFAULT '0.00',
  `status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `submitted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_submissions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
