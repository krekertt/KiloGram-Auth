import asyncio
import logging
import sqlite3
import random
from datetime import datetime, timedelta
from aiogram import Bot, Dispatcher, types, F
from aiogram.filters import Command
from aiogram.types import (
    InlineKeyboardMarkup, InlineKeyboardButton,
    LabeledPrice, PreCheckoutQuery, CallbackQuery,
    Message
)
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.fsm.storage.memory import MemoryStorage
import requests
import json
import os
import threading
from flask import Flask, request, jsonify

# ========== НАСТРОЙКИ ==========

TOKEN = '8081566708:AAHm4ppfiDQMVT_GCsTFmXXe-Z56UWae6AM'
PAYMENT_TOKEN = 'TEST_PROVIDER_TOKEN'  # Получи у BotFather
API_URL = 'https://kilogram.atwebpages.com/api.php'
ADMIN_IDS = [1726423121]  # Твой ID
SUPPORT_USERNAME = '@yourples'  # Твой юзернейм

# Настройка логирования
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Инициализация бота
bot = Bot(token=TOKEN)
storage = MemoryStorage()
dp = Dispatcher(storage=storage)

# ========== БАЗА ДАННЫХ ==========

class Database:
    def __init__(self, db_name='telegram_bot.db'):
        self.conn = sqlite3.connect(db_name, check_same_thread=False)
        self.cursor = self.conn.cursor()
        self.create_tables()
    
    def create_tables(self):
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
        
        # Коды подтверждения
        self.cursor.execute('''
        CREATE TABLE IF NOT EXISTS auth_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id INTEGER,
            phone TEXT,
            code TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            used INTEGER DEFAULT 0
        )
        ''')
        
        self.conn.commit()
        logger.info("База данных инициализирована")
    
    # ===== ПОЛЬЗОВАТЕЛИ =====
    
    async def get_user(self, chat_id):
        self.cursor.execute("SELECT * FROM users WHERE chat_id = ?", (chat_id,))
        return self.cursor.fetchone()
    
    async def create_user(self, chat_id, username=None, first_name=None, last_name=None):
        self.cursor.execute('''
            INSERT OR IGNORE INTO users (chat_id, username, first_name, last_name, stars_balance, created_at, last_active)
            VALUES (?, ?, ?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ''', (chat_id, username, first_name, last_name))
        self.conn.commit()
        logger.info(f"Новый пользователь: {chat_id}")
    
    async def update_last_active(self, chat_id):
        self.cursor.execute("UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE chat_id = ?", (chat_id,))
        self.conn.commit()
    
    # ===== БАНЫ =====
    
    async def check_ban(self, chat_id):
        self.cursor.execute("SELECT is_banned, ban_until FROM users WHERE chat_id = ?", (chat_id,))
        result = self.cursor.fetchone()
        
        if not result:
            return False
        
        is_banned, ban_until = result
        
        if is_banned and ban_until and ban_until != 'permanent':
            try:
                if datetime.now() > datetime.strptime(ban_until, '%Y-%m-%d %H:%M:%S'):
                    self.cursor.execute("UPDATE users SET is_banned = 0, ban_until = NULL WHERE chat_id = ?", (chat_id,))
                    self.conn.commit()
                    return False
            except:
                pass
        
        return bool(is_banned)
    
    async def ban_user(self, chat_id, admin_id, reason, duration='permanent', hours=None):
        if duration == 'permanent':
            self.cursor.execute('''
                UPDATE users SET is_banned = 1, ban_reason = ?, ban_until = 'permanent' 
                WHERE chat_id = ?
            ''', (reason, chat_id))
        else:
            ban_until = datetime.now() + timedelta(hours=hours)
            ban_until_str = ban_until.strftime('%Y-%m-%d %H:%M:%S')
            self.cursor.execute('''
                UPDATE users SET is_banned = 1, ban_reason = ?, ban_until = ? 
                WHERE chat_id = ?
            ''', (reason, ban_until_str, chat_id))
        
        self.conn.commit()
    
    async def unban_user(self, chat_id):
        self.cursor.execute("UPDATE users SET is_banned = 0, ban_reason = NULL, ban_until = NULL WHERE chat_id = ?", (chat_id,))
        self.conn.commit()
    
    # ===== ЗВЁЗДЫ =====
    
    async def get_stars_balance(self, chat_id):
        self.cursor.execute("SELECT stars_balance FROM users WHERE chat_id = ?", (chat_id,))
        result = self.cursor.fetchone()
        return result[0] if result else 0
    
    async def add_stars(self, chat_id, amount, description="Пополнение"):
        self.cursor.execute("UPDATE users SET stars_balance = stars_balance + ? WHERE chat_id = ?", (amount, chat_id))
        self.cursor.execute('''
            INSERT INTO transactions (chat_id, amount_stars, description, status)
            VALUES (?, ?, ?, 'completed')
        ''', (chat_id, amount, description))
        self.conn.commit()
    
    async def set_stars_balance(self, chat_id, amount, admin_id=None):
        self.cursor.execute("UPDATE users SET stars_balance = ? WHERE chat_id = ?", (amount, chat_id))
        self.cursor.execute('''
            INSERT INTO transactions (chat_id, amount_stars, description, status, admin_id)
            VALUES (?, ?, 'Баланс установлен администратором', 'admin_set', ?)
        ''', (chat_id, amount, admin_id))
        self.conn.commit()
    
    # ===== НОМЕРА =====
    
    async def add_number(self, chat_id, phone_number, number_type, price=0):
        self.cursor.execute('''
            INSERT INTO numbers (phone_number, chat_id, type, price_stars)
            VALUES (?, ?, ?, ?)
        ''', (phone_number, chat_id, number_type, price))
        self.conn.commit()
    
    async def get_user_numbers(self, chat_id):
        self.cursor.execute("SELECT * FROM numbers WHERE chat_id = ?", (chat_id,))
        return self.cursor.fetchall()
    
    async def is_number_available(self, phone_number):
        self.cursor.execute("SELECT 1 FROM numbers WHERE phone_number = ?", (phone_number,))
        return not self.cursor.fetchone()
    
    # ===== КОДЫ =====
    
    async def save_code(self, chat_id, phone, code):
        self.cursor.execute("INSERT INTO auth_codes (chat_id, phone, code) VALUES (?, ?, ?)", 
                          (chat_id, phone, code))
        self.conn.commit()
        return self.cursor.lastrowid
    
    async def verify_code(self, chat_id, code):
        self.cursor.execute("SELECT * FROM auth_codes WHERE chat_id = ? AND code = ? AND used = 0 ORDER BY created_at DESC LIMIT 1", 
                          (chat_id, code))
        return self.cursor.fetchone()

