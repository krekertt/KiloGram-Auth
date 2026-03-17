// ========== ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ==========
let currentChat = null;
let chats = [];
let messages = [];

// Переменные для файлов
let selectedFile = null;
let selectedFileType = null;

// Переменные для автообновления
let updateInterval = null;
let isUpdating = false;

// ========== ИНИЦИАЛИЗАЦИЯ ==========
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ script.js загружен');
    console.log('👤 Текущий пользователь:', currentUser);
    
    // Загружаем чаты и тему
    loadChats();
    loadTheme();
    
    // Поиск по чатам
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        console.log('🔍 searchInput найден');
        
        // Поиск при вводе
        searchInput.addEventListener('input', debounce(function(e) {
            const query = e.target.value.trim();
            console.log('🔍 Поиск чатов:', query);
            searchChats(query);
        }, 300));
        
        // Очистка при потере фокуса
        searchInput.addEventListener('blur', function() {
            setTimeout(() => {
                if (this.value.trim() === '') {
                    console.log('🔄 Очистка поиска чатов');
                    renderChatsList();
                }
            }, 200);
        });
        
        // Показываем/скрываем кнопку очистки
        const clearBtn = document.getElementById('clearSearch');
        if (clearBtn) {
            searchInput.addEventListener('input', function() {
                clearBtn.style.display = this.value ? 'block' : 'none';
            });
            
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                renderChatsList();
                clearBtn.style.display = 'none';
                searchInput.focus();
            });
        }
    } else {
        console.log('❌ searchInput не найден');
    }
    
    // Поиск пользователей
    const userSearch = document.getElementById('userSearch');
    if (userSearch) {
        console.log('👥 userSearch найден');
        
        userSearch.addEventListener('input', debounce(function(e) {
            const query = e.target.value.trim();
            console.log('👥 Поиск пользователей:', query);
            searchUsers(query);
        }, 300));
        
        // Очистка результатов при потере фокуса
        userSearch.addEventListener('blur', function() {
            setTimeout(() => {
                if (this.value.trim() === '') {
                    const resultsDiv = document.getElementById('searchResults');
                    if (resultsDiv) {
                        resultsDiv.style.display = 'none';
                    }
                }
            }, 200);
        });
    } else {
        console.log('❌ userSearch не найден');
    }
    
    // Кнопка назад на мобильных
    const backBtn = document.getElementById('mobileBackBtn');
    if (backBtn) {
        backBtn.onclick = mobileBack;
        console.log('🔙 mobileBackBtn найден');
    } else {
        console.log('❌ mobileBackBtn не найден');
    }
    
    // Проверяем размер экрана
    checkMobile();
    
    // Добавляем обработчик resize
    window.addEventListener('resize', checkMobile);
    
    console.log('✅ Инициализация завершена');
});

// ========== СУПЕР-ПРОКРУТКА ==========
function superScroll() {
    const container = document.getElementById('messagesContainer');
    if (!container) return;
    
    // Прокручиваем 20 раз с разными задержками
    for (let i = 0; i < 20; i++) {
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
            console.log(`📜 Супер-прокрутка #${i+1}`);
        }, i * 100);
    }
}

// ========== АДАПТИВНЫЙ ОТСТУП ==========
function setSpacerHeight() {
    const spacer = document.getElementById('messageSpacer');
    if (!spacer) return;
    
    // Определяем размер экрана
    const screenHeight = window.innerHeight;
    const inputHeight = document.querySelector('.message-input-container')?.offsetHeight || 60;
    
    // Для маленьких экранов (телефонов) - отступ меньше
    if (screenHeight < 700) {
        spacer.style.height = '50px';
        console.log('📱 Телефон: отступ 50px');
    } else {
        spacer.style.height = '70px';
        console.log('💻 ПК: отступ 70px');
    }
}

// Вызываем при загрузке и изменении размера
window.addEventListener('load', setSpacerHeight);
window.addEventListener('resize', setSpacerHeight);


// ========== ЧАТЫ ==========
function loadChats() {
    console.log('🔄 Загружаем чаты...');
    
    fetch('messages.php?action=get_chats')
        .then(response => response.json())
        .then(data => {
            console.log('📦 Данные чатов:', data);
            
            if (data.chats) {
                chats = data.chats;
                renderChatsList();
            } else {
                document.getElementById('chatsList').innerHTML = 
                    '<div class="no-chats">Ошибка: ' + (data.error || 'нет данных') + '</div>';
            }
        })
        .catch(error => {
            console.error('❌ Ошибка загрузки чатов:', error);
            document.getElementById('chatsList').innerHTML = 
                '<div class="no-chats">Ошибка загрузки</div>';
        });
}

