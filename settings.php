<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = getCurrentUser();
$success = '';
$error = '';

// Получаем активный раздел из URL
$section = $_GET['section'] ?? 'account';

// Обработка сохранения настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_profile'])) {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, bio = ? WHERE id = ?");
        if ($stmt->execute([$first_name, $last_name, $phone, $bio, $currentUser['id']])) {
            $success = 'Профиль обновлён';
            $currentUser = getCurrentUser();
        }
    }
    
    if (isset($_POST['change_password'])) {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($old, $currentUser['password'])) {
            $error = 'Неверный текущий пароль';
        } elseif ($new !== $confirm) {
            $error = 'Новые пароли не совпадают';
        } elseif (strlen($new) < 6) {
            $error = 'Пароль должен быть не менее 6 символов';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $currentUser['id']]);
            $success = 'Пароль изменён';
        }
    }
    
    if (isset($_POST['delete_account'])) {
        if (password_verify($_POST['password'] ?? '', $currentUser['password'])) {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            session_destroy();
            header('Location: login.php?deleted=1');
            exit;
        } else {
            $error = 'Неверный пароль';
        }
    }
    
    if (isset($_POST['save_profile'])) {
        $username = trim($_POST['username'] ?? $currentUser['username']);
        $email = trim($_POST['email'] ?? $currentUser['email']);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
    
    // Проверка уникальности username
    if ($username !== $currentUser['username']) {
        $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$username, $currentUser['id']]);
        if ($check->fetch()) {
            $error = 'Имя пользователя уже занято';
        }
    }
    
    // Проверка уникальности email
    if ($email !== $currentUser['email'] && !$error) {
        $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $currentUser['id']]);
        if ($check->fetch()) {
            $error = 'Email уже используется';
        }
    }
    
    if (!$error) {
        $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, bio = ? WHERE id = ?");
        if ($stmt->execute([$username, $email, $first_name, $last_name, $bio, $currentUser['id']])) {
            $success = 'Профиль обновлён';
            $currentUser = getCurrentUser();
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Настройки - KiloGram</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            height: 100vh;
            overflow: hidden;
        }
        
        .settings-app {
            display: flex;
            height: 100vh;
            width: 100vw;
        }
        
        /* Боковое меню */
        .settings-sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #ddd;
            display: flex;
            flex-direction: column;
        }
        
        .settings-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .back-btn {
            background: none;
            border: none;
            font-size: 1.3em;
            cursor: pointer;
            color: #0088cc;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
        }
        
        .back-btn:hover {
            background: #f0f0f0;
        }
        
        .settings-header h1 {
            font-size: 1.5em;
            color: #333;
        }
        
        .settings-menu {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
        }
        
        .menu-section {
            margin-bottom: 20px;
        }
        
        .menu-section-title {
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
            padding: 0 10px;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s;
            color: #333;
            text-decoration: none;
            margin-bottom: 2px;
        }
        
        .menu-item:hover {
            background: #f5f5f5;
        }
        
        .menu-item.active {
            background: #e3f2fd;
            color: #0088cc;
        }
        
        .menu-item i {
            width: 24px;
            font-size: 1.2em;
            color: #666;
        }
        
        .menu-item.active i {
            color: #0088cc;
        }
        
        /* Основная область */
        .settings-content {
            flex: 1;
            background: #fff;
            overflow-y: auto;
            padding: 30px;
        }
        
        .content-header {
            margin-bottom: 25px;
        }
        
        .content-header h2 {
            font-size: 1.8em;
            color: #333;
            margin-bottom: 5px;
        }
        
        .content-header p {
            color: #666;
            font-size: 14px;
        }
        
        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 600px;
        }
        
        /* Формы */
        .avatar-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #0088cc;
            margin-bottom: 10px;
        }
        
        .change-avatar-btn {
            background: none;
            border: 1px solid #0088cc;
            color: #0088cc;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .change-avatar-btn:hover {
            background: #0088cc;
            color: white;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-size: 14px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0088cc;
            box-shadow: 0 0 0 2px rgba(0,136,204,0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group input[disabled] {
            background: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }
        
        .btn-primary {
            background: #0088cc;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn-primary:hover {
            background: #0077b5;
        }
        
        .btn-danger {
            background: #ff4444;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn-danger:hover {
            background: #cc0000;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        /* Мобильная адаптация */
        @media (max-width: 768px) {
            .settings-app {
                flex-direction: column;
            }
            
            .settings-sidebar {
                width: 100%;
                height: auto;
                border-right: none;
                border-bottom: 1px solid #ddd;
            }
            
            .settings-menu {
                display: flex;
                overflow-x: auto;
                padding: 10px;
                gap: 10px;
            }
            
            .menu-section {
                margin-bottom: 0;
                min-width: max-content;
            }
            
            .menu-section-title {
                display: none;
            }
            
            .menu-item {
                white-space: nowrap;
            }
            
            .settings-content {
                padding: 20px;
            }
        }
        
        /* Тёмная тема */
        body.dark-theme {
            background: #1a1a1a;
        }
        
        body.dark-theme .settings-sidebar {
            background: #2d2d2d;
            border-color: #3d3d3d;
        }
        
        body.dark-theme .settings-header h1 {
            color: white;
        }
        
        body.dark-theme .menu-item {
            color: #b0b3b8;
        }
        
        body.dark-theme .menu-item:hover {
            background: #3d3d3d;
        }
        
        body.dark-theme .menu-item.active {
            background: #1e3a5f;
            color: #0088cc;
        }
        
        body.dark-theme .menu-item i {
            color: #808080;
        }
        
        body.dark-theme .menu-item.active i {
            color: #0088cc;
        }
        
        body.dark-theme .settings-content {
            background: #1a1a1a;
        }
        
        body.dark-theme .content-header h2 {
            color: white;
        }
        
        body.dark-theme .content-header p {
            color: #b0b3b8;
        }
        
        body.dark-theme .settings-card {
            background: #2d2d2d;
        }
        
        body.dark-theme .form-group label {
            color: #b0b3b8;
        }
        
        body.dark-theme .form-group input,
        body.dark-theme .form-group textarea {
            background: #3d3d3d;
            border-color: #3d3d3d;
            color: white;
        }
        
        body.dark-theme .form-group input[disabled] {
            background: #2d2d2d;
            color: #808080;
        }
            
/* Увеличиваем расстояние в разделе Аккаунт */
.settings-card {
    padding: 25px;
    padding-bottom: 40px !important; /* Увеличиваем отступ снизу */
    margin-bottom: 30px !important;
}

.settings-card form {
    margin-bottom: 20px;
}

.settings-card .btn-primary {
    margin-top: 20px;
    margin-bottom: 15px;
}

/* Добавляем дополнительный отступ после кнопки */
.settings-card .btn-primary + * {
    margin-top: 20px;
}

/* Специально для последнего элемента */
.settings-card:last-child {
    margin-bottom: 50px !important;
    padding-bottom: 50px !important;
}

/* Для мобильных */
@media (max-width: 768px) {
    .settings-card {
        padding-bottom: 60px !important;
        margin-bottom: 40px !important;
    }
    
    .settings-card:last-child {
        margin-bottom: 70px !important;
        padding-bottom: 70px !important;
    }
}
    </style>
</head>
<body>
    <div class="settings-app">
        <!-- Боковое меню -->
        <div class="settings-sidebar">
            <div class="settings-header">
                <button class="back-btn" onclick="window.location.href='index.php'">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h1>Настройки</h1>
            </div>
            
            <div class="settings-menu">
                <div class="menu-section">
                    <div class="menu-section-title">Основное</div>
                    <a href="?section=account" class="menu-item <?php echo $section === 'account' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span>Аккаунт</span>
                    </a>
                    <a href="?section=notifications" class="menu-item <?php echo $section === 'notifications' ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i>
                        <span>Уведомления</span>
                    </a>
                    <a href="?section=privacy" class="menu-item <?php echo $section === 'privacy' ? 'active' : ''; ?>">
                        <i class="fas fa-lock"></i>
                        <span>Конфиденциальность</span>
                    </a>
                </div>
                
                <div class="menu-section">
                    <div class="menu-section-title">Настройки чатов</div>
                    <a href="?section=chats" class="menu-item <?php echo $section === 'chats' ? 'active' : ''; ?>">
                        <i class="fas fa-comments"></i>
                        <span>Чаты</span>
                    </a>
                    <a href="?section=files" class="menu-item <?php echo $section === 'files' ? 'active' : ''; ?>">
                        <i class="fas fa-folder"></i>
                        <span>Файлы</span>
                    </a>
                </div>
                
                <div class="menu-section">
                    <div class="menu-section-title">Дополнительно</div>
                    <a href="?section=theme" class="menu-item <?php echo $section === 'theme' ? 'active' : ''; ?>">
                        <i class="fas fa-palette"></i>
                        <span>Оформление</span>
                    </a>
                    <a href="?section=language" class="menu-item <?php echo $section === 'language' ? 'active' : ''; ?>">
                        <i class="fas fa-globe"></i>
                        <span>Язык</span>
                    </a>
                    <a href="?section=about" class="menu-item <?php echo $section === 'about' ? 'active' : ''; ?>">
                        <i class="fas fa-info-circle"></i>
                        <span>О приложении</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Основная область -->
        <div class="settings-content">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Раздел Аккаунт -->
            <?php if ($section === 'account'): ?>
                <div class="content-header">
                    <h2>Аккаунт</h2>
                    <p>Управление вашим профилем и личными данными</p>
                </div>
                
                <div class="settings-card">
                    <!-- Аватар -->
                    <div class="avatar-section">
                        <img src="avatars/<?php echo htmlspecialchars($currentUser['avatar'] ?? 'default_avatar.png'); ?>" alt="Avatar" class="avatar-preview" id="avatarPreview">
                        <div>
                            <form method="POST" enctype="multipart/form-data" id="avatarForm">
                                <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display: none;">
                                <button type="button" class="change-avatar-btn" onclick="document.getElementById('avatarInput').click()">
                                    <i class="fas fa-camera"></i> Изменить фото
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <!-- Имя пользователя -->
                        <div class="form-group">
                            <label>Имя пользователя</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($currentUser['username']); ?>" required>
                            <div class="info-text">Можно изменить в любое время</div>
                        </div>
                        
                        <!-- Email -->
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                            <div class="info-text">Используется для входа</div>
                        </div>
                        
                        <!-- Имя -->
                        <div class="form-group">
                            <label>Имя</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>" placeholder="Ваше имя">
                        </div>
                        
                        <!-- Фамилия -->
                        <div class="form-group">
                            <label>Фамилия</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>" placeholder="Ваша фамилия">
                        </div>
                        
                        <!-- Телефон (только для просмотра) -->
                        <div class="form-group">
                            <label>Телефон</label>
                            <input type="tel" value="<?php echo htmlspecialchars($currentUser['phone'] ?? 'Не указан'); ?>" disabled>
                            <div class="info-text">Номер телефона нельзя изменить</div>
                        </div>
                        
                        <!-- О себе -->
                        <div class="form-group">
                            <label>О себе</label>
                            <textarea name="bio" placeholder="Расскажите немного о себе..."><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" name="save_profile" class="btn-primary">
                            <i class="fas fa-save"></i> Сохранить изменения
                        </button>
                    </form>
                </div>
                
                <script>
                // Обработка загрузки аватара
                document.getElementById('avatarInput')?.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    
                    // Проверка размера (макс 5 МБ)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('Файл слишком большой. Максимум 5 МБ');
                        return;
                    }
                    
                    // Проверка типа
                    if (!file.type.match('image.*')) {
                        alert('Можно загружать только изображения');
                        return;
                    }
                    
                    // Превью
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('avatarPreview').src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    
                    // Отправка на сервер
                    const formData = new FormData();
                    formData.append('avatar', file);
                    
                    fetch('upload_avatar.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ Аватар успешно обновлён');
                        } else {
                            alert('❌ Ошибка: ' + data.error);
                            // Возвращаем старый аватар
                            document.getElementById('avatarPreview').src = 'avatars/<?php echo htmlspecialchars($currentUser['avatar'] ?? 'default_avatar.png'); ?>';
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка:', error);
                        alert('❌ Ошибка при загрузке');
                    });
                });
                </script>
            <?php endif; ?>
            
            <!-- Раздел Уведомления -->
            <?php if ($section === 'notifications'): ?>
                <div class="content-header">
                    <h2>Уведомления</h2>
                    <p>Настройка уведомлений</p>
                </div>
                <div class="settings-card">
                    <p style="color: #666; text-align: center; padding: 40px;">Раздел в разработке</p>
                </div>
            <?php endif; ?>
            
            <!-- Раздел Конфиденциальность -->
            <?php if ($section === 'privacy'): ?>
                <div class="content-header">
                    <h2>Конфиденциальность</h2>
                    <p>Настройки приватности</p>
                </div>
                <div class="settings-card">
                    <p style="color: #666; text-align: center; padding: 40px;">Раздел в разработке</p>
                </div>
            <?php endif; ?>
            
            <!-- Раздел Чаты -->
            <?php if ($section === 'chats'): ?>
                <div class="content-header">
                    <h2>Чаты</h2>
                    <p>Настройки чатов</p>
                </div>
                <div class="settings-card">
                    <p style="color: #666; text-align: center; padding: 40px;">Раздел в разработке</p>
                </div>
            <?php endif; ?>
            
            <!-- Раздел Файлы -->
            <?php if ($section === 'files'): ?>
                <div class="content-header">
                    <h2>Файлы</h2>
                    <p>Управление файлами</p>
                </div>
                <div class="settings-card">
                    <p style="color: #666; text-align: center; padding: 40px;">Раздел в разработке</p>
                </div>
            <?php endif; ?>
            
            <!-- Раздел Оформление -->
            <?php if ($section === 'theme'): ?>
                <div class="content-header">
                    <h2>Оформление</h2>
                    <p>Настройка внешнего вида</p>
                </div>
                <div class="settings-card">
                    <p style="color: #666; text-align: center; padding: 40px;">Раздел в разработке</p>
                </div>
            <?php endif; ?>
            
            <!-- Раздел Язык -->
            <?php if ($section === 'language'): ?>
                <div class="content-header">
                    <h2>Язык</h2>
                    <p>Выберите язык интерфейса</p>
                </div>
                <div class="settings-card">
                    <p style="color: #666; text-align: center; padding: 40px;">Раздел в разработке</p>
                </div>
            <?php endif; ?>
            
            <!-- Раздел О приложении -->
            <?php if ($section === 'about'): ?>
                <div class="content-header">
                    <h2>О приложении</h2>
                    <p>Версия и информация</p>
                </div>
                <div class="settings-card">
                    <div style="text-align: center; padding: 40px 20px;">
                        <i class="fas fa-paper-plane" style="font-size: 4em; color: #0088cc; margin-bottom: 20px;"></i>
                        <h3 style="margin-bottom: 10px; font-size: 1.5em;">KiloGram</h3>
                        <p style="color: #666; margin-bottom: 5px;">Версия 1.0.0</p>
                        <p style="color: #666; margin-bottom: 20px;">Мессенджер с открытым исходным кодом</p>
                        <div style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px;">
                            <p style="color: #999; font-size: 12px;">© 2026 KiloGram. Все права защищены.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        var currentUser = <?php echo json_encode($currentUser); ?>;
    </script>
    <script src="script.js"></script>
</body>