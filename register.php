<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$pdo = getPDO();
$pageTitle = 'Регистрация - Многопользовательский блог';
$basePath = '';
$errors = [];

$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $csrf = $_POST['csrf_token'] ?? null;

    if (!verifyCsrfToken(is_string($csrf) ? $csrf : null)) {
        $errors[] = 'Недействительный токен запроса. Обновите страницу и попробуйте снова.';
    }

    if ($name === '') {
        $errors[] = 'Введите имя.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный адрес электронной почты.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Пароль должен содержать минимум 6 символов.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Пароли не совпадают.';
    }

    if (!$errors) {
        $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $emailCheck->execute([':email' => $email]);

        if ($emailCheck->fetch()) {
            $errors[] = 'Этот email уже зарегистрирован.';
        }
    }

    if (!$errors) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $insert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)');
        $insert->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $hashedPassword,
            ':role' => 'user',
        ]);

        setFlash('success', 'Регистрация прошла успешно. Войдите в аккаунт.');
        redirect('login.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Создать аккаунт</h1>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Имя</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($name) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Электронная почта</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Пароль</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Подтвердите пароль</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Зарегистрироваться</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