function renderChatsList() {
    const chatsList = document.getElementById('chatsList');
    if (!chatsList) return;
    
    if (!chats || chats.length === 0) {
        chatsList.innerHTML = '<div class="no-chats">Нет чатов</div>';
        return;
    }
    
    chatsList.innerHTML = chats.map(chat => {
        const isActive = currentChat && currentChat.id === chat.id;
        return `
            <div class="chat-item ${isActive ? 'active' : ''}" 
                 onclick="selectChat(${chat.id})"
                 data-chat-id="${chat.id}"
                 style="position: relative; overflow: hidden;">
                <img src="avatars/${chat.avatar || 'default_chat.png'}?t=${Date.now()}" alt="" class="avatar" onerror="this.src='https://via.placeholder.com/45x45?text=Chat'">
                <div class="chat-info">
                    <div class="chat-name">${escapeHtml(chat.display_name || 'Чат')}</div>
                    <div class="last-message">${escapeHtml(chat.last_message || '')}</div>
                </div>
                <div class="chat-meta">
                    ${chat.unread_count ? `<span class="unread-badge">${chat.unread_count}</span>` : ''}
                    <span class="last-time">${formatTime(chat.last_message_time)}</span>
                </div>
                <!-- Индикатор удаления -->
                <div class="delete-indicator" style="position: absolute; right: -80px; top: 0; bottom: 0; width: 80px; background: #ff4444; color: white; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-trash"></i>
                </div>
            </div>
        `;
    }).join('');
    
    addSwipeListeners();
}


function searchChats(query) {
    const chatsList = document.getElementById('chatsList');
    if (!chatsList) return;
    
    // Если поиск пустой - показываем все чаты
    if (!query || query.trim() === '') {
        renderChatsList();
        return;
    }
    
    // Фильтруем чаты
    const filtered = chats.filter(chat => 
        chat.display_name && chat.display_name.toLowerCase().includes(query.toLowerCase())
    );
    
    if (filtered.length === 0) {
        chatsList.innerHTML = '<div class="no-chats">Ничего не найдено</div>';
    } else {
        chatsList.innerHTML = filtered.map(chat => `
            <div class="chat-item" onclick="selectChat(${chat.id})">
                <img src="avatars/${chat.avatar || 'default_chat.png'}?t=${Date.now()}" alt="" class="avatar" onerror="this.src='https://via.placeholder.com/45x45?text=Chat'">
                <div class="chat-info">
                    <div class="chat-name">${escapeHtml(chat.display_name)}</div>
                    <div class="last-message">${escapeHtml(chat.last_message || '')}</div>
                </div>
            </div>
        `).join('');
    }
}

// ========== ЧАТ ==========
function selectChat(chatId) {
    // Останавливаем автообновление предыдущего чата
    stopAutoUpdate();
    
    currentChat = chats.find(c => c.id == chatId);
    if (!currentChat) return;
    
    // Показываем интерфейс чата
    document.getElementById('noChatSelected').style.display = 'none';
    document.getElementById('chatInterface').style.display = 'flex';
    
    document.getElementById('chatAvatar').src = 'avatars/' + (currentChat.avatar || 'default_chat.png') + '?t=' + Date.now();
    document.getElementById('chatName').textContent = currentChat.display_name || 'Чат';
    document.getElementById('chatStatus').textContent = currentChat.status || 'онлайн';
    
    // Загружаем сообщения
    loadMessages(chatId);
    
    // Запускаем автообновление
    startAutoUpdate();
    
    // На мобильных скрываем список чатов
    if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.add('hide');
    }
}

function loadMessages(chatId) {
    fetch(`messages.php?action=get_messages&chat_id=${chatId}&limit=50`)
        .then(response => response.json())
        .then(data => {
            if (data.messages) {
                messages = data.messages; // новые сообщения будут первыми
                renderMessages();
                setTimeout(setSpacerHeight, 300);
            }
        });
}

