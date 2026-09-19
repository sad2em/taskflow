# TaskFlow

> A lightweight, self-hosted project and task management system for teams.

TaskFlow helps teams organize projects, manage tasks, track progress, collaborate, and monitor productivity from one place.

Built with **PHP, MySQL/MariaDB, HTML, CSS, and Vanilla JavaScript** with a focus on simplicity, maintainability, and practical workflows.

## ✨ Features

- 📁 Project and task management
- 📋 Kanban board
- ✅ Tasks and subtasks
- 👥 Teams and departments
- 🔐 Roles and permissions
- 📊 Dashboard and reports
- 🔔 Notifications
- 💬 Comments and mentions
- 📎 File attachments
- ⏱️ Time tracking
- 📝 Activity history
- 🌍 English + Arabic localization
- ↔️ LTR / RTL interface support
- 🛡️ Authentication and security controls

> **Arabic support is currently partial and still being improved.** Some screens, components, and translations may not yet be fully optimized for Arabic and RTL.

## 🧰 Tech Stack

- **Backend:** PHP 8+
- **Database:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Database Access:** PDO
- **Authentication:** PHP Sessions
- **API:** PHP endpoints

No React, Vue, Laravel, Node.js, npm, or frontend build pipeline is required.

## ⚡ Quick Start

### Requirements

- PHP 8+
- MySQL 5.7+ or MariaDB 10.4+
- PDO MySQL extension

### Installation

```bash
git clone https://github.com/YOUR_USERNAME/taskflow.git
cd taskflow
```

Create a database named `taskflow`, then import:

```text
database/setup.sql
```

Copy:

```text
config/env.example.php
```

to:

```text
config/env.php
```

and configure your database credentials.

Run locally:

```bash
php -S 127.0.0.1:8099
```

Then open:

```text
http://127.0.0.1:8099
```

For Windows and Linux/macOS helper scripts, see `serve.bat` and `serve.sh`.

## 👤 Demo Accounts

The development database includes demo accounts for testing.

| Role | Email | Password |
|---|---|---|
| Admin | `admin@taskflow.test` | `Admin@123` |
| Manager | `sara@taskflow.test` | `Passw0rd!` |
| Team Lead | `omar@taskflow.test` | `Passw0rd!` |
| Supervisor | `lina@taskflow.test` | `Passw0rd!` |
| Member | `jad@taskflow.test` | `Passw0rd!` |

> Change or remove demo credentials before production deployment.

## 🛡️ Security

TaskFlow includes:

- Password hashing
- CSRF protection
- Session management
- Login throttling
- Role-based authorization
- Permission checks
- PDO prepared statements
- Upload restrictions

See [`SECURITY.md`](SECURITY.md) for additional guidance.

## 🤝 Contributing

Contributions, suggestions, and improvements are welcome.

Before submitting changes:

1. Test the application locally.
2. Keep changes focused.
3. Do not commit credentials or `config/env.php`.
4. Test both English and Arabic when changing the UI.
5. Document important changes.

See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## 🗺️ Roadmap

TaskFlow is actively evolving. Future improvements may include:

- More complete Arabic localization and RTL coverage
- Expanded reports and analytics
- Automated testing
- Extended API documentation
- Additional integrations
- Improved deployment tooling

## 👨‍💻 Author

### Asaad Eido

Web Pentester & Creator of **TaskFlow**.

I build practical software with a focus on simplicity, maintainability, and real-world usability.

### Connect

- 📢 **Telegram:** [Cyber Horizon](https://t.me/cyber_horizon_channel)
- 💼 **LinkedIn:** [Asaad Eido](https://www.linkedin.com/in/asaad-eido-515b20273/)

---

## ❤️ Final Note

TaskFlow started with a simple idea:

> **Make managing work easier without making the software harder to use.**

The project is still evolving, and there is always room for better features, stronger testing, and a more polished experience.

Thanks for checking out **TaskFlow**.

If you find the project useful, consider giving it a ⭐ and sharing your feedback.

**Built with PHP, MySQL, Vanilla JavaScript & a lot of ☕**

© Asaad Eido — TaskFlow