# Инициализация БД
db = Database()

# ========== FSM СОСТОЯНИЯ ==========

class CustomNumber(StatesGroup):
    waiting_for_number = State()

class AdminStates(StatesGroup):
    waiting_for_user_id = State()
    waiting_for_stars_amount = State()
    waiting_for_ban_reason = State()
    waiting_for_ban_hours = State()

# ========== ФИЛЬТРЫ ==========

async def is_admin(chat_id: int) -> bool:
    return chat_id in ADMIN_IDS

# ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========

def generate_phone_number(prefix, length):
    number = prefix
    for _ in range(length - len(prefix)):
        number += str(random.randint(0, 9))
    return number

async def send_to_site(chat_id, phone_number, number_type):
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
    except Exception as e:
        logger.error(f"❌ Ошибка отправки на сайт: {e}")

# ========== МЕНЮ ==========

async def show_main_menu(chat_id: int, message_id: int = None):
    stars = await db.get_stars_balance(chat_id)
    
    markup = InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="📱 Бесплатный номер (+1)", callback_data="free_number"),
            InlineKeyboardButton(text="💎 Премиум номер (+888)", callback_data="premium_menu")
        ],
        [
            InlineKeyboardButton(text="👤 Профиль", callback_data="profile"),
            InlineKeyboardButton(text="🆘 Поддержка", callback_data="support")
        ]
    ])
    
    text = f"🌟 *KiloGram Bot*\n\n⭐ Баланс: {stars}\n\nВыберите действие:"
    
    if message_id:
        await bot.edit_message_text(text, chat_id, message_id, parse_mode="Markdown", reply_markup=markup)
    else:
        await bot.send_message(chat_id, text, parse_mode="Markdown", reply_markup=markup)

