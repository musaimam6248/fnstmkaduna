# FNSTM Staff Excellence Portal

Full PHP web application for:
- Public homepage (first display)
- Admin dashboard
- Department dashboard
- Image management with resizing
- Role-based authentication
- Analytics charts
- Excel/PDF/print reporting

## Stack
- PHP 8+
- SQLite (default via PDO)
- Offline local UI assets (Bootstrap-like, chart-lite, lightbox-lite)

## Implemented Features

### Homepage (`index.php`)
- Navbar
- Current winner (image + name + reasons)
- Submission window display (open and close time set by admin)
- Past winners carousel
- Event gallery with lightbox
- Event video highlights (admin-uploaded short clips)
- Committee members cards
- Department info (vision, mission, objectives)
- Analytics charts
- All images pulled dynamically from database file names
- Mobile-responsive layout

### Admin Dashboard (`admin/dashboard.php`)
- View all department submissions
- Search/filter/pagination
- Select monthly winner with reasons
- Set submission open/close time window for departments
- Manage committee members
- Upload/manage gallery images
- Upload/manage event video clips
- Analytics charts
- Export reports (Excel-compatible CSV + PDF)
- Print report
- Send notifications/reminders (+ email logging)
- Update department vision/mission/objectives

### Department Dashboard (`department/dashboard.php`)
- Staff submission form (name, rank, photo)
- Selection submission (choose staff + 2 reasons)
- Selection submission is allowed only during the admin-defined open window
- Submission history
- Notifications from admin

### Image Pipeline
- Upload and save images to:
  - `uploads/staff`
  - `uploads/gallery`
  - `uploads/committee`
  - `uploads/winners`
- Store file names in database
- Resize images for consistency (GD-enabled PHP)
- Displayed on homepage and dashboards

### Auth & Security
- Login page for admin and departments
- Department registration (HOD, password confirmation)
- Forgot password for department login using HOD name as reset key
- Password hashing (`password_hash`)
- Session management
- Role-based access control

### Database
Schema in `database/schema.sql`:
- `users`
- `department`
- `staff`
- `submission`
- `winner`
- `gallery`
- `event_video`
- `committee`
- `department_info`
- `notifications`
- `email_logs`
- `submission_schedule`

Includes relationships and indexes for search/filter/pagination performance.

## Quick Start
1. Ensure PHP 8+ with extensions:
   - `pdo_sqlite`
   - `gd` (recommended for resize)
2. From project root, run:
   ```bash
   php database/migrate.php
   php -S localhost:8000
   ```
3. Open:
   - `http://localhost:8000/`

## Offline Start (Windows)
Use the included launcher to run fully offline without needing internet access.

1. Double-click `start-offline.bat`
2. Or run:
   ```powershell
   .\start-offline.ps1
   ```
3. Open:
   - `http://127.0.0.1:8000/`

Notes:
- The script auto-finds `php.exe` (or uses `PHP_EXE` env var if set).
- It loads required extensions (`pdo_sqlite`, `sqlite3`, `gd`, `mbstring`), runs migration, and starts the server.
- If port `8000` is busy, run:
  ```powershell
  .\start-offline.ps1 -Port 8001
  ```

## Online Update (Windows)
Use this before starting online mode to apply DB/schema updates and verify required PHP extensions.

1. Double-click `online-update.bat`
2. Or run:
   ```powershell
   .\online-update.ps1
   ```

Then start online server:
```powershell
.\start-online.ps1
```
## Online Start (Windows)
Use this to expose the app on your local network (LAN).

1. Double-click `start-online.bat`
2. Or run:
   ```powershell
   .\start-online.ps1
   ```
3. Open:
   - `http://localhost:8000/`
   - `http://<your-lan-ip>:8000/` (from another device on same network)

Notes:
- This mode binds to `0.0.0.0` so other devices on your network can access it.
- If port `8000` is busy, run:
  ```powershell
  .\start-online.ps1 -Port 8001
  ```
## Default Admin Login
- Username: `admin`
- Password: `admin12345`

You can override with env vars:
- `ADMIN_DEFAULT_USERNAME`
- `ADMIN_DEFAULT_PASSWORD`
- `DB_DSN` (for custom database)


