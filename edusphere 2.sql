-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 21, 2025 at 04:42 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `edusphere`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `user_id` int(11) NOT NULL,
  `role_description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`user_id`, `role_description`) VALUES
(6, ''),
(42, '');

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `class_name` varchar(100) NOT NULL DEFAULT 'Class 1',
  `subject` varchar(255) DEFAULT NULL,
  `teacher_name` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `title`, `due_date`, `status`, `class_name`, `subject`, `teacher_name`, `pdf_path`) VALUES
(1, 'Do 1 Page Handwriting', '2025-07-22', 'Open', 'Class 1', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `date`, `status`) VALUES
(1, 35, '2025-12-02', 'present'),
(2, 34, '2025-12-12', 'present'),
(3, 34, '2025-12-11', 'present'),
(4, 3, '2025-12-01', 'present'),
(5, 3, '2025-12-03', 'present'),
(6, 3, '2025-12-04', 'present');

-- --------------------------------------------------------

--
-- Table structure for table `fees`
--

CREATE TABLE `fees` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fees`
--

INSERT INTO `fees` (`id`, `student_id`, `class_name`, `description`, `amount`) VALUES
(1, 36, 'Grade 10', 'Tuition Fee', 5000.00),
(2, 36, 'Grade 10', 'Library Fee', 1000.00),
(3, 36, 'Grade 10', 'Lab Fee', 1500.00),
(4, 36, 'Grade 10', 'Total Fee', 7500.00),
(5, 34, 'Grade 9', 'Tuition Fee', 4500.00),
(6, 34, 'Grade 9', 'Library Fee', 900.00),
(7, 34, 'Grade 9', 'Lab Fee', 1200.00),
(8, 34, 'Grade 9', 'Total Fee', 6600.00);

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `category` enum('Assignment','Exam','Discipline','Classroom Activity') NOT NULL,
  `title` varchar(255) NOT NULL,
  `score` varchar(50) NOT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`id`, `student_id`, `category`, `title`, `score`, `grade`, `comments`, `date_added`) VALUES
(1, 1, 'Exam', 'First Term', '41', 'A', 'Very Good', '2025-07-21 08:06:24'),
(2, 36, 'Assignment', 'Assignment 3', '41', 'A', 'Very Good. Keep it up', '2025-07-21 09:33:59'),
(3, 36, 'Exam', 'First Term', '91', 'A+', 'Excellent', '2025-07-21 12:34:54'),
(4, 36, 'Discipline', 'Class Discipline', '72', 'B+', 'Good', '2025-07-21 13:34:18');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `sender_role` enum('parent','teacher') NOT NULL,
  `message` text NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `status` enum('sent','read') DEFAULT 'sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `user_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `relationship` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`user_id`, `student_id`, `relationship`) VALUES
(33, 3, 'Father'),
(41, 36, 'Father');

-- --------------------------------------------------------

--
-- Table structure for table `schedule_class_teachers`
--

