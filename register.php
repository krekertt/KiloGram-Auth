<?php
require_once 'config.php';

// Если уже авторизован - отправляем в чат
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    
    // Валидация
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Заполните все обязательные поля';
    } elseif ($password !== $confirm_password) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не менее 6 символов';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Неверный формат email';
    } else {
        // Проверка существования пользователя
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким именем или email уже существует';
        } else {
            // Хеширование пароля
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Добавление пользователя
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, status, last_seen) 
                VALUES (?, ?, ?, ?, ?, 'online', CURRENT_TIMESTAMP)
            ");
            
            if ($stmt->execute([$username, $email, $hashed_password, $first_name, $last_name])) {
                $success = 'Регистрация прошла успешно!';
                header('refresh:2;url=login.php');
            } else {
                $error = 'Ошибка при регистрации';
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
    <title>Регистрация в KiloGram</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .auth-container {
            width: 100%;
            max-width: 420px;
        }
        
        .auth-box {
            background: white;
            border-radius: 20px;
            padding: 30px 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .auth-box h1 {
            text-align: center;
            color: #0088cc;
            margin-bottom: 5px;
            font-size: 2.2em;
        }
        
        .auth-box h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #666;
            font-size: 1.1em;
            font-weight: normal;
        }
        
        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            color: #555;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .form-group input {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s;
            width: 100%;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #0088cc;
            box-shadow: 0 0 0 2px rgba(0,136,204,0.1);
        }
        
        .form-row {
            display: flex;
            gap: 8px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn-primary {
            padding: 12px;
            background-color: #0088cc;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 5px;
        }
        
        .btn-primary:hover {
            background-color: #0077b5;
        }
        
        .auth-link {
            text-align: center;
            margin-top: 15px;
            color: #666;
            font-size: 14px;
        }
        
        .auth-link a {
            color: #0088cc;
            text-decoration: none;
            font-weight: 600;
        }
        
        .auth-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .alert-error {
            background-color: #fee;
            color: #c00;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        /* Адаптация для мобильных */
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .auth-box {
                padding: 20px 15px;
            }
            
            .auth-box h1 {
                font-size: 2em;
            }
            
            .form-row {
                flex-direction: column;
                gap: 12px;
            }
            
            .form-group input {
                font-size: 16px;
                padding: 12px;
            }
            
            .btn-primary {
                padding: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>KiloGram</h1>
            <h2>Регистрация</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="username">Имя пользователя *</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Имя</label>
                        <input type="text" id="first_name" name="first_name"
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Фамилия</label>
                        <input type="text" id="last_name" name="last_name"
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Пароль *</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Подтвердите пароль *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn-primary">Зарегистрироваться</button>
            </form>
            
            <p class="auth-link">
                Уже есть аккаунт? <a href="login.php">Войдите</a>
            </p>
        </div>
    </div>
</body>
</html>