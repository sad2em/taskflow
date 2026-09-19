<?php
/**
 * TaskFlow — Roles & Permissions matrix
 */

$roles       = all_roles();
$grouped     = all_permissions_grouped();
$selectedId  = int_input('role', (int)($roles[0]['id'] ?? 0));
$selected    = null;
foreach ($roles as $r) {
    if ((int)$r['id'] === $selectedId) { $selected = $r; break; }
}
if (!$selected && $roles) { $selected = $roles[0]; $selectedId = (int)$selected['id']; }
$granted = $selected ? role_permission_ids($selectedId) : [];

$canManage = can('roles.manage');
$isSuper   = $selected && $selected['slug'] === 'super_admin';
$allPermIds = array_map(static fn($p) => (int)$p['id'], array_merge(...array_values($grouped)));

$roleMembers = $selected ? Db::all(
    'SELECT id, name, color, avatar, job_title FROM users WHERE role_id = ? ORDER BY name ASC',
    [$selectedId]
) : [];
?>
<?= page_header(
    'Roles & Permissions',
    'Define exactly what each role can see and do. Changes apply the next time a member loads a page.',
    $canManage ? '<button class="btn btn-primary btn-sm" data-modal-open="roleModal">' . icon('plus', 15) . ' New role</button>' : '',
    breadcrumbs([['label' => 'Administration', 'url' => page_url('dashboard')], ['label' => 'Roles & Permissions']])
) ?>

