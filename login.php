<?php
require_once 'config.php';

// Если уже авторизован - отправляем в чат
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$step = $_GET['step'] ?? 'phone'; // phone, code, или success
$phone = $_GET['phone'] ?? $_POST['phone'] ?? '';
$country_code = $_GET['country'] ?? '7'; // По умолчанию Россия (+7)

// Список кодов стран
$countries = [
    '7' => ['flag' => '🇷🇺', 'name' => 'Россия', 'code' => '+7', 'mask' => '999 999-99-99'],
    '380' => ['flag' => '🇺🇦', 'name' => 'Украина', 'code' => '+380', 'mask' => '99 999-99-99'],
    '375' => ['flag' => '🇧🇾', 'name' => 'Беларусь', 'code' => '+375', 'mask' => '99 999-99-99'],
    '1' => ['flag' => '🇺🇸', 'name' => 'США', 'code' => '+1', 'mask' => '999 999-9999'],
    '44' => ['flag' => '🇬🇧', 'name' => 'Великобритания', 'code' => '+44', 'mask' => '99 9999-9999'],
    '49' => ['flag' => '🇩🇪', 'name' => 'Германия', 'code' => '+49', 'mask' => '999 999-99999'],
    '33' => ['flag' => '🇫🇷', 'name' => 'Франция', 'code' => '+33', 'mask' => '99 99-99-99'],
    '39' => ['flag' => '🇮🇹', 'name' => 'Италия', 'code' => '+39', 'mask' => '999 999-9999'],
    '34' => ['flag' => '🇪🇸', 'name' => 'Испания', 'code' => '+34', 'mask' => '999 99-99-99'],
    '90' => ['flag' => '🇹🇷', 'name' => 'Турция', 'code' => '+90', 'mask' => '999 999-9999'],
    '86' => ['flag' => '🇨🇳', 'name' => 'Китай', 'code' => '+86', 'mask' => '999 9999-9999'],
    '81' => ['flag' => '🇯🇵', 'name' => 'Япония', 'code' => '+81', 'mask' => '99 9999-9999'],
    '82' => ['flag' => '🇰🇷', 'name' => 'Южная Корея', 'code' => '+82', 'mask' => '999 999-9999'],
    '91' => ['flag' => '🇮🇳', 'name' => 'Индия', 'code' => '+91', 'mask' => '99999 99999'],
    '55' => ['flag' => '🇧🇷', 'name' => 'Бразилия', 'code' => '+55', 'mask' => '99 99999-9999'],
    '52' => ['flag' => '🇲🇽', 'name' => 'Мексика', 'code' => '+52', 'mask' => '999 999-9999'],
    '61' => ['flag' => '🇦🇺', 'name' => 'Австралия', 'code' => '+61', 'mask' => '999 999-999'],
    '64' => ['flag' => '🇳🇿', 'name' => 'Новая Зеландия', 'code' => '+64', 'mask' => '99 999-9999'],
    '27' => ['flag' => '🇿🇦', 'name' => 'ЮАР', 'code' => '+27', 'mask' => '99 999-9999'],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Вход в KiloGram</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .auth-container {
            width: 100%;
            max-width: 450px;
        }
        
        .auth-box {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 3em;
            color: #0088cc;
            margin-bottom: 10px;
        }
        
        .logo h1 {
            color: #333;
            font-size: 2em;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f0f0f0;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
        }
        
        .step.active {
            background: #0088cc;
            color: white;
        }
        
        .step-line {
            width: 60px;
            height: 2px;
            background: #f0f0f0;
            margin: 0 10px;
        }
        
        .step-line.active {
            background: #0088cc;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Выбор страны */
        .country-selector {
            position: relative;
            margin-bottom: 15px;
        }
        
        .country-selector-btn {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            background: white;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
        }
        
        .country-selector-btn:hover {
            border-color: #0088cc;
        }
        
        .country-selector-btn .flag {
            font-size: 24px;
        }
        
        .country-selector-btn .country-name {
            flex: 1;
            text-align: left;
            color: #333;
        }
        
        .country-selector-btn .country-code {
            color: #0088cc;
            font-weight: 600;
        }
        
        .country-selector-btn i {
            color: #999;
        }
        
        .country-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            margin-top: 5px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .country-dropdown.show {
            display: block;
        }
        
        .country-option {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .country-option:hover {
            background: #f5f5f5;
        }
        
        .country-option .flag {
            font-size: 20px;
        }
        
        .country-option .country-name {
            flex: 1;
            color: #333;
        }
        
        .country-option .country-code {
            color: #0088cc;
            font-weight: 600;
        }
        
        .phone-input {
            display: flex;
            align-items: center;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .phone-prefix {
            background: #f5f5f5;
            padding: 15px;
            color: #0088cc;
            font-weight: 600;
            border-right: 2px solid #e0e0e0;
            min-width: 80px;
            text-align: center;
        }
        
        .phone-input input {
            border: none;
            border-radius: 0;
            flex: 1;
            padding: 15px;
            font-size: 18px;
            letter-spacing: 1px;
            outline: none;
        }
        
        .btn-primary {
            width: 100%;
            padding: 15px;
            background: #0088cc;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary:hover {
            background: #0077b5;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,136,204,0.3);
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-secondary {
            width: 100%;
            padding: 15px;
            background: white;
            color: #0088cc;
            border: 2px solid #0088cc;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            background: #f0f0f0;
        }
        
        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background: #fee;
            color: #c00;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .alert i {
            font-size: 1.2em;
        }
        
        .timer {
            text-align: center;
            margin: 15px 0;
            color: #666;
            font-size: 14px;
        }
        
        .timer span {
            font-weight: bold;
            color: #0088cc;
        }
        
        .code-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .code-input {
            width: 50px;
            height: 60px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            outline: none;
            transition: all 0.3s;
        }
        
        .code-input:focus {
            border-color: #0088cc;
            box-shadow: 0 0 0 3px rgba(0,136,204,0.1);
        }
        
        .bot-link {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 12px;
        }
        
        .bot-link p {
            color: #666;
            margin-bottom: 10px;
        }
        
        .bot-link a {
            color: #0088cc;
            text-decoration: none;
            font-weight: 600;
        }
        
        .bot-link a:hover {
            text-decoration: underline;
        }
        
        .resend-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .resend-link a {
            color: #0088cc;
            text-decoration: none;
            font-size: 14px;
        }
        
        .resend-link a:hover {
            text-decoration: underline;
        }
        
        /* Тёмная тема */
        body.dark-theme .auth-box {
            background: #2d2d2d;
        }
        
        body.dark-theme .logo h1 {
            color: white;
        }
        
        body.dark-theme .logo p {
            color: #b0b3b8;
        }
        
        body.dark-theme .step {
            background: #3d3d3d;
            color: #b0b3b8;
        }
        
        body.dark-theme .step-line {
            background: #3d3d3d;
        }
        
        body.dark-theme .form-group label {
            color: #b0b3b8;
        }
        
        body.dark-theme .country-selector-btn {
            background: #3d3d3d;
            border-color: #3d3d3d;
        }
        
        body.dark-theme .country-selector-btn .country-name {
            color: white;
        }
        
        body.dark-theme .country-dropdown {
            background: #3d3d3d;
            border-color: #4d4d4d;
        }
        
        body.dark-theme .country-option:hover {
            background: #4d4d4d;
        }
        
        body.dark-theme .country-option .country-name {
            color: white;
        }
        
        body.dark-theme .phone-input {
            border-color: #3d3d3d;
        }
        
        body.dark-theme .phone-prefix {
            background: #2d2d2d;
            color: #0088cc;
            border-color: #3d3d3d;
        }
        
        body.dark-theme .phone-input input {
            background: #3d3d3d;
            color: white;
        }
        
        body.dark-theme .bot-link {
            background: #3d3d3d;
        }
        
        body.dark-theme .bot-link p {
            color: #b0b3b8;
        }
        
        /* Адаптация для мобильных */
        @media (max-width: 480px) {
            .auth-box {
                padding: 30px 20px;
            }
            
            .code-inputs {
                gap: 5px;
            }
            
            .code-input {
                width: 40px;
                height: 50px;
                font-size: 20px;
            }
            
            .country-option .flag {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="logo">
                <i class="fas fa-paper-plane"></i>
                <h1>KiloGram</h1>
                <p>Вход по номеру телефона</p>
            </div>
            
            <!-- Индикатор шагов -->
            <div class="step-indicator">
                <div class="step <?php echo $step === 'phone' ? 'active' : ''; ?>">1</div>
                <div class="step-line <?php echo in_array($step, ['code', 'success']) ? 'active' : ''; ?>"></div>
                <div class="step <?php echo $step === 'code' ? 'active' : ''; ?>">2</div>
                <div class="step-line <?php echo $step === 'success' ? 'active' : ''; ?>"></div>
                <div class="step <?php echo $step === 'success' ? 'active' : ''; ?>">3</div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step === 'phone'): ?>
                <!-- Шаг 1: Ввод номера телефона -->
                <form method="POST" action="send_code.php" id="phoneForm">
                    <div class="form-group">
                        <label>Выберите страну</label>
                        <div class="country-selector">
                            <div class="country-selector-btn" onclick="toggleCountryDropdown()">
                                <span class="flag" id="selectedFlag"><?php echo $countries[$country_code]['flag'] ?? '🇷🇺'; ?></span>
                                <span class="country-name" id="selectedCountry"><?php echo $countries[$country_code]['name'] ?? 'Россия'; ?></span>
                                <span class="country-code" id="selectedCode"><?php echo $countries[$country_code]['code'] ?? '+7'; ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            
                            <div class="country-dropdown" id="countryDropdown">
                                <?php foreach ($countries as $code => $country): ?>
                                <div class="country-option" onclick="selectCountry('<?php echo $code; ?>', '<?php echo $country['flag']; ?>', '<?php echo $country['name']; ?>', '<?php echo $country['code']; ?>', '<?php echo $country['mask']; ?>')">
                                    <span class="flag"><?php echo $country['flag']; ?></span>
                                    <span class="country-name"><?php echo $country['name']; ?></span>
                                    <span class="country-code"><?php echo $country['code']; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Ваш номер телефона</label>
                        <div class="phone-input">
                            <span class="phone-prefix" id="phonePrefix"><?php echo $countries[$country_code]['code'] ?? '+7'; ?></span>
                            <input type="tel" 
                                   name="phone" 
                                   id="phoneNumber"
                                   placeholder="<?php echo $countries[$country_code]['mask'] ?? '999 999-99-99'; ?>"
                                   pattern="[0-9\s\-]+"
                                   required
                                   autofocus>
                        </div>
                        <input type="hidden" name="clean_phone" id="cleanPhone">
                    </div>
                    
                    <input type="hidden" name="country_code" id="countryCode" value="<?php echo $country_code; ?>">
                    <input type="hidden" name="full_phone" id="fullPhone">
                    
                    <button type="submit" class="btn-primary" id="sendCodeBtn">
                        <i class="fas fa-paper-plane"></i>
                        Получить код
                    </button>
                </form>
                
                <div class="bot-link">
                    <p>Нет номера?</p>
                    <a href="https://t.me/kilogramauthbot" target="_blank">
                        <i class="fab fa-telegram"></i> Получить номер в Telegram боте
                    </a>
                </div>
                
            <?php elseif ($step === 'code'): ?>
                <!-- Шаг 2: Ввод кода подтверждения -->
                <form method="POST" action="verify_code.php" id="codeForm">
                    <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                    
                    <div class="form-group">
                        <label>Введите код из Telegram</label>
                        <div class="code-inputs">
                            <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autofocus>
                            <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                            <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                            <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                            <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                            <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        </div>
                    </div>
                    
                    <div class="timer" id="timer">
                        Код действителен <span id="time">05:00</span>
                    </div>
                    
                    <button type="submit" class="btn-primary" id="verifyBtn">
                        <i class="fas fa-check"></i>
                        Подтвердить
                    </button>
                </form>
                
                <div class="resend-link">
                    <a href="#" id="resendCode">Отправить код повторно</a>
                </div>
                
                <button class="btn-secondary" onclick="window.location.href='login.php'">
                    <i class="fas fa-arrow-left"></i>
                    Назад
                </button>
                
            <?php elseif ($step === 'success'): ?>
                <!-- Шаг 3: Успешный вход -->
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Вход выполнен успешно!
                </div>
                
                <button class="btn-primary" onclick="window.location.href='index.php'">
                    <i class="fas fa-comments"></i>
                    Перейти в чаты
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Выбор страны
        function toggleCountryDropdown() {
            document.getElementById('countryDropdown').classList.toggle('show');
        }
        
        function selectCountry(code, flag, name, prefix, mask) {
            document.getElementById('selectedFlag').textContent = flag;
            document.getElementById('selectedCountry').textContent = name;
            document.getElementById('selectedCode').textContent = prefix;
            document.getElementById('phonePrefix').textContent = prefix;
            document.getElementById('countryCode').value = code;
            document.getElementById('phoneNumber').placeholder = mask;
            
            document.getElementById('countryDropdown').classList.remove('show');
        }
        
        // Закрыть дропдаун при клике вне его
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('countryDropdown');
            const btn = document.querySelector('.country-selector-btn');
            
            if (!btn.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        // Форматирование номера при вводе
        document.getElementById('phoneNumber')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, '');
            if (value.length > 10) value = value.slice(0, 10);
            
            // Форматируем по маске
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i === 1 || i === 4 || i === 7 || i === 9) {
                    formatted += ' ';
                }
                formatted += value[i];
            }
            
            e.target.value = formatted.trim();
            
            // Сохраняем полный номер
            const prefix = document.getElementById('phonePrefix').textContent;
            document.getElementById('fullPhone').value = prefix + value;
        });
        
        // Автоматический переход между полями кода
        document.querySelectorAll('.code-input').forEach((input, index, inputs) => {
            input.addEventListener('input', function() {
                if (this.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
                
                // Собираем все цифры
                const code = Array.from(inputs).map(i => i.value).join('');
                if (code.length === 6) {
                    document.getElementById('verifyBtn').disabled = false;
                }
            });
            
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });
        
        // Таймер для кода
        <?php if ($step === 'code'): ?>
        let timeLeft = 300; // 5 минут в секундах
        const timerElement = document.getElementById('time');
        
        const timer = setInterval(() => {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                timerElement.textContent = '00:00';
                document.getElementById('verifyBtn').disabled = true;
            }
        }, 1000);
        <?php endif; ?>
        
        // Повторная отправка кода
        document.getElementById('resendCode')?.addEventListener('click', function(e) {
            e.preventDefault();
            
            fetch('send_code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    phone: '<?php echo $phone; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Код отправлен повторно');
                    timeLeft = 300;
                } else {
                    alert('Ошибка: ' + data.error);
                }
            });
        });
    </script>
    <script>
    document.getElementById('phoneForm').addEventListener('submit', function(e) {
        const rawPhone = document.getElementById('phoneNumber').value;
        // Убираем все пробелы и оставляем только цифры
        const digits = rawPhone.replace(/\D/g, '');
        const prefix = document.getElementById('phonePrefix').textContent;
        // Формируем полный номер
        const cleanPhone = prefix + digits;
        document.getElementById('cleanPhone').value = cleanPhone;
    
       // Для отладки можно раскомментировать
       // alert('Отправляем номер: ' + cleanPhone);
});
</script>
</body>
</html>