function renderMessages() {
    const messagesList = document.getElementById('messagesList');
    if (!messagesList) {
        return;
    }
    
    if (!messages || messages.length === 0) {
        messagesList.innerHTML = '<div class="no-messages">Нет сообщений</div>';
        return;
    }
    
    let html = '';
    
    for (let i = 0; i < messages.length; i++) {
        const msg = messages[i];
        const isMyMessage = msg.user_id == currentUser.id;
        const timeStr = formatTime(msg.created_at);
        
        let contentHtml = '';
        
        switch (msg.message_type) {
            case 'image':
                contentHtml = `
                    <div onclick="window.open('${msg.file_path}', '_blank')">
                        <img src="${msg.thumbnail || msg.file_path}" alt="Изображение" style="max-width:200px; max-height:200px; border-radius:8px;">
                    </div>
                    ${msg.content ? `<div>${escapeHtml(msg.content)}</div>` : ''}
                `;
                break;
                
            case 'video':
                contentHtml = `
                    <div>
                        <video controls style="max-width:200px; max-height:200px;">
                            <source src="${msg.file_path}" type="${msg.mime_type || 'video/mp4'}">
                        </video>
                    </div>
                    ${msg.content ? `<div>${escapeHtml(msg.content)}</div>` : ''}
                `;
                break;
                
            case 'audio':
                contentHtml = `
                    <audio controls src="${msg.file_path}"></audio>
                `;
                break;
                
            default:
                contentHtml = escapeHtml(msg.content);
        }
        
        html += `
            <div class="message ${isMyMessage ? 'my-message' : 'other-message'}">
                ${!isMyMessage ? `<img src="avatars/${msg.avatar || 'default_avatar.png'}" class="message-avatar" style="width:35px; height:35px; border-radius:50%;">` : ''}
                <div class="message-content" style="background: ${isMyMessage ? '#0088cc' : '#f0f0f0'}; color: ${isMyMessage ? 'white' : 'black'}; padding: 10px; border-radius: 10px; max-width: 100%;">
                    ${!isMyMessage ? `<div style="font-size:12px; font-weight:bold; margin-bottom:2px;">${escapeHtml(msg.first_name || msg.username)}</div>` : ''}
                    <div>${contentHtml}</div>
                    <div style="font-size:10px; text-align:right; margin-top:4px; color:${isMyMessage ? 'rgba(255,255,255,0.7)' : '#999'}">${timeStr}</div>
                </div>
            </div>
        `;
    }
    
    messagesList.innerHTML = html;
    
    setTimeout(() => {
        const container = document.getElementById('messagesContainer');
        if (container) {
            container.scrollTop = 1000000;
            
            setTimeout(() => {
                container.scrollTop = container.scrollHeight;
            }, 500);
        }
    }, 300);
    setTimeout(setSpacerHeight, 300);
}

function sendMessage(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    console.log('📤 Функция sendMessage вызвана');
    
    const input = document.getElementById('messageInput');
    if (!input) {
        console.log('❌ Поле ввода не найдено');
        return false;
    }
    
    const messageText = input.value.trim();

    if (!messageText || !currentChat) {
        console.log('❌ Нет сообщения или чата');
        return false;
    }

    console.log('📤 Отправка:', messageText);

    // Создаём временное сообщение
    const tempMessage = {
        id: 'temp_' + Date.now(),
        user_id: currentUser.id,
        content: messageText,
        message_type: 'text',
        created_at: new Date().toISOString(),
        temp: true
    };

    messages.push(tempMessage);
    renderMessages();
    input.value = '';
    
    showSendingIndicator();
    
    // Отправляем на сервер
    fetch('messages.php?action=send_message', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            chat_id: currentChat.id,
            content: messageText
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('📦 Ответ сервера:', data);
        
        if (data.success) {
            // Заменяем временное сообщение на настоящее
            const index = messages.findIndex(m => m.id === tempMessage.id);
            if (index !== -1) {
                messages[index] = data.message;
            } else {
                messages.push(data.message);
            }
            renderMessages();
            
            // Принудительно перезагружаем все сообщения
            setTimeout(() => {
                loadMessages(currentChat.id);
                forceScrollToBottom();
            }, 500);
        } else {
            console.error('❌ Ошибка сервера:', data.error);
            // Удаляем временное сообщение
            const index = messages.findIndex(m => m.id === tempMessage.id);
            if (index !== -1) messages.splice(index, 1);
            renderMessages();
        }
        hideSendingIndicator();
    })
    .catch(error => {
        console.error('❌ Ошибка fetch:', error);
        hideSendingIndicator();
        const index = messages.findIndex(m => m.id === tempMessage.id);
        if (index !== -1) messages.splice(index, 1);
        renderMessages();
    });
    
    return false;
}

