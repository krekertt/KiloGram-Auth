<?php

// Этот файл подключается в index.php

if (!isset($currentUser)) {

    return;

}

?>

<div class="app-container">

    <!-- Левая боковая панель -->

    <div class="sidebar">

        <div class="sidebar-header">

            <div class="user-info" onclick="window.location.href='profile.php'">

                <img src="avatars/<?php echo htmlspecialchars($currentUser['avatar']); ?>" alt="Avatar" class="avatar" id="currentUserAvatar">

                <span id="currentUserName"><?php echo htmlspecialchars($currentUser['first_name'] ?: $currentUser['username']); ?></span>

            </div>

            <div class="sidebar-actions">

                <button onclick="showNewChatModal()" title="Новый чат">

                    <i class="fas fa-edit"></i>

                </button>

                <button onclick="toggleTheme()" title="Тема">

                    <i class="fas fa-moon"></i>

                </button>

                <button onclick="window.location.href='logout.php'" title="Выйти">

                    <i class="fas fa-sign-out-alt"></i>

                </button>

            </div>

        </div>

        

        <div class="search-box">

            <i class="fas fa-search"></i>

            <input type="text" placeholder="Поиск..." id="searchInput" onkeyup="searchChats(this.value)">

        </div>

        

        <div class="chats-list" id="chatsList">

            <div class="loading">Загрузка чатов...</div>

        </div>

    </div>

    <!-- Основная область чата -->

    <div class="chat-area" id="chatArea">

        <div class="no-chat-selected">

            <i class="fas fa-comment-dots"></i>

            <h2>Выберите чат</h2>

            <p>Начните общение с другом или создайте новую группу</p>

        </div>

    </div>

    <!-- Правая боковая панель (информация о чате) -->

    <div class="right-sidebar" id="rightSidebar">

        <!-- Будет заполняться при выборе чата -->

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

            <div class="search-users">

                <input type="text" placeholder="Введите имя пользователя..." id="userSearch" onkeyup="searchUsers(this.value)">

            </div>

            <div class="users-list" id="usersList">

                <!-- Список пользователей -->

            </div>

        </div>

    </div>

</div>

<!-- Модальное окно информации о чате -->

<div id="chatInfoModal" class="modal">

    <div class="modal-content">

        <div class="modal-header">

            <h2>Информация о чате</h2>

            <button onclick="closeInfoModal()"><i class="fas fa-times"></i></button>

        </div>

        <div class="modal-body" id="chatInfoContent">

            <!-- Информация о чате -->

        </div>

    </div>

</div>
