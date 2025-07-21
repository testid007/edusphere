<?php
// =============================================
// HARDCODED DATA WITH PROPER TEACHER DISTRIBUTION
// =============================================

// Subjects by grade level
$subjectsByGrade = [
    'Grade 1' => ['English', 'Nepali', 'Mathematics', 'My Science', 'Social Studies', 'Moral Education'],
    'Grade 2' => ['English', 'Nepali', 'Mathematics', 'My Science', 'Social Studies', 'Moral Education'],
    'Grade 3' => ['English', 'Nepali', 'Mathematics', 'My Science', 'Social Studies', 'Moral Education'],
    'Grade 4' => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Computer Studies'],
    'Grade 5' => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Computer Studies'],
    'Grade 6' => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Computer Science'],
    'Grade 7' => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Computer Science'],
    'Grade 8' => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Computer Science'],
    'Grade 9' => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Optional Mathematics', 'Computer Science'],
    'Grade 10' => ['English', 'Nepali', 'Mathematics', 'Science', 'Social Studies', 'HPE', 'Optional Mathematics', 'Computer Science']
];

// Teachers with their assigned subjects and maximum classes per day
$teachers = [
    'Ms. Gurung' => [
        'subjects' => ['English', 'Moral Education'],
        'max_classes' => 5
    ],
    'Mr. Sharma' => [
        'subjects' => ['Mathematics', 'Optional Mathematics'],
        'max_classes' => 6
    ],
    'Ms. Bhandari' => [
        'subjects' => ['Nepali', 'Social Studies'],
        'max_classes' => 5
    ],
    'Mr. Thapa' => [
        'subjects' => ['Science', 'My Science'],
        'max_classes' => 6
    ],
    'Mr. Rai' => [
        'subjects' => ['HPE', 'Social Studies'],
        'max_classes' => 4
    ],
    'Ms. Lama' => [
        'subjects' => ['Computer Studies', 'Computer Science'],
        'max_classes' => 5
    ],
    'Mr. Joshi' => [
        'subjects' => ['Mathematics', 'Science'],
        'max_classes' => 5
    ]
];

// Class teachers (homeroom teachers)
$classTeachers = [
    'Grade 1' => 'Ms. Gurung',
    'Grade 2' => 'Ms. Bhandari',
    'Grade 3' => 'Mr. Thapa',
    'Grade 4' => 'Mr. Sharma',
    'Grade 5' => 'Ms. Lama',
    'Grade 6' => 'Mr. Joshi',
    'Grade 7' => 'Mr. Rai',
    'Grade 8' => 'Ms. Gurung',
    'Grade 9' => 'Mr. Sharma',
    'Grade 10' => 'Ms. Bhandari'
];

// Time slots structure
$timeSlots = [
    'Regular' => [
        'Period 1' => '10:00-10:45',
        'Period 2' => '10:45-11:30',
        'Short Break' => '11:30-11:35',
        'Period 3' => '11:35-12:20',
        'Period 4' => '12:20-13:05',
        'Lunch' => '13:05-13:35',
        'Period 5' => '13:35-14:20',
        'Period 6' => '14:20-15:05',
        '2nd Short Break' => '15:05-15:10',
        'Period 7' => '15:10-15:55'
    ],
    'Friday' => [
        'Period 1' => '10:00-10:45',
        'Period 2' => '10:45-11:30',
        'Short Break' => '11:30-11:35',
        'Period 3' => '11:35-12:20',
        'Period 4' => '12:20-13:05',
        'Lunch' => '13:05-13:35',
        'Club 1' => '13:35-14:20',
        'Club 2' => '14:20-15:05',
        '2nd Short Break' => '15:05-15:10',
        'Club 3' => '15:10-15:55'
    ]
];

// =============================================
// SCHEDULE GENERATION WITH CONFLICT AVOIDANCE
// =============================================

$schedule = [];
$teacherAssignments = []; // Track teacher availability