// ========== АВТООБНОВЛЕНИЕ ==========

function startAutoUpdate() {
    stopAutoUpdate();
    
    updateInterval = setInterval(() => {
        if (currentChat && !isUpdating) {
            isUpdating = true;
            
            fetch(`messages.php?action=get_messages&chat_id=${currentChat.id}&limit=50`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network error');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.messages) {
                        const lastNewId = data.messages[data.messages.length-1]?.id;
                        const lastCurrentId = messages.length > 0 ? messages[messages.length-1]?.id : 0;
                        
                        if (lastNewId > lastCurrentId) {
                            console.log('📨 Новые сообщения!');
                            messages = data.messages;
                            renderMessages();
                        }
                    }
                    isUpdating = false;
                })
                .catch(error => {
                    console.log('Ошибка обновления (можно игнорировать)');
                    isUpdating = false;
                });
        }
    }, 5000); // Увеличили до 5 секунд
}

function stopAutoUpdate() {
    if (updateInterval) {
        clearInterval(updateInterval);
        updateInterval = null;
    }
}

// ========== ПОИСК ПОЛЬЗОВАТЕЛЕЙ ==========
function searchUsers(query) {
    console.log('1️⃣ searchUsers вызвана с query:', query);
    
    if (query.length < 2) {
        console.log('2️⃣ Запрос слишком короткий, очищаем список');
        document.getElementById('usersList').innerHTML = '';
        return;
    }
    
    console.log('3️⃣ Отправляем запрос к API...');
    
    fetch(`messages.php?action=search_users&q=${encodeURIComponent(query)}`)
        .then(response => {
            console.log('4️⃣ Статус ответа:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('5️⃣ Данные от сервера:', data);
            
            if (data.users) {
                console.log('6️⃣ Найдено пользователей:', data.users.length);
                renderUsersList(data.users);
            } else {
                console.error('❌ Ошибка:', data.error);
                document.getElementById('usersList').innerHTML = '<div class="no-results">Ошибка поиска</div>';
            }
        })
        .catch(error => {
            console.error('❌ Ошибка fetch:', error);
            document.getElementById('usersList').innerHTML = '<div class="no-results">Ошибка соединения</div>';
        });
}

function renderUsersList(users) {
    const resultsDiv = document.getElementById('searchResults');
    const usersList = document.getElementById('searchUsersList');
    
    if (!resultsDiv || !usersList) {
        console.log('❌ searchResults или searchUsersList не найдены');
        return;
    }
    
    console.log('📦 Отображаем пользователей:', users);
    
    if (!users || users.length === 0) {
        resultsDiv.style.display = 'none';
        return;
    }
    
    let html = '';
    for (let i = 0; i < users.length; i++) {
        const user = users[i];
        html += `
            <div class="search-user-item" onclick="selectUser(${user.id})">
                <img src="avatars/${user.avatar || 'default_avatar.png'}?t=${Date.now()}" alt="">
                <div class="search-user-info">
                    <div class="search-user-name">${escapeHtml(user.first_name || user.username)}</div>
                    <div class="search-user-status">${user.status === 'online' ? '● Онлайн' : '○ Офлайн'}</div>
                </div>
            </div>
        `;
    }
    
    usersList.innerHTML = html;
    resultsDiv.style.display = 'block';
}

function selectUser(userId) {
    createChat([userId]);
    
    // Очищаем поле поиска и скрываем результаты
    const searchInput = document.getElementById('userSearch');
    const searchResults = document.getElementById('searchResults');
    
    if (searchInput) {
        searchInput.value = '';
    }
    if (searchResults) {
        searchResults.style.display = 'none';
    }
}

// ========== СОЗДАНИЕ ЧАТА ==========
function createChat(userIds) {
    fetch('messages.php?action=create_chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            type: 'private',
            user_ids: userIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadChats(); // Перезагружаем список чатов
            selectChat(data.chat_id); // Открываем новый чат
        } else {
            alert('Ошибка: ' + (data.error || 'Не удалось создать чат'));
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        alert('Ошибка при создании чата');
    });
}

// ========== МОДАЛЬНЫЕ ОКНА ==========
function showNewChatModal() {
    const modal = document.getElementById('newChatModal');
    if (modal) {
        modal.classList.add('active');
        document.getElementById('userSearch').focus();
    }
}