async def show_profile(chat_id: int, message_id: int = None):
    stars = await db.get_stars_balance(chat_id)
    user = await db.get_user(chat_id)
    
    if user:
        _, username, first_name, last_name, _, is_banned, _, _, created, last_active = user
    else:
        username = first_name = last_name = "Не указано"
    
    markup = InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="💰 Пополнить баланс", callback_data="buy_stars")
        ],
        [
            InlineKeyboardButton(text="📱 Мои номера", callback_data="my_numbers"),
            InlineKeyboardButton(text="◀️ Назад", callback_data="back_to_main")
        ]
    ])
    
    text = f"👤 *Ваш профиль*\n\n" \
           f"🆔 ID: `{chat_id}`\n" \
           f"👤 Username: @{username}\n" \
           f"📝 Имя: {first_name or 'Не указано'}\n" \
           f"⭐ Баланс: {stars}\n" \
           f"📅 Регистрация: {created}"
    
    if message_id:
        await bot.edit_message_text(text, chat_id, message_id, parse_mode="Markdown", reply_markup=markup)
    else:
        await bot.send_message(chat_id, text, parse_mode="Markdown", reply_markup=markup)

async def show_premium_menu(chat_id: int, message_id: int = None):
    stars = await db.get_stars_balance(chat_id)
    
    markup = InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="🎲 Случайный длинный (5⭐)", callback_data="premium_random")
        ],
        [
            InlineKeyboardButton(text="✏️ Выбрать короткий (10⭐)", callback_data="premium_custom")
        ],
        [
            InlineKeyboardButton(text="◀️ Назад", callback_data="back_to_main")
        ]
    ])
    
    text = f"💎 *Премиум номера +888*\n\n" \
           f"• Длинный номер — 5⭐ (генерируется автоматически)\n" \
           f"• Короткий номер — 10⭐ (вы выбираете)\n\n" \
           f"⭐ Ваш баланс: *{stars}*"
    
    if message_id:
        await bot.edit_message_text(text, chat_id, message_id, parse_mode="Markdown", reply_markup=markup)
    else:
        await bot.send_message(chat_id, text, parse_mode="Markdown", reply_markup=markup)

async def show_buy_stars(chat_id: int, message_id: int = None):
    markup = InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="10 ⭐", callback_data="pay_10")
        ],
        [
            InlineKeyboardButton(text="50 ⭐", callback_data="pay_50")
        ],
        [
            InlineKeyboardButton(text="100 ⭐", callback_data="pay_100")
        ],
        [
            InlineKeyboardButton(text="500 ⭐", callback_data="pay_500")
        ],
        [
            InlineKeyboardButton(text="◀️ Назад", callback_data="profile")
        ]
    ])
    
    text = "⭐ *Пополнение баланса*\n\nВыберите количество звёзд:"
    
    if message_id:
        await bot.edit_message_text(text, chat_id, message_id, parse_mode="Markdown", reply_markup=markup)
    else:
        await bot.send_message(chat_id, text, parse_mode="Markdown", reply_markup=markup)

# ========== АДМИНСКОЕ МЕНЮ ==========

async def show_admin_menu(chat_id: int, message_id: int = None):
    markup = InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="👥 Управление пользователями", callback_data="admin_users"),
            InlineKeyboardButton(text="📱 Управление номерами", callback_data="admin_numbers")
        ],
        [
            InlineKeyboardButton(text="💰 Управление звёздами", callback_data="admin_stars"),
            InlineKeyboardButton(text="🔨 Баны", callback_data="admin_bans")
        ],
        [
            InlineKeyboardButton(text="📊 Статистика", callback_data="admin_stats"),
            InlineKeyboardButton(text="📝 Логи", callback_data="admin_logs")
        ],
        [
            InlineKeyboardButton(text="◀️ Выход", callback_data="back_to_main")
        ]
    ])
    
    text = "👑 *Админ-панель*\n\nВыберите раздел:"
    
    if message_id:
        await bot.edit_message_text(text, chat_id, message_id, parse_mode="Markdown", reply_markup=markup)
    else:
        await bot.send_message(chat_id, text, parse_mode="Markdown", reply_markup=markup)

