<?php
/**
 * TaskFlow — Shared modal templates (read by assets/js/app.js)
 * Rendered once per request and kept hidden until needed.
 */

$__projects   = Db::all('SELECT id, name, code, color FROM projects ORDER BY name ASC');
$__users      = Db::all("SELECT id, name, color, avatar, job_title FROM users WHERE status = 'active' ORDER BY name ASC");
$__statuses   = task_statuses();
$__priorities = priorities();
$__depts      = Db::all('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC');
?>

<!-- ================= TASK MODAL ================= -->
<script type="text/template" id="tpl-taskModal">
<form class="modal-form" id="taskForm" data-action="api/tasks.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="id" value="">
    <input type="hidden" name="status" value="todo">

    <div class="form-row">
        <label class="form-label" for="tf-title">Task title <span class="req">*</span></label>
        <input class="input" type="text" id="tf-title" name="title" maxlength="200" required
               placeholder="e.g. Implement the invoices API">
    </div>

    <div class="form-grid-2">
        <div class="form-row">
            <label class="form-label" for="tf-project">Project <span class="req">*</span></label>
            <select class="input" id="tf-project" name="project_id" required>
                <option value="">Select a project…</option>
                <?php foreach ($__projects as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= e($p['code'] . ' — ' . $p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label" for="tf-assignee">Assignee</label>
            <select class="input" id="tf-assignee" name="assignee_id">
                <option value="">Unassigned</option>
                <?php foreach ($__users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?><?= $u['job_title'] ? ' — ' . e($u['job_title']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <label class="form-label" for="tf-description">Description</label>
        <textarea class="input" id="tf-description" name="description" rows="4"
                  placeholder="Add context, acceptance criteria, links… Use @name to mention a teammate."></textarea>
    </div>

    <div class="form-grid-3">
        <div class="form-row">
            <label class="form-label" for="tf-priority">Priority</label>
            <select class="input" id="tf-priority" name="priority">
                <?php foreach ($__priorities as $key => $p): ?>
                    <option value="<?= e($key) ?>"<?= $key === 'medium' ? ' selected' : '' ?>><?= e($p['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label" for="tf-due">Due date</label>
            <input class="input" type="date" id="tf-due" name="due_date">
        </div>
        <div class="form-row">
            <label class="form-label" for="tf-hours">Estimate (hours)</label>
            <input class="input" type="number" id="tf-hours" name="estimated_hours" min="0" step="0.5" placeholder="0">
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-row">
            <label class="form-label" for="tf-start">Start date</label>
            <input class="input" type="date" id="tf-start" name="start_date">
        </div>
        <div class="form-row">
            <label class="form-label" for="tf-parent">Parent task (optional)</label>
            <select class="input" id="tf-parent" name="parent_id">
                <option value="">None — this is a top-level task</option>
            </select>
        </div>
    </div>

    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> <span data-submit-label>Create task</span></button>
    </div>
</form>
</script>

<!-- ================= PROJECT MODAL ================= -->
<script type="text/template" id="tpl-projectModal">
<form class="modal-form" id="projectForm" data-action="api/projects.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="id" value="">

    <div class="form-grid-2">
        <div class="form-row">
            <label class="form-label" for="pf-name">Project name <span class="req">*</span></label>
            <input class="input" type="text" id="pf-name" name="name" maxlength="150" required placeholder="e.g. Client Portal v2">
        </div>
        <div class="form-row">
            <label class="form-label" for="pf-code">Short code <span class="req">*</span></label>
            <input class="input" type="text" id="pf-code" name="code" maxlength="20" required
                   placeholder="PORTAL" pattern="[A-Za-z0-9\-_]{2,20}" title="2-20 letters, numbers, dash or underscore">
            <small class="form-hint">Used as the task key prefix (e.g. PORTAL-12).</small>
        </div>
    </div>

    <div class="form-row">
        <label class="form-label" for="pf-description">Description</label>
        <textarea class="input" id="pf-description" name="description" rows="3" placeholder="What is this project about?"></textarea>
    </div>

    <div class="form-grid-3">
        <div class="form-row">
            <label class="form-label" for="pf-status">Status</label>
            <select class="input" id="pf-status" name="status">
                <?php foreach (project_statuses() as $key => $s): ?>
                    <option value="<?= e($key) ?>"<?= $key === 'active' ? ' selected' : '' ?>><?= e($s['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label" for="pf-priority">Priority</label>
            <select class="input" id="pf-priority" name="priority">
                <?php foreach ($__priorities as $key => $p): ?>
                    <option value="<?= e($key) ?>"<?= $key === 'medium' ? ' selected' : '' ?>><?= e($p['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label" for="pf-dept">Department</label>
            <select class="input" id="pf-dept" name="department_id">
                <option value="">None</option>
                <?php foreach ($__depts as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-grid-3">
        <div class="form-row">
            <label class="form-label" for="pf-start">Start date</label>
            <input class="input" type="date" id="pf-start" name="start_date">
        </div>
        <div class="form-row">
            <label class="form-label" for="pf-due">Due date</label>
            <input class="input" type="date" id="pf-due" name="due_date">
        </div>
        <div class="form-row">
            <label class="form-label" for="pf-visibility">Visibility</label>
            <select class="input" id="pf-visibility" name="visibility">
                <option value="public">Public — everyone can see it</option>
                <option value="private">Private — members only</option>
            </select>
        </div>
    </div>

    <div class="form-row">
        <label class="form-label">Team members</label>
        <div class="member-picker" id="pf-members">
            <?php foreach ($__users as $u): ?>
                <label class="member-chip">
                    <input type="checkbox" name="members[]" value="<?= (int)$u['id'] ?>"<?= (int)$u['id'] === user_id() ? ' checked' : '' ?>>
                    <span><?= avatar_html($u, 24) ?> <?= e($u['name']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> <span data-submit-label>Create project</span></button>
    </div>
</form>
</script>

<!-- ================= USER MODAL ================= -->
<script type="text/template" id="tpl-userModal">
<form class="modal-form" id="userForm" data-action="api/users.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="id" value="">

    <div class="form-grid-2">
        <div class="form-row">
            <label class="form-label" for="uf-name">Full name <span class="req">*</span></label>
            <input class="input" type="text" id="uf-name" name="name" required maxlength="100" placeholder="Jane Cooper">
        </div>
        <div class="form-row">
            <label class="form-label" for="uf-email">Email <span class="req">*</span></label>
            <input class="input" type="email" id="uf-email" name="email" required placeholder="jane@company.com">
        </div>
    </div>

    <div class="form-grid-3">
        <div class="form-row">
            <label class="form-label" for="uf-role">Role</label>
            <select class="input" id="uf-role" name="role_id">
                <?php foreach (all_roles() as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label" for="uf-dept">Department</label>
            <select class="input" id="uf-dept" name="department_id">
                <option value="">None</option>
                <?php foreach ($__depts as $d): ?>
                    <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label" for="uf-title">Job title</label>
            <input class="input" type="text" id="uf-title" name="job_title" maxlength="100" placeholder="Frontend Developer">
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-row">
            <label class="form-label" for="uf-phone">Phone</label>
            <input class="input" type="text" id="uf-phone" name="phone" maxlength="40" placeholder="+961 3 000 000">
        </div>
        <div class="form-row">
            <label class="form-label" for="uf-password">Password <span class="req" data-only-create>*</span></label>
            <input class="input" type="password" id="uf-password" name="password" autocomplete="new-password"
                   placeholder="Min <?= (int)(defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8) ?> characters">
            <small class="form-hint">Leave empty when editing to keep the current password.</small>
        </div>
    </div>

    <div class="form-row">
        <label class="form-label" for="uf-status">Status</label>
        <select class="input" id="uf-status" name="status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
        </select>
    </div>

    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> <span data-submit-label>Add member</span></button>
    </div>
</form>
</script>

<!-- ================= DEPARTMENT MODAL ================= -->
<script type="text/template" id="tpl-deptModal">
<form class="modal-form" id="deptForm" data-action="api/departments.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="id" value="">

    <div class="form-grid-2">
        <div class="form-row">
            <label class="form-label" for="df-name">Department name <span class="req">*</span></label>
            <input class="input" type="text" id="df-name" name="name" required maxlength="100" placeholder="Development">
        </div>
        <div class="form-row">
            <label class="form-label" for="df-code">Code</label>
            <input class="input" type="text" id="df-code" name="code" maxlength="20" placeholder="DEV">
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-row">
            <label class="form-label" for="df-manager">Department manager</label>
            <select class="input" id="df-manager" name="manager_id">
                <option value="">Not set</option>
                <?php foreach ($__users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label class="form-label" for="df-color">Colour</label>
            <input class="input input-color" type="color" id="df-color" name="color" value="#6366f1">
        </div>
    </div>

    <div class="form-row">
        <label class="form-label" for="df-description">Description</label>
        <textarea class="input" id="df-description" name="description" rows="3"></textarea>
    </div>

    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> <span data-submit-label>Save department</span></button>
    </div>
</form>
</script>
