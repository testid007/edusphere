<?php
// dashboards/admin/schedule-view.php
require_once __DIR__ . '/../../functions/ScheduleManager.php';

$scheduleManager = new ScheduleManager();

// Selected class (grade). Default = 1
$classId = isset($_GET['class']) ? (int)$_GET['class'] : 1;

// Fetch flat schedule rows (one row per period)
$schedule = $scheduleManager->getClassSchedule($classId);

// Try to read class number from first row, otherwise fallback to selected id
$classNumber = (!empty($schedule) && isset($schedule[0]['class']))
    ? htmlspecialchars($schedule[0]['class'], ENT_QUOTES)
    : $classId;

// Are we returning only the inner table (for AJAX reload)?
$isPartial = isset($_GET['partial']) && $_GET['partial'] == '1';

/**
 * Render only the schedule table + heading
 */
function renderScheduleTable(array $schedule, $classNumber) {
    if (empty($schedule)) {
        echo '<div class="sv-alert sv-alert-info">No schedule found for this class.</div>';
        return;
    }
    ?>
    <h4 class="sv-class-title">Schedule for Class: <?= $classNumber ?></h4>
    <div class="sv-table-wrapper">
      <table class="sv-table">
        <?php
        $currentDay = null;
        foreach ($schedule as $row):
            if ($currentDay !== $row['day']):
                // Close previous <tbody> if set
                if ($currentDay !== null) {
                    echo '</tbody>';
                }
                $currentDay = $row['day'];
                ?>
                <thead class="sv-day-head">
                  <tr>
                    <th colspan="4"><?= htmlspecialchars($currentDay, ENT_QUOTES) ?></th>
                  </tr>
                  <tr class="sv-columns-head">
                    <th>Period</th>
                    <th>Time</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                  </tr>
                </thead>
                <tbody>
            <?php
            endif;
            ?>
            <tr>
              <td><?= htmlspecialchars($row['period_name'], ENT_QUOTES) ?></td>
              <td><?= substr($row['start_time'], 0, 5) ?> - <?= substr($row['end_time'], 0, 5) ?></td>
              <td><?= htmlspecialchars($row['subject'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars($row['teacher'] ?? 'Not assigned', ENT_QUOTES) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

// If partial request (AJAX reload), just output table and exit
if ($isPartial) {
    renderScheduleTable($schedule, $classNumber);
    exit;
}
?>

<section class="section schedule-view-section">
  <div class="sv-card">
    <div class="schedule-view-header">
      <div>
        <h3><i class="fas fa-table"></i> Schedule View</h3>
        <p>Select a class to view its weekly timetable. You can also print or save as PDF.</p>
      </div>

      <div class="schedule-controls">
        <div style="display: flex; align-items: center; gap: 6px;">
          <label for="sv-class-select">Class / Grade:</label>
          <select id="sv-class-select">
            <?php for ($i = 1; $i <= 12; $i++): ?>
              <option value="<?= $i ?>" <?= $i === (int)$classId ? 'selected' : '' ?>>
                Class <?= $i ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>

        <button type="button" id="sv-print-btn">
          <i class="fas fa-print"></i> Print / Save as PDF
        </button>
      </div>
    </div>

    <div id="sv-table-container">
      <?php renderScheduleTable($schedule, $classNumber); ?>
    </div>
  </div>
</section>

<style>
  /* ===== Schedule View (Admin) ===== */

  .schedule-view-section {
    max-width: 1100px;
  }

  .sv-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    padding: 18px 20px;
    box-shadow: 0 2px 12px rgba(60, 72, 88, 0.08);
  }

  .schedule-view-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 18px;
    gap: 10px;
  }

  .schedule-view-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 800;
    color: #111;
  }

  .schedule-view-header p {
    margin: 4px 0 0;
    font-size: 0.9rem;
    color: #555;
  }

  .schedule-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .schedule-controls label {
    font-size: 0.9rem;
    color: #333;
  }

  .schedule-controls select {
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid #d1d5db;
    font-size: 0.9rem;
    outline: none;
    background: #fff;
  }

  .schedule-controls button {
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    background: linear-gradient(90deg, #4caf50 60%, #388e3c 100%);
    color: #fff;
    box-shadow: 0 2px 8px rgba(76,175,80,0.24);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
  }

  .schedule-controls button:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 12px rgba(56,142,60,0.35);
  }

  .sv-class-title {
    margin: 0 0 12px 0;
    font-weight: 600;
    color: #222;
    font-size: 1rem;
  }

  .sv-table-wrapper {
    width: 100%;
    overflow-x: auto;
  }

  .sv-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
  }

  .sv-table th,
  .sv-table td {
    border: 1px solid #dee2e6;
    padding: 8px 10px;
    text-align: center;
    vertical-align: middle;
  }

  .sv-day-head th {
    background-color: #21860d;
    color: #fff;
    font-size: 0.95rem;
    padding: 10px;
  }

  .sv-columns-head th {
    background-color: #f8f9fa;
    color: #333;
    font-weight: 600;
  }

  .sv-table tbody tr:nth-child(even) {
    background-color: #f9fafb;
  }

  .sv-table tbody tr:hover {
    background-color: #f1f8e9;
  }

  .sv-table td:nth-child(3),
  .sv-table td:nth-child(4) {
    font-weight: 500;
  }

  .sv-alert {
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 0.9rem;
    margin-top: 10px;
  }

  .sv-alert-info {
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    color: #1d4ed8;
  }

  .sv-error {
    color: #b91c1c;
    font-size: 0.9rem;
    margin-top: 10px;
  }

  /* Print only the schedule card */
  @media print {
    body * {
      visibility: hidden;
    }
    .schedule-view-section, .schedule-view-section * {
      visibility: visible;
    }
    .schedule-view-section {
      position: absolute;
      left: 0;
      top: 0;
      width: 100%;
      padding: 0;
      margin: 0;
      box-shadow: none !important;
    }
  }
</style>

