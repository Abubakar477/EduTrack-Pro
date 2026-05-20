-- ============================================================
--  Student Performance Dashboard — Database Schema
--  Run this in phpMyAdmin or via setup.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS student_dashboard
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE student_dashboard;

-- ─── Users (teachers + students) ──────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(100) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('teacher','student') NOT NULL DEFAULT 'student',
    roll_no     VARCHAR(30)  NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── Semesters ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS semesters (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(60) NOT NULL,
    start_date  DATE,
    end_date    DATE,
    is_current  TINYINT(1) DEFAULT 0
) ENGINE=InnoDB;

-- ─── Subjects ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS subjects (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    name         VARCHAR(100) NOT NULL,
    code         VARCHAR(20)  NOT NULL,
    credit_hours INT DEFAULT 3
) ENGINE=InnoDB;

-- ─── Grades ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS grades (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    student_id      INT NOT NULL,
    subject_id      INT NOT NULL,
    semester_id     INT NOT NULL,
    marks_obtained  DECIMAL(5,2) NOT NULL,
    total_marks     DECIMAL(5,2) NOT NULL DEFAULT 100,
    submitted_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (subject_id)  REFERENCES subjects(id)  ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    UNIQUE KEY uq_grade (student_id, subject_id, semester_id)
) ENGINE=InnoDB;

-- ─── Attendance ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS attendance (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    student_id  INT NOT NULL,
    date        DATE NOT NULL,
    status      ENUM('present','absent','holiday','late') NOT NULL DEFAULT 'present',
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_att (student_id, date)
) ENGINE=InnoDB;
