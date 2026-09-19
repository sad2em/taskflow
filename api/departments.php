<?php
/**
 * TaskFlow — Departments API
 * Actions: create, update, delete, toggle
 */

require_once __DIR__ . '/bootstrap.php';

$action = api_action();

switch ($action) {

    case 'create':
        require_permission('departments.manage');
        $name = (string)input('name', '');
        if (mb_strlen($name) < 2) { api_fail('Department name is too short.'); }
        if (Db::count('SELECT COUNT(*) FROM departments WHERE name = ?', [$name]) > 0) { api_fail('That department already exists.'); }

        $color = (string)input('color', '#6366f1');
        $id = Db::insert('departments', [
            'name'        => mb_substr($name, 0, 100),
            'code'        => mb_substr(strtoupper((string)input('code', '')), 0, 20) ?: null,
            'description' => (string)input('description', '') ?: null,
            'color'       => preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : '#6366f1',
            'manager_id'  => int_input('manager_id') > 0 ? int_input('manager_id') : null,
            'is_active'   => 1,
        ]);
        activity('department.create', 'department', $id, 'Created department ' . $name);
        api_ok(['id' => $id, 'message' => 'Department created.']);
        break;

    case 'update':
        require_permission('departments.manage');
        $id = int_input('id');
        $dept = Db::one('SELECT * FROM departments WHERE id = ?', [$id]);
        if (!$dept) { api_fail('Department not found.', 404); }

        $data = [];
        if (isset($_POST['name'])) {
            $name = mb_substr(trim((string)$_POST['name']), 0, 100);
            if ($name === '') { api_fail('Department name cannot be empty.'); }
            if (Db::count('SELECT COUNT(*) FROM departments WHERE name = ? AND id <> ?', [$name, $id]) > 0) {
                api_fail('Another department already uses that name.');
            }
            $data['name'] = $name;
        }
        if (isset($_POST['code']))        { $data['code'] = mb_substr(strtoupper(trim((string)$_POST['code'])), 0, 20) ?: null; }
        if (isset($_POST['description'])) { $data['description'] = trim((string)$_POST['description']) ?: null; }
        if (isset($_POST['color']))       { $data['color'] = preg_match('/^#[0-9a-f]{6}$/i', (string)$_POST['color']) ? $_POST['color'] : $dept['color']; }
        if (isset($_POST['manager_id']))  { $data['manager_id'] = int_input('manager_id') > 0 ? int_input('manager_id') : null; }

        if (!$data) { api_fail('Nothing to update.'); }
        Db::update('departments', $data, 'id = :id', ['id' => $id]);
        activity('department.update', 'department', $id, 'Updated department ' . ($data['name'] ?? $dept['name']));
        api_ok(['message' => 'Department updated.']);
        break;

    case 'delete':
        require_permission('departments.manage');
        $id = int_input('id');
        $dept = Db::one('SELECT * FROM departments WHERE id = ?', [$id]);
        if (!$dept) { api_fail('Department not found.', 404); }
        $members = Db::count('SELECT COUNT(*) FROM users WHERE department_id = ?', [$id]);
        if ($members > 0) {
            api_fail('Move the ' . $members . ' member(s) of this department first.', 409);
        }
        Db::run('UPDATE projects SET department_id = NULL WHERE department_id = ?', [$id]);
        Db::run('DELETE FROM departments WHERE id = ?', [$id]);
        activity('department.delete', 'department', $id, 'Deleted department ' . $dept['name']);
        api_ok(['message' => 'Department deleted.']);
        break;

    default:
        api_fail('Unknown action.', 404);
}
