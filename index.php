<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = getCurrentUser();

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>KiloGram - Мессенджер</title>
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
        
        .app-container {
            display: flex;
            height: 100vh;
            width: 100vw;
        }
        
        /* Левая панель */
        .sidebar {
            width: 320px;
            background: white;
            border-right: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }
        
        .sidebar-header {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .app-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 0;
        }
        
        .app-logo i {
            color: #0088cc;
            font-size: 1.5em;
        }
        
        .app-logo span {
            font-size: 1.3em;
            font-weight: 600;
            color: #0088cc;
        }
        
        /* Меню с тремя точками */
        .sidebar-menu {
            position: relative;
        }
        
        .menu-btn {
            background: none;
            border: none;
            font-size: 1.3em;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 50%;
            color: #666;
            transition: all 0.2s;
        }
        
        .menu-btn:hover {
            background: #f0f0f0;
            color: #0088cc;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 45px;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            min-width: 200px;
            z-index: 1000;
            overflow: hidden;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.2s;
            color: #333;
        }
        
        .menu-item:hover {
            background: #f5f5f5;
        }
        
        .menu-item i {
            width: 20px;
            color: #0088cc;
        }
        
        .menu-divider {
            height: 1px;
            background: #ddd;
            margin: 5px 0;
        }
        
        /* Поиск */
        .search-box {
            padding: 10px 15px;
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            left: 25px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            z-index: 1;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 25px;
            outline: none;
            font-size: 14px;
            background: #f5f5f5;
            transition: all 0.2s;
        }
        
        .search-box input:focus {
            border-color: #0088cc;
            background: white;
        }
        
        .chats-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        
        .chat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 10px;
            cursor: pointer;
            margin-bottom: 2px;
            transition: background 0.2s;
        }
        
        .chat-item:hover {
            background: #f5f5f5;
        }
        
        .chat-item.active {
            background: #e3f2fd;
        }
        
        .chat-item .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #0088cc;
        }
        
        .chat-info {
            flex: 1;
            min-width: 0;
        }
        
        .chat-name {
            font-weight: 600;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .last-message {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-meta {
            font-size: 11px;
            color: #999;
            white-space: nowrap;
        }
        
        /* Правая область */
        .chat-area {
            flex: 1;
            background: #fff;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        .no-chat-selected {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 18px;
            background: #f9f9f9;
        }
        
        .chat-interface {
            display: none;
            flex-direction: column;
            height: 100%;
            width: 100%;
        }
        
        .chat-header {
            padding: 12px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            height: 70px;
            flex-shrink: 0;
        }
        
        .chat-header-info {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            flex: 1;
        }
        
        .chat-header-info .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #0088cc;
        }
        
        .chat-header-info h3 {
            font-size: 18px;
            margin-bottom: 4px;
        }
        
        .user-status {
            font-size: 13px;
            color: #666;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f5f7fa;
            display: flex;
            flex-direction: column;
        }
        
        .messages-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .message {
            max-width: 70%;
            margin-bottom: 2px;
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }
        
        .my-message {
            margin-left: auto;
            flex-direction: row-reverse;
        }
        
        .other-message {
            margin-right: auto;
        }
        
        .message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        
        .message-content {
            padding: 10px 14px;
            border-radius: 18px;
            word-wrap: break-word;
            max-width: 100%;
        }
        
        .my-message .message-content {
            background: #0088cc;
            color: white;
            border-top-right-radius: 4px;
        }
        
        .other-message .message-content {
            background: white;
            border-top-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message-author {
            font-size: 12px;
            font-weight: 600;
            color: #0088cc;
            margin-bottom: 2px;
        }
        
        .my-message .message-author {
            color: rgba(255,255,255,0.9);
            text-align: right;
        }
        
        .message-meta {
            font-size: 10px;
            color: #999;
            text-align: right;
            margin-top: 2px;
        }
        
        .my-message .message-meta {
            color: rgba(255,255,255,0.7);
        }
        
        /* Поле ввода */
        .message-input-container {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            background: white;
            width: 100%;
            flex-shrink: 0;
        }
        
        .message-form {
            display: flex;
            gap: 10px;
            align-items: center;
            width: 100%;
        }
        
        .message-form input {
            flex: 1;
            height: 45px;
            padding: 0 15px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 15px;
            outline: none;
            background: white;
            color: #333;
        }
        
        .message-form input:focus {
            border-color: #0088cc;
            box-shadow: 0 0 0 2px rgba(0,136,204,0.1);
        }
        
        .attach-btn {
            width: 45px;
            height: 45px;
            border: none;
            border-radius: 50%;
            background: #f0f0f0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            color: #666;
            transition: all 0.2s;
        }
        
        .attach-btn:hover {
            background: #ddd;
            color: #0088cc;
        }
        
        .send-btn {
            width: 45px;
            height: 45px;
            border: none;
            border-radius: 50%;
            background: #0088cc;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            transition: all 0.2s;
        }
        
        .send-btn:hover {
            background: #0077b5;
            transform: scale(1.05);
        }
        
        /* Кнопка назад */
        .mobile-back-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.8em;
            color: #333;
            margin-right: 5px;
            cursor: pointer;
            padding: 0 10px;
        }
        
        /* Меню выбора файлов */
        .file-menu {
            position: fixed;
            bottom: 80px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            align-items: flex-end;
            z-index: 2000;
            animation: slideUp 0.3s ease;
        }
        
        .file-menu-content {
            background: white;
            border-radius: 20px 20px 0 0;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
        }
        
        .file-menu-header {
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .file-menu-header h3 {
            margin: 0;
            color: #333;
        }
        
        .file-menu-close {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #666;
            padding: 5px;
        }
        
        .file-menu-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 20px;
        }
        
        .file-menu-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 15px 10px;
            background: #f5f5f5;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .file-menu-item:hover {
            background: #e3f2fd;
            transform: scale(1.02);
        }
        
        .file-menu-item i {
            font-size: 2em;
            color: #0088cc;
        }
        
        .file-menu-item span {
            font-size: 12px;
            color: #666;
        }
        
        /* Модальные окна */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            max-height: 80vh;
            overflow: hidden;
        }
        
        .modal-header {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-body {
            padding: 15px;
            overflow-y: auto;
        }
        
        .users-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            cursor: pointer;
            border-radius: 8px;
        }
        
        .user-item:hover {
            background: #f5f5f5;
        }
        
        .user-item .avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* Индикаторы */
        .sending-indicator {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #0088cc;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            z-index: 3000;
            display: none;
            align-items: center;
            gap: 8px;
        }
        
        /* Темная тема */
        body.dark-theme {
            background: #1a1a1a;
            color: #fff;
        }
        
        body.dark-theme .sidebar {
            background: #2d2d2d;
            border-color: #3d3d3d;
        }
        
        body.dark-theme .menu-btn {
            color: #b0b3b8;
        }
        
        body.dark-theme .menu-btn:hover {
            background: #3d3d3d;
            color: #0088cc;
        }
        
        body.dark-theme .dropdown-menu {
            background: #2d2d2d;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        body.dark-theme .menu-item {
            color: white;
        }
        
        body.dark-theme .menu-item:hover {
            background: #3d3d3d;
        }
        
        body.dark-theme .menu-divider {
            background: #3d3d3d;
        }
        
        body.dark-theme .search-box input {
            background: #3d3d3d;
            border-color: #3d3d3d;
            color: white;
        }
        
        body.dark-theme .chat-item:hover {
            background: #3d3d3d;
        }
        
        body.dark-theme .chat-item.active {
            background: #1e3a5f;
        }
        
        body.dark-theme .chat-area {
            background: #1a1a1a;
        }
        
        body.dark-theme .no-chat-selected {
            background: #1a1a1a;
            color: #666;
        }
        
        body.dark-theme .chat-header {
            background: #2d2d2d;
            border-color: #3d3d3d;
        }
        
        body.dark-theme .messages-container {
            background: #1a1a1a;
        }
        
        body.dark-theme .other-message .message-content {
            background: #2d2d2d;
            color: white;
        }
        
        body.dark-theme .message-input-container {
            background: #2d2d2d;
            border-color: #3d3d3d;
        }
        
        body.dark-theme .message-form input {
            background: #3d3d3d;
            border-color: #3d3d3d;
            color: white;
        }
        
        body.dark-theme .attach-btn {
            background: #3d3d3d;
            color: #b0b3b8;
        }
        
        body.dark-theme .attach-btn:hover {
            background: #4d4d4d;
        }
        
        body.dark-theme .mobile-back-btn {
            color: white;
        }
        
        body.dark-theme .file-menu-content {
            background: #2d2d2d;
        }
        
        body.dark-theme .file-menu-header {
            border-color: #3d3d3d;
        }
        
        body.dark-theme .file-menu-header h3 {
            color: white;
        }
        
        body.dark-theme .file-menu-item {
            background: #3d3d3d;
        }
        
        body.dark-theme .file-menu-item span {
            color: #b0b3b8;
        }
        
        /* Мобильная адаптация */
        @media (max-width: 768px) {
            .sidebar {
                width: 100% !important;
                position: fixed !important;
                z-index: 1000 !important;
                height: 100vh !important;
                transition: transform 0.3s ease !important;
            }
            
            .sidebar.hide {
                transform: translateX(-100%) !important;
            }
            
            .chat-area {
                width: 100% !important;
                margin-left: 0 !important;
            }
            
            .mobile-back-btn {
                display: inline-block !important;
            }
            
            .message {
                max-width: 85% !important;
            }
            
            .messages-container {
                flex: 1 !important;
                overflow-y: auto !important;
                padding: 15px !important;
                padding-bottom: 80px !important;
            }
            
            .message-input-container {
                display: block !important;
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                background: white !important;
                padding: 10px 15px !important;
                border-top: 2px solid #ddd !important;
                z-index: 2000 !important;
            }
            
            .message-form input {
                height: 45px !important;
                font-size: 16px !important;
            }
            
            .attach-btn, .send-btn {
                width: 45px !important;
                height: 45px !important;
                min-width: 45px !important;
            }
            
            .chat-header {
                height: 60px !important;
                padding: 0 15px !important;
            }
            
            .chat-header-info h3 {
                font-size: 16px !important;
                max-width: 180px !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            
            .file-menu-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            body.dark-theme .message-input-container {
                background: #2d2d2d !important;
                border-top-color: #3d3d3d !important;
            }
            
            body.dark-theme .message-form input {
                background: #3d3d3d !important;
                border-color: #3d3d3d !important;
                color: white !important;
            }
        }
        
        /* Уменьшаем расстояние между последним сообщением и полем ввода */
        .messages-container {
            padding-bottom: 10px !important;
        }
        
        .messages-list {
            padding-bottom: 5px !important;
        }
        
        .messages-list .message:last-child {
            margin-bottom: 5px !important;
        }
        
        .message {
            margin-bottom: 2px !important;
        }
        
        .message-content {
            margin-bottom: 0 !important;
        }
    
/* ========== ИСПРАВЛЕНИЕ ПОСЛЕДНЕГО СООБЩЕНИЯ ========== */
.messages-container {
    padding-bottom: 20px !important;
}

.messages-list {
    padding-bottom: 30px !important;
}

.messages-list .message:last-child {
    margin-bottom: 40px !important;
}

/* Дополнительный отступ для мобильных */
@media (max-width: 768px) {
    .messages-container {
        padding-bottom: 30px !important;
    }
    
    .messages-list {
        padding-bottom: 40px !important;
    }
    
    .messages-list .message:last-child {
        margin-bottom: 50px !important;
    }
}
            
.dynamic-spacer {
    height: 40px !important;
    width: 100%;
    flex-shrink: 0;
    transition: height 0.2s ease;
}
            
/* ========== ФИКСИРОВАННЫЙ ОТСТУП ========== */
.messages-container {
    padding-bottom: 10px !important;
}

.messages-list {
    padding-bottom: 5px !important;
}

.messages-list .message:last-child {
    margin-bottom: 5px !important;
}

/* Отступ для всех пользователей одинаковый */
.messages-container > div:last-child {
    height: 70px !important;
}

/* Для мобильных */
@media (max-width: 768px) {
    .messages-container > div:last-child {
        height: 80px !important;
    }
}
            
/* Принудительный отступ для последнего сообщения */
.messages-list .message:last-child {
    margin-bottom: 30px !important;
}

.messages-container {
    padding-bottom: 20px !important;
}

/* ========== ПОИСК ПОЛЬЗОВАТЕЛЕЙ ========== */
.search-results {
    background: white;
    border-bottom: 1px solid #ddd;
    max-height: 300px;
    overflow-y: auto;
    padding: 10px 15px;
    display: none;
}

.search-results-header {
    font-size: 12px;
    color: #666;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.search-results-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.search-user-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s;
}

.search-user-item:hover {
    background: #f5f5f5;
}

.search-user-item img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #0088cc;
}

