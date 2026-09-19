-- TaskFlow database setup
-- MySQL 5.7+ / MariaDB 10.4+
-- Idempotent: safe to run on a new database and on an existing TaskFlow database.
-- Demo accounts are included for local evaluation; change/remove them before production use.

-- TaskFlow schema (MySQL / MariaDB). Matches the application schema and setup script.
-- SQLite installs are converted at import time.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `description` text,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(60) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text,
  `color` varchar(16) DEFAULT '#6366f1',
  `manager_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `job_title` varchar(120) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `color` varchar(16) DEFAULT '#6366f1',
  `avatar` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `locale` varchar(5) NOT NULL DEFAULT 'en',
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `department_id` (`department_id`),
  KEY `manager_id` (`manager_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `key` varchar(80) NOT NULL,
  `value` text,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `code` varchar(12) NOT NULL,
  `description` text,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `priority` varchar(20) NOT NULL DEFAULT 'medium',
  `visibility` varchar(20) NOT NULL DEFAULT 'public',
  `owner_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `budget` decimal(12,2) DEFAULT NULL,
  `progress` int(11) NOT NULL DEFAULT 0,
  `task_counter` int(11) NOT NULL DEFAULT 0,
  `color` varchar(16) DEFAULT '#6366f1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `owner_id` (`owner_id`),
  KEY `department_id` (`department_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_projects_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projects_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_members` (
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `project_role` varchar(20) NOT NULL DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`project_id`, `user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_pm_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `task_key` varchar(40) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `status` varchar(20) NOT NULL DEFAULT 'todo',
  `priority` varchar(20) NOT NULL DEFAULT 'medium',
  `assignee_id` int(11) DEFAULT NULL,
  `reporter_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `estimated_hours` decimal(8,2) DEFAULT NULL,
  `actual_hours` decimal(8,2) NOT NULL DEFAULT 0.00,
  `progress` int(11) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL DEFAULT 0,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_key` (`task_key`),
  KEY `project_id` (`project_id`),
  KEY `parent_id` (`parent_id`),
  KEY `assignee_id` (`assignee_id`),
  KEY `reporter_id` (`reporter_id`),
  KEY `status` (`status`),
  KEY `due_date` (`due_date`),
  CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_parent` FOREIGN KEY (`parent_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_assignee` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_watchers` (
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`task_id`, `user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_tw_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tw_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `fk_comments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `size` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_att_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `time_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hours` decimal(8,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `log_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`),
  KEY `log_date` (`log_date`),
  CONSTRAINT `fk_tl_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `type` varchar(40) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` varchar(1000) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_unread` (`user_id`, `is_read`),
  KEY `actor_id` (`actor_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `meta` JSON DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `entity` (`entity_type`, `entity_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(250) DEFAULT NULL,
  `payload` mediumtext,
  `last_activity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `last_activity` (`last_activity`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip_time` (`ip_address`, `attempted_at`),
  KEY `email_time` (`email`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- Keep the setup script safe for older TaskFlow databases that predate the locale column.
SET @has_locale := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'locale');
SET @add_locale_sql := IF(@has_locale = 0, 'ALTER TABLE users ADD COLUMN locale varchar(5) NOT NULL DEFAULT ''en'' AFTER email_notifications', 'SELECT 1');
PREPARE taskflow_locale_stmt FROM @add_locale_sql;
EXECUTE taskflow_locale_stmt;
DEALLOCATE PREPARE taskflow_locale_stmt;

-- ============================================================
--  TaskFlow — Seed data (roles, permissions, departments, demo users)
--  Default admin login : admin@taskflow.test / Admin@123
-- ============================================================

INSERT IGNORE INTO roles (name, slug, description, is_system) VALUES
 ('Super Admin','super_admin','Full unrestricted access to every module and setting',1),
 ('Manager','manager','Manages projects, assigns tasks and reviews team output',1),
 ('Team Lead','team_lead','Leads a department, can create and delegate tasks',1),
 ('Supervisor','supervisor','Supervises team members, projects and task delegation',1),
 ('Member','member','Works on tasks assigned to them',1),
 ('Viewer','viewer','Read-only access to projects and tasks',1);

INSERT IGNORE INTO permissions (module, slug, description) VALUES
 ('Dashboard','dashboard.view','View the main dashboard and KPI widgets'),
 ('Users','users.view','View team members list and profiles'),
 ('Users','users.create','Add new team members'),
 ('Users','users.edit','Edit team member details and status'),
 ('Users','users.delete','Deactivate or delete team members'),
 ('Roles','roles.view','View roles and permission matrix'),
 ('Roles','roles.manage','Create roles and change their permissions'),
 ('Departments','departments.view','View departments'),
 ('Departments','departments.manage','Create, edit and delete departments'),
 ('Projects','projects.view','View projects'),
 ('Projects','projects.create','Create new projects'),
 ('Projects','projects.edit','Edit project details and members'),
 ('Projects','projects.delete','Delete or archive projects'),
 ('Tasks','tasks.view','View tasks'),
 ('Tasks','tasks.create','Create tasks and sub-tasks'),
 ('Tasks','tasks.edit','Edit tasks (own projects)'),
 ('Tasks','tasks.delete','Delete tasks'),
 ('Tasks','tasks.assign','Assign tasks to team members'),
 ('Tasks','tasks.comment','Post comments and mentions'),
 ('Tasks','tasks.attach','Upload and delete attachments'),
 ('Tasks','tasks.time','Log work time on tasks'),
 ('Reports','reports.view','View reports, charts and productivity analytics'),
 ('Reports','reports.export','Export reports to CSV'),
 ('Activity','activity.view','View the system activity / audit log'),
 ('Notifications','notifications.view','Receive and view notifications'),
 ('Settings','settings.company','Edit company profile settings'),
 ('Settings','settings.system','Edit system preferences (theme, limits, mail)'),
 ('Settings','settings.profile','Edit own profile and password'),
 ('Scopes','scope.own','Data scope: only own assigned items'),
 ('Scopes','scope.project','Data scope: items in projects they belong to'),
 ('Scopes','scope.all','Data scope: every item in the system');

-- Super Admin : everything
INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r, permissions p WHERE r.slug='super_admin';

-- Manager : everything except roles.manage, settings.system, users.delete
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r, permissions p
 WHERE r.slug='manager' AND p.slug NOT IN ('roles.manage','settings.system','users.delete');

-- Team Lead
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r, permissions p
 WHERE r.slug='team_lead' AND p.slug IN
 ('dashboard.view','users.view','departments.view','projects.view','projects.create','projects.edit',
  'tasks.view','tasks.create','tasks.edit','tasks.assign','tasks.comment','tasks.attach','tasks.time',
  'reports.view','notifications.view','settings.profile','scope.project');

-- Supervisor : practical team supervision defaults
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r, permissions p
 WHERE r.slug='supervisor' AND p.slug IN
 ('dashboard.view','users.view','departments.view','projects.view','projects.create','projects.edit',
  'tasks.view','tasks.create','tasks.edit','tasks.assign','tasks.comment','tasks.attach','tasks.time',
  'reports.view','notifications.view','settings.profile','scope.project');

-- Member
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r, permissions p
 WHERE r.slug='member' AND p.slug IN
 ('dashboard.view','users.view','projects.view','tasks.view','tasks.create','tasks.edit','tasks.comment',
  'tasks.attach','tasks.time','notifications.view','settings.profile','scope.own');

-- Viewer
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r, permissions p
 WHERE r.slug='viewer' AND p.slug IN
 ('dashboard.view','projects.view','tasks.view','notifications.view','settings.profile','scope.own');

INSERT IGNORE INTO departments (name, code, description, color) VALUES
 ('Development','DEV','Software engineering, backend and frontend squads','#6366f1'),
 ('Design','DSG','UI/UX design, branding and design systems','#ec4899'),
 ('Marketing','MKT','Growth, content, SEO and paid campaigns','#f59e0b'),
 ('Quality Assurance','QA','Manual and automated testing','#10b981'),
 ('Customer Support','SUP','Customer success, tickets and helpdesk','#06b6d4'),
 ('Human Resources','HR','Recruitment, onboarding and people ops','#8b5cf6');

-- password for every demo account : Passw0rd!   (admin: Admin@123)
INSERT IGNORE INTO users (name,email,password_hash,role_id,department_id,job_title,phone,color,status,locale) VALUES
 ('Adam Haddad','admin@taskflow.test','$2y$10$gOOWZMRCvJub32olSDDCC.qLqSm4HtEfMVNsO37TsJdQ0y8rZT.si',(SELECT id FROM roles WHERE slug='super_admin'),(SELECT id FROM departments WHERE name='Development'),'Chief Technology Officer','+961 3 000 001','#6366f1','active','en'),
 ('Sara Mansour','sara@taskflow.test','$2y$10$Vj7m8R5n/M3CDdk/GX3GheKKkrDElQSs5CNjWntKCwyoNYiCEaK1a',(SELECT id FROM roles WHERE slug='manager'),(SELECT id FROM departments WHERE name='Development'),'Engineering Manager','+961 3 000 002','#ec4899','active','en'),
 ('Omar Khaled','omar@taskflow.test','$2y$10$Vj7m8R5n/M3CDdk/GX3GheKKkrDElQSs5CNjWntKCwyoNYiCEaK1a',(SELECT id FROM roles WHERE slug='team_lead'),(SELECT id FROM departments WHERE name='Design'),'Lead UI/UX Designer','+961 3 000 003','#f59e0b','active','en'),
 ('Lina Nassar','lina@taskflow.test','$2y$10$Vj7m8R5n/M3CDdk/GX3GheKKkrDElQSs5CNjWntKCwyoNYiCEaK1a',(SELECT id FROM roles WHERE slug='supervisor'),(SELECT id FROM departments WHERE name='Development'),'Backend Developer','+961 3 000 004','#10b981','active','en'),
 ('Karim Fares','karim@taskflow.test','$2y$10$Vj7m8R5n/M3CDdk/GX3GheKKkrDElQSs5CNjWntKCwyoNYiCEaK1a',(SELECT id FROM roles WHERE slug='supervisor'),(SELECT id FROM departments WHERE name='Marketing'),'Growth Marketer','+961 3 000 005','#06b6d4','active','en'),
 ('Maya Rizk','maya@taskflow.test','$2y$10$Vj7m8R5n/M3CDdk/GX3GheKKkrDElQSs5CNjWntKCwyoNYiCEaK1a',(SELECT id FROM roles WHERE slug='supervisor'),(SELECT id FROM departments WHERE name='Quality Assurance'),'QA Engineer','+961 3 000 006','#8b5cf6','active','en'),
 ('Jad Youssef','jad@taskflow.test','$2y$10$Vj7m8R5n/M3CDdk/GX3GheKKkrDElQSs5CNjWntKCwyoNYiCEaK1a',(SELECT id FROM roles WHERE slug='member'),(SELECT id FROM departments WHERE name='Customer Support'),'Support Agent','+961 3 000 007','#ef4444','active','en');

INSERT IGNORE INTO settings (`key`,`value`) VALUES
 ('company_name','TaskFlow Technologies'),
 ('company_logo',''),
 ('company_email','hello@taskflow.test'),
 ('company_phone','+961 1 000 000'),
 ('company_address','Beirut, Lebanon'),
 ('theme','light'),
 ('locale','en'),
 ('items_per_page','12'),
 ('max_upload_mb','8'),
 ('allowed_extensions','jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip,txt,md,svg'),
 ('mail_enabled','0'),
 ('mail_from','no-reply@taskflow.test'),
 ('mail_from_name','TaskFlow'),
 ('due_soon_days','3'),
 ('allow_registration','0');

SET FOREIGN_KEY_CHECKS = 1;
