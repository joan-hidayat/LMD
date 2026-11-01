<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/notifier.php';

/**
 * Kotak surat notifikasi (T-43), khusus admin: daftar & isi notifikasi yang dikirim ke customer.
 */
class NotificationController {
    public function index() {
        $db = Database::getInstance()->getConnection();
        $where = [];
        $params = [];
        if (!empty($_GET['q'])) {
            $where[] = '(recipient LIKE :q OR ref LIKE :q2 OR subject LIKE :q3)';
            $params['q'] = $params['q2'] = $params['q3'] = '%' . $_GET['q'] . '%';
        }
        $sql = "SELECT id, event, channel, recipient, recipient_name, subject, ref, status, last_error, sent_at, created_at
                FROM notifications" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY id DESC LIMIT 200";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        sendJsonResponse(['channel' => Notifier::config('channel'), 'items' => $stmt->fetchAll()], 200, 'Notifications fetched.');
    }

    public function show($id) {
        $stmt = Database::getInstance()->getConnection()->prepare("SELECT * FROM notifications WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            sendJsonResponse(null, 404, 'Notification not found.');
        }
        sendJsonResponse($row, 200, 'Notification fetched.');
    }
}
