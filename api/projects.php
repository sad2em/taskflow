<?php
/**
 * TaskFlow — Projects API
 * Actions: create, update, delete, add_member, remove_member, set_member_role, update_status
 */

require_once __DIR__ . '/bootstrap.php';

$action = api_action();

switch ($action) {

    /* ================= CREATE ================= */
    case 'create':
        require_permission('projects.create');
        $name = (string)input('name', '');
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)input('code', '')) ?? '');
        if (mb_strlen($name) < 3) { api_fail('Project name must be at least 3 characters.'); }
        if (mb_strlen($code) < 2 || mb_strlen($code) > 12) { api_fail('Short code must be 2–12 letters/numbers.'); }
        if (Db::count('SELECT COUNT(*) FROM projects WHERE code = ?', [$code]) > 0) { api_fail('That short code is already used.'); }

        $status = (string)input('status', 'active');
        if (!array_key_exists($status, project_statuses())) { $status = 'active'; }
        $priority = (string)input('priority', 'medium');
        if (!array_key_exists($priority, priorities())) { $priority = 'medium'; }
        $visibility = input('visibility') === 'private' ? 'private' : 'public';

        $colors = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#8b5cf6', '#ef4444'];
        $projectId = Db::insert('projects', [
            'name'          => mb_substr($name, 0, 150),
            'code'          => $code,
            'description'   => (string)input('description', '') ?: null,
            'status'        => $status,
            'priority'      => $priority,
            'visibility'    => $visibility,
            'owner_id'      => user_id(),
            'department_id' => int_input('department_id') > 0 ? int_input('department_id') : null,
            'start_date'    => input('start_date') ?: null,
            'due_date'      => input('due_date') ?: null,
            'budget'        => is_numeric(input('budget')) ? (float)input('budget') : null,
            'color'         => $colors[count(Db::all('SELECT id FROM projects')) % count($colors)],
        ]);

        $members = array_input('members');
        $members[] = user_id();
        foreach (array_unique(array_map('intval', $members)) as $memberId) {
            if ($memberId <= 0) { continue; }
            Db::insertIgnore('project_members', ['project_id', 'user_id', 'project_role'], '?, ?, ?',
                [$projectId, $memberId, $memberId === user_id() ? 'owner' : 'member']);
        }

        activity('project.create', 'project', $projectId, 'Created project ' . $name);
        notify_many(array_map('intval', $members), 'project_member',
            'You were added to ' . $name,
            user_name() . ' created the project and added you as a member.',
            'index.php?page=project&id=' . $projectId, 'project', $projectId);

        api_ok(['id' => $projectId, 'message' => 'Project created.', 'redirect' => page_url('project', ['id' => $projectId])]);
        break;

    /* ================= UPDATE ================= */
    case 'update':
        require_permission('projects.edit');
        $projectId = int_input('id');
        $project = Db::one('SELECT * FROM projects WHERE id = ?', [$projectId]);
        if (!$project) { api_fail('Project not found.', 404); }
        if (!can_manage_project($project)) { api_fail('You are not allowed to manage this project.', 403); }

        $data = [];
        foreach (['name', 'description', 'start_date', 'due_date'] as $field) {
            if (isset($_POST[$field])) {
                $value = trim((string)$_POST[$field]);
                $data[$field] = in_array($field, ['start_date', 'due_date'], true) && $value === '' ? null : $value;
            }
        }
        foreach (['status', 'priority', 'visibility'] as $field) {
            if (isset($_POST[$field])) {
                $value = (string)$_POST[$field];
                if ($field === 'status' && !array_key_exists($value, project_statuses())) { continue; }
                if ($field === 'priority' && !array_key_exists($value, priorities())) { continue; }
                if ($field === 'visibility' && !in_array($value, ['public', 'private'], true)) { continue; }
                $data[$field] = $value;
            }
        }
        if (isset($_POST['department_id'])) {
            $data['department_id'] = int_input('department_id') > 0 ? int_input('department_id') : null;
        }
        if (isset($_POST['color']) && preg_match('/^#[0-9a-f]{6}$/i', (string)$_POST['color'])) {
            $data['color'] = $_POST['color'];
        }
        if (!$data) { api_fail('Nothing to update.'); }

        Db::update('projects', $data, 'id = :id', ['id' => $projectId]);
        activity('project.update', 'project', $projectId, 'Updated project ' . $project['name'] . ' (' . implode(', ', array_keys($data)) . ')');
        api_ok(['message' => 'Project updated.', 'id' => $projectId]);
        break;

    /* ================= DELETE / ARCHIVE ================= */
    case 'delete':
        require_permission('projects.delete');
        $projectId = int_input('id');
        $project = Db::one('SELECT * FROM projects WHERE id = ?', [$projectId]);
        if (!$project) { api_fail('Project not found.', 404); }

        $taskCount = Db::count('SELECT COUNT(*) FROM tasks WHERE project_id = ?', [$projectId]);
        if ($taskCount > 0 && !bool_input('confirm_tasks')) {
            api_fail('This project still has ' . $taskCount . ' task(s). Deleting the project deletes them too.', 409,
                ['confirm_required' => true, 'task_count' => $taskCount]);
        }

        Db::run('DELETE FROM projects WHERE id = ?', [$projectId]);
        activity('project.delete', 'project', $projectId, 'Deleted project ' . $project['name'] . ' (' . $taskCount . ' tasks)');
        api_ok(['message' => 'Project and its tasks were deleted.', 'redirect' => page_url('projects')]);
        break;

    /* ================= MEMBERS ================= */
    case 'add_member':
        require_permission('projects.edit');
        $projectId = int_input('project_id');
        $userId    = int_input('user_id');
        $project = Db::one('SELECT * FROM projects WHERE id = ?', [$projectId]);
        $member  = Db::one('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$project || !$member) { api_fail('Project or user not found.', 404); }
        if (!can_manage_project($project)) { api_fail('You cannot manage this project.', 403); }

        $role = (string)input('project_role', 'member');
        if (!in_array($role, ['owner', 'manager', 'member', 'viewer'], true)) { $role = 'member'; }

        Db::insertIgnore('project_members', ['project_id', 'user_id', 'project_role'], '?, ?, ?', [$projectId, $userId, $role]);
        notify_user($userId, 'project_member', 'You joined ' . $project['name'],
            user_name() . ' added you to the project team.',
            'index.php?page=project&id=' . $projectId, 'project', $projectId);
        activity('project.add_member', 'project', $projectId, 'Added ' . $member['name'] . ' to ' . $project['name']);
        api_ok(['message' => $member['name'] . ' added to the project.']);
        break;

    case 'remove_member':
        require_permission('projects.edit');
        $projectId = int_input('project_id');
        $userId    = int_input('user_id');
        $project = Db::one('SELECT * FROM projects WHERE id = ?', [$projectId]);
        if (!$project) { api_fail('Project not found.', 404); }
        if (!can_manage_project($project)) { api_fail('You cannot manage this project.', 403); }
        if ((int)$project['owner_id'] === $userId) { api_fail('The project owner cannot be removed.'); }

        Db::run('DELETE FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $userId]);
        activity('project.remove_member', 'project', $projectId, 'Removed a member from ' . $project['name']);
        api_ok(['message' => 'Member removed.']);
        break;

    case 'set_member_role':
        require_permission('projects.edit');
        $projectId = int_input('project_id');
        $userId    = int_input('user_id');
        $role      = (string)input('project_role', 'member');
        if (!in_array($role, ['owner', 'manager', 'member', 'viewer'], true)) { api_fail('Invalid project role.'); }
        $project = Db::one('SELECT * FROM projects WHERE id = ?', [$projectId]);
        if (!$project || !can_manage_project($project)) { api_fail('You cannot manage this project.', 403); }

        Db::run('UPDATE project_members SET project_role = ? WHERE project_id = ? AND user_id = ?', [$role, $projectId, $userId]);
        activity('project.member_role', 'project', $projectId, 'Changed a member role to ' . $role);
        api_ok(['message' => 'Member role updated.']);
        break;

    /* ================= STATUS ================= */
    case 'update_status':
        require_permission('projects.edit');
        $projectId = int_input('id');
        $status = (string)input('status', '');
        if (!array_key_exists($status, project_statuses())) { api_fail('Invalid status.'); }
        $project = Db::one('SELECT * FROM projects WHERE id = ?', [$projectId]);
        if (!$project) { api_fail('Project not found.', 404); }
        if (!can_manage_project($project)) { api_fail('You cannot manage this project.', 403); }

        Db::update('projects', ['status' => $status], 'id = :id', ['id' => $projectId]);
        $memberIds = array_map('intval', array_column(
            Db::all('SELECT user_id FROM project_members WHERE project_id = ?', [$projectId]), 'user_id'));
        notify_many($memberIds, 'project_status',
            $project['name'] . ' is now ' . project_statuses()[$status]['label'],
            user_name() . ' changed the project status.',
            'index.php?page=project&id=' . $projectId, 'project', $projectId);
        activity('project.status', 'project', $projectId, 'Set ' . $project['name'] . ' to ' . project_statuses()[$status]['label']);
        api_ok(['message' => 'Status updated.', 'status' => $status]);
        break;

    default:
        api_fail('Unknown action.', 404);
}
