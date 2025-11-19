<?php
// functions/EventManager.php

class EventManager
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new event.
     * Expects keys: title, description, category_id, event_date,
     *               start_time, end_time, location, created_by, image_path (optional)
     */
    public function createEvent(array $data): bool
    {
        $sql = "
            INSERT INTO events
            (title, description, image_path, category_id, event_date, start_time, end_time, location, created_by)
            VALUES
            (:title, :description, :image_path, :category_id, :event_date, :start_time, :end_time, :location, :created_by)
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':title'       => $data['title'],
            ':description' => $data['description'],
            ':image_path'  => $data['image_path'] ?? null,
            ':category_id' => $data['category_id'],
            ':event_date'  => $data['event_date'],
            ':start_time'  => $data['start_time'] ?: null,
            ':end_time'    => $data['end_time'] ?: null,
            ':location'    => $data['location'],
            ':created_by'  => $data['created_by'],
        ]);
    }

    /**
     * Update an existing event.
     * Same keys as createEvent (image_path can be null to clear or existing to keep).
     */
    public function updateEvent(int $id, array $data): bool
    {
        $sql = "
            UPDATE events
            SET title       = :title,
                description = :description,
                image_path  = :image_path,
                category_id = :category_id,
                event_date  = :event_date,
                start_time  = :start_time,
                end_time    = :end_time,
                location    = :location
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':title'       => $data['title'],
            ':description' => $data['description'],
            ':image_path'  => $data['image_path'] ?? null,
            ':category_id' => $data['category_id'],
            ':event_date'  => $data['event_date'],
            ':start_time'  => $data['start_time'] ?: null,
            ':end_time'    => $data['end_time'] ?: null,
            ':location'    => $data['location'],
            ':id'          => $id,
        ]);
    }

    /**
     * Delete an event by ID.
     */
    public function deleteEvent(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM events WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Simple upcoming events (no user context).
     * Useful for admin views or generic listing.
     */
    public function getUpcomingEvents(int $limit = 50): array
    {
        $limit = (int)$limit;

        $sql = "
            SELECT e.*, c.name AS category_name
            FROM events e
            JOIN event_categories c ON e.category_id = c.id
            WHERE e.is_active = 1
              AND e.event_date >= CURDATE()
            ORDER BY e.event_date ASC, e.start_time ASC
            LIMIT $limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Upcoming events for a specific user, with participation status.
     * We HIDE events where the student marked 'not_interested'.
     */
    public function getUpcomingEventsForUser(int $userId, int $limit = 100): array
    {
        $limit = (int)$limit;

        $sql = "
            SELECT 
                e.*,
                c.name AS category_name,
                ep.status AS participation_status
            FROM events e
            JOIN event_categories c ON e.category_id = c.id
            LEFT JOIN event_participation ep 
                   ON ep.event_id = e.id AND ep.user_id = :uid
            WHERE e.is_active = 1
              AND e.event_date >= CURDATE()
              AND (ep.status IS NULL OR ep.status <> 'not_interested')
            ORDER BY e.event_date ASC, e.start_time ASC
            LIMIT $limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Past events (before today) PLUS events explicitly marked 'not_interested'.
     * Used for the "Past & Uninterested Events" section.
     */
    public function getPastEventsForUser(int $userId, int $limit = 100): array
    {
        $limit = (int)$limit;

        $sql = "
            SELECT 
                e.*,
                c.name AS category_name,
                ep.status AS participation_status
            FROM events e
            JOIN event_categories c ON e.category_id = c.id
            LEFT JOIN event_participation ep 
                   ON ep.event_id = e.id AND ep.user_id = :uid
            WHERE e.is_active = 1
              AND (
                    e.event_date < CURDATE()
                    OR ep.status = 'not_interested'
                  )
            ORDER BY 
                e.event_date DESC,
                e.start_time DESC
            LIMIT $limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Insert or update participation for a student in an event.
     * Status can be: 'interested', 'not_interested', 'registered', 'participated'.
     */
    public function markParticipation(int $eventId, int $userId, string $status): bool
    {
        // Check if record already exists for this (event, user)
        $check = $this->db->prepare("
            SELECT id 
            FROM event_participation
            WHERE event_id = :event_id AND user_id = :user_id
            LIMIT 1
        ");
        $check->execute([
            ':event_id' => $eventId,
            ':user_id'  => $userId,
        ]);

        if ($row = $check->fetch()) {
            // Update existing row
            $update = $this->db->prepare("
                UPDATE event_participation
                SET status = :status
                WHERE id = :id
            ");
            return $update->execute([
                ':status' => $status,
                ':id'     => $row['id'],
            ]);
        } else {
            // Insert new row
            $insert = $this->db->prepare("
                INSERT INTO event_participation (event_id, user_id, status)
                VALUES (:event_id, :user_id, :status)
            ");
            return $insert->execute([
                ':event_id' => $eventId,
                ':user_id'  => $userId,
                ':status'   => $status,
            ]);
        }
    }

    /**
     * Recommended upcoming events for a student based on preferences.
     *
     * Liked categories:
     *   - statuses: 'interested', 'registered', 'participated'
     *
     * Disliked categories:
     *   - status: 'not_interested'
     *
     * Logic:
     *   - If the student has no positive history -> return [] (no recommendations).
     *   - Else return upcoming events:
     *       * category in liked categories
     *       * category NOT in disliked categories
     */
    public function getStudentRecommendedEvents(int $userId, int $limit = 5): array
    {
        // 1) Check if the student has any *positive* history.
        $check = $this->db->prepare("
            SELECT COUNT(*) AS cnt
            FROM event_participation ep
            WHERE ep.user_id = :uid_check
              AND ep.status IN ('interested', 'registered', 'participated')
        ");
        $check->execute([':uid_check' => $userId]);
        $row = $check->fetch();

        if (!$row || (int)$row['cnt'] === 0) {
            // No positive history -> no recommendations
            return [];
        }

        $limit = (int)$limit;

        // 2) Recommend only from liked categories, excluding disliked ones
        $sql = "
            SELECT 
                e.*,
                c.name AS category_name
            FROM events e
            JOIN event_categories c ON e.category_id = c.id
            WHERE e.is_active = 1
              AND e.event_date >= CURDATE()
              -- Only categories the student liked
              AND e.category_id IN (
                    SELECT DISTINCT ev.category_id
                    FROM events ev
                    JOIN event_participation ep_like 
                          ON ep_like.event_id = ev.id
                    WHERE ep_like.user_id = :uid_like
                      AND ep_like.status IN ('interested','registered','participated')
              )
              -- Exclude categories the student marked not_interested
              AND e.category_id NOT IN (
                    SELECT DISTINCT ev2.category_id
                    FROM events ev2
                    JOIN event_participation ep_dis 
                          ON ep_dis.event_id = ev2.id
                    WHERE ep_dis.user_id = :uid_dis
                      AND ep_dis.status = 'not_interested'
              )
            ORDER BY e.event_date ASC, e.start_time ASC
            LIMIT $limit
        ";

        $stmt = $this->db->prepare($sql);
        // Different parameter names so MySQL doesn't complain
        $stmt->bindValue(':uid_like', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid_dis',  $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