# ========== КОМАНДЫ ==========

@dp.message(Command("start"))
async def cmd_start(message: Message):
    chat_id = message.chat.id
    username = message.from_user.username
    first_name = message.from_user.first_name
    last_name = message.from_user.last_name
    
    await db.create_user(chat_id, username, first_name, last_name)
    
    if await is_admin(chat_id):
        await show_admin_menu(chat_id)
    else:
        await show_main_menu(chat_id)

# ========== ОБРАБОТКА КНОПОК ==========

@dp.callback_query(F.data == "back_to_main")
async def back_to_main(callback: CallbackQuery):
    if await is_admin(callback.message.chat.id):
        await show_admin_menu(callback.message.chat.id, callback.message.message_id)
    else:
        await show_main_menu(callback.message.chat.id, callback.message.message_id)
    await callback.answer()

@dp.callback_query(F.data == "profile")
async def profile(callback: CallbackQuery):
    await show_profile(callback.message.chat.id, callback.message.message_id)
    await callback.answer()

@dp.callback_query(F.data == "support")
async def support(callback: CallbackQuery):
    markup = InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="👤 Написать в поддержку", url=f"https://t.me/{SUPPORT_USERNAME[1:]}")],
        [InlineKeyboardButton(text="◀️ Назад", callback_data="back_to_main")]
    ])
    
    await bot.edit_message_text(
        f"🆘 *Поддержка*\n\nСвяжитесь с нами: {SUPPORT_USERNAME}",
        callback.message.chat.id,
        callback.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )
    await callback.answer()

@dp.callback_query(F.data == "buy_stars")
async def buy_stars(callback: CallbackQuery):
    await show_buy_stars(callback.message.chat.id, callback.message.message_id)
    await callback.answer()

@dp.callback_query(F.data == "premium_menu")
async def premium_menu(callback: CallbackQuery):
    await show_premium_menu(callback.message.chat.id, callback.message.message_id)
    await callback.answer()

# ========== БЕСПЛАТНЫЙ НОМЕР ==========

@dp.callback_query(F.data == "free_number")
async def free_number(callback: CallbackQuery):
    chat_id = callback.message.chat.id
    
    numbers = await db.get_user_numbers(chat_id)
    free_exists = any(n[2] == 'free' for n in numbers)
    
    if free_exists:
        await callback.answer("❌ У вас уже есть бесплатный номер", show_alert=True)
        return
    
    number = generate_phone_number("+1", 12)
    
    while not await db.is_number_available(number):
        number = generate_phone_number("+1", 12)
    
    await db.add_number(chat_id, number, 'free')
    await send_to_site(chat_id, number, 'free')
    
    await bot.edit_message_text(
        f"✅ *Бесплатный номер получен!*\n\n📱 Ваш номер: `{number}`\n\nКод для входа будет приходить в этого бота.",
        chat_id,
        callback.message.message_id,
        parse_mode="Markdown"
    )
    
    await asyncio.sleep(3)
    await show_main_menu(chat_id)
    await callback.answer()

# ========== ПРЕМИУМ НОМЕРА ==========

@dp.callback_query(F.data == "premium_random")
async def premium_random(callback: CallbackQuery):
    chat_id = callback.message.chat.id
    stars = await db.get_stars_balance(chat_id)
    
    if stars < 5:
        await callback.answer("❌ Недостаточно звёзд. Нужно 5⭐", show_alert=True)
        return
    
    number = generate_phone_number("+888", 12)
    
    while not await db.is_number_available(number):
        number = generate_phone_number("+888", 12)
    
    await db.add_stars(chat_id, -5, "Покупка длинного номера")
    await db.add_number(chat_id, number, 'premium_random', 5)
    await send_to_site(chat_id, number, 'premium')
    
    await bot.edit_message_text(
        f"✅ *Премиум номер куплен!*\n\n📱 Ваш номер: `{number}`\n⭐ Списано: 5",
        chat_id,
        callback.message.message_id,
        parse_mode="Markdown"
    )
    
    await asyncio.sleep(3)
    await show_main_menu(chat_id)
    await callback.answer()

