/* ==========================================================================
   TaskFlow — Application JavaScript (vanilla, no dependencies)
   ========================================================================== */
(function () {
    'use strict';

    /* ------------------------------ Core ------------------------------ */

    const BASE = (document.querySelector('meta[name="base-url"]')?.content || '').replace(/\/$/, '');
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const $  = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const apiUrl = (file) => `${BASE}/${file}`;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    async function api(file, data = {}, method = 'POST') {
        const body = new FormData();
        if (!('_token' in data)) body.append('_token', CSRF);
        Object.entries(data).forEach(([key, value]) => {
            if (value === null || value === undefined) return;
            if (Array.isArray(value)) value.forEach((v) => body.append(`${key}[]`, v));
            else body.append(key, value);
        });

        const response = await fetch(apiUrl(file), {
            method,
            body: method === 'GET' ? undefined : body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        let payload = {};
        try { payload = await response.json(); } catch (e) { payload = { ok: false, error: 'Invalid server response.' }; }

        if (response.status === 401 && payload.redirect) {
            window.location.href = payload.redirect;
            throw new Error('Unauthorized');
        }
        if (!payload.ok) throw new Error(payload.error || 'Something went wrong.');
        return payload;
    }

    /* ------------------------------ Toasts ------------------------------ */

    function toast(message, type = 'info', title = '') {
        const zone = $('#toastZone') || (() => {
            const el = document.createElement('div');
            el.id = 'toastZone';
            el.className = 'toast-zone';
            document.body.appendChild(el);
            return el;
        })();

        const icons = { success: 'M20 6L9 17l-5-5', error: 'M12 8v5M12 16h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z', info: 'M12 16v-4M12 8h.01' };
        const el = document.createElement('div');
        el.className = `toast ${type}`;
        el.innerHTML = `
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="${icons[type] || icons.info}"/></svg>
            <div class="toast-body">${title ? `<strong>${escapeHtml(title)}</strong>` : ''}${escapeHtml(message)}</div>
            <button class="toast-close" aria-label="Close">&times;</button>`;
        zone.appendChild(el);

        const remove = () => {
            el.style.transition = 'opacity .2s, transform .2s';
            el.style.opacity = '0';
            el.style.transform = 'translateX(14px)';
            setTimeout(() => el.remove(), 220);
        };
        el.querySelector('.toast-close').addEventListener('click', remove);
        setTimeout(remove, type === 'error' ? 6500 : 3800);
    }

    /* ------------------------------ Modal engine ------------------------------ */

    const modalRoot = $('#modalRoot');
    const modalBody = $('#modalBody');
    const modalTitle = $('#modalTitle');
    let lastFocused = null;

    function openModal(title, contentNode, { wide = false } = {}) {
        if (!modalRoot || !modalBody) return null;
        lastFocused = document.activeElement;
        modalTitle.textContent = title;
        modalBody.innerHTML = '';
        modalBody.appendChild(contentNode);
        modalRoot.querySelector('.modal').classList.toggle('wide', wide);
        modalRoot.hidden = false;
        document.body.style.overflow = 'hidden';

        const focusable = contentNode.querySelector('input:not([type=hidden]), select, textarea, button');
        setTimeout(() => focusable?.focus(), 40);
        return contentNode;
    }

    function closeModal() {
        if (!modalRoot) return;
        modalRoot.hidden = true;
        modalBody.innerHTML = '';
        document.body.style.overflow = '';
        lastFocused?.focus?.();
    }

    /** Open a <script type="text/template" id="tpl-XXX"> inside the modal. */
    function openTemplate(id, title, prepare) {
        const tpl = document.getElementById(`tpl-${id}`);
        if (!tpl) { toast(`Template "${id}" is missing.`, 'error'); return null; }
        const node = document.createElement('div');
        node.innerHTML = tpl.innerHTML.trim();
        const form = node.firstElementChild;
        if (prepare) prepare(form, node);
        openModal(title, node);
        return form;
    }

    /** Promise-based confirm dialog. */
    function confirmDialog({ title = 'Are you sure?', message = '', confirmText = 'Confirm', danger = true, icon = 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6' }) {
        return new Promise((resolve) => {
            const node = document.createElement('div');
            node.innerHTML = `
                <div class="confirm-body">
                    <div class="confirm-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="${icon}"/></svg>
                    </div>
                    <h3>${escapeHtml(title)}</h3>
                    <p>${escapeHtml(message)}</p>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-ghost" data-cancel>Cancel</button>
                    <button type="button" class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-confirm>${escapeHtml(confirmText)}</button>
                </div>`;
            openModal(title, node);
            const done = (value) => { closeModal(); resolve(value); };
            node.querySelector('[data-cancel]').addEventListener('click', () => done(false));
            node.querySelector('[data-confirm]').addEventListener('click', () => done(true));
            node.addEventListener('click', (e) => { if (e.target === node) done(false); });
        });
    }

    function setBusy(form, busy) {
        if (!form) return;
        const button = form.querySelector('button[type="submit"], .btn-primary');
        if (!button) return;
        button.disabled = busy;
        button.style.opacity = busy ? '.7' : '';
        if (busy && !button.dataset.label) {
            button.dataset.label = button.innerHTML;
            button.innerHTML = `<svg class="spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4"/></svg> Working…`;
        } else if (!busy && button.dataset.label) {
            button.innerHTML = button.dataset.label;
            delete button.dataset.label;
        }
    }

    /* ------------------------------ Theme ------------------------------ */

    const THEME_KEY = 'taskflow_theme';

    function readStoredTheme() {
        try {
            const raw = localStorage.getItem(THEME_KEY);
            if (raw === 'dark' || raw === 'light') return raw;
            // قيمة قديمة/تالفة (مثل "\"dark\"") — ننظّفها بدل أن تعطّل الوضع الليلي
            if (raw !== null) localStorage.removeItem(THEME_KEY);
        } catch (e) { /* localStorage غير متاح */ }
        return null;
    }

    function storeTheme(theme) {
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) { /* تجاهل */ }
    }

    function applyTheme(theme) {
        theme = theme === 'dark' ? 'dark' : 'light';
        const root = document.documentElement;
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = theme;
        if (document.body) document.body.classList.toggle('theme-dark', theme === 'dark');
        storeTheme(theme);
        $$('#themeToggle').forEach((btn) => {
            btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            btn.innerHTML = theme === 'dark'
                ? '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.5 1.5M17.6 17.6l1.5 1.5M19.1 4.9l-1.5 1.5M6.4 17.6l-1.5 1.5"/></svg>'
                : '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M21 13.5A9 9 0 1110.5 3a7 7 0 0010.5 10.5z"/></svg>';
        });
        return theme;
    }

    function initTheme() {
        const stored = readStoredTheme();
        const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        applyTheme(stored || current);

        // تفويض الحدث على مستوى المستند: يعمل حتى لو أُعيد بناء الزر أو أُضيف لاحقاً
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#themeToggle')) return;
            e.preventDefault();
            const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            api('api/settings.php', { action: 'theme', theme: next }).catch(() => {});
        });
    }

    /* ------------------------------ Sidebar ------------------------------ */

    function initSidebar() {
        const toggle = $('#sidebarToggle');
        const backdrop = $('#sidebarBackdrop');
        if (!toggle) return;
        const close = () => document.body.classList.remove('sidebar-open');
        toggle.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
        backdrop?.addEventListener('click', close);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    }

    /* ------------------------------ Dropdowns ------------------------------ */

    function initDropdowns() {
        document.addEventListener('click', (e) => {
            const toggleBtn = e.target.closest('[data-dropdown-toggle]');
            $$('.dropdown.open').forEach((dd) => {
                if (!toggleBtn || dd !== toggleBtn.closest('.dropdown')) dd.classList.remove('open');
            });
            if (toggleBtn) {
                e.preventDefault();
                toggleBtn.closest('.dropdown').classList.toggle('open');
                if (toggleBtn.closest('#notifDropdown')?.classList.contains('open')) loadNotifications();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') $$('.dropdown.open').forEach((dd) => dd.classList.remove('open'));
        });
    }

    /* ------------------------------ Notifications ------------------------------ */

    let notifLoaded = false;

    async function loadNotifications(force = false) {
        const list = $('#notifList');
        if (!list || (notifLoaded && !force)) return;
        try {
            const data = await api('api/notifications.php', { action: 'list', limit: 10 }, 'GET');
            notifLoaded = true;
            if (!data.notifications.length) {
                list.innerHTML = '<div class="dropdown-loading">You are all caught up 🎉</div>';
                return;
            }
            list.innerHTML = data.notifications.map((n) => `
                <a class="notif-item${n.is_read == 0 ? ' unread' : ''}" data-id="${n.id}"
                   href="${n.link ? BASE + '/' + n.link : '#'}" data-notif-link="${n.id}" style="text-decoration:none;color:inherit">
                    <span class="notif-icon">${n.actor_id ? (n.actor_avatar || '') : ''}</span>
                    <div class="notif-body">
                        <p><strong>${escapeHtml(n.title)}</strong></p>
                        ${n.body ? `<span>${escapeHtml(n.body)}</span>` : ''}
                        <time>${escapeHtml(n.time_ago)}</time>
                    </div>
                </a>`).join('');
        } catch (err) {
            list.innerHTML = `<div class="dropdown-loading">${escapeHtml(err.message)}</div>`;
        }
    }

    function updateBell(count) {
        $$('.bell-count').forEach((el) => {
            el.textContent = count > 9 ? '9+' : count;
            el.style.display = count > 0 ? '' : 'none';
        });
    }

    function initNotifications() {
        document.addEventListener('click', async (e) => {
            const link = e.target.closest('[data-notif-link]');
            if (link) {
                api('api/notifications.php', { action: 'read', id: link.dataset.notifLink }).then((r) => updateBell(r.unread)).catch(() => {});
                return; // let the browser navigate
            }
            const readBtn = e.target.closest('[data-notif-read]');
            if (readBtn) {
                const r = await api('api/notifications.php', { action: 'read', id: readBtn.dataset.notifRead });
                updateBell(r.unread);
                readBtn.closest('.notif-item')?.classList.remove('unread');
            }
            const delBtn = e.target.closest('[data-notif-delete]');
            if (delBtn) {
                const r = await api('api/notifications.php', { action: 'delete', id: delBtn.dataset.notifDelete });
                updateBell(r.unread);
                delBtn.closest('.notif-item')?.remove();
                notifLoaded = false;
            }
        });

        const markAll = async () => {
            const r = await api('api/notifications.php', { action: 'read_all' });
            updateBell(r.unread);
            $$('.notif-item.unread').forEach((el) => el.classList.remove('unread'));
            notifLoaded = false;
            toast('All notifications marked as read.', 'success');
        };
        $('#markAllRead')?.addEventListener('click', markAll);
        $('#markAllReadPage')?.addEventListener('click', markAll);

        $('#clearReadPage')?.addEventListener('click', async () => {
            await api('api/notifications.php', { action: 'clear' });
            toast('Read notifications cleared.', 'success');
            setTimeout(() => window.location.reload(), 600);
        });

        // Poll for new notifications every 60s
        if ($('.bell-btn')) {
            setInterval(() => {
                api('api/notifications.php', { action: 'unread_count' }, 'GET')
                    .then((r) => updateBell(r.unread)).catch(() => {});
            }, 60000);
        }
    }

    /* ------------------------------ Global search ------------------------------ */

    function initSearch() {
        const input = $('#globalSearch');
        const results = $('#searchResults');
        if (!input || !results) return;

        let timer = null;
        const close = () => { results.hidden = true; results.innerHTML = ''; };

        const run = async () => {
            const term = input.value.trim();
            if (term.length < 2) { close(); return; }
            results.hidden = false;
            results.innerHTML = '<div class="search-empty">Searching…</div>';
            try {
                const data = await api('api/search.php', { q: term }, 'GET');
                if (!data.count) {
                    results.innerHTML = `<div class="search-empty">No results for “${escapeHtml(term)}”</div>`;
                    return;
                }
                let html = '';
                if (data.tasks.length) {
                    html += '<div class="search-group">Tasks</div>';
                    html += data.tasks.map((t) => `
                        <a class="search-item" href="${BASE}/index.php?page=task&id=${t.id}">
                            <span class="task-key" style="--key-color:${escapeHtml(t.project_color || '#6366f1')}">${escapeHtml(t.task_key)}</span>
                            <span class="si-main"><span class="si-title">${escapeHtml(t.title)}</span>
                            <span class="si-sub">${escapeHtml(t.project_name)} · ${escapeHtml(t.assignee_name || 'Unassigned')}</span></span>
                        </a>`).join('');
                }
                if (data.projects.length) {
                    html += '<div class="search-group">Projects</div>';
                    html += data.projects.map((p) => `
                        <a class="search-item" href="${BASE}/index.php?page=project&id=${p.id}">
                            <span class="project-badge sm" style="--p-color:${escapeHtml(p.color)}">${escapeHtml(p.code)}</span>
                            <span class="si-main"><span class="si-title">${escapeHtml(p.name)}</span>
                            <span class="si-sub">${p.progress}% complete</span></span>
                        </a>`).join('');
                }
                if (data.users.length) {
                    html += '<div class="search-group">People</div>';
                    html += data.users.map((u) => `
                        <a class="search-item" href="${BASE}/index.php?page=profile&id=${u.id}">
                            <span class="avatar" style="width:26px;height:26px;font-size:10px;background:${escapeHtml(u.color)}">${escapeHtml(initials(u.name))}</span>
                            <span class="si-main"><span class="si-title">${escapeHtml(u.name)}</span>
                            <span class="si-sub">${escapeHtml(u.job_title || u.department_name || u.email)}</span></span>
                        </a>`).join('');
                }
                results.innerHTML = html;
            } catch (err) {
                results.innerHTML = `<div class="search-empty">${escapeHtml(err.message)}</div>`;
            }
        };

        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 260); });
        input.addEventListener('focus', () => { if (input.value.trim().length >= 2) run(); });
        document.addEventListener('click', (e) => { if (!e.target.closest('.global-search')) close(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {
                e.preventDefault(); input.focus();
            }
            if (e.key === 'Escape') close();
        });
    }

    function initials(name) {
        const parts = String(name || '').trim().split(/[\s\-_.]+/).filter(Boolean);
        if (!parts.length) return '?';
        return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
    }

    /* ------------------------------ Modals wiring ------------------------------ */

    function fillForm(form, values) {
        Object.entries(values).forEach(([key, value]) => {
            const field = form.querySelector(`[name="${key}"]`);
            if (!field) return;
            if (field.type === 'checkbox') field.checked = !!Number(value) || value === true;
            else field.value = value ?? '';
        });
    }

    function initModals() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-modal-close]')) { closeModal(); return; }

            const opener = e.target.closest('[data-modal-open]');
            if (opener) {
                const id = opener.dataset.modalOpen;
                const titles = { taskModal: 'New task', projectModal: 'New project', userModal: 'Add team member', deptModal: 'New department', roleModal: 'New role', avatarModal: 'Change profile picture' };
                openTemplate(id, titles[id] || 'Form', (form) => {
                    if (id === 'taskModal') {
                        if (opener.dataset.project) form.project_id.value = opener.dataset.project;
                        if (opener.dataset.status) form.status.value = opener.dataset.status;
                        populateParentTasks(form, opener.dataset.project || '');
                    }
                    if (id === 'projectModal') {
                        form.code?.addEventListener('input', () => {
                            form.code.value = form.code.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                        });
                    }
                });
                return;
            }

            const editTask = e.target.closest('[data-edit-task]');
            if (editTask) {
                const t = JSON.parse(editTask.dataset.editTask);
                openTemplate('taskModal', `Edit ${t.task_key}`, (form) => {
                    form.action_mode.value = 'update';
                    form.querySelector('[name="action"]').value = 'update';
                    form.id.value = t.id;
                    fillForm(form, t);
                    form.querySelector('[data-submit-label]').textContent = 'Save changes';
                    populateParentTasks(form, t.project_id, t.id);
                    form.parent_id.value = t.parent_id || '';
                });
                return;
            }

            const editUser = e.target.closest('[data-edit-user]');
            if (editUser) {
                const u = JSON.parse(editUser.dataset.editUser);
                openTemplate('userModal', `Edit ${u.name}`, (form) => {
                    form.querySelector('[name="action"]').value = 'update';
                    form.id.value = u.id;
                    fillForm(form, u);
                    form.password.required = false;
                    form.password.placeholder = 'Leave empty to keep current';
                    form.querySelector('[data-submit-label]').textContent = 'Save changes';
                    const req = form.querySelector('[data-only-create]');
                    if (req) req.style.display = 'none';
                });
                return;
            }

            const editDept = e.target.closest('[data-edit-dept]');
            if (editDept) {
                const d = JSON.parse(editDept.dataset.editDept);
                openTemplate('deptModal', `Edit ${d.name}`, (form) => {
                    form.querySelector('[name="action"]').value = 'update';
                    form.id.value = d.id;
                    fillForm(form, d);
                    form.querySelector('[data-submit-label]').textContent = 'Save changes';
                });
                return;
            }

            const editRole = e.target.closest('[data-edit-role]');
            if (editRole) {
                const r = JSON.parse(editRole.dataset.editRole);
                openTemplate('roleModal', `Edit ${r.name}`, (form) => {
                    form.querySelector('[name="action"]').value = 'update';
                    form.id.value = r.id;
                    fillForm(form, r);
                    const slug = form.querySelector('[name="slug"]');
                    if (slug) { slug.disabled = true; slug.parentElement.querySelector('.form-hint').textContent = 'The slug of an existing role cannot change.'; }
                    form.querySelector('[data-submit-label]').textContent = 'Save role';
                });
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modalRoot && !modalRoot.hidden) closeModal();
        });
    }

    async function populateParentTasks(form, projectId, excludeId = '') {
        const select = form.parent_id;
        if (!select) return;
        select.innerHTML = '<option value="">None — this is a top-level task</option>';
        if (!projectId) return;
        try {
            const data = await api('api/tasks.php', { action: 'list_for_project', project_id: projectId }, 'GET');
            (data.tasks || []).forEach((t) => {
                if (String(t.id) === String(excludeId)) return;
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = `${t.task_key} — ${t.title}`;
                select.appendChild(opt);
            });
        } catch (err) { /* non blocking */ }
    }

    /* ------------------------------ Form submission ------------------------------ */

    function initForms() {
        document.addEventListener('submit', async (e) => {
            const form = e.target;

            /* AJAX forms (settings pages) */
            if (form.hasAttribute('data-ajax-form')) {
                e.preventDefault();
                const action = form.getAttribute('action');
                setBusy(form, true);
                try {
                    const data = await fetch(action, {
                        method: 'POST', body: new FormData(form),
                        credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then((r) => r.json());
                    if (!data.ok) throw new Error(data.error || 'Save failed.');
                    toast(data.message || 'Saved.', 'success');
                    if (form.hasAttribute('data-reload')) setTimeout(() => window.location.reload(), 700);
                } catch (err) {
                    toast(err.message, 'error');
                } finally {
                    setBusy(form, false);
                }
                return;
            }

            /* Modal forms */
            if (form.classList.contains('modal-form')) {
                e.preventDefault();
                const endpoint = form.dataset.action;
                if (!endpoint) return;
                setBusy(form, true);
                try {
                    const data = await fetch(apiUrl(endpoint), {
                        method: 'POST', body: new FormData(form),
                        credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then((r) => r.json());
                    if (!data.ok) throw new Error(data.error || 'Request failed.');
                    toast(data.message || 'Saved.', 'success');
                    closeModal();
                    if (data.redirect) { setTimeout(() => { window.location.href = data.redirect; }, 500); }
                    else { setTimeout(() => window.location.reload(), 750); }
                } catch (err) {
                    toast(err.message, 'error');
                    setBusy(form, false);
                }
                return;
            }

            /* Dangerous forms (project delete etc.) */
            if (form.hasAttribute('data-confirm-delete')) {
                const ok = await confirmDialog({
                    title: 'Delete permanently?',
                    message: form.dataset.confirm || 'This cannot be undone.',
                    confirmText: 'Yes, delete'
                });
                if (!ok) { e.preventDefault(); return; }
                const action = form.getAttribute('action');
                e.preventDefault();
                try {
                    const data = await fetch(action, {
                        method: 'POST', body: new FormData(form),
                        credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then((r) => r.json());
                    if (!data.ok) throw new Error(data.error);
                    toast(data.message || 'Deleted.', 'success');
                    setTimeout(() => { window.location.href = data.redirect || window.location.href; }, 800);
                } catch (err) {
                    toast(err.message, 'error');
                }
            }
        });

        /* Project selector refreshes the parent-task list */
        document.addEventListener('change', (e) => {
            if (e.target.id === 'tf-project') {
                const form = e.target.closest('form');
                if (form) populateParentTasks(form, e.target.value, form.id?.value || '');
            }
        });
    }

    /* ------------------------------ Inline task updates ------------------------------ */

    function initInlineTaskUpdates() {
        // Sidebar selects / date inputs
        document.addEventListener('change', async (e) => {
            const el = e.target.closest('[data-task-update]');
            if (!el) return;
            const field = el.dataset.field;
            const value = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
            try {
                const r = await api('api/tasks.php', { action: 'update', id: el.dataset.taskUpdate, [field]: value });
                toast(r.message || 'Saved.', 'success');
                if (field === 'status' || field === 'project_id') setTimeout(() => window.location.reload(), 700);
            } catch (err) {
                toast(err.message, 'error');
            }
        });

        // Progress slider
        const range = $('#progressRange');
        const output = $('#progressValue');
        range?.addEventListener('input', () => { if (output) output.textContent = `${range.value}%`; });

        // Status select inside the task table
        document.addEventListener('change', async (e) => {
            const sel = e.target.closest('[data-status-change]');
            if (!sel) return;
            const row = sel.closest('tr');
            try {
                await api('api/tasks.php', { action: 'update', id: sel.dataset.statusChange, status: sel.value });
                toast('Status updated.', 'success');
                row?.classList.toggle('row-done', sel.value === 'done');
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    }

    async function toggleDone(taskId, trigger) {
        if (!trigger) trigger = document.querySelector(`[data-toggle-done="${taskId}"]`);
        trigger?.classList.add('busy');
        try {
            const r = await api('api/tasks.php', { action: 'toggle_done', id: taskId });
            toast(r.message, 'success');
            const done = r.task?.status === 'done';
            const btn = document.querySelector(`[data-toggle-done="${taskId}"]`);
            if (btn) {
                btn.classList.toggle('checked', done);
                btn.innerHTML = done ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>' : '';
            }
            btn?.closest('tr')?.classList.toggle('row-done', done);
            btn?.closest('.mini-task')?.classList.toggle('done', done);
            btn?.closest('li')?.classList.toggle('done', done);
            if (document.querySelector('.task-layout')) setTimeout(() => window.location.reload(), 700);
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            trigger?.classList.remove('busy');
        }
    }

    /* ------------------------------ Deletes ------------------------------ */

    function initRoleDefaults() {
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-apply-defaults]');
            if (!btn) return;
            const roleName = btn.dataset.defaultRole || 'this role';
            const ok = await confirmDialog({
                title: `Restore default permissions for ${roleName}?`,
                message: 'This will replace the current permissions for this role with the built-in defaults. All users with this role will use the restored permissions.',
                confirmText: 'Restore defaults'
            });
            if (!ok) return;
            btn.classList.add('busy');
            try {
                const r = await api('api/roles.php', { action: 'apply_defaults', id: btn.dataset.applyDefaults });
                toast(r.message, 'success');
                setTimeout(() => window.location.reload(), 450);
            } catch (err) {
                toast(err.message, 'error');
                btn.classList.remove('busy');
            }
        });
    }

    function initDeletes() {
        document.addEventListener('click', async (e) => {
            const taskBtn = e.target.closest('[data-delete-task]');
            if (taskBtn) {
                const ok = await confirmDialog({
                    title: `Delete ${taskBtn.dataset.title || 'this task'}?`,
                    message: 'The task, its sub-tasks, comments and attachments will be removed permanently.',
                    confirmText: 'Delete task'
                });
                if (!ok) return;
                try {
                    const r = await api('api/tasks.php', { action: 'delete', id: taskBtn.dataset.deleteTask });
                    toast(r.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                } catch (err) { toast(err.message, 'error'); }
                return;
            }

            const userBtn = e.target.closest('[data-delete-user]');
            if (userBtn) {
                const ok = await confirmDialog({
                    title: `Remove ${userBtn.dataset.name}?`,
                    message: 'Their open tasks will become unassigned and they will lose access immediately.',
                    confirmText: 'Remove member'
                });
                if (!ok) return;
                try {
                    const r = await api('api/users.php', { action: 'delete', id: userBtn.dataset.deleteUser });
                    toast(r.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                } catch (err) { toast(err.message, 'error'); }
                return;
            }

            const deptBtn = e.target.closest('[data-delete-dept]');
            if (deptBtn) {
                const ok = await confirmDialog({ title: `Delete ${deptBtn.dataset.name}?`, message: 'Departments with members cannot be deleted.', confirmText: 'Delete' });
                if (!ok) return;
                try {
                    const r = await api('api/departments.php', { action: 'delete', id: deptBtn.dataset.deleteDept });
                    toast(r.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                } catch (err) { toast(err.message, 'error'); }
                return;
            }

            const roleBtn = e.target.closest('[data-delete-role]');
            if (roleBtn) {
                const ok = await confirmDialog({ title: `Delete the role “${roleBtn.dataset.name}”?`, message: 'Roles still assigned to members cannot be deleted.', confirmText: 'Delete role' });
                if (!ok) return;
                try {
                    const r = await api('api/roles.php', { action: 'delete', id: roleBtn.dataset.deleteRole });
                    toast(r.message, 'success');
                    setTimeout(() => { window.location.href = BASE + '/index.php?page=roles'; }, 700);
                } catch (err) { toast(err.message, 'error'); }
                return;
            }

            const fileBtn = e.target.closest('[data-delete-file]');
            if (fileBtn) {
                try {
                    const r = await api('api/attachments.php', { action: 'delete', id: fileBtn.dataset.deleteFile });
                    toast(r.message, 'success');
                    fileBtn.closest('li')?.remove();
                    bumpCount('#fileCount', -1);
                } catch (err) { toast(err.message, 'error'); }
                return;
            }

            const commentBtn = e.target.closest('[data-delete-comment]');
            if (commentBtn) {
                const ok = await confirmDialog({ title: 'Delete this comment?', message: 'This cannot be undone.', confirmText: 'Delete comment', danger: true, icon: 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6' });
                if (!ok) return;
                try {
                    const r = await api('api/comments.php', { action: 'delete', id: commentBtn.dataset.deleteComment });
                    toast(r.message, 'success');
                    commentBtn.closest('.comment')?.remove();
                    bumpCount('#commentCount', -1);
                } catch (err) { toast(err.message, 'error'); }
                return;
            }

            const memberBtn = e.target.closest('[data-remove-member]');
            if (memberBtn) {
                const ok = await confirmDialog({ title: `Remove ${memberBtn.dataset.name} from this project?`, message: 'They will lose access to the project tasks.', confirmText: 'Remove' });
                if (!ok) return;
                try {
                    const r = await api('api/projects.php', {
                        action: 'remove_member', project_id: memberBtn.dataset.project, user_id: memberBtn.dataset.removeMember
                    });
                    toast(r.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                } catch (err) { toast(err.message, 'error'); }
                return;
            }

            const resetBtn = e.target.closest('[data-reset-password]');
            if (resetBtn) {
                const ok = await confirmDialog({
                    title: `Reset the password of ${resetBtn.dataset.name}?`,
                    message: 'A new random password will be generated and shown to you once.',
                    confirmText: 'Generate password', danger: false, icon: 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z'
                });
                if (!ok) return;
                try {
                    const r = await api('api/users.php', { action: 'reset_password', id: resetBtn.dataset.resetPassword });
                    const node = document.createElement('div');
                    node.innerHTML = `<div class="confirm-body"><h3>New password</h3>
                        <p>Share this with <strong>${escapeHtml(resetBtn.dataset.name)}</strong> through a secure channel. It is shown only once.</p>
                        <p style="margin-top:14px"><code style="font-size:17px;padding:9px 14px;display:inline-block">${escapeHtml(r.password)}</code></p></div>
                        <div class="modal-foot"><button type="button" class="btn btn-primary" data-modal-close>Done</button></div>`;
                    openModal('Password reset', node);
                } catch (err) { toast(err.message, 'error'); }
            }
        });
    }

    function bumpCount(selector, delta) {
        const el = $(selector);
        if (!el) return;
        const next = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
        el.textContent = next;
    }

    /* ------------------------------ Comments ------------------------------ */

    function initComments() {
        const form = $('#commentForm');
        if (!form) return;
        const textarea = form.querySelector('textarea');
        const list = $('#commentList');

        const submit = async () => {
            const body = textarea.value.trim();
            if (!body) { textarea.focus(); return; }
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            try {
                const r = await api('api/comments.php', { action: 'create', task_id: form.dataset.taskId, body });
                $('#noComments')?.remove();
                const c = r.comment;
                const li = document.createElement('li');
                li.className = 'comment';
                li.dataset.id = c.id;
                li.innerHTML = `
                    ${c.avatar}
                    <div class="comment-body">
                        <div class="comment-head">
                            <strong>${escapeHtml(c.user_name)}</strong>
                            <time>${escapeHtml(c.time_ago)}</time>
                            <span class="comment-tools">
                                <button class="link-btn sm" data-edit-comment="${c.id}">Edit</button>
                                <button class="link-btn sm danger" data-delete-comment="${c.id}">Delete</button>
                            </span>
                        </div>
                        <div class="comment-text">${c.body_html}</div>
                    </div>`;
                list.appendChild(li);
                textarea.value = '';
                bumpCount('#commentCount', 1);
                li.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                toast('Comment posted.', 'success');
            } catch (err) {
                toast(err.message, 'error');
            } finally {
                button.disabled = false;
                textarea.focus();
            }
        };

        form.addEventListener('submit', (e) => { e.preventDefault(); submit(); });
        textarea.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); submit(); }
        });

        // Edit comment inline
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-edit-comment]');
            if (!btn) return;
            const li = btn.closest('.comment');
            const textEl = li.querySelector('.comment-text');
            if (li.dataset.editing === '1') return;
            li.dataset.editing = '1';
            const original = textEl.textContent;
            textEl.innerHTML = `<textarea class="input" rows="3">${escapeHtml(original)}</textarea>
                <div class="comment-actions" style="justify-content:flex-end">
                    <button class="btn btn-ghost btn-sm" data-cancel-edit>Cancel</button>
                    <button class="btn btn-primary btn-sm" data-save-edit="${btn.dataset.editComment}">Save</button>
                </div>`;
            textEl.querySelector('textarea').focus();

            textEl.querySelector('[data-cancel-edit]').addEventListener('click', () => {
                textEl.innerHTML = '';
                textEl.innerHTML = original.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])).replace(/\n/g, '<br>');
                li.dataset.editing = '0';
            });
            textEl.querySelector('[data-save-edit]').addEventListener('click', async () => {
                const value = textEl.querySelector('textarea').value.trim();
                if (!value) return;
                try {
                    const r = await api('api/comments.php', { action: 'update', id: btn.dataset.editComment, body: value });
                    textEl.innerHTML = r.body_html;
                    li.dataset.editing = '0';
                    toast('Comment updated.', 'success');
                } catch (err) { toast(err.message, 'error'); }
            });
        });
    }

    /* ------------------------------ Attachments ------------------------------ */

    function initUploads() {
        const form = $('#uploadForm');
        if (!form) return;
        const input = $('#fileInput');
        const list = $('#fileList');

        const upload = async (file) => {
            if (!file) return;
            const data = new FormData(form);
            data.set('action', 'upload');
            data.set('file', file);
            const zone = form.querySelector('.dropzone-inner');
            const original = zone.innerHTML;
            zone.innerHTML = '<svg class="spin" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4"/></svg><span>Uploading…</span>';
            try {
                const r = await fetch(apiUrl('api/attachments.php'), {
                    method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then((res) => res.json());
                if (!r.ok) throw new Error(r.error);
                const a = r.attachment;
                $('#noFiles')?.remove();
                const li = document.createElement('li');
                li.dataset.id = a.id;
                li.innerHTML = `
                    <span class="file-icon">${a.is_image ? '🖼' : '📄'}</span>
                    <div class="file-meta">
                        <a href="${a.url}" target="_blank" rel="noopener">${escapeHtml(a.original_name)}</a>
                        <small>${escapeHtml(a.size)} · ${escapeHtml(a.user_name)} · just now</small>
                    </div>
                    <button class="icon-btn danger sm" data-delete-file="${a.id}" title="Delete file">✕</button>`;
                list.prepend(li);
                bumpCount('#fileCount', 1);
                toast(r.message, 'success');
            } catch (err) {
                toast(err.message, 'error');
            } finally {
                zone.innerHTML = original;
                input.value = '';
            }
        };

        input.addEventListener('change', () => upload(input.files[0]));

        ['dragenter', 'dragover'].forEach((evt) => form.addEventListener(evt, (e) => { e.preventDefault(); form.classList.add('dragging'); }));
        ['dragleave', 'drop'].forEach((evt) => form.addEventListener(evt, (e) => { e.preventDefault(); form.classList.remove('dragging'); }));
        form.addEventListener('drop', (e) => upload(e.dataTransfer.files[0]));
    }

    /* ------------------------------ Time logging ------------------------------ */

    function initTimeLog() {
        const form = $('#timeForm');
        if (!form) return;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            try {
                const data = Object.fromEntries(new FormData(form).entries());
                data.action = 'log_time';
                data.id = form.dataset.taskId;
                const r = await api('api/tasks.php', data);
                toast(r.message, 'success');
                setTimeout(() => window.location.reload(), 700);
            } catch (err) {
                toast(err.message, 'error');
                button.disabled = false;
            }
        });
    }

    /* ------------------------------ Watch / follow ------------------------------ */

    function initWatch() {
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-watch-task]');
            if (!btn) return;
            try {
                const r = await api('api/tasks.php', { action: 'watch', id: btn.dataset.watchTask });
                toast(r.message, 'success');
                setTimeout(() => window.location.reload(), 600);
            } catch (err) { toast(err.message, 'error'); }
        });
    }

    /* ------------------------------ Sub-tasks ------------------------------ */

    function initSubtasks() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-add-subtask]');
            if (!btn) return;
            openTemplate('taskModal', 'New sub-task', (form) => {
                form.querySelector('[name="action"]').value = 'create';
                form.project_id.value = btn.dataset.project;
                form.status.value = 'todo';
                populateParentTasks(form, btn.dataset.project).then(() => { form.parent_id.value = btn.dataset.addSubtask; });
                form.querySelector('[data-submit-label]').textContent = 'Create sub-task';
            });
        });
    }

    /* ------------------------------ Project team ------------------------------ */

    function initProjectTeam() {
        const addBtn = $('#addMemberBtn');
        addBtn?.addEventListener('click', async () => {
            const select = $('#newMemberSelect');
            const userId = select.value;
            if (!userId) { toast('Choose a member first.', 'error'); return; }
            try {
                const r = await api('api/projects.php', { action: 'add_member', project_id: addBtn.dataset.project, user_id: userId });
                toast(r.message, 'success');
                setTimeout(() => window.location.reload(), 700);
            } catch (err) { toast(err.message, 'error'); }
        });

        document.addEventListener('change', async (e) => {
            const sel = e.target.closest('[data-member-role]');
            if (!sel) return;
            try {
                const r = await api('api/projects.php', {
                    action: 'set_member_role', project_id: sel.dataset.project,
                    user_id: sel.dataset.user, project_role: sel.value
                });
                toast(r.message, 'success');
            } catch (err) { toast(err.message, 'error'); }
        });
    }

    /* ------------------------------ Roles matrix ------------------------------ */

    function initRoles() {
        const form = $('#permForm');
        if (!form) return;
        const boxes = $$('input[name="permissions[]"]', form);
        const counter = $('#permCount');
        const update = () => { if (counter) counter.textContent = `${boxes.filter((b) => b.checked).length} selected`; };
        boxes.forEach((b) => b.addEventListener('change', update));

        $$('[data-perm-all]').forEach((btn) => btn.addEventListener('click', () => {
            boxes.forEach((b) => { if (!b.disabled) b.checked = btn.dataset.permAll === '1'; });
            update();
        }));

        $$('[data-toggle-module]').forEach((btn) => btn.addEventListener('click', () => {
            const headerRow = btn.closest('tr');
            let row = headerRow.nextElementSibling;
            const targets = [];
            while (row && !row.classList.contains('row-module')) {
                const box = row.querySelector('input[name="permissions[]"]');
                if (box && !box.disabled) targets.push(box);
                row = row.nextElementSibling;
            }
            const allChecked = targets.every((t) => t.checked);
            targets.forEach((t) => { t.checked = !allChecked; });
            update();
        }));
    }

    /* ------------------------------ Misc UI ------------------------------ */

    function initMisc() {
        // Dismiss alerts
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-dismiss="alert"]')) e.target.closest('.alert')?.remove();
        });

        // Password visibility
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-toggle-password]');
            if (!btn) return;
            const field = $(btn.dataset.togglePassword);
            if (!field) return;
            field.type = field.type === 'password' ? 'text' : 'password';
        });

        // Auto-grow textareas
        $$('textarea').forEach((ta) => {
            ta.addEventListener('input', () => {
                ta.style.height = 'auto';
                ta.style.height = `${Math.min(ta.scrollHeight, 420)}px`;
            });
        });

        // Keyboard shortcut: "n" opens the new task modal
        document.addEventListener('keydown', (e) => {
            if (e.key.toLowerCase() !== 'n' || e.metaKey || e.ctrlKey || e.altKey) return;
            if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
            if (!modalRoot || !modalRoot.hidden) return;
            const opener = $('[data-modal-open="taskModal"]');
            if (opener) { e.preventDefault(); opener.click(); }
        });
    }

    /* ------------------------------ Boot ------------------------------ */

    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initSidebar();
        initDropdowns();
        initNotifications();
        initSearch();
        initModals();
        initForms();
        initInlineTaskUpdates();
        initRoleDefaults();
        initDeletes();
        initComments();
        initUploads();
        initTimeLog();
        initWatch();
        initSubtasks();
        initProjectTeam();
        initRoles();
        initMisc();

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-toggle-done]');
            if (btn) { e.preventDefault(); toggleDone(btn.dataset.toggleDone, btn); }
        });
    });

    // Expose a tiny API for page-specific scripts
    window.TaskFlow = { api, toast, openModal, closeModal, openTemplate, confirmDialog, BASE };
})();
