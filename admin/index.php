<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$pdo = getPDO();
$pageTitle = 'Панель администратора - Многопользовательский блог';
$basePath = '../';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $csrf = $_POST['csrf_token'] ?? null;

    if (!verifyCsrfToken(is_string($csrf) ? $csrf : null)) {
        setFlash('error', 'Недействительный токен запроса.');
        redirect('index.php');
    }

    $commentId = (int) ($_POST['comment_id'] ?? 0);

    if ($commentId > 0) {
        $deleteStmt = $pdo->prepare('DELETE FROM comments WHERE id = :id');
        $deleteStmt->execute([':id' => $commentId]);
        setFlash('success', 'Комментарий успешно удален.');
    }

    redirect('index.php');
}

$latestCommentsStmt = $pdo->query(
    'SELECT comments.id, comments.comment, comments.created_at, posts.title AS post_title, users.name AS user_name
     FROM comments
     LEFT JOIN posts ON comments.post_id = posts.id
     LEFT JOIN users ON comments.user_id = users.id
     ORDER BY comments.created_at DESC
     LIMIT 20'
);
$latestComments = $latestCommentsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Панель администратора</h1>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Управление публикациями</h2>
                <p class="text-muted">Создавайте, редактируйте и удаляйте публикации.</p>
                <a href="posts.php" class="btn btn-primary">Перейти к публикациям</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Публичная главная</h2>
                <p class="text-muted">Открыть блог как посетитель.</p>
                <a href="../index.php" class="btn btn-outline-secondary">Открыть сайт</a>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">Последние комментарии</h2>

        <?php if (!$latestComments): ?>
            <div class="alert alert-info mb-0">Комментарии не найдены.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Пользователь</th>
                            <th>Публикация</th>
                            <th>Комментарий</th>
                            <th>Дата</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latestComments as $comment): ?>
                            <tr>
                                <td><?= e($comment['user_name'] ?? 'Удаленный пользователь') ?></td>
                                <td><?= e($comment['post_title'] ?? 'Удаленная публикация') ?></td>
                                <td><?= e($comment['comment']) ?></td>
                                <td><?= e(formatDate($comment['created_at'])) ?></td>
                                <td>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Удалить этот комментарий?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                                        <button type="submit" name="delete_comment" class="btn btn-sm btn-danger">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