<div class="roles-layout">
    <!-- Role list -->
    <aside class="card roles-side">
        <div class="card-head"><h3><?= icon('shield', 16) ?> Roles</h3></div>
        <div class="card-body no-pad">
            <ul class="role-list">
                <?php foreach ($roles as $r): ?>
                    <li>
                        <a class="role-item<?= (int)$r['id'] === $selectedId ? ' active' : '' ?>"
                           href="<?= e(page_url('roles', ['role' => (int)$r['id']])) ?>">
                            <span class="role-icon"><?= icon((int)$r['is_system'] === 1 ? 'lock' : 'shield', 15) ?></span>
                            <span class="role-meta">
                                <strong><?= e($r['name']) ?></strong>
                                <small><?= (int)$r['users_count'] ?> member<?= (int)$r['users_count'] === 1 ? '' : 's' ?> ·
                                    <?= count(role_permission_ids((int)$r['id'])) ?> permissions</small>
                            </span>
                            <?php if ((int)$r['is_system'] === 1): ?><span class="chip chip-muted sm-chip">Built-in</span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($selected): ?>
            <div class="card-body role-summary">
                <p class="muted"><?= e((string)($selected['description'] ?: 'No description.')) ?></p>
                <?php if ($roleMembers): ?>
                    <span class="side-label">Members with this role</span>
                    <div class="people-row wrap">
                        <?php foreach (array_slice($roleMembers, 0, 12) as $m): ?>
                            <a href="<?= e(page_url('profile', ['id' => (int)$m['id']])) ?>" title="<?= e($m['name']) ?>"><?= avatar_html($m, 26) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>

    <!-- Permission matrix -->
    <div class="roles-main">
        <?php if (!$selected): ?>
            <div class="card"><div class="card-body"><?= empty_state('shield', 'No roles defined', 'Create a role to start granting permissions.') ?></div></div>
        <?php else: ?>
            <div class="card">
                <div class="card-head">
                    <div>
                        <h3><?= e($selected['name']) ?> permissions</h3>
                        <p class="card-subtitle">
                            <?= count($granted) ?> of <?= count($allPermIds) ?> permissions granted
                            <?php if ($isSuper): ?> · this role always has full access<?php endif; ?>
                        </p>
                    </div>
                    <?php if ($canManage && !$isSuper): ?>
                        <div class="head-btns">
                            <?php if (in_array((string)$selected['slug'], ['supervisor', 'employee', 'member', 'team_lead', 'manager', 'viewer'], true)): ?>
                                <button class="btn btn-ghost btn-sm" title="Restore the recommended permissions for this built-in role" data-apply-defaults="<?= (int)$selected['id'] ?>" data-default-role="<?= e($selected['name']) ?>">
                                    <?= icon('refresh', 14) ?> Restore default permissions
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-ghost btn-sm" data-perm-all="1"><?= icon('check', 14) ?> Select all</button>
                            <button class="btn btn-ghost btn-sm" data-perm-all="0"><?= icon('x', 14) ?> Clear</button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($canManage): ?>
                    <form class="card-body no-pad" id="permForm" data-role="<?= $selectedId ?>"
                          method="post" action="<?= e(url('api/roles.php')) ?>" data-ajax-form data-reload="1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_permissions">
                        <input type="hidden" name="id" value="<?= $selectedId ?>">
                <?php else: ?>
                    <div class="card-body no-pad">
                <?php endif; ?>

                    <div class="table-scroll">
                        <table class="table table-perms">
                            <thead>
                                <tr>
                                    <th>Module / permission</th>
                                    <th class="center w-120">Granted</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grouped as $module => $perms): ?>
                                    <tr class="row-module">
                                        <td colspan="3">
                                            <strong><?= icon('grid', 14) ?> <?= e($module) ?></strong>
                                            <?php if ($canManage && !$isSuper): ?>
                                                <button type="button" class="link-btn sm" data-toggle-module>Toggle module</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($perms as $p): ?>
                                        <?php $checked = in_array((int)$p['id'], $granted, true) || $isSuper; ?>
                                        <tr>
                                            <td><code class="perm-slug"><?= e($p['slug']) ?></code></td>
                                            <td class="center">
                                                <label class="switch">
                                                    <input type="checkbox" name="permissions[]" value="<?= (int)$p['id'] ?>"
                                                           <?= $checked ? 'checked' : '' ?> <?= ($isSuper || !$canManage) ? 'disabled' : '' ?>>
                                                    <span class="slider"></span>
                                                </label>
                                            </td>
                                            <td class="muted"><?= e((string)$p['description']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php if ($canManage): ?>
                    </form>
                    <div class="card-foot sticky-foot">
                        <span class="muted" id="permCount"><?= count($granted) ?> selected</span>
                        <div class="head-btns">
                            <?php if (!$isSuper): ?>
                                <button class="btn btn-ghost btn-sm" data-edit-role='<?= json_encode([
                                    'id' => (int)$selected['id'], 'name' => $selected['name'], 'description' => (string)$selected['description'],
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>'><?= icon('edit', 15) ?> Edit role</button>
                                <?php if ((int)$selected['is_system'] !== 1): ?>
                                    <button class="btn btn-danger btn-sm" data-delete-role="<?= (int)$selected['id'] ?>" data-name="<?= e($selected['name']) ?>">
                                        <?= icon('trash', 15) ?> Delete role
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <button class="btn btn-primary btn-sm" form="permForm" type="submit" <?= $isSuper ? 'disabled' : '' ?>>
                                <?= icon('save', 15) ?> Save permissions
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    </div>
                    <div class="card-foot"><span class="muted"><?= icon('lock', 14) ?> You can view permissions but not change them.</span></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<script type="text/template" id="tpl-roleModal">
<form class="modal-form" id="roleForm" data-action="api/roles.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="id" value="">
    <div class="form-row">
        <label class="form-label" for="rf-name">Role name <span class="req">*</span></label>
        <input class="input" type="text" id="rf-name" name="name" required maxlength="60" placeholder="e.g. Project Coordinator">
    </div>
    <div class="form-row">
        <label class="form-label" for="rf-slug">Slug</label>
        <input class="input" type="text" id="rf-slug" name="slug" maxlength="60" placeholder="project_coordinator">
        <small class="form-hint">Lowercase letters, numbers and underscores. Leave empty to generate it from the name.</small>
    </div>
    <div class="form-row">
        <label class="form-label" for="rf-description">Description</label>
        <textarea class="input" id="rf-description" name="description" rows="3" placeholder="What is this role responsible for?"></textarea>
    </div>
    <div class="alert alert-info"><?= icon('info', 16) ?><span>New roles start with the same permissions as <strong>Member</strong>. Adjust them in the matrix afterwards.</span></div>
    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> <span data-submit-label>Create role</span></button>
    </div>
</form>
</script>
<?php endif; ?>