<?php
/**
 * TaskFlow — Departments
 */

$today = sql_date();

$departments = Db::all(
    "SELECT d.*, m.name AS manager_name, m.color AS manager_color, m.avatar AS manager_avatar, m.job_title AS manager_title,
            (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.status = 'active') AS members_count,
            (SELECT COUNT(*) FROM projects p WHERE p.department_id = d.id AND p.status IN ('active','planning')) AS open_projects,
            (SELECT COUNT(*) FROM tasks t JOIN users u2 ON u2.id = t.assignee_id
              WHERE u2.department_id = d.id AND t.status <> 'done' AND t.is_archived = 0 AND t.parent_id IS NULL) AS open_tasks,
            (SELECT COUNT(*) FROM tasks t JOIN users u3 ON u3.id = t.assignee_id
              WHERE u3.department_id = d.id AND t.status = 'done' AND t.is_archived = 0 AND t.parent_id IS NULL) AS done_tasks,
            (SELECT COALESCE(SUM(t.actual_hours),0) FROM tasks t JOIN users u4 ON u4.id = t.assignee_id
              WHERE u4.department_id = d.id) AS logged_hours
     FROM departments d
     LEFT JOIN users m ON m.id = d.manager_id
     WHERE d.is_active = 1
     ORDER BY d.name ASC"
);

$unassigned = Db::count("SELECT COUNT(*) FROM users WHERE department_id IS NULL AND status = 'active'");
$canManage  = can('departments.manage');
?>
<?= page_header(
    'Departments',
    count($departments) . ' departments' . ($unassigned > 0 ? ' · ' . $unassigned . ' member(s) without a department' : ''),
    $canManage ? '<button class="btn btn-primary btn-sm" data-modal-open="deptModal">' . icon('plus', 15) . ' New department</button>' : '',
    breadcrumbs([['label' => 'Workspace', 'url' => page_url('dashboard')], ['label' => 'Departments']])
) ?>

<?php if (!$departments): ?>
    <div class="card"><div class="card-body">
        <?= empty_state('building', 'No departments yet',
            'Departments help you group people and route work to the right team.',
            $canManage ? '<button class="btn btn-primary" data-modal-open="deptModal">' . icon('plus', 16) . ' Create department</button>' : '') ?>
    </div></div>
<?php else: ?>
    <div class="dept-grid">
        <?php foreach ($departments as $d): ?>
            <?php
            $totalDept = (int)$d['open_tasks'] + (int)$d['done_tasks'];
            $rate = $totalDept > 0 ? (int)round(((int)$d['done_tasks'] / $totalDept) * 100) : 0;
            $members = Db::all(
                "SELECT id, name, color, avatar, job_title FROM users WHERE department_id = ? AND status = 'active' ORDER BY name ASC LIMIT 8",
                [(int)$d['id']]
            );
            ?>
            <article class="dept-card" style="--dept-color:<?= e($d['color'] ?: '#6366f1') ?>">
                <div class="dept-card-head">
                    <span class="dept-icon"><?= icon('building', 18) ?></span>
                    <div>
                        <h3><?= e($d['name']) ?></h3>
                        <?php if ($d['code']): ?><span class="dept-code"><?= e($d['code']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($canManage): ?>
                        <div class="dept-actions">
                            <button class="icon-btn sm" data-edit-dept='<?= json_encode([
                                'id' => (int)$d['id'], 'name' => $d['name'], 'code' => (string)$d['code'],
                                'color' => $d['color'], 'manager_id' => (int)$d['manager_id'],
                                'description' => (string)$d['description'],
                            ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>' title="Edit"><?= icon('edit', 14) ?></button>
                            <button class="icon-btn danger sm" data-delete-dept="<?= (int)$d['id'] ?>" data-name="<?= e($d['name']) ?>" title="Delete"><?= icon('trash', 14) ?></button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($d['description']): ?><p class="dept-desc"><?= e(excerpt((string)$d['description'], 110)) ?></p><?php endif; ?>

                <div class="dept-manager">
                    <?php if ($d['manager_id']): ?>
                        <span class="side-label">Manager</span>
                        <a class="person" href="<?= e(page_url('profile', ['id' => (int)$d['manager_id']])) ?>">
                            <?= avatar_html($d, 28) ?>
                            <span><strong><?= e($d['manager_name']) ?></strong><small><?= e($d['manager_title'] ?: '') ?></small></span>
                        </a>
                    <?php else: ?>
                        <span class="muted">No manager assigned</span>
                    <?php endif; ?>
                </div>

                <div class="dept-stats">
                    <div><strong><?= (int)$d['members_count'] ?></strong><small>Members</small></div>
                    <div><strong><?= (int)$d['open_tasks'] ?></strong><small>Open tasks</small></div>
                    <div><strong><?= (int)$d['open_projects'] ?></strong><small>Projects</small></div>
                    <div><strong><?= e(format_hours((float)$d['logged_hours'])) ?></strong><small>Logged</small></div>
                </div>

                <div class="dept-rate">
                    <div class="progress-head"><span>Completion rate</span><strong><?= $rate ?>%</strong></div>
                    <?= progress_bar($rate, e($d['color'] ?: '#6366f1')) ?>
                </div>

                <div class="dept-foot">
                    <?= avatar_stack($members, 26, 6) ?>
                    <a class="link-btn" href="<?= e(page_url('team', ['department' => (int)$d['id']])) ?>">View members <?= icon('chevron-right', 13) ?></a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
