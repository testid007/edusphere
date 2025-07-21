<?php if (!empty($schedule)): ?>
    <div class="table-responsive">
        <table class="table table-bordered">
            <?php
            $currentDay = null;
            foreach ($schedule as $row):
                if ($currentDay != $row['day']):
                    if ($currentDay !== null) echo '</tbody>';
                    $currentDay = $row['day'];
            ?>
            <thead class="table-primary">
                <tr>
                    <th colspan="4"><?= $currentDay ?></th>
                </tr>
                <tr>
                    <th>Period</th>
                    <th>Time</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                </tr>
            </thead>
            <tbody>
            <?php endif; ?>
                <tr>
                    <td><?= htmlspecialchars($row['period_name']) ?></td>
                    <td><?= substr($row['start_time'], 0, 5) ?>-<?= substr($row['end_time'], 0, 5) ?></td>
                    <td><?= htmlspecialchars($row['subject']) ?></td>
                    <td><?= htmlspecialchars($row['teacher'] ?? 'Not assigned') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">No schedule found for this class.</div>
<?php endif; ?>