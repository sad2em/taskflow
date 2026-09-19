<?php
/**
 * TaskFlow — Roles & Permissions (dynamic, editable from the admin panel)
 */

/**
 * Per-request permission cache.
 * Deliberately NOT a session cache: role permissions are edited from the admin
 * panel while other people are signed in, and a session copy would keep serving
 * the old set until they signed out again.
 */
function permission_cache(?array $set = null, bool $clear = false): ?array
{
    static $cache = null;
    if ($clear)        { $cache = null; return null; }
    if ($set !== null) { $cache = $set; }
    return $cache;
}

/** Load (or refresh) the permission set of a user straight from the database. */
function load_user_permissions(int $userId): array
{
    $perms = [];
    try {
        $rows = Db::all(
            'SELECT p.slug FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = (SELECT role_id FROM users WHERE id = ?)',
            [$userId]
        );
        foreach ($rows as $r) {
            $perms[] = $r['slug'];
        }
    } catch (Throwable $e) {
        return [];
    }

    // Only cache when we loaded the *signed-in* user, never another account.
    if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
        permission_cache($perms);
        $_SESSION['permissions'] = $perms; // kept for backwards compatibility only
    }
    return $perms;
}

function permissions(): array
{
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return [];
    }
    $cached = permission_cache();
    if (is_array($cached)) {
        return $cached;
    }
    return load_user_permissions($uid);
}

/**
 * can('tasks.edit')            -> single permission
 * can(['tasks.edit','tasks.assign']) -> ANY of them
 */
function can($slug): bool
{
    $role = (string)(current_user()['role_slug'] ?? '');
    if ($role === 'super_admin') {
        return true;
    }
    $owned = permissions();
    foreach ((array)$slug as $s) {
        if (in_array($s, $owned, true)) {
            return true;
        }
    }
    return false;
}

/** True only when the user owns every listed permission. */
function can_all(array $slugs): bool
{
    foreach ($slugs as $s) {
        if (!can($s)) {
            return false;
        }
    }
    return true;
}

function role_is(string ...$slugs): bool
{
    $role = (string)(current_user()['role_slug'] ?? '');
    return in_array($role, $slugs, true);
}

/* ========================= Data scoping ========================= */

/**
 * Determine how much of the system a user may see.
 * @return string all|project|own
 */
function data_scope(): string
{
    if (can('scope.all'))     { return 'all'; }
    if (can('scope.project')) { return 'project'; }
    if (can('scope.own'))     { return 'own'; }
    return 'own';
}

/**
 * Build a reusable WHERE fragment + params that restricts tasks to what the
 * current user is allowed to see.
 *
 * @param string $taskAlias table alias used in the outer query
 */
function scope_tasks_sql(string $taskAlias = 't'): array
{
    $scope = data_scope();
    $uid = user_id();

    if ($scope === 'all') {
        return ['sql' => '1=1', 'params' => []];
    }

    if ($scope === 'project') {
        return [
            'sql' => "(EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = {$taskAlias}.project_id AND pm.user_id = ?) "
                   . "OR {$taskAlias}.assignee_id = ? OR {$taskAlias}.reporter_id = ?)",
            'params' => [$uid, $uid, $uid],
        ];
    }

    return [
        'sql' => "({$taskAlias}.assignee_id = ? OR {$taskAlias}.reporter_id = ? "
               . "OR EXISTS (SELECT 1 FROM task_watchers tw WHERE tw.task_id = {$taskAlias}.id AND tw.user_id = ?))",
        'params' => [$uid, $uid, $uid],
    ];
}

/** Same as scope_tasks_sql() but for the projects table. */
function scope_projects_sql(string $alias = 'p'): array
{
    $scope = data_scope();
    $uid = user_id();

    if ($scope === 'all') {
        return ['sql' => '1=1', 'params' => []];
    }

    return [
        'sql' => "({$alias}.visibility = 'public' OR {$alias}.owner_id = ? "
               . "OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = {$alias}.id AND pm.user_id = ?))",
        'params' => [$uid, $uid],
    ];
}

/** May the current user see this specific project? */
function can_view_project(array $project): bool
{
    if (data_scope() === 'all') { return true; }
    $uid = user_id();
    if ((int)$project['owner_id'] === $uid) { return true; }
    if (($project['visibility'] ?? 'public') === 'public') { return true; }
    return Db::count('SELECT COUNT(*) FROM project_members WHERE project_id = ? AND user_id = ?',
        [(int)$project['id'], $uid]) > 0;
}

/** May the current user manage this project (edit details / members)? */
function can_manage_project(array $project): bool
{
    if (can('projects.edit') === false) { return false; }
    $uid = user_id();
    if ((int)$project['owner_id'] === $uid) { return true; }
    if (data_scope() === 'all' && can('projects.edit')) { return true; }
    $membership = Db::one('SELECT project_role FROM project_members WHERE project_id = ? AND user_id = ?',
        [(int)$project['id'], $uid]);
    return $membership && in_array($membership['project_role'], ['owner', 'manager'], true);
}