@dp.callback_query(F.data == "premium_custom")
async def premium_custom(callback: CallbackQuery, state: FSMContext):
    chat_id = callback.message.chat.id
    stars = await db.get_stars_balance(chat_id)
    
    if stars < 10:
        await callback.answer("❌ Недостаточно звёзд. Нужно 10⭐", show_alert=True)
        return
    
    await state.set_state(CustomNumber.waiting_for_number)
    
    await bot.edit_message_text(
        "✏️ *Выбор короткого номера*\n\nВведите желаемый номер (после +888):\nНапример: `123` или `999`\n\n⚠️ Длина: от 3 до 6 цифр\n⭐ Стоимость: 10",
        chat_id,
        callback.message.message_id,
        parse_mode="Markdown"
    )
    await callback.answer()

@dp.message(CustomNumber.waiting_for_number)
async def process_custom_number(message: Message, state: FSMContext):
    chat_id = message.chat.id
    custom = message.text.strip()
    
    if not custom.isdigit():
        await message.answer("❌ Только цифры!")
        await show_premium_menu(chat_id)
        await state.clear()
        return
    
    if len(custom) < 3 or len(custom) > 6:
        await message.answer("❌ Длина должна быть от 3 до 6 цифр")
        await show_premium_menu(chat_id)
        await state.clear()
        return
    
    number = "+888" + custom
    
    if not await db.is_number_available(number):
        await message.answer("❌ Этот номер уже занят. Попробуйте другой.")
        await show_premium_menu(chat_id)
        await state.clear()
        return
    
    stars = await db.get_stars_balance(chat_id)
    if stars < 10:
        await message.answer("❌ Недостаточно звёзд")
        await show_premium_menu(chat_id)
        await state.clear()
        return
    
    await db.add_stars(chat_id, -10, "Покупка короткого номера")
    await db.add_number(chat_id, number, 'premium_custom', 10)
    await send_to_site(chat_id, number, 'premium')
    
    await message.answer(
        f"✅ *Короткий номер куплен!*\n\n📱 Ваш номер: `{number}`\n⭐ Списано: 10",
        parse_mode="Markdown"
    )
    
    await show_main_menu(chat_id)
    await state.clear()

# ========== МОИ НОМЕРА ==========

@dp.callback_query(F.data == "my_numbers")
async def my_numbers(callback: CallbackQuery):
    chat_id = callback.message.chat.id
    numbers = await db.get_user_numbers(chat_id)
    
    if not numbers:
        await callback.answer("📱 У вас пока нет номеров", show_alert=True)
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
    
    markup = InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="◀️ Назад", callback_data="profile")]
    ])
    
    await bot.edit_message_text(
        text,
        chat_id,
        callback.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )
    await callback.answer()

# ========== АДМИНСКИЕ КНОПКИ ==========

@dp.callback_query(F.data == "admin_users")
async def admin_users(callback: CallbackQuery):
    if not await is_admin(callback.message.chat.id):
        await callback.answer("⛔ Доступ запрещён", show_alert=True)
        return
    
    markup = InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="🔍 Найти пользователя", callback_data="admin_find_user")],
        [InlineKeyboardButton(text="📋 Список пользователей", callback_data="admin_list_users")],
        [InlineKeyboardButton(text="◀️ Назад", callback_data="back_to_main")]
    ])
    
    await bot.edit_message_text(
        "👥 *Управление пользователями*",
        callback.message.chat.id,
        callback.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )
    await callback.answer()

