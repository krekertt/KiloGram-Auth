<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Обновление основной информации
    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
    $stmt->execute([$first_name, $last_name, $phone, $user['id']]);
    
    // Смена пароля
    if (!empty($current_password) && !empty($new_password)) {
        if (!password_verify($current_password, $user['password'])) {
            $error = 'Неверный текущий пароль';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Новые пароли не совпадают';
        } elseif (strlen($new_password) < 6) {
            $error = 'Новый пароль должен быть не менее 6 символов';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user['id']]);
            $success = 'Пароль успешно изменен';
        }
    }
    
    // Загрузка аватара
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['avatar']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = 'avatars/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filepath)) {
                // Удаляем старый аватар, если это не стандартный
                if ($user['avatar'] !== 'default_avatar.png' && file_exists($user['avatar'])) {
                    unlink($user['avatar']);
                }
                
                $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$filename, $user['id']]);
                $user['avatar'] = $filename;
                $success = 'Аватар успешно обновлен';
            }
        } else {
            $error = 'Неподдерживаемый формат файла';
        }
    }
    
    // Обновляем данные пользователя
    $user = getCurrentUser();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль - KiloGram</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <button onclick="window.location.href='index.html'" class="btn-back">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h1>Профиль</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="profile-content">
            <div class="avatar-section">
                <img src="avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="profile-avatar">
                <form method="POST" enctype="multipart/form-data" class="avatar-form">
                    <label for="avatar" class="btn-secondary">
                        <i class="fas fa-camera"></i> Изменить фото
                    </label>
                    <input type="file" id="avatar" name="avatar" accept="image/*" style="display: none;" onchange="this.form.submit()">
                </form>
            </div>
            
            <form method="POST" class="profile-form">
                <div class="form-group">
                    <label for="username">Имя пользователя</label>
                    <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    <small>Имя пользователя нельзя изменить</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Имя</label>
                        <input type="text" id="first_name" name="first_name" 
                               value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Фамилия</label>
                        <input type="text" id="last_name" name="last_name" 
                               value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Телефон</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                
                <h3>Смена пароля</h3>
                
                <div class="form-group">
                    <label for="current_password">Текущий пароль</label>
                    <input type="password" id="current_password" name="current_password">
                </div>
                
                <div class="form-group">
                    <label for="new_password">Новый пароль</label>
                    <input type="password" id="new_password" name="new_password">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Подтвердите новый пароль</label>
                    <input type="password" id="confirm_password" name="confirm_password">
                </div>
                
                <button type="submit" class="btn-primary">Сохранить изменения</button>
            </form>
        </div>
    </div>
</body>
</html>