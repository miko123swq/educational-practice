<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешен.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Чтобы комментировать, нужно войти в аккаунт.']);
    exit;
}

$csrf = $_POST['csrf_token'] ?? null;
if (!verifyCsrfToken(is_string($csrf) ? $csrf : null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Недействительный токен запроса.']);
    exit;
}

$postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
$commentText = trim((string) ($_POST['comment'] ?? ''));

if ($postId <= 0 || $commentText === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Укажите публикацию и текст комментария.']);
    exit;
}

if (mb_strlen($commentText) > 2000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Комментарий слишком длинный (максимум 2000 символов).']);
    exit;
}

$pdo = getPDO();

$postCheck = $pdo->prepare('SELECT id FROM posts WHERE id = :id LIMIT 1');
$postCheck->execute([':id' => $postId]);
if (!$postCheck->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Публикация не найдена.']);
    exit;
}

$insert = $pdo->prepare('INSERT INTO comments (post_id, user_id, comment) VALUES (:post_id, :user_id, :comment)');
$insert->execute([
    ':post_id' => $postId,
    ':user_id' => (int) $_SESSION['user_id'],
    ':comment' => $commentText,
]);

$commentId = (int) $pdo->lastInsertId();

echo json_encode([
    'success' => true,
    'message' => 'Комментарий успешно добавлен.',
    'comment' => [
        'id' => $commentId,
        'user_name' => (string) ($_SESSION['name'] ?? 'Пользователь'),
        'comment' => $commentText,
        'created_at' => formatDate(date('Y-m-d H:i:s')),
    ],
]);