function closeModal() {
    const modal = document.getElementById('newChatModal');
    if (modal) {
        modal.classList.remove('active');
        document.getElementById('usersList').innerHTML = '';
        document.getElementById('userSearch').value = '';
    }
}

// ========== ТЕМА ==========
function toggleTheme() {
    document.body.classList.toggle('dark-theme');
    const isDark = document.body.classList.contains('dark-theme');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
}

function loadTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
    }
}

// ========== МОБИЛЬНЫЕ ФУНКЦИИ ==========
function mobileBack(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    stopAutoUpdate();
    document.getElementById('sidebar').classList.remove('hide');
    document.getElementById('noChatSelected').style.display = 'flex';
    document.getElementById('chatInterface').style.display = 'none';
    document.getElementById('messagesList').innerHTML = '';
    currentChat = null;
}

function hideChat() {
    stopAutoUpdate();
    document.getElementById('noChatSelected').style.display = 'flex';
    document.getElementById('chatInterface').style.display = 'none';
    document.getElementById('messagesList').innerHTML = '';
    currentChat = null;
}

function checkMobile() {
    if (window.innerWidth > 768) {
        document.getElementById('sidebar').classList.remove('hide');
    }
}

// ========== МЕНЮ ВЫБОРА ФАЙЛОВ ==========
window.showFileMenu = function() {
    if (!currentChat) {
        alert('Сначала выберите чат');
        return;
    }
    document.getElementById('fileTypeMenu').style.display = 'flex';
};

window.closeFileMenu = function() {
    document.getElementById('fileTypeMenu').style.display = 'none';
};

window.openFileDialog = function(accept, type) {
    closeFileMenu();
    
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = accept;
    
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const text = document.getElementById('messageInput').value.trim();
        sendFile(file, type, text);
        document.getElementById('messageInput').value = '';
    };
    
    input.click();
};

function sendFile(file, type, text = '') {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('chat_id', currentChat.id);
    formData.append('file_type', type);
    formData.append('text', text);
    
    showSendingIndicator();
    
    fetch('messages.php?action=send_message', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messages.push(data.message);
            renderMessages();
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
        hideSendingIndicator();
    })
    .catch(error => {
        console.error('Ошибка:', error);
        alert('Ошибка при отправке файла');
        hideSendingIndicator();
    });
}

// ========== МЕНЮ ==========
window.toggleMenu = function() {
    const menu = document.getElementById('dropdownMenu');
    if (menu) {
        if (menu.style.display === 'none' || menu.style.display === '') {
            menu.style.display = 'block';
            // Закрываем при клике вне меню
            setTimeout(() => {
                document.addEventListener('click', closeMenuOnClickOutside);
            }, 0);
        } else {
            menu.style.display = 'none';
        }
    }
};

function closeMenuOnClickOutside(event) {
    const menu = document.getElementById('dropdownMenu');
    const btn = document.querySelector('.menu-btn');
    
    if (!menu.contains(event.target) && !btn.contains(event.target)) {
        menu.style.display = 'none';
        document.removeEventListener('click', closeMenuOnClickOutside);
    }
}

// Заглушки для новых функций
window.showSettings = function() {
    alert('Настройки (скоро будут)');
    toggleMenu();
};

window.createGroup = function() {
    alert('Создание группы (скоро будет)');
    toggleMenu();
};

window.showFavorites = function() {
    alert('Избранное (скоро будет)');
    toggleMenu();
};

// ========== ИНДИКАТОРЫ ==========
function showSendingIndicator() {
    const indicator = document.getElementById('sendingIndicator');
    if (indicator) {
        indicator.style.display = 'flex';
    }
}

function hideSendingIndicator() {
    const indicator = document.getElementById('sendingIndicator');
    if (indicator) {
        indicator.style.display = 'none';
    }
}

// ========== ВСПОМОГАТЕЛЬНЫЕ ==========
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(timestamp) {
    if (!timestamp) return '';
    
    const date = new Date(timestamp);
    const now = new Date();
    
    // Добавляем 3 часа для МСК
    const mskDate = new Date(date.getTime() + (3 * 60 * 60 * 1000));
    const mskNow = new Date(now.getTime() + (3 * 60 * 60 * 1000));
    
    // Разница в днях
    const diffTime = mskNow - mskDate;
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) {
        // Сегодня - показываем время
        return mskDate.toLocaleTimeString('ru-RU', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: false 
        });
    } else if (diffDays === 1) {
        // Вчера
        return 'вчера';
    } else {
        // Старые сообщения - показываем дату
        return mskDate.toLocaleDateString('ru-RU', { 
            day: '2-digit', 
            month: '2-digit' 
        });
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if (container) {
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
        }, 100);
    }
}

