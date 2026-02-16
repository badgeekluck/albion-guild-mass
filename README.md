⚔️ Albion Online Party Manager
A web-based roster management tool designed to replace spreadsheets for ZvZ parties. Features visual role selection, drag-and-drop management, automatic waitlists, and attendance tracking.

🔥 Key Features
Visual Roster: Drag & Drop interface for managing party slots.

Role Validation: Ensures players select valid weapons for specific roles (Tank, Heal, DPS, Support).

Dynamic Slots: "Bomb Squad" or extra slots can be added instantly by callers.

Attendance System: Tracks player participation history and role preferences (Archived data supported).

Discord Integration: Secure login and authentication via Discord OAuth2.

Waitlist Management: Auto-overflow system for players when the main party is full.

Staff Tools: Special dashboard for Admins and Callers to manage templates and rosters.

🛠️ Tech Stack
Framework: Laravel 12+ (PHP)

Database: MySQL

Frontend: Blade Templates, Vanilla JS (Drag & Drop), Custom CSS

Environment: Docker & Docker Compose

🚀 Getting Started (Local Development)
Follow these steps to get the project running on your local machine.

Prerequisites
Docker Desktop

Git

1. Clone the Repository
   Bash
   git clone https://github.com/badgeekluck/albion-party-manager.git
   cd albion-party-manager
2. Environment Setup
   Copy the example environment file and configure it.

Bash
cp .env.example .env
Open .env and make sure your database and app settings are correct. Crucially, set up your Discord Developer credentials:

Ini, TOML
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=albion_guild
DB_USERNAME=root
DB_PASSWORD=root

# Discord OAuth2
DISCORD_CLIENT_ID=your_client_id
DISCORD_CLIENT_SECRET=your_client_secret
DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback
3. Build and Start Containers
   Bash
   docker-compose up -d --build
4. Install Dependencies
   Run these commands inside the container:

Bash
# Install PHP dependencies
docker-compose exec app composer install

# Generate App Key
docker-compose exec app php artisan key:generate
5. Database Setup
   Run migrations to create tables (Shared Links, Attendees, Roles, etc.).

Bash
docker-compose exec app php artisan migrate

# (Optional) Seed the database with default roles/weapons
docker-compose exec app php artisan db:seed
6. Access the App
   Open your browser and visit:

http://localhost:8000
📜 Common Commands
Here are some useful commands for development:

Clear Cache:

Bash
docker-compose exec app php artisan optimize:clear
Create a new Migration:

Bash
docker-compose exec app php artisan make:migration create_new_table
Access Database CLI:

Bash
docker-compose exec db mysql -u root -p
📂 Project Structure
app/Models: Database models (SharedLink, LinkAttendee, etc.).

app/Http/Controllers: Logic for Dashboard, Attendance, and Party management.

resources/views: Blade templates (party-screen.blade.php, dashboard.blade.php).

routes/web.php: All application routes.

🛡️ Role & Permission System
Admin: Full access to dashboard, templates, and staff list.

Content Creator: Can create links and manage their own parties.

Member: Can only join parties via shared links.

Note: Roles are managed via the users table in the database.

🤝 Contributing
Fork the repository.

Create a new feature branch (git checkout -b feature/amazing-feature).

Commit your changes (git commit -m 'Add amazing feature').

Push to the branch (git push origin feature/amazing-feature).

Open a Pull Request.
