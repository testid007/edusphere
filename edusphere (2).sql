-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 19, 2025 at 05:13 PM
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
(42, ''),
(49, '');

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
  `file_url` varchar(500) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `teacher_name` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `title`, `due_date`, `status`, `class_name`, `file_url`, `subject`, `teacher_name`, `pdf_path`, `teacher_id`, `description`, `image_url`, `created_at`) VALUES
(1, 'Do 1 Page Handwriting', '2025-07-22', 'Open', 'Class 1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-19 21:03:19'),
(2, 'Assignment 2', '2025-07-24', 'Open', 'Class 1', 'uploads/assign_687ed4d77d85f3.73285542.pdf', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-19 21:03:19'),
(3, 'Test Assignment', '2025-07-24', 'Open', 'Class 1', '', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-19 21:03:19');

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
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classrooms`
--

CREATE TABLE `classrooms` (
  `id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `section` varchar(10) DEFAULT NULL,
  `total_students` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classrooms`
--

INSERT INTO `classrooms` (`id`, `class_name`, `section`, `total_students`) VALUES
(1, 'Grade 10', 'A', 45),
(2, 'Grade 10', 'B', 43),
(3, 'Grade 9', 'A', 47);

-- --------------------------------------------------------

--
-- Table structure for table `class_requirements`
--

CREATE TABLE `class_requirements` (
  `id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `periods_per_week` int(11) DEFAULT 1,
  `min_gap_hours` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `image_path`, `category_id`, `event_date`, `start_time`, `end_time`, `location`, `created_by`, `updated_by`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'Inter-house Football Tournament', 'Knockout football competition for class 8–10.', NULL, 1, '2025-09-11', '07:00:00', '18:00:00', 'School Premises', 49, NULL, '2025-11-19 06:18:06', NULL, 1),
(3, 'Inter-house Cricket Tournamen', 'Knockout Tournament', 'events/1763537559_2db72a93.png', 2, '2025-11-30', '07:00:00', '17:20:00', 'School Premises', 49, NULL, '2025-11-19 06:29:24', '2025-11-19 07:32:39', 1),
(4, 'District Level CricketTournament', 'League games with only top 2 team from each group proceeding to next round', 'events/1763537575_533e2c08.png', 2, '2025-12-22', '07:20:00', '19:20:00', 'District Cricket Ground', 49, NULL, '2025-11-19 06:37:00', '2025-11-19 07:32:55', 1);

-- --------------------------------------------------------

--
-- Table structure for table `event_categories`
--

CREATE TABLE `event_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_categories`
--

INSERT INTO `event_categories` (`id`, `name`) VALUES
(7, 'Art'),
(4, 'Athletics'),
(3, 'Basketball'),
(2, 'Cricket'),
(6, 'Dance'),
(1, 'Football'),
(5, 'Music'),
(10, 'Other'),
(8, 'Seminar'),
(9, 'Workshop');

-- --------------------------------------------------------

--
-- Table structure for table `event_participation`
--

CREATE TABLE `event_participation` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('interested','registered','participated','absent') DEFAULT 'interested',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_participation`
--

INSERT INTO `event_participation` (`id`, `event_id`, `user_id`, `status`, `created_at`) VALUES
(1, 3, 50, 'registered', '2025-11-19 06:29:39'),
(2, 3, 51, '', '2025-11-19 06:59:07'),
(3, 4, 51, '', '2025-11-19 06:59:15');

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
-- Table structure for table `generated_schedules`
--

CREATE TABLE `generated_schedules` (
  `id` int(11) NOT NULL,
  `schedule_name` varchar(100) NOT NULL,
  `generated_by` int(11) NOT NULL,
  `generation_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 0,
  `fitness_score` decimal(5,2) DEFAULT NULL,
  `conflicts_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `special_name` varchar(50) DEFAULT NULL,
  `schedule_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_entries`
--

INSERT INTO `schedule_entries` (`id`, `class`, `day`, `time_slot_id`, `subject_id`, `user_id`, `is_special`, `special_name`, `schedule_id`) VALUES
(1, '1', 'Sunday', 1, 1, 5, 0, NULL, NULL),
(2, '1', 'Sunday', 2, 2, 7, 0, NULL, NULL),
(3, '1', 'Sunday', 4, 3, 10, 0, NULL, NULL),
(4, '1', 'Sunday', 3, 12, 43, 0, NULL, NULL),
(5, '1', 'Sunday', 1, 7, 12, 0, NULL, NULL),
(6, '1', 'Sunday', 15, 8, 11, 0, NULL, NULL),
(7, '1', 'Sunday', 15, 8, 11, 0, NULL, NULL),
(8, '1', 'Sunday', 15, 8, 11, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `schedule_subjects`
--

CREATE TABLE `schedule_subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  `grade_range` varchar(20) NOT NULL COMMENT 'Format: 1-3, 4-5, 6-8, 9-10',
  `is_core` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_subjects`
--

INSERT INTO `schedule_subjects` (`id`, `name`, `color`, `grade_range`, `is_core`) VALUES
(1, 'English', '#667eea', '1-10', 1),
(2, 'Nepali', '#667eea', '1-10', 1),
(3, 'Mathematics', '#667eea', '1-10', 1),
(4, 'My Science', '#667eea', '1-3', 1),
(5, 'Science', '#667eea', '4-10', 1),
(6, 'Social Studies', '#667eea', '1-10', 1),
(7, 'Moral Education', '#667eea', '1-3', 1),
(8, 'HPE', '#667eea', '4-10', 1),
(9, 'Computer Studies', '#667eea', '4-5', 0),
(10, 'Computer Science', '#667eea', '6-10', 0),
(11, 'Optional Mathematics', '#667eea', '9-10', 0),
(12, 'Short Break', '#667eea', '1-10', 1),
(13, 'Lunch Break', '#667eea', '1-10', 1),
(14, 'Mathematics', '#FF6B6B', '', 1),
(15, 'English', '#4ECDC4', '', 1),
(16, 'Science', '#45B7D1', '', 1),
(17, 'History', '#96CEB4', '', 1),
(18, 'Geography', '#FFEAA7', '', 1),
(19, 'Computer Science', '#DDA0DD', '', 1),
(20, 'Physical Education', '#98D8C8', '', 1),
(21, 'Art', '#F7DC6F', '', 1),
(22, 'Physics', '#E74C3C', '', 1),
(23, 'Chemistry', '#9B59B6', '', 1),
(24, 'Biology', '#27AE60', '', 1),
(25, 'Literature', '#F39C12', '', 1),
(26, 'Social Studies', '#34495E', '', 1),
(27, 'Music', '#E67E22', '', 1),
(28, 'French', '#3498DB', '', 1),
(29, 'Spanish', '#2ECC71', '', 1);

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
(11, 8),
(12, 2),
(12, 7);

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
(14, 'Club 3', '15:10:00', '15:55:00', 1),
(15, 'Period 1', '08:00:00', '09:00:00', 0),
(16, 'Period 2', '09:00:00', '10:00:00', 0),
(17, 'Break', '10:00:00', '10:30:00', 0),
(18, 'Period 3', '10:30:00', '11:30:00', 0),
(19, 'Period 4', '11:30:00', '12:30:00', 0),
(20, 'Lunch', '12:30:00', '13:30:00', 0),
(21, 'Period 5', '13:30:00', '14:30:00', 0),
(22, 'Period 6', '14:30:00', '15:30:00', 0),
(23, 'Period 7', '15:30:00', '16:30:00', 0);

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
(36, '10', '2025-06-19', '9872500004'),
(44, '8', '2010-02-12', '9872500005'),
(50, '1', '2006-08-12', '9872500006'),
(51, '2', '2009-12-25', '9872500007');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subject_teacher`
--

CREATE TABLE `subject_teacher` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(40, '', ''),
(43, '', ''),
(52, '', '');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_availability`
--

CREATE TABLE `teacher_availability` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_constraints`
--

CREATE TABLE `teacher_constraints` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `max_periods_per_day` int(11) DEFAULT 6,
  `max_periods_per_week` int(11) DEFAULT 25,
  `min_break_between_classes` int(11) DEFAULT 0,
  `preferred_subjects` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(42, 'Bigyan', 'Basnet', 'bigyanadmin@admin.com', '$2y$10$Z6g6OpND/K6UgsCOJ0sSleVPl63EGt9oeVgocRx7ogx7oFckvHjOO', '9856575777', 'Admin', 'male', '2025-07-21 14:02:17'),
(43, 'No', 'teacher', 'noteacher@noteacher.vom', '$2y$10$92Y.vejwgejXNyVyckuQkOVpCt3G3/ltncOsJzvhEqyjSff3r0gbW', '5674656767', 'Teacher', 'male', '2025-07-22 02:58:32'),
(44, 'Test', 'Student', 'teststudent@gmail.com', '$2y$10$mNnqAD2jesDRrOOtOUWXvOZWU3WOw2fncjUMI0TUUPpTPI/qiM/fe', '9845343343', 'Student', 'male', '2025-11-12 14:39:32'),
(49, 'Main', 'Admin', 'mainadmin@gmail.com', '$2y$10$8D5vKR4x/oYyt7MkK8t2WeznH76SzbdYqiVaaWW2.k/PyyalbVPxW', '9867656565', 'Admin', 'male', '2025-11-14 05:07:49'),
(50, 'teststudent', 'one', 'teststudent1@gmail.com', '$2y$10$8tmXWJyLnm/9HSdbT7q0yuBM1VNWl5JdBoYrUzGQfitKKGXxiHegq', '9855652265', 'Student', 'male', '2025-11-19 05:46:05'),
(51, 'teststudent', 'two', 'teststudent2@gmail.com', '$2y$10$li7D/FOtaXUX3xmHUfyK0upHTDTKKgWe4qNdyZlYpe2Qk90QTIUYm', '9852542256', 'Student', 'male', '2025-11-19 06:38:18'),
(52, 'testteacher', 'one', 'testteacher1@gmail.com', '$2y$10$Zen9Fz8prX7Qc8X5BFshJuLoULygxTNF5Ie23YkLZTQzRwXE1Km8u', '9852142635', 'Teacher', 'female', '2025-11-19 15:20:35');

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
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `classrooms`
--
ALTER TABLE `classrooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `class_requirements`
--
ALTER TABLE `class_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_class_subject` (`class_name`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_events_category` (`category_id`),
  ADD KEY `fk_events_created_by` (`created_by`);

--
-- Indexes for table `event_categories`
--
ALTER TABLE `event_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `event_participation`
--
ALTER TABLE `event_participation`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_event` (`event_id`,`user_id`),
  ADD KEY `fk_participation_user` (`user_id`);

--
-- Indexes for table `fees`
--
ALTER TABLE `fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `generated_schedules`
--
ALTER TABLE `generated_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `generated_by` (`generated_by`);

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
  ADD KEY `user_id` (`user_id`),
  ADD KEY `schedule_id` (`schedule_id`);

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
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subject_teacher`
--
ALTER TABLE `subject_teacher`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

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
-- Indexes for table `teacher_availability`
--
ALTER TABLE `teacher_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `teacher_constraints`
--
ALTER TABLE `teacher_constraints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_teacher_constraint` (`teacher_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classrooms`
--
ALTER TABLE `classrooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `class_requirements`
--
ALTER TABLE `class_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `event_categories`
--
ALTER TABLE `event_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `event_participation`
--
ALTER TABLE `event_participation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `fees`
--
ALTER TABLE `fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `generated_schedules`
--
ALTER TABLE `generated_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `schedule_subjects`
--
ALTER TABLE `schedule_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `schedule_time_slots`
--
ALTER TABLE `schedule_time_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subject_teacher`
--
ALTER TABLE `subject_teacher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teacher_availability`
--
ALTER TABLE `teacher_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teacher_constraints`
--
ALTER TABLE `teacher_constraints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

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
-- Constraints for table `class_requirements`
--
ALTER TABLE `class_requirements`
  ADD CONSTRAINT `class_requirements_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `schedule_subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_category` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`),
  ADD CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_participation`
--
ALTER TABLE `event_participation`
  ADD CONSTRAINT `fk_participation_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_participation_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fees`
--
ALTER TABLE `fees`
  ADD CONSTRAINT `fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `generated_schedules`
--
ALTER TABLE `generated_schedules`
  ADD CONSTRAINT `generated_schedules_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`);

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
  ADD CONSTRAINT `schedule_entries_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `schedule_entries_ibfk_4` FOREIGN KEY (`schedule_id`) REFERENCES `generated_schedules` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `subject_teacher`
--
ALTER TABLE `subject_teacher`
  ADD CONSTRAINT `subject_teacher_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subject_teacher_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`user_id`) ON DELETE CASCADE;

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

--
-- Constraints for table `teacher_availability`
--
ALTER TABLE `teacher_availability`
  ADD CONSTRAINT `teacher_availability_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_constraints`
--
ALTER TABLE `teacher_constraints`
  ADD CONSTRAINT `teacher_constraints_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
