<?php
/**
 * TaskFlow — Roles & Permissions API
 * Actions: create, update, delete, save_permissions
 */

require_once __DIR__ . '/bootstrap.php';

$action = api_action();

switch ($action) {

    case 'create':
        require_permission('roles.manage');
        $name = trim((string)input('name', ''));
        if (mb_strlen($name) < 2) { api_fail('Role name is too short.'); }
        $slug = slugify((string)input('slug', $name));
        if (Db::count('SELECT COUNT(*) FROM roles WHERE slug = ? OR name = ?', [$slug, $name]) > 0) {
            api_fail('That role already exists.');
        }
        $id = Db::insert('roles', [
            'name'        => mb_substr($name, 0, 60),
            'slug'        => mb_substr($slug, 0, 60),
            'description' => (string)input('description', '') ?: null,
            'is_system'   => 0,
        ]);

        // New roles get safe defaults automatically. Built-in-like names use their
        // corresponding default role; all other custom roles start from Member.
        $defaultSlug = in_array($slug, ['member','employee','supervisor','team_lead','manager','viewer'], true) ? $slug : 'member';
        $sourceRole = Db::one("SELECT id FROM roles WHERE slug = ?", [$defaultSlug]);
        if ($sourceRole && (int)$sourceRole['id'] !== $id) {
            sync_role_permissions($id, role_permission_ids((int)$sourceRole['id']));
        }
        activity('role.create', 'role', $id, 'Created role ' . $name);
        api_ok(['id' => $id, 'message' => 'Role created.']);
        break;

    case 'update':
        require_permission('roles.manage');
        $id = int_input('id');
        $role = Db::one('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$role) { api_fail('Role not found.', 404); }

        $data = [];
        if (isset($_POST['name'])) {
            $name = trim((string)$_POST['name']);
            if (mb_strlen($name) < 2) { api_fail('Role name is too short.'); }
            $data['name'] = mb_substr($name, 0, 60);
        }
        if (isset($_POST['description'])) { $data['description'] = trim((string)$_POST['description']) ?: null; }
        if (!$data) { api_fail('Nothing to update.'); }

        Db::update('roles', $data, 'id = :id', ['id' => $id]);
        activity('role.update', 'role', $id, 'Updated role ' . $role['name']);
        api_ok(['message' => 'Role updated.']);
        break;

    case 'apply_defaults':
        require_permission('roles.manage');
        $id = int_input('id');
        $role = Db::one('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$role) { api_fail('Role not found.', 404); }
        if ((string)$role['slug'] === 'super_admin') { api_fail('Super Admin already has full permissions.'); }

        $count = apply_default_role_permissions($id);
        activity('role.permissions.defaults', 'role', $id, 'Restored default permissions for role ' . $role['name']);
        api_ok([
            'message' => 'Default permissions restored for ' . $role['name'] . '.',
            'count' => $count,
            'role_id' => $id,
        ]);
        break;

    case 'save_permissions':
        require_permission('roles.manage');
        $id = int_input('id');
        $role = Db::one('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$role) { api_fail('Role not found.', 404); }

        $perms = array_input('permissions');
        if (!is_array($perms)) { $perms = []; }
        $valid = array_map('intval', array_column(Db::all('SELECT id FROM permissions'), 'id'));
        $perms = array_values(array_intersect(array_map('intval', $perms), $valid));

        if ($role['slug'] === 'super_admin' && count($perms) < count($valid)) {
            $perms = $valid; // Super Admin always keeps everything
        }
        if ($role['slug'] === 'super_admin' && !in_array((int)Db::value("SELECT id FROM permissions WHERE slug = 'scope.all'"), $perms, true)) {
            api_fail('The Super Admin role must keep full data scope.');
        }
        // Never let a role lose its dashboard access
        $dash = (int)Db::value("SELECT id FROM permissions WHERE slug = 'dashboard.view'");
        if ($dash && !in_array($dash, $perms, true)) { $perms[] = $dash; }

        sync_role_permissions($id, $perms);
        activity('role.permissions', 'role', $id, 'Updated permissions of role ' . $role['name'] . ' (' . count($perms) . ' granted)');
        api_ok(['message' => 'Permissions saved for ' . $role['name'] . '.', 'count' => count($perms)]);
        break;

    case 'delete':
        require_permission('roles.manage');
        $id = int_input('id');
        $role = Db::one('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$role) { api_fail('Role not found.', 404); }
        if ((int)$role['is_system'] === 1) { api_fail('Built-in roles cannot be deleted.'); }
        $users = Db::count('SELECT COUNT(*) FROM users WHERE role_id = ?', [$id]);
        if ($users > 0) { api_fail('Move the ' . $users . ' user(s) using this role to another role first.', 409); }

        Db::run('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
        Db::run('DELETE FROM roles WHERE id = ?', [$id]);
        activity('role.delete', 'role', $id, 'Deleted role ' . $role['name']);
        api_ok(['message' => 'Role deleted.']);
        break;

    default:
        api_fail('Unknown action.', 404);
}
