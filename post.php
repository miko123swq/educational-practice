<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$pdo = getPDO();
$basePath = '';

$postId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$postStmt = $pdo->prepare(
    'SELECT posts.id, posts.title, posts.content, posts.image, posts.created_at, users.name AS author_name
     FROM posts
     INNER JOIN users ON posts.author_id = users.id
     WHERE posts.id = :id
     LIMIT 1'
);
$postStmt->execute([':id' => $postId]);
$post = $postStmt->fetch();

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Публикация не найдена - Многопользовательский блог';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="text-center py-5">
        <h1 class="display-6">404</h1>
        <p class="lead mb-3">Публикация, которую вы ищете, не существует.</p>
        <a class="btn btn-primary" href="index.php">Вернуться на главную</a>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $post['title'] . ' - Многопользовательский блог';

$commentsStmt = $pdo->prepare(
    'SELECT comments.id, comments.comment, comments.created_at, users.name AS user_name
     FROM comments
     LEFT JOIN users ON comments.user_id = users.id
     WHERE comments.post_id = :post_id
     ORDER BY comments.created_at DESC'
);
$commentsStmt->execute([':post_id' => $postId]);
$comments = $commentsStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<article class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <h1 class="h2 mb-3"><?= e($post['title']) ?></h1>
        <p class="text-muted">Автор: <?= e($post['author_name']) ?> | <?= e(formatDate($post['created_at'])) ?></p>

        <?php if (!empty($post['image']) && file_exists(__DIR__ . '/' . $post['image'])): ?>
            <img src="<?= e($post['image']) ?>" alt="<?= e($post['title']) ?>" class="post-image mb-3">
        <?php else: ?>
            <div class="post-placeholder mb-3">Изображение отсутствует</div>
        <?php endif; ?>

        <div class="mb-3"><?= nl2br(e($post['content'])) ?></div>

        <button type="button" class="btn btn-outline-primary like-button" data-like-key="post-<?= (int) $post['id'] ?>">Нравится</button>
        <span class="ms-2 like-count" data-like-key="post-<?= (int) $post['id'] ?>">0</span>
    </div>
</article>

<section class="mb-4">
    <h2 class="h4">Комментарии</h2>

    <?php if (isLoggedIn()): ?>
        <form id="commentForm" action="add_comment.php" method="post" class="card card-body mb-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">

            <div class="mb-3">
                <label for="comment" class="form-label">Добавить комментарий</label>
                <textarea id="comment" name="comment" class="form-control" rows="4" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Отправить комментарий</button>
            <div id="commentFeedback" class="mt-3"></div>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">Чтобы оставить комментарий, войдите в аккаунт.</div>
    <?php endif; ?>

    <div id="commentsList">
        <?php if (!$comments): ?>
            <div class="alert alert-light border">Комментариев пока нет. Будьте первым.</div>
        <?php endif; ?>

        <?php foreach ($comments as $comment): ?>
            <div class="card mb-3 comment-item">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong><?= e($comment['user_name'] ?? 'Удаленный пользователь') ?></strong>
                        <small class="text-muted"><?= e(formatDate($comment['created_at'])) ?></small>
                    </div>
                    <p class="mb-2"><?= nl2br(e($comment['comment'])) ?></p>
                    <button type="button" class="btn btn-sm btn-outline-primary like-button" data-like-key="comment-<?= (int) $comment['id'] ?>">Нравится</button>
                    <span class="like-count" data-like-key="comment-<?= (int) $comment['id'] ?>">0</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
