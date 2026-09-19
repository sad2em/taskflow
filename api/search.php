<?php
/**
 * TaskFlow — Unified global search (tasks, projects, people)
 * Used by the top-bar search box via assets/js/app.js
 */

require_once __DIR__ . '/bootstrap.php';

$term = trim((string)input('q', ''));
if (mb_strlen($term) < 2) {
    api_ok(['tasks' => [], 'projects' => [], 'users' => [], 'count' => 0]);
}

$like = '%' . $term . '%';
$taskScope = scope_tasks_sql('t');
$projScope = scope_projects_sql('p');

$tasks = Db::all(
    "SELECT t.id, t.task_key, t.title, t.status, t.priority, t.due_date, t.progress,
            p.name AS project_name, p.code AS project_code, p.color AS project_color,
            u.name AS assignee_name, u.color AS assignee_color, u.avatar AS assignee_avatar, u.job_title AS assignee_title
     FROM tasks t
     JOIN projects p ON p.id = t.project_id
     LEFT JOIN users u ON u.id = t.assignee_id
     WHERE t.is_archived = 0 AND (t.title LIKE ? OR t.task_key LIKE ? OR t.description LIKE ?) AND " . $taskScope['sql'] . "
     ORDER BY (t.status <> 'done') DESC, t.updated_at DESC
     LIMIT 6",
    array_merge([$like, $like, $like], $taskScope['params'])
);

$projects = Db::all(
    "SELECT p.id, p.name, p.code, p.color, p.status, p.progress
     FROM projects p
     WHERE (p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ?) AND " . $projScope['sql'] . "
     ORDER BY p.updated_at DESC LIMIT 4",
    array_merge([$like, $like, $like], $projScope['params'])
);

$users = can('users.view')
    ? Db::all(
        "SELECT u.id, u.name, u.email, u.color, u.avatar, u.job_title, d.name AS department_name
         FROM users u LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.name LIKE ? OR u.email LIKE ? OR u.job_title LIKE ?
         ORDER BY u.name ASC LIMIT 4",
        [$like, $like, $like]
    )
    : [];

api_ok([
    'tasks'    => $tasks,
    'projects' => $projects,
    'users'    => $users,
    'count'    => count($tasks) + count($projects) + count($users),
    'term'     => $term,
]);