@dp.callback_query(F.data == "admin_stats")
async def admin_stats(callback: CallbackQuery):
    if not await is_admin(callback.message.chat.id):
        await callback.answer("⛔ Доступ запрещён", show_alert=True)
        return
    
    # Здесь должна быть статистика из БД
    text = "📊 *Статистика*\n\n"
    text += "👥 Пользователей: " + str(len(db.cursor.execute("SELECT * FROM users").fetchall())) + "\n"
    text += "📱 Номеров: " + str(len(db.cursor.execute("SELECT * FROM numbers").fetchall())) + "\n"
    text += "⭐ Всего звёзд: 0\n"
    
    markup = InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text="◀️ Назад", callback_data="admin_users")]
    ])
    
    await bot.edit_message_text(
        text,
        callback.message.chat.id,
        callback.message.message_id,
        parse_mode="Markdown",
        reply_markup=markup
    )
    await callback.answer()

# ========== ОПЛАТА ==========

@dp.callback_query(F.data.startswith("pay_"))
async def process_payment(callback: CallbackQuery):
    chat_id = callback.message.chat.id
    stars_amount = int(callback.data.replace("pay_", ""))
    
    prices = [LabeledPrice(label=f"{stars_amount} ⭐", amount=stars_amount * 100)]
    
    await bot.send_invoice(
        chat_id,
        title=f"Пополнение звёзд",
        description=f"Купить {stars_amount} звёзд",
        invoice_payload=f"stars_{stars_amount}",
        provider_token=PAYMENT_TOKEN,
        currency="XTR",
        prices=prices,
        start_parameter="create_invoice"
    )
    await callback.answer()

@dp.pre_checkout_query()
async def pre_checkout_handler(pre_checkout_q: PreCheckoutQuery):
    await bot.answer_pre_checkout_query(pre_checkout_q.id, ok=True)

@dp.message(F.successful_payment)
async def successful_payment_handler(message: Message):
    chat_id = message.chat.id
    payload = message.successful_payment.invoice_payload
    stars_amount = int(payload.replace("stars_", ""))
    
    await db.add_stars(chat_id, stars_amount, f"Пополнение {stars_amount}⭐")
    
    await message.answer(
        f"✅ *Пополнение успешно!*\n\n➕ Начислено: {stars_amount}⭐\n💰 Новый баланс: {await db.get_stars_balance(chat_id)}⭐",
        parse_mode="Markdown"
    )
    await show_main_menu(chat_id)

# ========== ВЕБ-СЕРВЕР ДЛЯ API ==========

web_app = Flask(__name__)

@web_app.route('/send_code', methods=['POST'])
def handle_send_code():
    """Эндпоинт для отправки кода с сайта"""
    data = request.json
    chat_id = data.get('chat_id')
    phone = data.get('phone')
    
    if not chat_id:
        return jsonify({'error': 'no_chat_id'}), 400
    
    code = random.randint(100000, 999999)
    
    try:
        # Создаём новый цикл событий для потока
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        
        # Сохраняем код в БД
        loop.run_until_complete(db.save_code(chat_id, phone, str(code)))
        
        # Отправляем сообщение через бота
        loop.run_until_complete(
            bot.send_message(
                chat_id,
                f"🔑 *Код подтверждения*\n\nВаш код для входа: `{code}`\n\n⏱️ Действителен 5 минут",
                parse_mode='Markdown'
            )
        )
        loop.close()
        
        return jsonify({'success': True, 'code': code})
    except Exception as e:
        logger.error(f"Ошибка отправки кода: {e}")
        return jsonify({'error': 'telegram_error'}), 500

@web_app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'bot': 'KiloGram Bot'})

def run_web_server():
    """Запуск веб-сервера в отдельном потоке"""
    port = int(os.getenv("PORT", 3000))
    web_app.run(host='0.0.0.0', port=port, debug=False, use_reloader=False)

# ========== ЗАПУСК ==========

async def main():
    print("=" * 50)
    print("🤖 KiloGram Bot запущен...")
    print(f"👑 Админ ID: {ADMIN_IDS[0]}")
    print(f"🆘 Поддержка: {SUPPORT_USERNAME}")
    print("=" * 50)
    
    await dp.start_polling(bot)

if __name__ == '__main__':
    # Запускаем веб-сервер в отдельном потоке
    web_thread = threading.Thread(target=run_web_server, daemon=True)
    web_thread.start()
    print(f"🌐 Веб-сервер для API запущен на порту {os.getenv('PORT', 3000)}")
    
    # Запускаем бота
    asyncio.run(main())