// ========== ПРИНУДИТЕЛЬНАЯ ПРОКРУТКА ВНИЗ ==========
function scrollToLastMessage() {
    const container = document.getElementById('messagesContainer');
    if (!container) return;
    
    // Множественные попытки с задержкой
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
        console.log('📜 Прокрутка 1');
    }, 100);
    
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
        console.log('📜 Прокрутка 2');
    }, 300);
    
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
        console.log('📜 Прокрутка 3');
    }, 500);
    
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
        console.log('📜 Прокрутка 4');
    }, 1000);
}

// ========== ИНФОРМАЦИЯ О ПОЛЬЗОВАТЕЛЕ ==========
window.showUserInfo = function() {
    if (currentChat) {
        alert('Информация о пользователе: ' + currentChat.display_name);
    }
};

// ========== УДАЛЕНИЕ ЧАТА СВАЙПОМ ==========
let touchStartX = 0;
let touchEndX = 0;
let currentSwipeItem = null;

function handleTouchStart(e) {
    touchStartX = e.touches[0].clientX;
    currentSwipeItem = e.currentTarget;
}

function handleTouchMove(e) {
    if (!currentSwipeItem) return;
    
    const touch = e.touches[0];
    const diff = touch.clientX - touchStartX;
    
    // Свайп вправо (отрицательное значение)
    if (diff < -50) {
        currentSwipeItem.style.transform = 'translateX(-80px)';
        currentSwipeItem.style.transition = 'transform 0.2s';
    } else {
        currentSwipeItem.style.transform = 'translateX(0)';
    }
}

function handleTouchEnd(e) {
    if (!currentSwipeItem) return;
    
    const touchEndX = e.changedTouches[0].clientX;
    const diff = touchEndX - touchStartX;
    
    // Если свайп вправо достаточно большой
    if (diff < -70) {
        const chatId = currentSwipeItem.getAttribute('data-chat-id');
        deleteChat(chatId, currentSwipeItem);
    } else {
        // Возвращаем на место
        currentSwipeItem.style.transform = 'translateX(0)';
    }
    
    currentSwipeItem = null;
}


// Добавляем обработчики к каждому чату
function addSwipeListeners() {
    document.querySelectorAll('.chat-item').forEach(item => {
        item.addEventListener('touchstart', handleTouchStart);
        item.addEventListener('touchmove', handleTouchMove);
        item.addEventListener('touchend', handleTouchEnd);
    });
}

function deleteChat(chatId, element) {
    if (!confirm('Удалить чат из списка?')) {
        element.style.transform = 'translateX(0)';
        return;
    }
    
    console.log('🗑️ Удаляем чат ID:', chatId);
    
    // Отправляем запрос на сервер
    fetch('messages.php?action=delete_chat', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            chat_id: chatId  // ← здесь должен быть chatId, а не что-то другое
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('📦 Ответ:', data);
        
        if (data.success) {
            // Анимация удаления
            element.style.transform = 'translateX(-100%)';
            element.style.opacity = '0';
            element.style.transition = 'all 0.3s';
            
            setTimeout(() => {
                // Удаляем из массива
                chats = chats.filter(chat => chat.id != chatId);
                
                if (currentChat && currentChat.id == chatId) {
                    hideChat();
                }
                
                renderChatsList();
            }, 300);
        } else {
            alert('Ошибка: ' + (data.error || 'Не удалось удалить чат'));
            element.style.transform = 'translateX(0)';
        }
    })
    .catch(error => {
        console.error('❌ Ошибка:', error);
        element.style.transform = 'translateX(0)';
    });
}

// ========== ПРИВЯЗКА К WINDOW ==========
window.selectChat = selectChat;
window.sendMessage = sendMessage;
window.toggleTheme = toggleTheme;
window.showNewChatModal = showNewChatModal;
window.closeModal = closeModal;
window.mobileBack = mobileBack;
window.showFileMenu = showFileMenu;
window.closeFileMenu = closeFileMenu;
window.showUserInfo = showUserInfo;
window.searchUsers = searchUsers;
window.selectUser = selectUser;

// Следим за размером окна
window.addEventListener('resize', checkMobile);

console.log('✅ Все функции готовы');