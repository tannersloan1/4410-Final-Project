DROP DATABASE IF EXISTS lms;
CREATE DATABASE lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lms;

-- ---- Users ----
CREATE TABLE STUDENT_INFO (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(100) UNIQUE NOT NULL,
    full_name  VARCHAR(100)
);

CREATE TABLE TEACHER_INFO (
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(100) UNIQUE NOT NULL,
    full_name  VARCHAR(100)
);

CREATE TABLE ADMIN_INFO (
    admin_id  INT AUTO_INCREMENT PRIMARY KEY,
    email     VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100)
);

CREATE TABLE STUDENT_USERS (
    student_id    INT PRIMARY KEY,
    email         VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES STUDENT_INFO(student_id) ON DELETE CASCADE
);

CREATE TABLE TEACHER_USERS (
    teacher_id    INT PRIMARY KEY,
    email         VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES TEACHER_INFO(teacher_id) ON DELETE CASCADE
);

CREATE TABLE ADMIN_USERS (
    admin_id      INT PRIMARY KEY,
    email         VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES ADMIN_INFO(admin_id) ON DELETE CASCADE
);

-- Default admin (password: admin)
INSERT INTO ADMIN_INFO (email, full_name) VALUES ('admin@gmail.com', 'Example Admin');
INSERT INTO ADMIN_USERS (admin_id, email, password_hash)
VALUES (1, 'admin@gmail.com', '$2y$10$CrE8Fwi8NYqsmOh4.NV0u.0CG2chACqR3GBLPhfu1MyAwAtTmMa2K');

-- ---- Activity log ----
CREATE TABLE LOGS (
    record_id          INT AUTO_INCREMENT PRIMARY KEY,
    id                 INT NOT NULL,
    `role`             VARCHAR(10) NOT NULL,
    action_type        VARCHAR(255) NOT NULL,
    action_description VARCHAR(255),
    table_affected     VARCHAR(255),
    ip_address         VARCHAR(255),
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---- CLASSES: one row per class, no student_id here ----
CREATE TABLE CLASSES (
    class_id   INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES TEACHER_INFO(teacher_id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_class (teacher_id, class_name)
);

-- ---- CLASS_ENROLMENTS: one row per student per class ----
CREATE TABLE CLASS_ENROLMENTS (
    enrolment_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id     INT NOT NULL,
    student_id   INT NOT NULL,
    enrolled_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id)   REFERENCES CLASSES(class_id)         ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES STUDENT_INFO(student_id)  ON DELETE CASCADE,
    UNIQUE KEY one_enrolment (class_id, student_id)
);

-- ---- QUIZZES ----
CREATE TABLE QUIZZES (
    quiz_id      INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id   INT NOT NULL,
    class_id     INT DEFAULT NULL,
    title        VARCHAR(255) NOT NULL DEFAULT 'Untitled Quiz',
    description  TEXT,
    time_limit   INT DEFAULT NULL,
    is_published BOOLEAN DEFAULT FALSE,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES TEACHER_INFO(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id)   REFERENCES CLASSES(class_id)        ON DELETE SET NULL
);

-- ---- QUESTIONS ----
CREATE TABLE QUESTIONS (
    question_id   INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id       INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice','fill_in_the_blank','free_response') NOT NULL,
    answer        TEXT NOT NULL,
    points        INT DEFAULT 1,
    auto_graded   BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (quiz_id) REFERENCES QUIZZES(quiz_id) ON DELETE CASCADE
);

-- ---- ANSWER_CHOICES ----
CREATE TABLE ANSWER_CHOICES (
    choice_id    INT AUTO_INCREMENT PRIMARY KEY,
    question_id  INT NOT NULL,
    choice_text  TEXT NOT NULL,
    is_correct   BOOLEAN DEFAULT FALSE,
    choice_order TINYINT DEFAULT 1,
    FOREIGN KEY (question_id) REFERENCES QUESTIONS(question_id) ON DELETE CASCADE
);

-- ---- STUDENT_SUBMISSIONS ----
CREATE TABLE STUDENT_SUBMISSIONS (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id       INT NOT NULL,
    student_id    INT NOT NULL,
    started_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at  TIMESTAMP NULL,
    score         INT DEFAULT 0,
    total_points  INT DEFAULT 0,
    percentage    DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (quiz_id)    REFERENCES QUIZZES(quiz_id)         ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES STUDENT_INFO(student_id) ON DELETE CASCADE,
    UNIQUE KEY one_attempt (quiz_id, student_id)
);

-- ---- STUDENT_ANSWERS ----
CREATE TABLE STUDENT_ANSWERS (
    answer_id        INT AUTO_INCREMENT PRIMARY KEY,
    submission_id    INT NOT NULL,
    question_id      INT NOT NULL,
    chosen_choice_id INT DEFAULT NULL,
    answer_text      TEXT DEFAULT NULL,
    is_correct       BOOLEAN DEFAULT FALSE,
    points_earned    INT DEFAULT 0,
    FOREIGN KEY (submission_id)    REFERENCES STUDENT_SUBMISSIONS(submission_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id)      REFERENCES QUESTIONS(question_id)             ON DELETE CASCADE,
    FOREIGN KEY (chosen_choice_id) REFERENCES ANSWER_CHOICES(choice_id)         ON DELETE SET NULL
);
