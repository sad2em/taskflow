<?php
/**
 * TaskFlow — Front controller / router
 *   index.php?page=dashboard
 *   index.php?page=task&id=12
 */

require_once __DIR__ . '/includes/app.php';

taskflow_require_database();
session_boot();

$routes = [
    'dashboard'    => ['file' => 'dashboard',    'auth' => true,  'permission' => 'dashboard.view',     'title' => 'Dashboard'],
    'board'        => ['file' => 'board',        'auth' => true,  'permission' => 'tasks.view',         'title' => 'Task Board'],
    'tasks'        => ['file' => 'tasks',        'auth' => true,  'permission' => 'tasks.view',         'title' => 'All Tasks'],
    'task'         => ['file' => 'task',         'auth' => true,  'permission' => 'tasks.view',         'title' => 'Task'],
    'projects'     => ['file' => 'projects',     'auth' => true,  'permission' => 'projects.view',      'title' => 'Projects'],
    'project'      => ['file' => 'project',      'auth' => true,  'permission' => 'projects.view',      'title' => 'Project'],
    'team'         => ['file' => 'team',         'auth' => true,  'permission' => 'users.view',         'title' => 'Team'],
    'profile'      => ['file' => 'profile',      'auth' => true,  'permission' => 'users.view',         'title' => 'Profile'],
    'departments'  => ['file' => 'departments',  'auth' => true,  'permission' => 'departments.view',   'title' => 'Departments'],
    'reports'      => ['file' => 'reports',      'auth' => true,  'permission' => 'reports.view',       'title' => 'Reports & Analytics'],
    'activity'     => ['file' => 'activity',     'auth' => true,  'permission' => 'activity.view',      'title' => 'Activity Log'],
    'notifications'=> ['file' => 'notifications','auth' => true,  'permission' => 'notifications.view', 'title' => 'Notifications'],
    'roles'        => ['file' => 'roles',        'auth' => true,  'permission' => 'roles.view',         'title' => 'Roles & Permissions'],
    'ux-audit'     => ['file' => 'ux-audit',     'auth' => true,  'permission' => 'settings.system',    'title' => 'UX Audit & Analytics'],
    'settings'     => ['file' => 'settings',     'auth' => true,  'permission' => ['settings.profile','settings.company','settings.system'], 'title' => 'Settings'],

    'login'        => ['file' => 'login',        'auth' => false, 'public' => true, 'title' => 'Sign in'],
    'logout'       => ['file' => 'logout',       'auth' => true,  'title' => 'Sign out'],
    'register'     => ['file' => 'register',     'auth' => false, 'public' => true, 'title' => 'Create account'],
    '403'          => ['file' => '403',          'auth' => false, 'public' => true, 'title' => 'Access denied'],
    '404'          => ['file' => '404',          'auth' => false, 'public' => true, 'title' => 'Page not found'],
];

$page = (string)query('page', 'dashboard');
$page = preg_replace('/[^a-z0-9_\-]/i', '', $page) ?: 'dashboard';

if (!isset($routes[$page])) {
    http_response_code(404);
    render('404', [], 'Page not found');
    exit;
}

$route = $routes[$page];

if (!empty($route['auth'])) {
    require_login();
}

if (!empty($route['public']) && is_logged_in() && in_array($page, ['login', 'register'], true)) {
    redirect(page_url('dashboard'));
}

csrf_guard();

if (!empty($route['permission'])) {
    require_permission($route['permission']);
}

$title = $route['title'] ?? ucfirst($page);

if (in_array($page, ['login', 'register', '403', '404'], true)) {
    render_blank($route['file'], [], $title);
} else {
    render($route['file'], [], $title);
}
