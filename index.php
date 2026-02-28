<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$pdo = getPDO();
$pageTitle = 'Главная - Многопользовательский блог';
$basePath = '';

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 5;
$offset = ($page - 1) * $perPage;

$totalStmt = $pdo->query('SELECT COUNT(*) FROM posts');
$totalPosts = (int) $totalStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalPosts / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmt = $pdo->prepare(
    'SELECT posts.id, posts.title, posts.content, posts.image, posts.created_at, users.name AS author_name
     FROM posts
     INNER JOIN users ON posts.author_id = users.id
     ORDER BY posts.created_at DESC
     LIMIT :lim OFFSET :off'
);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Последние публикации</h1>
</div>

<?php if (!$posts): ?>
    <div class="alert alert-info">Публикаций пока нет.</div>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($posts as $post): ?>
        <?php
        $plainContent = strip_tags($post['content']);
        $preview = mb_strlen($plainContent) > 200 ? mb_substr($plainContent, 0, 200) . '...' : $plainContent;
        ?>
        <div class="col-12">
            <article class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="row g-3 align-items-start">
                        <div class="col-md-4">
                            <?php if (!empty($post['image']) && file_exists(__DIR__ . '/' . $post['image'])): ?>
                                <img src="<?= e($post['image']) ?>" alt="<?= e($post['title']) ?>" class="post-image">
                            <?php else: ?>
                                <div class="post-placeholder">Изображение отсутствует</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <h2 class="h4"><a class="text-decoration-none" href="post.php?id=<?= (int) $post['id'] ?>"><?= e($post['title']) ?></a></h2>
                            <p class="text-muted mb-2">Автор: <?= e($post['author_name']) ?> | <?= e(formatDate($post['created_at'])) ?></p>
                            <p class="mb-0"><?= e($preview) ?></p>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-4" aria-label="Пагинация публикаций">
        <ul class="pagination justify-content-center flex-wrap">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>">Назад</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>">Вперед</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