.search-user-info {
    flex: 1;
}

.search-user-name {
    font-weight: 600;
    font-size: 15px;
    color: #333;
    margin-bottom: 2px;
}

.search-user-status {
    font-size: 12px;
    color: #666;
}

/* Тёмная тема */
body.dark-theme .search-results {
    background: #2d2d2d;
    border-color: #3d3d3d;
}

body.dark-theme .search-results-header {
    color: #b0b3b8;
}

body.dark-theme .search-user-item:hover {
    background: #3d3d3d;
}

body.dark-theme .search-user-name {
    color: white;
}

body.dark-theme .search-user-status {
    color: #b0b3b8;
}

/* Адаптация для мобильных */
@media (max-width: 768px) {
    .search-results {
        max-height: 250px;
        padding: 8px 12px;
    }
    
    .search-user-item img {
        width: 35px;
        height: 35px;
    }
    
    .search-user-name {
        font-size: 14px;
    }
}
            
/* Индикатор удаления */
.delete-indicator {
    position: absolute;
    right: -80px;
    top: 0;
    bottom: 0;
    width: 80px;
    background: #ff4444;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: right 0.2s;
}

.chat-item {
    position: relative;
    overflow: hidden;
    transition: transform 0.2s, opacity 0.3s;
}

.chat-item.swiped .delete-indicator {
    right: 0;
}
            