// Days of the week
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Initialize teacher assignments tracker
foreach ($days as $day) {
    $teacherAssignments[$day] = [];
    foreach ($timeSlots['Regular'] as $period => $time) {
        $teacherAssignments[$day][$period] = [];
    }
}

// Generate schedule for each grade
foreach ($subjectsByGrade as $grade => $subjects) {
    foreach ($days as $day) {
        $isFriday = ($day == 'Friday');
        $timeTemplate = $isFriday ? $timeSlots['Friday'] : $timeSlots['Regular'];
        
        // Assign class teacher to first period
        $classTeacher = $classTeachers[$grade];
        $firstSubject = $subjects[0];
        
        $schedule[$grade][$day]['Period 1'] = [
            'subject' => $firstSubject,
            'teacher' => $classTeacher
        ];
        $teacherAssignments[$day]['Period 1'][$classTeacher] = true;
        
        // Assign remaining periods
        $periodCount = 1;
        foreach ($timeTemplate as $period => $time) {
            if ($period == 'Period 1') continue; // Already assigned
            
            // Skip breaks and lunch
            if (strpos($period, 'Break') !== false || $period == 'Lunch') {
                $schedule[$grade][$day][$period] = [
                    'subject' => strpos($period, 'Break') !== false ? 'Short Break' : 'Lunch Break',
                    'teacher' => ''
                ];
                continue;
            }
            
            // For Friday clubs after period 4
            if ($isFriday && $periodCount > 4) {
                $schedule[$grade][$day][$period] = [
                    'subject' => 'Club Activity',
                    'teacher' => getAvailableTeacher($day, $period, ['HPE', 'Social Studies', 'Moral Education'], $teacherAssignments)
                ];
                $periodCount++;
                continue;
            }
            
            $periodCount++;
            if ($periodCount > ($isFriday ? 4 : 7)) continue;
            
            // Get subject for this period (rotating through subjects)
            $subjectIndex = ($periodCount - 2) % count($subjects);
            $subject = $subjects[$subjectIndex];
            
            // Find available teacher for this subject
            $teacher = getAvailableTeacher($day, $period, [$subject], $teacherAssignments);
            
            $schedule[$grade][$day][$period] = [
                'subject' => $subject,
                'teacher' => $teacher
            ];
            
            if (!empty($teacher)) {
                $teacherAssignments[$day][$period][$teacher] = true;
            }
        }
    }
}

// Helper function to find available teacher for a subject
function getAvailableTeacher($day, $period, $subjects, &$teacherAssignments) {
    global $teachers;
    
    foreach ($teachers as $teacher => $data) {
        // Check if teacher is already assigned this period
        if (isset($teacherAssignments[$day][$period][$teacher])) {
            continue;
        }
        
        // Check if teacher teaches any of the required subjects
        $commonSubjects = array_intersect($data['subjects'], $subjects);
        if (!empty($commonSubjects)) {
            return $teacher;
        }
    }
    
    return 'To be assigned';
}