/** Is the user a member of the project? */
function is_project_member(int $projectId, ?int $userId = null): bool
{
    $userId = $userId ?? user_id();
    return Db::count('SELECT COUNT(*) FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $userId]) > 0;
}

function project_role_of(int $projectId, ?int $userId = null): ?string
{
    $userId = $userId ?? user_id();
    $row = Db::one('SELECT project_role FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $userId]);
    return $row['project_role'] ?? null;
}

/* ========================= Role helpers ========================= */

function all_roles(): array
{
    return Db::all('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS users_count
                    FROM roles r ORDER BY r.is_system DESC, r.id ASC');
}

function all_permissions_grouped(): array
{
    $grouped = [];
    foreach (Db::all('SELECT * FROM permissions ORDER BY module ASC, id ASC') as $p) {
        $grouped[$p['module']][] = $p;
    }
    return $grouped;
}

function role_permission_ids(int $roleId): array
{
    return array_map('intval', array_column(
        Db::all('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$roleId]),
        'permission_id'
    ));
}

/**
 * Canonical permission defaults for built-in operational roles.
 * Keeping these in one place lets administrators safely restore the defaults
 * without having to manually tick every permission.
 */
function default_role_permission_slugs(string $roleSlug): array
{
    $defaults = [
        'super_admin' => ['*'],
        'supervisor' => [
            'dashboard.view','users.view','departments.view','projects.view','projects.create','projects.edit',
            'tasks.view','tasks.create','tasks.edit','tasks.assign','tasks.comment','tasks.attach','tasks.time',
            'reports.view','notifications.view','settings.profile','scope.project'
        ],
        'employee' => [
            'dashboard.view','users.view','projects.view','tasks.view','tasks.create','tasks.edit','tasks.comment',
            'tasks.attach','tasks.time','notifications.view','settings.profile','scope.own'
        ],
        'member' => [
            'dashboard.view','users.view','projects.view','tasks.view','tasks.create','tasks.edit','tasks.comment',
            'tasks.attach','tasks.time','notifications.view','settings.profile','scope.own'
        ],
        'viewer' => [
            'dashboard.view','projects.view','tasks.view','notifications.view','settings.profile','scope.own'
        ],
        'team_lead' => [
            'dashboard.view','users.view','departments.view','projects.view','projects.create','projects.edit',
            'tasks.view','tasks.create','tasks.edit','tasks.assign','tasks.comment','tasks.attach','tasks.time',
            'reports.view','notifications.view','settings.profile','scope.project'
        ],
        'manager' => ['__manager__'],
    ];
    return $defaults[$roleSlug] ?? $defaults['member'];
}

/** Apply the canonical defaults to a role. Returns the number of permissions granted. */
function apply_default_role_permissions(int $roleId): int
{
    $role = Db::one('SELECT id, slug FROM roles WHERE id = ?', [$roleId]);
    if (!$role) { return 0; }

    $slug = (string)$role['slug'];
    $all = Db::all('SELECT id, slug FROM permissions ORDER BY id ASC');
    $bySlug = [];
    foreach ($all as $permission) { $bySlug[(string)$permission['slug']] = (int)$permission['id']; }

    $slugs = default_role_permission_slugs($slug);
    if ($slugs === ['*']) {
        $ids = array_values(array_map('intval', array_column($all, 'id')));
    } elseif ($slugs === ['__manager__']) {
        $blocked = ['roles.manage','settings.system','users.delete'];
        $ids = [];
        foreach ($all as $permission) {
            if (!in_array((string)$permission['slug'], $blocked, true)) {
                $ids[] = (int)$permission['id'];
            }
        }
    } else {
        $ids = [];
        foreach ($slugs as $slugItem) {
            if (isset($bySlug[$slugItem])) { $ids[] = $bySlug[$slugItem]; }
        }
    }

    sync_role_permissions($roleId, $ids);
    return count($ids);
}

function sync_role_permissions(int $roleId, array $permissionIds): void
{
    Db::run('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
    $ids = array_values(array_unique(array_map('intval', $permissionIds)));
    $sql = Db::isSqlite()
        ? 'INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)'
        : 'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)';
    foreach ($ids as $pid) {
        Db::run($sql, [$roleId, $pid]);
    }
    // Everyone else picks the new set up on their next request (permissions() reads
    // the database). We only need to drop the cache of the admin doing the editing.
    permission_cache(null, true);
    unset($_SESSION['permissions']);
    if (user_id() > 0) {
        load_user_permissions(user_id());
    }
}