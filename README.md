# TaskFlow

A lightweight project and task management app built with PHP, MySQL/MariaDB, HTML, CSS and vanilla JavaScript.

TaskFlow is designed for small teams that need projects, tasks, a Kanban board, team management, roles and permissions, reports, notifications, activity history, and Arabic/English UI support without a frontend framework.

## Highlights

- Projects, tasks, subtasks and Kanban workflow
- Team members, departments and project membership
- Roles and granular permissions with built-in defaults
- Arabic and English with automatic RTL/LTR layout switching
- Comments, mentions, attachments and time tracking
- Notifications and activity/audit log
- Reports and productivity views
- CSRF protection, password hashing and login throttling
- MySQL/MariaDB with PDO
- No build step and no frontend dependencies

## Requirements

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.4+
- PDO MySQL extension
- Apache/XAMPP or PHP's built-in development server

## Quick start

### 1. Create the database

Create an empty database named `taskflow`, then import:

```text
taskflow/database/setup.sql
```

The setup file creates the schema, roles, permissions, demo data and settings. It also adds the account locale column when it is missing, so the same file can be used to bring an older TaskFlow database up to date.

### 2. Configure the database

Copy:

```text
taskflow/config/env.example.php
```

to:

```text
taskflow/config/env.php
```

Then set your MySQL/MariaDB credentials. For a default XAMPP installation, the example values are usually enough.

`env.php` is local configuration and should not be committed.

### 3. Run locally

From the repository root:

```bash
./serve.sh
```

Then open:

```text
http://127.0.0.1:8099
```

On Windows, `serve.bat` can be used if PHP is available in `PATH`.

You can also serve the `taskflow/` directory through Apache/XAMPP.

## Demo account

The database setup includes demo accounts for local evaluation.

```text
Admin:    admin@taskflow.test / Admin@123
Manager:  sara@taskflow.test  / Passw0rd!
Team Lead: omar@taskflow.test / Passw0rd!
Supervisor: lina@taskflow.test / Passw0rd!
Member:   jad@taskflow.test   / Passw0rd!
```

Change or remove demo credentials before using the application with real data.

## Roles and permissions

Permissions are stored in the database rather than hard-coded into the UI. Built-in roles have recommended defaults, and administrators can restore those defaults from the Roles & Permissions screen.

The application uses role slugs when resolving built-in roles, so database auto-increment IDs do not need to match the seed data.

## Localization

English is the default language. Users can switch to Arabic from their profile/settings. The interface changes direction immediately and uses RTL layout for Arabic.

## Project structure

```text
.
├── serve.sh
├── serve.bat
├── README.md
└── taskflow/
    ├── api/          API endpoints
    ├── assets/       CSS and JavaScript
    ├── config/       Local configuration
    ├── database/     Database setup
    ├── includes/     Application services and helpers
    ├── pages/        Application pages
    ├── storage/      Runtime state
    ├── uploads/      User uploads
    └── index.php     Application entry point
```

## Production notes

- Set `APP_ENV` to `production` and keep `APP_DEBUG` disabled.
- Use a dedicated database user instead of `root`.
- Keep `config/env.php` out of version control.
- Keep `database/` inaccessible from the public web root. The included Apache rules already block direct access.
- Review and remove demo accounts before deployment.
- Back up the database before upgrades or permission changes.
- Restrict write access to `uploads/` to the web server account.