// =============================================
// HTML OUTPUT WITH CSS STYLING
// =============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Schedule System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        .grade-selector {
            background-color: #3498db;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: white;
        }
        .grade-selector select, .grade-selector button {
            padding: 8px 15px;
            border-radius: 4px;
            border: none;
            font-size: 16px;
        }
        .grade-selector button {
            background-color: #2c3e50;
            color: white;
            cursor: pointer;
            margin-left: 10px;
        }
        .grade-selector button:hover {
            background-color: #1a252f;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background-color: white;
        }
        .schedule-table th {
            background-color: #2c3e50;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .schedule-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .schedule-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .schedule-table tr:hover {
            background-color: #e3f2fd;
        }
        .day-header {
            background-color: #3498db !important;
            color: white;
            font-weight: bold;
        }
        .break-cell {
            background-color: #e74c3c;
            color: white;
            font-weight: bold;
        }
        .lunch-cell {
            background-color: #f39c12;
            color: white;
            font-weight: bold;
        }
        .teacher-name {
            font-style: italic;
            color: #7f8c8d;
        }
        .print-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        .print-button:hover {
            background-color: #219653;
        }
        @media print {
            .grade-selector, .print-button {
                display: none;
            }
            body {
                background-color: white;
                padding: 0;
            }
            .schedule-table {
                box-shadow: none;
            }
        }
        .conflict {
            background-color: #ffdddd;
            color: #d32f2f;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>School Teacher Schedule System</h1>
    
    <div class="grade-selector">
        <form method="get">
            <label for="grade">Select Grade:</label>
            <select name="grade" id="grade">
                <?php foreach ($subjectsByGrade as $grade => $subjects): ?>
                    <option value="<?= $grade ?>" <?= isset($_GET['grade']) && $_GET['grade'] == $grade ? 'selected' : '' ?>>
                        <?= $grade ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Show Schedule</button>
        </form>
    </div>
    
    <?php
    // Determine which grade to show
    $selectedGrade = isset($_GET['grade']) && array_key_exists($_GET['grade'], $schedule) 
                    ? $_GET['grade'] 
                    : 'Grade 1';
    
    // Display schedule for selected grade
    function displayGradeSchedule($grade) {
        global $schedule, $timeSlots;
        
        if (!array_key_exists($grade, $schedule)) {
            echo "<p>No schedule available for {$grade}.</p>";
            return;
        }
        
        echo "<h2>{$grade} Weekly Schedule</h2>";
        echo "<p>Class Teacher: <strong>{$GLOBALS['classTeachers'][$grade]}</strong></p>";
        
        foreach ($schedule[$grade] as $day => $periods) {
            $isFriday = ($day == 'Friday');
            $timeTemplate = $isFriday ? $timeSlots['Friday'] : $timeSlots['Regular'];
            
            echo "<h3>{$day}</h3>";
            echo "<table class='schedule-table'>";
            echo "<tr><th>Period</th><th>Time</th><th>Subject</th><th>Teacher</th></tr>";
            
            foreach ($timeTemplate as $periodName => $time) {
                $subject = $periods[$periodName]['subject'] ?? '';
                $teacher = $periods[$periodName]['teacher'] ?? '';
                
                // Check for teacher conflicts
                $conflictClass = '';
                if (!empty($teacher) && isTeacherOverbooked($teacher, $day, $periodName)) {
                    $conflictClass = 'conflict';
                }
                
                // Special formatting for breaks and lunch
                $rowClass = '';
                if (strpos($periodName, 'Break') !== false) {
                    $rowClass = 'break-cell';
                } elseif ($periodName == 'Lunch') {
                    $rowClass = 'lunch-cell';
                }
                
                echo "<tr class='{$rowClass} {$conflictClass}'>";
                echo "<td>{$periodName}</td>";
                echo "<td>{$time}</td>";
                echo "<td>{$subject}</td>";
                echo "<td class='teacher-name'>{$teacher}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
    }
    
    // Function to check if teacher is overbooked
    function isTeacherOverbooked($teacher, $day, $period) {
        global $schedule;
        $count = 0;
        
        foreach ($schedule as $grade => $days) {
            if (isset($days[$day][$period]['teacher'])) {
                if ($days[$day][$period]['teacher'] == $teacher) {
                    $count++;
                    if ($count > 1) return true;
                }
            }
        }
        
        return false;
    }
    
    // Display the selected grade's schedule
    displayGradeSchedule($selectedGrade);
    ?>
    
    <button class="print-button" onclick="window.print()">Print Schedule</button>
    
    <div style="margin-top: 40px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
        <h3>Teacher Assignments Summary</h3>
        <table class="schedule-table">
            <tr>
                <th>Teacher</th>
                <th>Subjects</th>
                <th>Class Teacher For</th>
            </tr>
            <?php foreach ($teachers as $teacher => $data): ?>
                <tr>
                    <td><?= $teacher ?></td>
                    <td><?= implode(', ', $data['subjects']) ?></td>
                    <td>
                        <?php
                        $homeroom = array_search($teacher, $classTeachers);
                        echo $homeroom ? $homeroom : 'N/A';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>