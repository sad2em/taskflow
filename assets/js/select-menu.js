/**
 * TaskFlow — Themed <select> menus
 * ---------------------------------------------------------------------------
 * The closed control keeps its existing CSS. Only the popup — which the OS
 * normally draws and CSS cannot reach — is replaced by a themed listbox.
 *
 * The original <select> stays in the DOM and keeps its value, so form
 * submission and every existing change-listener keep working untouched.
 */
(function () {
    'use strict';

    var open = null;           // { select, menu, items, active }
    var typeBuffer = '';
    var typeTimer = null;
    var idSeq = 0;

    var CHECK = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" '
              + 'stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>';

    /* Touch devices get the native wheel/picker, which is better there. */
    var COARSE = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

    function enhanceable(sel) {
        return sel && sel.tagName === 'SELECT'
            && !sel.multiple && !sel.disabled
            && (!sel.size || sel.size <= 1)
            && !sel.hasAttribute('data-native-select');
    }

    /* ------------------------------------------------------------------ build */

    function buildMenu(sel) {
        var menu = document.createElement('div');
        menu.className = 'tf-selectmenu';
        menu.setAttribute('role', 'listbox');
        menu.id = 'tf-selectmenu-' + (++idSeq);

        var list = document.createElement('div');
        list.className = 'tf-selectmenu-list';
        var items = [];

        function addOption(opt) {
            var item = document.createElement('div');
            item.className = 'tf-selectmenu-item';
            item.setAttribute('role', 'option');
            item.id = menu.id + '-opt' + opt.index;
            item.dataset.index = String(opt.index);

            if (opt.disabled) {
                item.classList.add('is-disabled');
                item.setAttribute('aria-disabled', 'true');
            }
            if (opt.index === sel.selectedIndex) {
                item.classList.add('is-selected');
                item.setAttribute('aria-selected', 'true');
            }

            var label = document.createElement('span');
            label.className = 'tf-selectmenu-label';
            label.textContent = opt.textContent.trim() || '\u00a0';

            var tick = document.createElement('span');
            tick.className = 'tf-selectmenu-tick';
            tick.innerHTML = CHECK;

            /* Lets a colour-coded option (status, priority…) carry its dot. */
            var colour = opt.getAttribute('data-color');
            if (colour) {
                var dot = document.createElement('span');
                dot.className = 'tf-selectmenu-dot';
                dot.style.background = colour;
                item.appendChild(dot);
            }

            item.appendChild(label);
            item.appendChild(tick);
            list.appendChild(item);
            if (!opt.disabled) { items.push(item); }
        }

        Array.prototype.forEach.call(sel.children, function (child) {
            if (child.tagName === 'OPTGROUP') {
                var head = document.createElement('div');
                head.className = 'tf-selectmenu-group';
                head.textContent = child.label;
                list.appendChild(head);
                Array.prototype.forEach.call(child.children, addOption);
            } else if (child.tagName === 'OPTION') {
                addOption(child);
            }
        });

        if (!list.children.length) {
            var empty = document.createElement('div');
            empty.className = 'tf-selectmenu-empty';
            empty.textContent = 'No options';
            list.appendChild(empty);
        }

        menu.appendChild(list);
        return { menu: menu, items: items };
    }

    /* --------------------------------------------------------------- position */

    function place(menu, sel) {
        var r = sel.getBoundingClientRect();
        var gap = 6;
        var width = Math.max(r.width, 180);

        menu.style.width = width + 'px';
        menu.style.left = Math.min(r.left, window.innerWidth - width - 8) + 'px';
        menu.style.top = '0px';
        menu.style.maxHeight = '';

        var height = menu.offsetHeight;
        var below = window.innerHeight - r.bottom - gap - 8;
        var above = r.top - gap - 8;

        if (height <= below || below >= above) {
            menu.style.top = (r.bottom + gap) + 'px';
            menu.style.maxHeight = Math.max(below, 120) + 'px';
            menu.classList.remove('is-above');
        } else {
            menu.style.maxHeight = Math.max(above, 120) + 'px';
            menu.style.top = Math.max(8, r.top - gap - Math.min(height, above)) + 'px';
            menu.classList.add('is-above');
        }
    }

    /* ------------------------------------------------------------ open/close */

    function openMenu(sel) {
        closeMenu();

        var built = buildMenu(sel);
        document.body.appendChild(built.menu);

        open = { select: sel, menu: built.menu, items: built.items, active: null };

        sel.classList.add('tf-select-open');
        sel.setAttribute('aria-expanded', 'true');
        sel.setAttribute('aria-controls', built.menu.id);

        place(built.menu, sel);

        var current = built.menu.querySelector('.is-selected') || built.items[0];
        setActive(current, false);
        if (current) {
            built.menu.querySelector('.tf-selectmenu-list').scrollTop =
                Math.max(0, current.offsetTop - 80);
        }
    }

    function closeMenu() {
        if (!open) { return; }
        open.select.classList.remove('tf-select-open');
        open.select.removeAttribute('aria-expanded');
        open.select.removeAttribute('aria-controls');
        open.select.removeAttribute('aria-activedescendant');
        if (open.menu.parentNode) { open.menu.parentNode.removeChild(open.menu); }
        open = null;
    }

    function setActive(item, scroll) {
        if (!open) { return; }
        if (open.active) { open.active.classList.remove('is-active'); }
        open.active = item || null;
        if (!item) { return; }
        item.classList.add('is-active');
        open.select.setAttribute('aria-activedescendant', item.id);
        if (scroll !== false && item.scrollIntoView) {
            item.scrollIntoView({ block: 'nearest' });
        }
    }

    function move(step) {
        if (!open || !open.items.length) { return; }
        var i = open.items.indexOf(open.active);
        i = i < 0 ? 0 : i + step;
        if (i < 0) { i = 0; }
        if (i > open.items.length - 1) { i = open.items.length - 1; }
        setActive(open.items[i]);
    }

    function commit(item) {
        if (!open || !item || item.classList.contains('is-disabled')) { return; }
        var sel = open.select;
        var index = parseInt(item.dataset.index, 10);
        closeMenu();

        if (sel.selectedIndex !== index) {
            sel.selectedIndex = index;
            sel.dispatchEvent(new Event('input', { bubbles: true }));
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
        sel.focus();
    }

    /* ------------------------------------------------------------- listeners */

    /* Suppressing mousedown is what stops the native popup from appearing. */
    document.addEventListener('mousedown', function (e) {
        var sel = e.target.closest ? e.target.closest('select') : null;
        if (!sel || COARSE || !enhanceable(sel)) { return; }
        e.preventDefault();
        if (open && open.select === sel) { closeMenu(); return; }
        sel.focus();
        openMenu(sel);
    });

    document.addEventListener('mousedown', function (e) {
        if (!open || !e.target || !e.target.closest) { return; }
        if (e.target.closest('select')) { return; }
        if (e.target.closest('.tf-selectmenu')) {
            /* Keep focus on the <select> so it doesn't blur before the
               click fires — a non-focusable item would otherwise steal
               focus away, triggering focusout -> closeMenu() and losing
               the race against the upcoming click/commit. */
            e.preventDefault();
            return;
        }
        closeMenu();
    });

    document.addEventListener('click', function (e) {
        if (!open || !e.target || !e.target.closest) { return; }
        var item = e.target.closest('.tf-selectmenu-item');
        if (item && open.menu.contains(item)) { commit(item); }
    });

    document.addEventListener('mousemove', function (e) {
        if (!open || !e.target || !e.target.closest) { return; }
        var item = e.target.closest('.tf-selectmenu-item');
        if (item && open.menu.contains(item) && !item.classList.contains('is-disabled')) {
            setActive(item, false);
        }
    });

    document.addEventListener('keydown', function (e) {
        var sel = e.target.tagName === 'SELECT' ? e.target : null;

        if (open) {
            switch (e.key) {
                case 'Escape':
                    e.preventDefault();
                    var trigger = open.select;
                    closeMenu();
                    trigger.focus();
                    return;
                case 'ArrowDown': e.preventDefault(); move(1); return;
                case 'ArrowUp':   e.preventDefault(); move(-1); return;
                case 'Home':      e.preventDefault(); setActive(open.items[0]); return;
                case 'End':       e.preventDefault(); setActive(open.items[open.items.length - 1]); return;
                case 'Enter':
                case ' ':
                case 'Tab':
                    e.preventDefault();
                    commit(open.active);
                    return;
                default: break;
            }

            if (e.key.length === 1) {
                typeBuffer += e.key.toLowerCase();
                clearTimeout(typeTimer);
                typeTimer = setTimeout(function () { typeBuffer = ''; }, 600);
                var hit = open.items.filter(function (it) {
                    return it.textContent.trim().toLowerCase().indexOf(typeBuffer) === 0;
                })[0];
                if (hit) { e.preventDefault(); setActive(hit); }
            }
            return;
        }

        if (!sel || COARSE || !enhanceable(sel)) { return; }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openMenu(sel);
        }
    });

    /* Anything that moves the trigger closes the menu rather than detaching it. */
    window.addEventListener('scroll', function () { closeMenu(); }, true);
    window.addEventListener('resize', closeMenu);
    document.addEventListener('focusout', function (e) {
        if (open && e.target === open.select) {
            setTimeout(function () {
                if (open && document.activeElement !== open.select
                    && !open.menu.contains(document.activeElement)) { closeMenu(); }
            }, 0);
        }
    });
}());