CREATE TABLE `schedule_class_teachers` (
  `class` varchar(10) NOT NULL COMMENT 'Grade level (1-10)',
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_class_teachers`
--

INSERT INTO `schedule_class_teachers` (`class`, `user_id`) VALUES
('1', 5),
('8', 5),
('4', 6),
('9', 6),
('10', 7),
('2', 7),
('5', 8),
('3', 9),
('6', 10),
('7', 11);

-- --------------------------------------------------------

--
-- Table structure for table `schedule_entries`
--

CREATE TABLE `schedule_entries` (
  `id` int(11) NOT NULL,
  `class` varchar(10) NOT NULL,
  `day` enum('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  `time_slot_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Teacher user_id',
  `is_special` tinyint(1) DEFAULT 0 COMMENT 'For breaks/lunch/clubs',
  `special_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_subjects`
--

CREATE TABLE `schedule_subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `grade_range` varchar(20) NOT NULL COMMENT 'Format: 1-3, 4-5, 6-8, 9-10',
  `is_core` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_subjects`
--

INSERT INTO `schedule_subjects` (`id`, `name`, `grade_range`, `is_core`) VALUES
(1, 'English', '1-10', 1),
(2, 'Nepali', '1-10', 1),
(3, 'Mathematics', '1-10', 1),
(4, 'My Science', '1-3', 1),
(5, 'Science', '4-10', 1),
(6, 'Social Studies', '1-10', 1),
(7, 'Moral Education', '1-3', 1),
(8, 'HPE', '4-10', 1),
(9, 'Computer Studies', '4-5', 0),
(10, 'Computer Science', '6-10', 0),
(11, 'Optional Mathematics', '9-10', 0);

-- --------------------------------------------------------

--
-- Table structure for table `schedule_teacher_subjects`
--

CREATE TABLE `schedule_teacher_subjects` (
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_teacher_subjects`
--

INSERT INTO `schedule_teacher_subjects` (`user_id`, `subject_id`) VALUES
(5, 1),
(5, 7),
(6, 3),
(6, 11),
(7, 2),
(7, 6),
(8, 9),
(8, 10),
(9, 4),
(9, 5),
(10, 3),
(10, 5),
(11, 6),
(11, 8);

-- --------------------------------------------------------

--
-- Table structure for table `schedule_time_slots`
--

CREATE TABLE `schedule_time_slots` (
  `id` int(11) NOT NULL,
  `period_name` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_friday_special` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_time_slots`
--

INSERT INTO `schedule_time_slots` (`id`, `period_name`, `start_time`, `end_time`, `is_friday_special`) VALUES
(1, 'Period 1', '10:00:00', '10:45:00', 0),
(2, 'Period 2', '10:45:00', '11:30:00', 0),
(3, 'Short Break 1', '11:30:00', '11:35:00', 0),
(4, 'Period 3', '11:35:00', '12:20:00', 0),
(5, 'Period 4', '12:20:00', '13:05:00', 0),
(6, 'Lunch', '13:05:00', '13:35:00', 0),
(7, 'Period 5', '13:35:00', '14:20:00', 0),
(8, 'Period 6', '14:20:00', '15:05:00', 0),
(9, 'Short Break 2', '15:05:00', '15:10:00', 0),
(10, 'Period 7', '15:10:00', '15:55:00', 0),
(11, 'Club 1', '13:35:00', '14:20:00', 1),
(12, 'Club 2', '14:20:00', '15:05:00', 1),
(13, 'Short Break Friday', '15:05:00', '15:10:00', 1),
(14, 'Club 3', '15:10:00', '15:55:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `user_id` int(11) NOT NULL,
  `class` varchar(50) NOT NULL,
  `dob` date NOT NULL,
  `student_serial` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`user_id`, `class`, `dob`, `student_serial`) VALUES
(3, '10', '2002-12-07', '9872500001'),
(34, '8', '2025-06-19', '9872500002'),
(35, '10', '2025-06-18', '9872500003'),
(36, '10', '2025-06-19', '9872500004');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(11) NOT NULL,
  `student_email` varchar(100) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `submitted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`id`, `student_email`, `assignment_id`, `submitted_at`) VALUES
(1, 'student@example.com', 1, '2025-07-21 15:22:24');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `user_id` int(11) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`user_id`, `subject`, `department`) VALUES
(5, '', ''),
(7, '', ''),
(8, '', ''),
(9, '', ''),
(10, '', ''),
(11, '', ''),
(12, '', ''),
(13, '', ''),
(14, '', ''),
(15, '', ''),
(40, '', '');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `role` enum('Student','Teacher','Admin','Parent') NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password_hash`, `phone`, `role`, `gender`, `created_at`) VALUES
(3, 'Sanit', 'Basnet', 'sanitbasnet.fb27@gmail.com', '$2y$10$3WDXyU2CKDh4jc..ZzTGc.Wtpg4r2SvrAK72gjHPpQveo6o8NDG.G', 'sanitbasnet.fb27@gma', 'Student', 'male', '2025-07-01 17:14:24'),
(5, 'Bigyan', 'Basnet', 'bigyan@gmail.com', '$2y$10$suywxdxCy9ZfEmxgeldNnewzKJmXOIHHtmy25LK4qrKwIWNV5DnEa', 'bigyan@gmail.com', 'Teacher', 'male', '2025-07-08 03:34:00'),
(6, 'Sanit', 'Basnet', 'sanitadmin@gmail.com', '$2y$10$LwEkKbQGj2ooZLdFOoDcW.1del8V3dtZqBrSs6aKomjvYnfZlu5du', '9874561001', 'Admin', 'male', '2025-07-20 14:56:33'),
(7, 'Aashish', 'Satyal', 'aashish@gmail.com', '$2y$10$izm4eeaiCxkuo4/QaVEBD.b28vK7S6AGDQGiT4nVV4Pc0mqmOeGNq', '9856231447', 'Teacher', 'male', '2025-07-20 14:57:47'),
(8, 'Aayush', 'Timalsina', 'aayush@gmail.com', '$2y$10$QNZdUQWwTL/g6axqUVqele.5LdgkmdctXrGx.QejhVs40N4Ce.4OS', '9821256789', 'Teacher', 'male', '2025-07-20 14:58:55'),
(9, 'Amit', 'Rai', 'amit@gmail.com', '$2y$10$Qkl0xmOo1ZzQxWw7NeRFOuhGrWMeHMliLCyqkig72J413iAVbtVAS', '9871236540', 'Teacher', 'male', '2025-07-20 14:59:50'),
(10, 'Avipsa', 'Joshi', 'avipsa@gmail.com', '$2y$10$yCd5mlb3AAeQf4Z3Z.Vw2uUdiQsQc8/Yh7js2zy/Wb5R7ZleXpGq6', '9845633739', 'Teacher', 'female', '2025-07-20 15:02:18'),
(11, 'Bijaya', 'Giri', 'bijaya@gmail.com', '$2y$10$xCzg0dOgxkXmK/iZJtjgk.L0uTVcWCVKlNQbaRLKGnTAxBLiVu4CK', '9874156230', 'Teacher', 'female', '2025-07-20 15:03:43'),
(12, 'Biplov', 'Malla', 'biplov@gmail.com', '$2y$10$bL6djqz.3eALfI4gc.MxluVv2wBvk2fPoPgPhGHi4FKXYr5XtOwX2', '9876543210', 'Teacher', 'male', '2025-07-20 15:05:08'),
(13, 'Dinanath', 'Mukhiya', 'dinanath@gmail.com', '$2y$10$dHj4Z4ga7ZFoCdw1ZAFAUO4aC1/l31wEhT08ctNP0PFdaYj5lF0SK', '9880562144', 'Teacher', 'male', '2025-07-20 15:06:27'),
(14, 'Chandika', 'Wagle', 'chandika@gmail.com', '$2y$10$oVUpkTf/nraqfXzJ.RthUOpc5gSIjwOHJcE6ZjiB7/FPc6jNZIRKm', '9878456312', 'Teacher', 'female', '2025-07-20 15:08:19'),
(15, 'Hasin', 'Maharjan', 'hasin@gmail.com', '$2y$10$v32.Z4LOITVhWpJQZ3erde8EsqEDcSqJ5MiHWAmdfNIf2TsID4Qc.', '9820053221', 'Teacher', 'male', '2025-07-20 15:09:28'),
(33, 'Balaram', 'Basnet', 'balaram@gmail.com', '$2y$10$lfTQJexs9HbZZ1/DWpFYWO2NfF18KcKVHfK5jtnEQQybWbCwgYqnC', '9829895221', 'Parent', 'male', '2025-07-20 17:59:50'),
(34, 'Falano', 'Sharma', 'falano@gmail.com', '$2y$10$wvAJBC8oRWPuxrh2IvSf5egVq/vqJBIOO9WQAIzgY4fcMDnbl8Scy', '9823223323', 'Student', 'male', '2025-07-21 05:19:20'),
(35, 'bigyan', 'basnet', 'bigyan@student.com', '$2y$10$AQaKKd7QM0K1.4FuaUisi.ENHdLWJBS1noczTx2.hpZDnoIH9yita', '9877676765', 'Student', 'male', '2025-07-21 06:51:12'),
(36, 'Falano', 'Student', 'falanostudent@gmail.com', '$2y$10$Wmp9LfLAJTwc4ry9E.1qmOiV4sXafcUxPthKq2/T1xtGammnzfecy', '4526332345', 'Student', 'male', '2025-07-21 08:28:17'),
(39, 'Parent', 'One', 'parentone@gmail.com', '$2y$10$sBkUHB0LLQFnaU6vb6K3Iuk/nkbhJ/.0RuO0BAtWTM8W7Dtk/hIZC', '8767665654', 'Parent', 'male', '2025-07-21 10:06:41'),
(40, 'Teacher', 'One', 'teacherone@gmail.com', '$2y$10$KQ1tYR2E6WxSQyaNVj7Ue.2DpRUyLU1NtxigJ/uTtWqqv3WNDCon2', '7878767676', 'Teacher', 'male', '2025-07-21 10:55:10'),
(41, 'Falano', 'Parent', 'falankoparent@gmail.com', '$2y$10$4LkWGQF7b4/maTePHDf5GOvyRgBhI2XFWuB69FkdmHFEtjQQbTd1m', '9876767687', 'Parent', 'male', '2025-07-21 12:34:02'),
(42, 'Bigyan', 'Basnet', 'bigyanadmin@admin.com', '$2y$10$Z6g6OpND/K6UgsCOJ0sSleVPl63EGt9oeVgocRx7ogx7oFckvHjOO', '9856575777', 'Admin', 'male', '2025-07-21 14:02:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`student_id`,`date`);

--
-- Indexes for table `fees`
--
ALTER TABLE `fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `schedule_class_teachers`
--
ALTER TABLE `schedule_class_teachers`
  ADD PRIMARY KEY (`class`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `schedule_entries`
--
ALTER TABLE `schedule_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `time_slot_id` (`time_slot_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `schedule_subjects`
--
ALTER TABLE `schedule_subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedule_teacher_subjects`
--
ALTER TABLE `schedule_teacher_subjects`
  ADD PRIMARY KEY (`user_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `schedule_time_slots`
--
ALTER TABLE `schedule_time_slots`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `student_serial` (`student_serial`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_email` (`student_email`,`assignment_id`),
  ADD KEY `assignment_id` (`assignment_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `fees`
--
ALTER TABLE `fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_entries`
--
ALTER TABLE `schedule_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_subjects`
--
ALTER TABLE `schedule_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `schedule_time_slots`
--
ALTER TABLE `schedule_time_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `fees`
--
ALTER TABLE `fees`
  ADD CONSTRAINT `fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parents_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `schedule_class_teachers`
--
ALTER TABLE `schedule_class_teachers`
  ADD CONSTRAINT `schedule_class_teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedule_entries`
--
ALTER TABLE `schedule_entries`
  ADD CONSTRAINT `schedule_entries_ibfk_1` FOREIGN KEY (`time_slot_id`) REFERENCES `schedule_time_slots` (`id`),
  ADD CONSTRAINT `schedule_entries_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `schedule_subjects` (`id`),
  ADD CONSTRAINT `schedule_entries_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `schedule_teacher_subjects`
--
ALTER TABLE `schedule_teacher_subjects`
  ADD CONSTRAINT `schedule_teacher_subjects_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_teacher_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `schedule_subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`);

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