/* Кнопка очистки поиска */
.clear-search {
    position: absolute;
    right: 25px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #999;
    cursor: pointer;
    font-size: 18px;
    padding: 5px 10px;
    z-index: 2;
    display: none;
}

.clear-search:hover {
    color: #ff4444;
}

.search-box {
    position: relative;
}
            
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Левая панель -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="app-logo">
                    <i class="fas fa-paper-plane"></i>
                    <span>KiloGram</span>
                </div>
                
                <!-- Меню с тремя точками -->
                <div class="sidebar-menu">
                    <button onclick="toggleMenu()" class="menu-btn" title="Меню">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    
                    <!-- Выпадающее меню -->
                    <div class="dropdown-menu" id="dropdownMenu" style="display: none;">
                        <div class="menu-item" onclick="toggleTheme()">
                            <i class="fas fa-moon"></i>
                            <span>Тёмная тема</span>
                        </div>
                        <div class="menu-item" onclick="window.location.href='settings.php'">
                            <i class="fas fa-cog"></i>
                            <span>Настройки</span>
                        </div>
                        <div class="menu-item" onclick="createGroup()">
                            <i class="fas fa-users"></i>
                            <span>Создать группу</span>
                        </div>
                        <div class="menu-item" onclick="showFavorites()">
                            <i class="fas fa-star"></i>
                            <span>Избранное</span>
                        </div>
                        <div class="menu-divider"></div>
                        <div class="menu-item" onclick="window.location.href='logout.php'">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Выйти</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Поиск -->
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Поиск пользователей..." id="userSearch">
                <button class="clear-search" id="clearSearch" style="display: none;">✕</button>
            </div>

            <!-- Контейнер для результатов поиска пользователей -->
            <div class="search-results" id="searchResults" style="display: none;">
                <div class="search-results-header">Найденные пользователи:</div>
                <div class="search-results-list" id="searchUsersList"></div>
            </div>
            
            <!-- Список чатов -->
            <div class="chats-list" id="chatsList">
                Загрузка...
            </div>
        </div>

        <!-- Правая область -->
        <div class="chat-area">
            <div class="no-chat-selected" id="noChatSelected">
                Выберите чат
            </div>

            <!-- Интерфейс чата -->
            <div class="chat-interface" id="chatInterface">
                <!-- Шапка чата -->
                <div class="chat-header">
                    <div class="chat-header-info" onclick="showUserInfo()">
                        <button class="mobile-back-btn" id="mobileBackBtn" onclick="mobileBack(event)">←</button>
                        <img src="" alt="Avatar" class="avatar" id="chatAvatar">
                        <div>
                            <h3 id="chatName"></h3>
                            <span class="user-status" id="chatStatus"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Сообщения -->
                <div class="messages-container" id="messagesContainer">
                    <div class="messages-list" id="messagesList"></div>
                    <!-- Увеличиваем отступ для последнего сообщения -->
                    <div style="height: 40px; width: 100%; flex-shrink: 0;"></div>
                </div>
                
                <!-- Поле ввода -->
                <div class="message-input-container">
                    <form onsubmit="return sendMessage(event)" class="message-form">
                        <button type="button" onclick="showFileMenu()" class="attach-btn" title="Прикрепить файл">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <input type="text" 
                               placeholder="Написать сообщение..." 
                               id="messageInput" 
                               autocomplete="off">
                        <button type="submit" class="send-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Меню выбора файлов -->
    <div id="fileTypeMenu" class="file-menu" style="display: none;">
        <div class="file-menu-content">
            <div class="file-menu-header">
                <h3>Выберите тип файла</h3>
                <button onclick="closeFileMenu()" class="file-menu-close">✕</button>
            </div>
            <div class="file-menu-grid">
                <div class="file-menu-item" onclick="openFileDialog('image/*', 'image')">
                    <i class="fas fa-image"></i>
                    <span>Фото</span>
                </div>
                <div class="file-menu-item" onclick="openFileDialog('video/*', 'video')">
                    <i class="fas fa-video"></i>
                    <span>Видео</span>
                </div>
                <div class="file-menu-item" onclick="openFileDialog('audio/*', 'audio')">
                    <i class="fas fa-music"></i>
                    <span>Аудио</span>
                </div>
                <div class="file-menu-item" onclick="openFileDialog('.pdf,.doc,.docx,.xls,.xlsx,.txt', 'file')">
                    <i class="fas fa-file"></i>
                    <span>Документ</span>
                </div>
                <div class="file-menu-item" onclick="openFileDialog('*/*', 'file')">
                    <i class="fas fa-archive"></i>
                    <span>Любой файл</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно нового чата -->
    <div id="newChatModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Новый чат</h2>
                <button onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <input type="text" placeholder="Поиск пользователей..." id="userSearch" style="width:100%; padding:10px; margin-bottom:10px; border-radius:8px; border:1px solid #ddd;">
                <div class="users-list" id="usersList"></div>
            </div>
        </div>
    </div>

    <!-- Индикатор отправки -->
    <div id="sendingIndicator" class="sending-indicator">
        <i class="fas fa-spinner fa-spin"></i> Отправка...
    </div>

    <script>
        var currentUser = <?php echo json_encode($currentUser); ?>;
    </script>
    <script src="script.js"></script>
</body>
</html>