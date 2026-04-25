USE lms;


-- QUIZZES
ALTER TABLE QUIZZES
    ADD COLUMN title        VARCHAR(255) NOT NULL DEFAULT 'Untitled Quiz' AFTER class_id,
    ADD COLUMN description  TEXT                                           AFTER title,
    ADD COLUMN time_limit   INT          DEFAULT NULL                      AFTER description,
    ADD COLUMN is_published BOOLEAN      DEFAULT FALSE                     AFTER time_limit,
    ADD COLUMN created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP         AFTER is_published;



-- QUESTIONS
ALTER TABLE QUESTIONS
    ADD COLUMN points INT DEFAULT 1 AFTER answer;
  


-- ANSWER_CHOICES  QUESTIONS.answer stores the correct choice_id after insert.
CREATE TABLE ANSWER_CHOICES (
    choice_id    INT AUTO_INCREMENT PRIMARY KEY,
    question_id  INT          NOT NULL,
    choice_text  TEXT         NOT NULL,
    is_correct   BOOLEAN      DEFAULT FALSE,
    choice_order TINYINT      DEFAULT 1,        -- 1-4, controls display order
    FOREIGN KEY (question_id) REFERENCES QUESTIONS(question_id) ON DELETE CASCADE
);


-- STUDENT_SUBMISSIONS
CREATE TABLE STUDENT_SUBMISSIONS (
    submission_id  INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id        INT       NOT NULL,
    student_id     INT       NOT NULL,
    started_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at   TIMESTAMP NULL,            
    score          INT       DEFAULT 0,       
    total_points   INT       DEFAULT 0,       
    percentage     DECIMAL(5,2) DEFAULT 0.00,  
    FOREIGN KEY (quiz_id)    REFERENCES QUIZZES(quiz_id)           ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES STUDENT_INFO(student_id)   ON DELETE CASCADE,
    UNIQUE KEY one_attempt (quiz_id, student_id)
);



-- STUDENT_ANSWERS
CREATE TABLE STUDENT_ANSWERS (
    answer_id       INT AUTO_INCREMENT PRIMARY KEY,
    submission_id   INT  NOT NULL,
    question_id     INT  NOT NULL,
    chosen_choice_id INT  DEFAULT NULL,
    answer_text     TEXT DEFAULT NULL,
    is_correct      BOOLEAN   DEFAULT FALSE,
    points_earned   INT       DEFAULT 0,
    FOREIGN KEY (submission_id)    REFERENCES STUDENT_SUBMISSIONS(submission_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id)      REFERENCES QUESTIONS(question_id)             ON DELETE CASCADE,
    FOREIGN KEY (chosen_choice_id) REFERENCES ANSWER_CHOICES(choice_id)         ON DELETE SET NULL
);


