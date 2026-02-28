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
$pageTitle = 'Управление публикациями - Многопользовательский блог';
$basePath = '../';
$errors = [];

function processUpload(string $inputName, ?string $currentImage = null): array
{
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => $currentImage, 'error' => null];
    }

    $file = $_FILES[$inputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => $currentImage, 'error' => 'Не удалось загрузить изображение.'];
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return ['path' => $currentImage, 'error' => 'Размер изображения не должен превышать 2 МБ.'];
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['path' => $currentImage, 'error' => 'Разрешены только изображения JPG, JPEG и PNG.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    $allowedMime = ['image/jpeg', 'image/png'];
    if (!in_array($mime, $allowedMime, true)) {
        return ['path' => $currentImage, 'error' => 'Недопустимый тип файла изображения.'];
    }

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        return ['path' => $currentImage, 'error' => 'Не удалось создать папку загрузок.'];
    }

    $filename = date('Y-m-d') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['path' => $currentImage, 'error' => 'Не удалось переместить загруженное изображение.'];
    }

    if ($currentImage && file_exists(__DIR__ . '/../' . $currentImage)) {
        @unlink(__DIR__ . '/../' . $currentImage);
    }

    return ['path' => 'uploads/' . $filename, 'error' => null];
}

$action = $_GET['action'] ?? 'list';
$postId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;

    if (!verifyCsrfToken(is_string($csrf) ? $csrf : null)) {
        setFlash('error', 'Недействительный токен запроса.');
        redirect('posts.php');
    }

    if (isset($_POST['delete_post'])) {
        $deleteId = (int) ($_POST['post_id'] ?? 0);
        if ($deleteId > 0) {
            $imgStmt = $pdo->prepare('SELECT image FROM posts WHERE id = :id');
            $imgStmt->execute([':id' => $deleteId]);
            $existing = $imgStmt->fetch();

            $deleteStmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');
            $deleteStmt->execute([':id' => $deleteId]);

            if ($existing && !empty($existing['image']) && file_exists(__DIR__ . '/../' . $existing['image'])) {
                @unlink(__DIR__ . '/../' . $existing['image']);
            }

            setFlash('success', 'Публикация успешно удалена.');
        }
        redirect('posts.php');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));

    if ($title === '') {
        $errors[] = 'Введите заголовок.';
    }

    if ($content === '') {
        $errors[] = 'Введите содержание публикации.';
    }

    if (isset($_POST['create_post'])) {
        $upload = processUpload('image');
        if ($upload['error']) {
            $errors[] = $upload['error'];
        }

        if (!$errors) {
            $insert = $pdo->prepare('INSERT INTO posts (title, content, image, author_id) VALUES (:title, :content, :image, :author_id)');
            $insert->execute([
                ':title' => $title,
                ':content' => $content,
                ':image' => $upload['path'],
                ':author_id' => (int) $_SESSION['user_id'],
            ]);

            setFlash('success', 'Публикация успешно создана.');
            redirect('posts.php');
        }

        $action = 'add';
    }

    if (isset($_POST['update_post'])) {
        $updateId = (int) ($_POST['post_id'] ?? 0);

        $currentStmt = $pdo->prepare('SELECT image FROM posts WHERE id = :id LIMIT 1');
        $currentStmt->execute([':id' => $updateId]);
        $currentPost = $currentStmt->fetch();

        if (!$currentPost) {
            setFlash('error', 'Публикация не найдена.');
            redirect('posts.php');
        }

        $upload = processUpload('image', $currentPost['image']);
        if ($upload['error']) {
            $errors[] = $upload['error'];
        }

        if (!$errors) {
            $update = $pdo->prepare('UPDATE posts SET title = :title, content = :content, image = :image WHERE id = :id');
            $update->execute([
                ':title' => $title,
                ':content' => $content,
                ':image' => $upload['path'],
                ':id' => $updateId,
            ]);

            setFlash('success', 'Публикация успешно обновлена.');
            redirect('posts.php');
        }

        $action = 'edit';
        $postId = $updateId;
    }
}

$editingPost = null;
if ($action === 'edit' && $postId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
    $editStmt->execute([':id' => $postId]);
    $editingPost = $editStmt->fetch();

    if (!$editingPost) {
        setFlash('error', 'Публикация не найдена.');
        redirect('posts.php');
    }
}

$postsStmt = $pdo->query(
    'SELECT posts.id, posts.title, posts.image, posts.created_at, users.name AS author_name
     FROM posts
     INNER JOIN users ON posts.author_id = users.id
     ORDER BY posts.created_at DESC'
);
$posts = $postsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Управление публикациями</h1>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary">Панель</a>
        <a href="posts.php?action=add" class="btn btn-primary">Добавить публикацию</a>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <?php
    $isEdit = $action === 'edit' && $editingPost;
    $formTitle = $isEdit ? 'Редактировать публикацию' : 'Добавить публикацию';
    $titleValue = $isEdit ? $editingPost['title'] : ($_POST['title'] ?? '');
    $contentValue = $isEdit ? $editingPost['content'] : ($_POST['content'] ?? '');
    $imageValue = $isEdit ? $editingPost['image'] : null;
    ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3"><?= e($formTitle) ?></h2>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <?php if ($isEdit): ?>
                    <input type="hidden" name="post_id" value="<?= (int) $editingPost['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="title" class="form-label">Заголовок</label>
                    <input type="text" class="form-control" id="title" name="title" value="<?= e((string) $titleValue) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="content" class="form-label">Содержание</label>
                    <textarea class="form-control" id="content" name="content" rows="8" required><?= e((string) $contentValue) ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label">Изображение (JPG, JPEG, PNG, максимум 2 МБ)</label>
                    <input type="file" class="form-control" id="image" name="image" accept=".jpg,.jpeg,.png">
                </div>

                <?php if ($isEdit && !empty($imageValue) && file_exists(__DIR__ . '/../' . $imageValue)): ?>
                    <div class="mb-3">
                        <p class="mb-1">Текущее изображение:</p>
                        <img src="../<?= e($imageValue) ?>" alt="Текущее изображение" style="max-width: 240px;" class="img-thumbnail">
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" name="<?= $isEdit ? 'update_post' : 'create_post' ?>" class="btn btn-success">
                        <?= $isEdit ? 'Обновить публикацию' : 'Создать публикацию' ?>
                    </button>
                    <a href="posts.php" class="btn btn-outline-secondary">Отмена</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <h2 class="h5 mb-3">Все публикации</h2>

        <?php if (!$posts): ?>
            <div class="alert alert-info mb-0">Публикации не найдены.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Заголовок</th>
                            <th>Автор</th>
                            <th>Дата</th>
                            <th>Изображение</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td><?= (int) $post['id'] ?></td>
                                <td><?= e($post['title']) ?></td>
                                <td><?= e($post['author_name']) ?></td>
                                <td><?= e(formatDate($post['created_at'])) ?></td>
                                <td>
                                    <?php if (!empty($post['image']) && file_exists(__DIR__ . '/../' . $post['image'])): ?>
                                        <img src="../<?= e($post['image']) ?>" alt="Изображение публикации" style="width: 70px; height: 45px; object-fit: cover;" class="rounded">
                                    <?php else: ?>
                                        <span class="text-muted">Нет изображения</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="posts.php?action=edit&id=<?= (int) $post['id'] ?>" class="btn btn-sm btn-warning">Редактировать</a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Удалить эту публикацию?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                                        <button type="submit" name="delete_post" class="btn btn-sm btn-danger">Удалить</button>
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
