import telebot
from telebot.types import InlineKeyboardMarkup, InlineKeyboardButton, LabeledPrice, PreCheckoutQuery
import sqlite3
import random
import string
import requests
import json
from datetime import datetime, timedelta
import time
import logging
import os
from threading import Thread

# ========== НАСТРОЙКИ ==========

# Токены (ЗАМЕНИТЬ НА СВОИ)
TOKEN = '8520227149:AAESRVJc5jKC_6PqxpFTWuiMXA__WmTQ_LI'  # Токен от @BotFather
PAYMENT_TOKEN = 'TEST_PROVIDER_TOKEN'  # Токен для звёзд от BotFather
API_URL = 'https://kilogram.atwebpages.com/api.php'  # URL твоего сайта

# Администраторы (твой ID)
ADMIN_IDS = [1726423121]

# Настройки логирования
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('bot.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Инициализация бота
bot = telebot.TeleBot(TOKEN)

# ========== БАЗА ДАННЫХ ==========

class Database:
    def __init__(self, db_name='telegram_bot.db'):
        self.conn = sqlite3.connect(db_name, check_same_thread=False)
        self.cursor = self.conn.cursor()
        self.create_tables()
    
    def create_tables(self):
        """Создание всех таблиц"""
        
        # Пользователи
        self.cursor.execute('''
        CREATE TABLE IF NOT EXISTS users (
            chat_id INTEGER PRIMARY KEY,
            username TEXT,
            first_name TEXT,
            last_name TEXT,
            stars_balance INTEGER DEFAULT 0,
            is_banned INTEGER DEFAULT 0,
            ban_reason TEXT,
            ban_until DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_active DATETIME
        )
        ''')
        
        # Номера
        self.cursor.execute('''
        CREATE TABLE IF NOT EXISTS numbers (
            phone_number TEXT PRIMARY KEY,
            chat_id INTEGER,
            type TEXT,
            price_stars INTEGER,
            expires_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chat_id) REFERENCES users(chat_id)
        )
        ''')
        
        # Транзакции
        self.cursor.execute('''
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id INTEGER,
            amount_stars INTEGER,
            description TEXT,
            status TEXT,
            admin_id INTEGER,
            telegram_payment_id TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chat_id) REFERENCES users(chat_id)
        )
        ''')
        
        # Баны
        self.cursor.execute('''
        CREATE TABLE IF NOT EXISTS bans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id INTEGER,
            admin_id INTEGER,
            ban_type TEXT,
            reason TEXT,
            duration TEXT,
            expires_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chat_id) REFERENCES users(chat_id),
            FOREIGN KEY (admin_id) REFERENCES users(chat_id)
        )
        ''')
        
        # Настройки
        self.cursor.execute('''
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT,
            description TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        ''')
        
        # Логи админов
        self.cursor.execute('''
        CREATE TABLE IF NOT EXISTS admin_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER,
            action TEXT,
            target_id INTEGER,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(chat_id)
        )
        ''')
        
        self.conn.commit()
        logger.info("База данных инициализирована")
    
    # ===== ПОЛЬЗОВАТЕЛИ =====
    
    def get_user(self, chat_id):
        """Получить пользователя по ID"""
        self.cursor.execute("SELECT * FROM users WHERE chat_id = ?", (chat_id,))
        return self.cursor.fetchone()
    
    def create_user(self, chat_id, username=None, first_name=None, last_name=None):
        """Создать нового пользователя"""
        self.cursor.execute('''
            INSERT OR IGNORE INTO users (chat_id, username, first_name, last_name, stars_balance, created_at, last_active)
            VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ''', (chat_id, username, first_name, last_name))
        self.conn.commit()
        logger.info(f"Новый пользователь: {chat_id}")
    
    def update_last_active(self, chat_id):
        """Обновить время последней активности"""
        self.cursor.execute("UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE chat_id = ?", (chat_id,))
        self.conn.commit()
    
    # ===== БАНЫ =====
    
    def check_ban(self, chat_id):
        """Проверить, забанен ли пользователь"""
        self.cursor.execute("SELECT is_banned, ban_until FROM users WHERE chat_id = ?", (chat_id,))
        result = self.cursor.fetchone()
        
        if not result:
            return False
        
        is_banned, ban_until = result
        
        if is_banned and ban_until and ban_until != 'permanent':
            try:
                if datetime.now() > datetime.strptime(ban_until, '%Y-%m-%d %H:%M:%S'):
                    # Снимаем бан если время истекло
                    self.cursor.execute("UPDATE users SET is_banned = 0, ban_until = NULL WHERE chat_id = ?", (chat_id,))
                    self.conn.commit()
                    return False
            except:
                pass
        
        return bool(is_banned)
    
    def ban_user(self, chat_id, admin_id, reason, duration='permanent', hours=None):
        """Забанить пользователя"""
        if duration == 'permanent':
            self.cursor.execute('''
                UPDATE users SET is_banned = 1, ban_reason = ?, ban_until = 'permanent' 
                WHERE chat_id = ?
            ''', (reason, chat_id))
            
            self.cursor.execute('''
                INSERT INTO bans (chat_id, admin_id, ban_type, reason, duration)
                VALUES (?, ?, 'permanent', ?, 'permanent')
            ''', (chat_id, admin_id, reason))
        else:
            ban_until = datetime.now() + timedelta(hours=hours)
            ban_until_str = ban_until.strftime('%Y-%m-%d %H:%M:%S')
            
            self.cursor.execute('''
                UPDATE users SET is_banned = 1, ban_reason = ?, ban_until = ? 
                WHERE chat_id = ?
            ''', (reason, ban_until_str, chat_id))
            
            self.cursor.execute('''
                INSERT INTO bans (chat_id, admin_id, ban_type, reason, duration, expires_at)
                VALUES (?, ?, 'temporary', ?, ?, ?)
            ''', (chat_id, admin_id, reason, f"{hours} часов", ban_until_str))
        
        self.conn.commit()
        logger.info(f"Пользователь {chat_id} забанен админом {admin_id}: {reason}")
    
    def unban_user(self, chat_id):
        """Разбанить пользователя"""
        self.cursor.execute("UPDATE users SET is_banned = 0, ban_reason = NULL, ban_until = NULL WHERE chat_id = ?", (chat_id,))
        self.conn.commit()
        logger.info(f"Пользователь {chat_id} разбанен")
    
    # ===== ЗВЁЗДЫ =====
    
    def get_stars_balance(self, chat_id):
        """Получить баланс звёзд"""
        self.cursor.execute("SELECT stars_balance FROM users WHERE chat_id = ?", (chat_id,))
        result = self.cursor.fetchone()
        return result[0] if result else 0
    
    def add_stars(self, chat_id, amount, admin_id=None, description="Пополнение"):
        """Добавить звёзды пользователю"""
        self.cursor.execute("UPDATE users SET stars_balance = stars_balance + ? WHERE chat_id = ?", (amount, chat_id))
        self.cursor.execute('''
            INSERT INTO transactions (chat_id, amount_stars, description, status, admin_id)
            VALUES (?, ?, ?, 'completed', ?)
        ''', (chat_id, amount, description, admin_id))
        self.conn.commit()
        logger.info(f"Добавлено {amount}⭐ пользователю {chat_id}")
    
    def set_stars_balance(self, chat_id, amount, admin_id=None):
        """Установить баланс звёзд"""
        self.cursor.execute("UPDATE users SET stars_balance = ? WHERE chat_id = ?", (amount, chat_id))
        self.cursor.execute('''
            INSERT INTO transactions (chat_id, amount_stars, description, status, admin_id)
            VALUES (?, ?, 'Баланс установлен администратором', 'admin_set', ?)
        ''', (chat_id, amount, admin_id))
        self.conn.commit()
        logger.info(f"Баланс пользователя {chat_id} установлен на {amount}⭐")
    
    # ===== НОМЕРА =====
    
    def add_number(self, chat_id, phone_number, number_type, price=0):
        """Добавить номер пользователю"""
        self.cursor.execute('''
            INSERT INTO numbers (phone_number, chat_id, type, price_stars)
            VALUES (?, ?, ?, ?)
        ''', (phone_number, chat_id, number_type, price))
        self.conn.commit()
        logger.info(f"Номер {phone_number} выдан пользователю {chat_id}")
    
    def get_user_numbers(self, chat_id):
        """Получить все номера пользователя"""
        self.cursor.execute("SELECT * FROM numbers WHERE chat_id = ?", (chat_id,))
        return self.cursor.fetchall()
    
    def is_number_available(self, phone_number):
        """Проверить, свободен ли номер"""
        self.cursor.execute("SELECT 1 FROM numbers WHERE phone_number = ?", (phone_number,))
        return not self.cursor.fetchone()
    
    # ===== СТАТИСТИКА =====
    
    def get_stats(self):
        """Получить общую статистику"""
        stats = {}
        
        # Пользователи
        self.cursor.execute("SELECT COUNT(*) FROM users")
        stats['total_users'] = self.cursor.fetchone()[0]
        
        self.cursor.execute("SELECT COUNT(*) FROM users WHERE is_banned = 1")
        stats['banned_users'] = self.cursor.fetchone()[0]
        
        self.cursor.execute("SELECT COUNT(*) FROM users WHERE date(created_at) = date('now')")
        stats['new_users_today'] = self.cursor.fetchone()[0]
        
        # Номера
        self.cursor.execute("SELECT COUNT(*) FROM numbers")
        stats['total_numbers'] = self.cursor.fetchone()[0]
        
        self.cursor.execute("SELECT COUNT(*) FROM numbers WHERE type='free'")
        stats['free_numbers'] = self.cursor.fetchone()[0]
        
        self.cursor.execute("SELECT COUNT(*) FROM numbers WHERE type LIKE 'premium%'")
        stats['premium_numbers'] = self.cursor.fetchone()[0]
        
        # Транзакции
        self.cursor.execute("SELECT COALESCE(SUM(amount_stars), 0) FROM transactions WHERE amount_stars > 0 AND status='completed'")
        stats['total_stars_purchased'] = self.cursor.fetchone()[0]
        
        self.cursor.execute("SELECT COALESCE(SUM(amount_stars), 0) FROM transactions WHERE amount_stars < 0 AND status='completed'")
        stats['total_stars_spent'] = abs(self.cursor.fetchone()[0])
        
        self.cursor.execute("SELECT COUNT(*) FROM transactions WHERE status='completed'")
        stats['total_transactions'] = self.cursor.fetchone()[0]
        
        self.cursor.execute("SELECT COUNT(*) FROM transactions WHERE date(created_at) = date('now')")
        stats['transactions_today'] = self.cursor.fetchone()[0]
        
        return stats

# Инициализация БД
db = Database()

# ========== ДЕКОРАТОРЫ ==========

def admin_required(func):
    """Декоратор для админских функций"""
    def wrapper(message, *args, **kwargs):
        if message.chat.id not in ADMIN_IDS:
            bot.reply_to(message, "⛔ Доступ запрещён. Эта команда только для администраторов.")
            return
        return func(message, *args, **kwargs)
    return wrapper

def admin_callback_required(func):
    """Декоратор для админских callback'ов"""
    def wrapper(call, *args, **kwargs):
        if call.message.chat.id not in ADMIN_IDS:
            bot.answer_callback_query(call.id, "⛔ Доступ запрещён", show_alert=True)
            return
        return func(call, *args, **kwargs)
    return wrapper

def check_ban_decorator(func):
    """Декоратор для проверки бана"""
    def wrapper(message, *args, **kwargs):
        if db.check_ban(message.chat.id):
            bot.reply_to(message, "⛔ Вы забанены. Доступ запрещён.")
            return
        db.update_last_active(message.chat.id)
        return func(message, *args, **kwargs)
    return wrapper

def check_ban_callback(func):
    """Декоратор для проверки бана в callback'ах"""
    def wrapper(call, *args, **kwargs):
        if db.check_ban(call.message.chat.id):
            bot.answer_callback_query(call.id, "⛔ Вы забанены", show_alert=True)
            return
        db.update_last_active(call.message.chat.id)
        return func(call, *args, **kwargs)
    return wrapper

# ========== ОБЩИЕ ФУНКЦИИ ==========

def generate_phone_number(prefix, length):
    """Генерация случайного номера"""
    number = prefix
    for _ in range(length - len(prefix)):
        number += str(random.randint(0, 9))
    return number

def send_to_site(chat_id, phone_number, number_type):
    """Отправка номера на основной сайт"""
    try:
        data = {
            'action': 'register_phone',
            'chat_id': chat_id,
            'phone': phone_number,
            'type': number_type
        }
        
        response = requests.post(API_URL, json=data, timeout=5)
        if response.status_code == 200:
            logger.info(f"✅ Номер {phone_number} отправлен на сайт")
        else:
            logger.error(f"❌ Ошибка отправки: {response.status_code}")
    except Exception as e:
        logger.error(f"❌ Ошибка отправки на сайт: {e}")

# ========== КОМАНДЫ ==========

@bot.message_handler(commands=['start'])
@check_ban_decorator
def start(message):
    chat_id = message.chat.id
    username = message.from_user.username
    first_name = message.from_user.first_name
    last_name = message.from_user.last_name
    
    db.create_user(chat_id, username, first_name, last_name)
    
    if chat_id in ADMIN_IDS:
        show_admin_main_menu(chat_id)
    else:
        show_main_menu(chat_id)

@bot.message_handler(commands=['admin'])
@admin_required
def admin_command(message):
    show_admin_main_menu(message.chat.id)

@bot.message_handler(commands=['help'])
@check_ban_decorator
def help_command(message):
    show_help(message.chat.id)

# ========== ПОЛЬЗОВАТЕЛЬСКИЕ МЕНЮ ==========

def show_main_menu(chat_id):
    """Главное меню пользователя"""
    stars = db.get_stars_balance(chat_id)
    
    markup = InlineKeyboardMarkup(row_width=2)
    markup.add(
        InlineKeyboardButton("📱 Бесплатный номер (+1)", callback_data="free_number"),
        InlineKeyboardButton("💎 Премиум номер (+888)", callback_data="premium_menu")
    )
    markup.add(
        InlineKeyboardButton("⭐ Пополнить звёзды", callback_data="buy_stars"),
        InlineKeyboardButton("📋 Мои номера", callback_data="my_numbers")
    )
    markup.add(
        InlineKeyboardButton(f"💰 Баланс: {stars} ⭐", callback_data="balance"),
        InlineKeyboardButton("❓ Помощь", callback_data="help")
    )
    
    bot.send_message(
        chat_id,
        "🌟 *KiloGram Bot*\n\n"
        "Выберите действие:",
        parse_mode="Markdown",
        reply_markup=markup
    )

def show_premium_menu(chat_id, message_id=None):
    """Меню выбора премиум номера"""
    stars = db.get_stars_balance(chat_id)
    
    markup = InlineKeyboardMarkup(row_width=2)
    markup.add(
        InlineKeyboardButton("🎲 Случайный длинный (5⭐)", callback_data="premium_random"),
        InlineKeyboardButton("✏️ Выбрать короткий (10⭐)", callback_data="premium_custom")
    )
    markup.add(
        InlineKeyboardButton("◀️ Назад", callback_data="back_to_main")
    )
    
    text = f"💎 *Премиум номера +888*\n\n" \
           f"• Длинный номер — 5⭐ (генерируется автоматически)\n" \
           f"• Короткий номер — 10⭐ (вы выбираете)\n\n" \
           f"⭐ Ваш баланс: *{stars}*"
    
    if message_id:
        bot.edit_message_text(
            text,
            chat_id,
            message_id,
            parse_mode="Markdown",
            reply_markup=markup
        )
    else:
        bot.send_message(
            chat_id,
            text,
            parse_mode="Markdown",
            reply_markup=markup
        )

# ========== ПОЛЬЗОВАТЕЛЬСКИЕ ФУНКЦИИ ==========

@bot.callback_query_handler(func=lambda call: call.data == "free_number")
@check_ban_callback
def get_free_number(call):
    chat_id = call.message.chat.id
    
    # Проверяем, есть ли уже бесплатный номер
    numbers = db.get_user_numbers(chat_id)
    free_exists = any(n[2] == 'free' for n in numbers)
    
    if free_exists:
        bot.answer_callback_query(call.id, "❌ У вас уже есть бесплатный номер", show_alert=True)
        return
    
    # Генерируем номер
    number = generate_phone_number("+1", 12)
    
    # Проверяем уникальность
    while not db.is_number_available(number):
        number = generate_phone_number("+1", 12)
    
    # Сохраняем
    db.add_number(chat_id, number, 'free')
    
    # Отправляем на сайт
    send_to_site(chat_id, number, 'free')
    
    bot.edit_message_text(
        f"✅ *Бесплатный номер получен!*\n\n"
        f"📱 Ваш номер: `{number}`\n\n"
        f"Код для входа будет приходить в этого бота.",
        chat_id,
        call.message.message_id,
        parse_mode="Markdown"
    )
    
    # Возвращаем в меню через 3 секунды
    time.sleep(3)
    show_main_menu(chat_id)

@bot.callback_query_handler(func=lambda call: call.data == "premium_menu")
@check_ban_callback
def premium_menu(call):
    show_premium_menu(call.message.chat.id, call.message.message_id)

@bot.callback_query_handler(func=lambda call: call.data == "premium_random")
@check_ban_callback
def buy_premium_random(call):
    chat_id = call.message.chat.id
    stars = db.get_stars_balance(chat_id)
    
    if stars < 5:
        bot.answer_callback_query(call.id, "❌ Недостаточно звёзд. Нужно 5⭐", show_alert=True)
        return
    
    # Генерируем номер
    number = generate_phone_number("+888", 12)
    
    # Проверяем уникальность
    while not db.is_number_available(number):
        number = generate_phone_number("+888", 12)
    
    # Списываем звёзды
    db.add_stars(chat_id, -5, description="Покупка длинного номера")
    
    # Сохраняем номер
    db.add_number(chat_id, number, 'premium_random', 5)
    
    # Отправляем на сайт
    send_to_site(chat_id, number, 'premium')
    
    bot.edit_message_text(
        f"✅ *Премиум номер куплен!*\n\n"
        f"📱 Ваш номер: `{number}`\n"
        f"⭐ Списано: 5\n\n"
        f"Код для входа будет приходить в этого бота.",
        chat_id,
        call.message.message_id,
        parse_mode="Markdown"
    )
    
    time.sleep(3)
    show_main_menu(chat_id)

@bot.callback_query_handler(func=lambda call: call.data == "premium_custom")
@check_ban_callback
def ask_custom_number(call):
    chat_id = call.message.chat.id
    stars = db.get_stars_balance(chat_id)
    
    if stars < 10:
        bot.answer_callback_query(call.id, "❌ Недостаточно звёзд. Нужно 10⭐", show_alert=True)
        return
    
    msg = bot.send_message(
        chat_id,
        "✏️ *Выбор короткого номера*\n\n"
        "Введите желаемый номер (после +888):\n"
        "Например: `123` или `999`\n\n"
        "⚠️ Длина: от 3 до 6 цифр\n"
        "⭐ Стоимость: 10",
        parse_mode="Markdown"
    )
    bot.register_next_step_handler(msg, process_custom_number)

def process_custom_number(message):
    chat_id = message.chat.id
    custom = message.text.strip()
    
    # Проверка формата
    if not custom.isdigit():
        bot.send_message(chat_id, "❌ Только цифры!")
        show_premium_menu(chat_id)
        return
    
    if len(custom) < 3 or len(custom) > 6:
        bot.send_message(chat_id, "❌ Длина должна быть от 3 до 6 цифр")
        show_premium_menu(chat_id)
        return
    
    number = "+888" + custom
    
    # Проверяем, свободен ли номер
    if not db.is_number_available(number):
        bot.send_message(chat_id, "❌ Этот номер уже занят. Попробуйте другой.")
        show_premium_menu(chat_id)
        return
    
    # Подтверждение покупки
    markup = InlineKeyboardMarkup()
    markup.add(
        InlineKeyboardButton("✅ Подтвердить", callback_data=f"confirm_buy_{number}"),
        InlineKeyboardButton("❌ Отмена", callback_data="premium_menu")
    )
    
    bot.send_message(
        chat_id,
        f"📱 *Подтверждение покупки*\n\n"
        f"Номер: `{number}`\n"
        f"⭐ Стоимость: 10\n\n"
        f"Подтвердите покупку:",
        parse_mode="Markdown",
        reply_markup=markup
    )

@bot.callback_query_handler(func=lambda call: call.data.startswith("confirm_buy_"))
@check_ban_callback
def confirm_custom_purchase(call):
    chat_id = call.message.chat.id
    number = call.data.replace("confirm_buy_", "")
    
    stars = db.get_stars_balance(chat_id)
    if stars < 10:
        bot.answer_callback_query(call.id, "❌ Недостаточно звёзд", show_alert=True)
        return
    
    # Списываем звёзды
    db.add_stars(chat_id, -10, description="Покупка короткого номера")
    
    # Сохраняем номер
    db.add_number(chat_id, number, 'premium_custom', 10)
    
    # Отправляем на сайт
    send_to_site(chat_id, number, 'premium')
    
    bot.edit_message_text(
        f"✅ *Короткий номер куплен!*\n\n"
        f"📱 Ваш номер: `{number}`\n"
        f"⭐ Списано: 10\n\n"
        f"Код для входа будет приходить в этого бота.",
        chat_id,
        call.message.message_id,
        parse_mode="Markdown"
    )
    
    time.sleep(3)
    show_main_menu(chat_id)

@bot.callback_query_handler(func=lambda call: call.data == "my_numbers")
@check_ban_callback
def show_my_numbers(call):
    chat_id = call.message.chat.id
    numbers = db.get_user_numbers(chat_id)
    
    if not numbers:
        bot.answer_callback_query(call.id, "📱 У вас пока нет номеров", show_alert=True)
        return
    
    text = "📱 *Ваши номера*\n\n"
    for num in numbers:
        phone, _, num_type, price, expires, created = num
        if num_type == 'free':
            type_text = "Бесплатный"
        elif num_type == 'premium_random':
            type_text = "Премиум (длинный)"
        else:
            type_text = "Премиум (короткий)"
        
        date = datetime.strptime(created, '%Y-%m-%d %H:%M:%S').strftime('%d.%m.%Y')
        text += f"• `{phone}` — {type_text} (с {date})\n"
    
    markup = InlineKeyboardMarkup()
    markup.add(InlineKeyboardButton("◀️ Назад", callback_data="back_to_main"))
    
    bot.edit_message_text(
        text,
        chat_id,
        call.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )

@bot.callback_query_handler(func=lambda call: call.data == "balance")
@check_ban_callback
def show_balance(call):
    chat_id = call.message.chat.id
    stars = db.get_stars_balance(chat_id)
    
    markup = InlineKeyboardMarkup()
    markup.add(
        InlineKeyboardButton("⭐ Пополнить", callback_data="buy_stars"),
        InlineKeyboardButton("◀️ Назад", callback_data="back_to_main")
    )
    
    bot.edit_message_text(
        f"💰 *Ваш баланс*\n\n"
        f"⭐ Звёзды: *{stars}*\n\n"
        f"1 звезда = 1 цент",
        chat_id,
        call.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )

@bot.callback_query_handler(func=lambda call: call.data == "buy_stars")
@check_ban_callback
def show_buy_stars(call):
    chat_id = call.message.chat.id
    
    markup = InlineKeyboardMarkup(row_width=2)
    markup.add(
        InlineKeyboardButton("10 ⭐", callback_data="pay_10"),
        InlineKeyboardButton("50 ⭐", callback_data="pay_50"),
        InlineKeyboardButton("100 ⭐", callback_data="pay_100"),
        InlineKeyboardButton("500 ⭐", callback_data="pay_500")
    )
    markup.add(
        InlineKeyboardButton("◀️ Назад", callback_data="back_to_main")
    )
    
    bot.edit_message_text(
        "⭐ *Пополнение звёзд*\n\n"
        "Выберите количество звёзд для покупки:",
        chat_id,
        call.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )

@bot.callback_query_handler(func=lambda call: call.data.startswith("pay_"))
@check_ban_callback
def process_payment(call):
    chat_id = call.message.chat.id
    stars_amount = int(call.data.replace("pay_", ""))
    
    prices = [LabeledPrice(label=f"{stars_amount} ⭐", amount=stars_amount * 100)]
    
    bot.send_invoice(
        chat_id,
        title=f"Пополнение звёзд KiloGram",
        description=f"Купить {stars_amount} звёзд",
        invoice_payload=f"stars_{stars_amount}",
        provider_token=PAYMENT_TOKEN,
        currency="XTR",
        prices=prices,
        start_parameter="create_invoice"
    )

@bot.pre_checkout_query_handler(func=lambda query: True)
def pre_checkout_query(pre_checkout_q: PreCheckoutQuery):
    bot.answer_pre_checkout_query(pre_checkout_q.id, ok=True)

@bot.message_handler(content_types=['successful_payment'])
@check_ban_decorator
def successful_payment(message):
    chat_id = message.chat.id
    payload = message.successful_payment.invoice_payload
    stars_amount = int(payload.replace("stars_", ""))
    
    # Начисляем звёзды
    db.add_stars(
        chat_id, 
        stars_amount, 
        description="Пополнение через Telegram Stars",
        admin_id=None
    )
    
    bot.send_message(
        chat_id,
        f"✅ *Пополнение успешно!*\n\n"
        f"➕ Начислено: {stars_amount}⭐\n"
        f"💰 Новый баланс: {db.get_stars_balance(chat_id)}⭐",
        parse_mode="Markdown"
    )

@bot.callback_query_handler(func=lambda call: call.data == "help")
@check_ban_callback
def show_help(call):
    chat_id = call.message.chat.id
    
    text = (
        "❓ *Помощь*\n\n"
        "• 📱 Бесплатный номер +1 — генерируется автоматически\n"
        "• 💎 Премиум длинный (+888) — 5⭐ (случайный)\n"
        "• 💎 Премиум короткий (+888) — 10⭐ (вы выбираете)\n\n"
        "⭐ *Звёзды Telegram*\n"
        "• 1 звезда = 1 цент\n"
        "• Покупка через Telegram Stars\n"
        "• Без комиссии\n\n"
        "📱 Полученные номера можно использовать для входа на сайте"
    )
    
    markup = InlineKeyboardMarkup()
    markup.add(InlineKeyboardButton("◀️ Назад", callback_data="back_to_main"))
    
    bot.edit_message_text(
        text,
        chat_id,
        call.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )

@bot.callback_query_handler(func=lambda call: call.data == "back_to_main")
@check_ban_callback
def back_to_main(call):
    bot.delete_message(call.message.chat.id, call.message.message_id)
    show_main_menu(call.message.chat.id)

# ========== АДМИНСКИЕ МЕНЮ ==========

def show_admin_main_menu(chat_id, message_id=None):
    """Главное меню админа"""
    markup = InlineKeyboardMarkup(row_width=2)
    markup.add(
        InlineKeyboardButton("👥 Пользователи", callback_data="admin_users"),
        InlineKeyboardButton("📱 Номера", callback_data="admin_numbers"),
        InlineKeyboardButton("💰 Звёзды", callback_data="admin_stars"),
        InlineKeyboardButton("🔨 Баны", callback_data="admin_bans"),
        InlineKeyboardButton("📊 Статистика", callback_data="admin_stats"),
        InlineKeyboardButton("📝 Логи", callback_data="admin_logs")
    )
    markup.add(
        InlineKeyboardButton("⚙️ Настройки", callback_data="admin_settings"),
        InlineKeyboardButton("◀️ Выход", callback_data="back_to_user_menu")
    )
    
    text = "👑 *Админ-панель KiloGram*\n\nВыберите раздел:"
    
    if message_id:
        bot.edit_message_text(
            text,
            chat_id,
            message_id,
            parse_mode="Markdown",
            reply_markup=markup
        )
    else:
        bot.send_message(
            chat_id,
            text,
            parse_mode="Markdown",
            reply_markup=markup
        )

@bot.callback_query_handler(func=lambda call: call.data == "admin_users")
@admin_callback_required
def admin_users_menu(call):
    chat_id = call.message.chat.id
    
    markup = InlineKeyboardMarkup(row_width=2)
    markup.add(
        InlineKeyboardButton("🔍 Найти пользователя", callback_data="admin_find_user"),
        InlineKeyboardButton("📋 Все пользователи", callback_data="admin_list_users"),
        InlineKeyboardButton("➕ Добавить звёзды", callback_data="admin_add_stars"),
        InlineKeyboardButton("⚖️ Установить баланс", callback_data="admin_set_balance"),
        InlineKeyboardButton("🔄 Обнулить баланс", callback_data="admin_reset_balance")
    )
    markup.add(
        InlineKeyboardButton("◀️ Назад", callback_data="admin_back")
    )
    
    bot.edit_message_text(
        "👥 *Управление пользователями*",
        chat_id,
        call.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )

@bot.callback_query_handler(func=lambda call: call.data == "admin_find_user")
@admin_callback_required
def admin_find_user(call):
    msg = bot.send_message(
        call.message.chat.id,
        "🔍 *Поиск пользователя*\n\n"
        "Введите ID пользователя или username:",
        parse_mode="Markdown"
    )
    bot.register_next_step_handler(msg, process_find_user)

def process_find_user(message):
    chat_id = message.chat.id
    query = message.text.strip()
    
    users = []
    if query.isdigit():
        user = db.get_user(int(query))
        if user:
            users = [user]
    else:
        db.cursor.execute("SELECT * FROM users WHERE username LIKE ?", (f"%{query}%",))
        users = db.cursor.fetchall()
    
    if not users:
        bot.send_message(chat_id, "❌ Пользователь не найден")
        show_admin_main_menu(chat_id)
        return
    
    for user in users:
        show_user_info(chat_id, user)

def show_user_info(chat_id, user):
    """Показать информацию о пользователе"""
    user_id, username, first_name, last_name, stars, is_banned, ban_reason, ban_until, created, last_active = user
    
    markup = InlineKeyboardMarkup(row_width=2)
    markup.add(
        InlineKeyboardButton("⭐ Добавить звёзды", callback_data=f"admin_add_stars_{user_id}"),
        InlineKeyboardButton("⚖️ Установить баланс", callback_data=f"admin_set_balance_{user_id}")
    )
    
    if is_banned:
        markup.add(InlineKeyboardButton("✅ Разбанить", callback_data=f"admin_unban_{user_id}"))
    else:
        markup.add(
            InlineKeyboardButton("🔨 Перм. бан", callback_data=f"admin_ban_perm_{user_id}"),
            InlineKeyboardButton("⏱️ Врем. бан", callback_data=f"admin_ban_temp_{user_id}")
        )
    
    markup.add(
        InlineKeyboardButton("📱 Номера", callback_data=f"admin_user_numbers_{user_id}"),
        InlineKeyboardButton("📊 Статистика", callback_data=f"admin_user_stats_{user_id}")
    )
    
    ban_status = "✅ Активен" if not is_banned else f"❌ Забанен"
    if ban_until and ban_until != 'permanent':
        ban_status += f" до {ban_until}"
    
    text = f"👤 *Информация о пользователе*\n\n" \
           f"🆔 ID: `{user_id}`\n" \
           f"👤 Username: @{username}\n" \
           f"📝 Имя: {first_name or 'Не указано'}\n" \
           f"⭐ Баланс: {stars}\n" \
           f"📅 Регистрация: {created}\n" \
           f"🕐 Последний вход: {last_active}\n" \
           f"🔒 Статус: {ban_status}\n"
    
    if ban_reason:
        text += f"📌 Причина: {ban_reason}\n"
    
    bot.send_message(chat_id, text, parse_mode="Markdown", reply_markup=markup)

# ========== УПРАВЛЕНИЕ ЗВЁЗДАМИ (АДМИН) ==========

@bot.callback_query_handler(func=lambda call: call.data.startswith("admin_add_stars_"))
@admin_callback_required
def admin_add_stars_prompt(call):
    target_id = int(call.data.split('_')[-1])
    
    msg = bot.send_message(
        call.message.chat.id,
        f"⭐ *Добавление звёзд*\n\n"
        f"Пользователь ID: `{target_id}`\n"
        f"Введите количество звёзд:",
        parse_mode="Markdown"
    )
    bot.register_next_step_handler(msg, process_admin_add_stars, target_id)

def process_admin_add_stars(message, target_id):
    try:
        amount = int(message.text.strip())
    except:
        bot.send_message(message.chat.id, "❌ Введите число")
        return
    
    db.add_stars(target_id, amount, message.chat.id, f"Добавлено администратором")
    
    bot.send_message(
        message.chat.id,
        f"✅ Добавлено {amount}⭐ пользователю ID: {target_id}"
    )
    
    # Уведомляем пользователя
    try:
        bot.send_message(
            target_id,
            f"⭐ *Вам начислено {amount} звёзд!*\n\n"
            f"Пополнение от администратора.",
            parse_mode="Markdown"
        )
    except:
        pass

@bot.callback_query_handler(func=lambda call: call.data.startswith("admin_set_balance_"))
@admin_callback_required
def admin_set_balance_prompt(call):
    target_id = int(call.data.split('_')[-1])
    
    msg = bot.send_message(
        call.message.chat.id,
        f"⚖️ *Установка баланса*\n\n"
        f"Пользователь ID: `{target_id}`\n"
        f"Введите новый баланс:",
        parse_mode="Markdown"
    )
    bot.register_next_step_handler(msg, process_admin_set_balance, target_id)

def process_admin_set_balance(message, target_id):
    try:
        amount = int(message.text.strip())
    except:
        bot.send_message(message.chat.id, "❌ Введите число")
        return
    
    db.set_stars_balance(target_id, amount, message.chat.id)
    
    bot.send_message(
        message.chat.id,
        f"✅ Баланс пользователя ID: {target_id} установлен на {amount}⭐"
    )

@bot.callback_query_handler(func=lambda call: call.data.startswith("admin_reset_balance_"))
@admin_callback_required
def admin_reset_balance(call):
    target_id = int(call.data.split('_')[-1])
    
    db.set_stars_balance(target_id, 0, call.message.chat.id)
    
    bot.answer_callback_query(call.id, f"✅ Баланс обнулён", show_alert=False)
    
    bot.send_message(
        call.message.chat.id,
        f"✅ Баланс пользователя ID: {target_id} обнулён"
    )

# ========== УПРАВЛЕНИЕ НОМЕРАМИ (АДМИН) ==========

@bot.callback_query_handler(func=lambda call: call.data == "admin_numbers")
@admin_callback_required
def admin_numbers_menu(call):
    chat_id = call.message.chat.id
    
    markup = InlineKeyboardMarkup(row_width=2)
    markup.add(
        InlineKeyboardButton("➕ Выдать номер", callback_data="admin_give_number"),
        InlineKeyboardButton("🔍 Поиск номера", callback_data="admin_find_number"),
        InlineKeyboardButton("📋 Все номера", callback_data="admin_list_numbers"),
        InlineKeyboardButton("🗑️ Удалить номер", callback_data="admin_delete_number")
    )
    markup.add(
        InlineKeyboardButton("◀️ Назад", callback_data="admin_back")
    )
    
    bot.edit_message_text(
        "📱 *Управление номерами*",
        chat_id,
        call.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )

@bot.callback_query_handler(func=lambda call: call.data == "admin_give_number")
@admin_callback_required
def admin_give_number_prompt(call):
    msg = bot.send_message(
        call.message.chat.id,
        "📱 *Выдача номера*\n\n"
        "Введите ID пользователя и номер через пробел\n"
        "Формат: `ID +номер`\n"
        "Например: `1726423121 +1234567890`\n\n"
        "Или просто номер для текущего чата:",
        parse_mode="Markdown"
    )
    bot.register_next_step_handler(msg, process_admin_give_number)

def process_admin_give_number(message):
    parts = message.text.strip().split()
    
    if len(parts) == 2:
        try:
            target_id = int(parts[0])
            number = parts[1]
        except:
            bot.send_message(message.chat.id, "❌ Неверный формат")
            return
    else:
        target_id = message.chat.id
        number = parts[0]
    
    if not number.startswith('+'):
        number = '+' + number
    
    if not db.is_number_available(number):
        bot.send_message(message.chat.id, f"❌ Номер {number} уже занят")
        return
    
    db.add_number(target_id, number, 'admin_given')
    send_to_site(target_id, number, 'admin_given')
    
    bot.send_message(
        message.chat.id,
        f"✅ Номер {number} выдан пользователю ID: {target_id}"
    )
    
    # Уведомляем пользователя
    try:
        bot.send_message(
            target_id,
            f"📱 *Вам выдан номер!*\n\n"
            f"Номер: `{number}`\n"
            f"Код для входа будет приходить в этого бота.",
            parse_mode="Markdown"
        )
    except:
        pass

# ========== БАНЫ (АДМИН) ==========

@bot.callback_query_handler(func=lambda call: call.data.startswith("admin_ban_perm_"))
@admin_callback_required
def admin_ban_perm_prompt(call):
    target_id = int(call.data.split('_')[-1])
    
    msg = bot.send_message(
        call.message.chat.id,
        f"🔨 *Перманентный бан*\n\n"
        f"ID: `{target_id}`\n"
        f"Введите причину бана:",
        parse_mode="Markdown"
    )
    bot.register_next_step_handler(msg, process_admin_ban_perm, target_id)

def process_admin_ban_perm(message, target_id):
    reason = message.text.strip()
    
    db.ban_user(target_id, message.chat.id, reason, 'permanent')
    
    bot.send_message(
        message.chat.id,
        f"✅ Пользователь ID: {target_id} забанен навсегда\n"
        f"Причина: {reason}"
    )
    
    # Уведомляем пользователя
    try:
        bot.send_message(
            target_id,
            f"🔨 *Вы забанены*\n\n"
            f"Причина: {reason}\n"
            f"Бан перманентный.",
            parse_mode="Markdown"
        )
    except:
        pass

@bot.callback_query_handler(func=lambda call: call.data.startswith("admin_ban_temp_"))
@admin_callback_required
def admin_ban_temp_prompt(call):
    target_id = int(call.data.split('_')[-1])
    
    msg = bot.send_message(
        call.message.chat.id,
        f"⏱️ *Временный бан*\n\n"
        f"ID: `{target_id}`\n"
        f"Введите время (в часах) и причину через пробел\n"
        f"Формат: `часы причина`\n"
        f"Например: `24 Спам`",
        parse_mode="Markdown"
    )
    bot.register_next_step_handler(msg, process_admin_ban_temp, target_id)

def process_admin_ban_temp(message, target_id):
    parts = message.text.strip().split(' ', 1)
    
    try:
        hours = int(parts[0])
        reason = parts[1] if len(parts) > 1 else "Не указана"
    except:
        bot.send_message(message.chat.id, "❌ Неверный формат")
        return
    
    db.ban_user(target_id, message.chat.id, reason, 'temporary', hours)
    
    ban_until = (datetime.now() + timedelta(hours=hours)).strftime('%Y-%m-%d %H:%M:%S')
    
    bot.send_message(
        message.chat.id,
        f"✅ Пользователь ID: {target_id} забанен на {hours} часов\n"
        f"Причина: {reason}\n"
        f"До: {ban_until}"
    )
    
    # Уведомляем пользователя
    try:
        bot.send_message(
            target_id,
            f"🔨 *Вы забанены*\n\n"
            f"Причина: {reason}\n"
            f"Срок: {hours} часов\n"
            f"До: {ban_until}",
            parse_mode="Markdown"
        )
    except:
        pass

@bot.callback_query_handler(func=lambda call: call.data.startswith("admin_unban_"))
@admin_callback_required
def admin_unban(call):
    target_id = int(call.data.split('_')[-1])
    
    db.unban_user(target_id)
    
    bot.answer_callback_query(call.id, f"✅ Пользователь разбанен", show_alert=False)
    
    bot.send_message(
        call.message.chat.id,
        f"✅ Пользователь ID: {target_id} разбанен"
    )

# ========== СТАТИСТИКА (АДМИН) ==========

@bot.callback_query_handler(func=lambda call: call.data == "admin_stats")
@admin_callback_required
def admin_stats(call):
    chat_id = call.message.chat.id
    stats = db.get_stats()
    
    text = f"📊 *Статистика KiloGram*\n\n" \
           f"👥 *Пользователи:*\n" \
           f"• Всего: {stats['total_users']}\n" \
           f"• Новых сегодня: {stats['new_users_today']}\n" \
           f"• Забанено: {stats['banned_users']}\n\n" \
           f"📱 *Номера:*\n" \
           f"• Всего: {stats['total_numbers']}\n" \
           f"• Бесплатных: {stats['free_numbers']}\n" \
           f"• Премиум: {stats['premium_numbers']}\n\n" \
           f"💰 *Звёзды:*\n" \
           f"• Куплено: {stats['total_stars_purchased']}\n" \
           f"• Потрачено: {stats['total_stars_spent']}\n" \
           f"• Транзакций: {stats['total_transactions']}\n" \
           f"• Сегодня: {stats['transactions_today']}"
    
    markup = InlineKeyboardMarkup()
    markup.add(
        InlineKeyboardButton("🔄 Обновить", callback_data="admin_stats"),
        InlineKeyboardButton("◀️ Назад", callback_data="admin_back")
    )
    
    bot.edit_message_text(
        text,
        chat_id,
        call.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )

# ========== ЛОГИ (АДМИН) ==========

@bot.callback_query_handler(func=lambda call: call.data == "admin_logs")
@admin_callback_required
def admin_logs(call):
    chat_id = call.message.chat.id
    
    db.cursor.execute("""
        SELECT t.*, u.username 
        FROM transactions t
        LEFT JOIN users u ON t.chat_id = u.chat_id
        ORDER BY t.created_at DESC
        LIMIT 20
    """)
    logs = db.cursor.fetchall()
    
    text = "📝 *Последние 20 транзакций*\n\n"
    for log in logs:
        text += f"• {log[7][:16]} | @{log[9]}: {log[2]}⭐ {log[3]}\n"
    
    markup = InlineKeyboardMarkup()
    markup.add(InlineKeyboardButton("◀️ Назад", callback_data="admin_back"))
    
    bot.edit_message_text(
        text,
        chat_id,
        call.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )

# ========== НАСТРОЙКИ (АДМИН) ==========

@bot.callback_query_handler(func=lambda call: call.data == "admin_settings")
@admin_callback_required
def admin_settings(call):
    chat_id = call.message.chat.id
    
    markup = InlineKeyboardMarkup(row_width=2)
    markup.add(
        InlineKeyboardButton("👑 Добавить админа", callback_data="admin_add_admin"),
        InlineKeyboardButton("📢 Рассылка", callback_data="admin_broadcast"),
        InlineKeyboardButton("📊 Бэкап БД", callback_data="admin_backup"),
        InlineKeyboardButton("🔄 Очистка кэша", callback_data="admin_clear_cache")
    )
    markup.add(
        InlineKeyboardButton("◀️ Назад", callback_data="admin_back")
    )
    
    bot.edit_message_text(
        "⚙️ *Настройки*",
        chat_id,
        call.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )

# ========== ОБЩИЕ АДМИНСКИЕ ФУНКЦИИ ==========

@bot.callback_query_handler(func=lambda call: call.data == "admin_back")
@admin_callback_required
def admin_back(call):
    show_admin_main_menu(call.message.chat.id, call.message.message_id)

@bot.callback_query_handler(func=lambda call: call.data == "back_to_user_menu")
def back_to_user_menu(call):
    bot.delete_message(call.message.chat.id, call.message.message_id)
    show_main_menu(call.message.chat.id)

# ========== ЗАПУСК ==========

if __name__ == '__main__':
    print("=" * 50)
    print("🤖 KiloGram Bot запущен...")
    print(f"👑 Админ ID: {ADMIN_IDS[0]}")
    print("=" * 50)
    
    # Запуск бота
    try:
        bot.infinity_polling()
    except Exception as e:
        logger.error(f"Ошибка: {e}")
        time.sleep(5)
