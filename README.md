<div align="center">

# ⚔️ Albion Online Party Manager

**2v2 partileri için elektronik tabloların (Excel/Sheets) yerini almak üzere tasarlanmış, web tabanlı roster yönetim aracı.**

![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Discord](https://img.shields.io/badge/Discord-5865F2?style=for-the-badge&logo=discord&logoColor=white)

</div>

---

## 🚀 Key Features (Özellikler)

* **🛡️ Visual Roster:** Parti slotlarını yönetmek için Sürükle & Bırak (Drag & Drop) arayüzü.
* **✅ Role Validation:** Oyuncuların belirli roller (Tank, Heal, DPS, Support) için geçerli silahları seçmesini sağlar.
* **💣 Dynamic Slots:** Caller'lar tarafından anlık olarak "Bomb Squad" veya ekstra slotlar eklenebilir.
* **📊 Attendance System:** Oyuncu katılım geçmişini ve rol tercihlerini takip eder (Arşivlenmiş veri desteği).
* **🔐 Discord Integration:** Discord OAuth2 ile güvenli giriş ve kimlik doğrulama.
* **⏳ Waitlist Management:** Ana parti dolduğunda oyuncular için otomatik taşma (overflow) sistemi.
* **🛠️ Staff Tools:** Adminler ve Caller'lar için şablonları ve rosterları yönetmek üzere özel panel.

---

## 💻 Tech Stack

* **Framework:** Laravel 12+ (PHP)
* **Database:** MySQL
* **Frontend:** Blade Templates, Vanilla JS (Drag & Drop), Custom CSS
* **Environment:** Docker & Docker Compose

---

## 🛠️ Getting Started (Local Development)

Follow steps for local development

### Needs
* Docker Desktop

### 1. Clone projkect
```bash
git clone https://github.com/badgeekduck/albion-party-manager.git
cd albion-party-manager
```

### 2. You need to set environments (Environment)
```bash
cp .env.example .env
APP_URL=http://localhost:8000

# Database Settings
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=albion_guild
DB_USERNAME=root
DB_PASSWORD=root

# Discord OAuth2 Settings
DISCORD_CLIENT_ID=your_client_id
DISCORD_CLIENT_SECRET=your_client_secret
DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback
```

### 3. Start container
```bash
docker-compose up -d --build
```

### 4. Install dependencies
```bash
docker-compose exec app composer install
```

### 5. ready to make db with migrations and generate a token for app
```bash
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
```

### 6. You need Albion Online Items Data (Seed)
```bash
docker compose exec app php artisan db:seed --class=GameRoleSeeder
```

Setup is ready! Visit in your browser: http://localhost:8000


📂 Project Structure

* app/Http/Controllers: Dashboard, Attendance ve Party management logics.

* resources/views: Blade templates (party-screen.blade.php, dashboard.blade.php).

* routes/web.php: Your routes.

📂 Roles & Permissions
* System Admin: Dashboard, tepmlates ve staff all acess.

* Content Creator: Can make a link and can make their builds.

* Member: Only visits links that shared from admin, caller, content-creator

🤝 Contributing
* Fork the repo

* New feature name (branch) create (git checkout -b feature/amazing-feature).

* Make sure save your changes (git commit -m 'Add amazing feature').

* Push your branch (git push origin feature/amazing-feature).

* Make a pull request.

![img.png](img.png)

![img_1.png](img_1.png)

![img_2.png](img_2.png)

![img_4.png](img_4.png)

![img_5.png](img_5.png)

![img_6.png](img_6.png)

![img_7.png](img_7.png)


## Credits

- [Harun Baş](https://github.com/badgeekluck)
- [Mircea Maldo](https://github.com/mirceamoldo)

## New Features

- NEW FEATURES ARE ON THE WAY!
- WE ARE COOKING!
