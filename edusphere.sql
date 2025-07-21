-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 20, 2025 at 09:01 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

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
(6, '');

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
(33, 3, 'Father');

-- --------------------------------------------------------

--
-- Table structure for table `schedule_class_teachers`
--

CREATE TABLE `schedule_class_teachers` (
  `class` varchar(10) NOT NULL COMMENT 'Grade level (1-10)',
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `schedule_teacher_subjects`
--

CREATE TABLE `schedule_teacher_subjects` (
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(3, '10', '2002-12-07', '9872500001');

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
(15, '', '');

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
(33, 'Balaram', 'Basnet', 'balaram@gmail.com', '$2y$10$lfTQJexs9HbZZ1/DWpFYWO2NfF18KcKVHfK5jtnEQQybWbCwgYqnC', '9829895221', 'Parent', 'male', '2025-07-20 17:59:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`user_id`);

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
-- AUTO_INCREMENT for table `schedule_entries`
--
ALTER TABLE `schedule_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_subjects`
--
ALTER TABLE `schedule_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_time_slots`
--
ALTER TABLE `schedule_time_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
