/* ==========================================================================
   TaskFlow — Kanban board: native HTML5 drag & drop (no libraries)
   ========================================================================== */
(function () {
    'use strict';

    const board = document.getElementById('kanbanBoard');
    if (!board) return;

    const canEdit = board.dataset.canEdit === '1';
    const zones = Array.from(board.querySelectorAll('[data-dropzone]'));
    let dragged = null;
    let originZone = null;

    const toast = (msg, type = 'info') => window.TaskFlow?.toast(msg, type);
    const api = window.TaskFlow?.api;

    if (!canEdit) {
        board.querySelectorAll('.task-card').forEach((card) => card.setAttribute('draggable', 'false'));
        return;
    }

    function cardCenter(card) {
        const rect = card.getBoundingClientRect();
        return rect.top + rect.height / 2;
    }

    function afterElement(zone, y) {
        const cards = Array.from(zone.querySelectorAll('.task-card:not(.dragging)'));
        let closest = { offset: Number.NEGATIVE_INFINITY, element: null };
        cards.forEach((card) => {
            const offset = y - cardCenter(card);
            if (offset < 0 && offset > closest.offset) closest = { offset, element: card };
        });
        return closest.element;
    }

    function recount(zone) {
        const column = zone.closest('.board-column');
        const counter = column?.querySelector('.col-count');
        if (counter) counter.textContent = zone.querySelectorAll('.task-card').length;
        const empty = zone.querySelector('.board-empty');
        const hasCards = zone.querySelectorAll('.task-card').length > 0;
        if (empty && hasCards) empty.remove();
        if (!empty && !hasCards) {
            const div = document.createElement('div');
            div.className = 'board-empty';
            div.textContent = 'Drop tasks here';
            zone.appendChild(div);
        }
    }

    board.addEventListener('dragstart', (e) => {
        const card = e.target.closest('.task-card');
        if (!card) return;
        dragged = card;
        originZone = card.parentElement;
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        try {
            e.dataTransfer.setData('text/plain', card.dataset.id);
        } catch (err) { /* older browsers */ }
    });

    board.addEventListener('dragend', () => {
        if (!dragged) return;
        dragged.classList.remove('dragging');
        zones.forEach((z) => z.classList.remove('drag-over'));
        board.querySelectorAll('.task-card.dragging').forEach((c) => c.classList.remove('dragging'));
        dragged = null;
        originZone = null;
    });

    zones.forEach((zone) => {
        zone.addEventListener('dragover', (e) => {
            if (!dragged) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            zone.classList.add('drag-over');

            const placeholder = zone.querySelector('.board-empty');
            const after = afterElement(zone, e.clientY);
            if (after) zone.insertBefore(dragged, after);
            else zone.appendChild(dragged);
            if (placeholder) placeholder.remove();
        });

        zone.addEventListener('dragleave', (e) => {
            if (!zone.contains(e.relatedTarget)) zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', async (e) => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (!dragged) return;

            const card = dragged;
            const taskId = card.dataset.id;
            const newStatus = zone.dataset.dropzone;
            const oldStatus = card.dataset.status;
            const newProject = Number(board.dataset.project) || Number(card.dataset.project);

            card.dataset.status = newStatus;
            recount(zone);
            if (originZone && originZone !== zone) recount(originZone);

            const order = Array.from(zone.querySelectorAll('.task-card')).map((c) => Number(c.dataset.id));
            const position = order.indexOf(Number(taskId)) + 1;

            if (!api) return;
            try {
                const r = await api('api/tasks.php', {
                    action: 'move', id: taskId, status: newStatus,
                    project_id: newProject, position, order
                });
                if (newStatus !== oldStatus) toast(r.message || 'Moved.', 'success');
            } catch (err) {
                toast(err.message, 'error');
                // Roll back to the original column
                if (originZone) {
                    card.dataset.status = oldStatus;
                    originZone.appendChild(card);
                    recount(originZone);
                    recount(zone);
                }
            }
        });
    });

    /* Touch support: long-press then drag between columns (basic fallback) */
    let touchCard = null;
    board.addEventListener('touchstart', (e) => {
        const card = e.target.closest('.task-card');
        if (!card) return;
        touchCard = card;
    }, { passive: true });

    board.addEventListener('touchend', async (e) => {
        if (!touchCard) return;
        const touch = e.changedTouches[0];
        const target = document.elementFromPoint(touch.clientX, touch.clientY);
        const zone = target?.closest('[data-dropzone]');
        if (zone && zone.dataset.dropzone !== touchCard.dataset.status) {
            const status = zone.dataset.dropzone;
            const id = touchCard.dataset.id;
            touchCard.dataset.status = status;
            zone.appendChild(touchCard);
            recount(zone);
            try {
                await api('api/tasks.php', { action: 'move', id, status, project_id: touchCard.dataset.project, position: 999 });
                toast('Moved to ' + zone.closest('.board-column').querySelector('h3').textContent, 'success');
            } catch (err) {
                toast(err.message, 'error');
            }
        }
        touchCard = null;
    }, { passive: true });
})();
