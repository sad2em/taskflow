/**
 * TaskFlow — UX/UI Enhancements
 * تحسينات تجربة المستخدم والواجهة
 */

(function() {
    'use strict';

    /* ==========================================================================
       1. تحسينات إمكانية الوصول (Accessibility)
       ========================================================================== */
    
    // إضافة تلميحات لوحة المفاتيح للأزرار الهامة
    function addKeyboardHints() {
        const shortcuts = [
            { keys: ['Ctrl+K'], target: '#globalSearch', hint: 'بحث سريع' },
            { keys: ['?'], target: null, hint: 'اختصارات لوحة المفاتيح' },
        ];
        
        shortcuts.forEach(shortcut => {
            if (shortcut.target) {
                const el = document.querySelector(shortcut.target);
                if (el && !el.getAttribute('aria-keyshortcuts')) {
                    el.setAttribute('aria-keyshortcuts', shortcut.keys.join(', '));
                    el.setAttribute('title', `${shortcut.hint} (${shortcut.keys.join(', ')})`);
                }
            }
        });
    }

    // تحسين التباين للعناصر الهامة
    function enhanceContrast() {
        document.querySelectorAll('.btn-primary, .badge, .chip').forEach(el => {
            el.style.webkitTapHighlightColor = 'transparent';
        });
    }

    /* ==========================================================================
       2. تحسينات الأداء (Performance)
       ========================================================================== */
    
    // تأخير تحميل العناصر غير المرئية (Lazy Loading)
    function initLazyLoad() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    observer.unobserve(img);
                }
            });
        }, { rootMargin: '50px' });

        document.querySelectorAll('img[data-src]').forEach(img => observer.observe(img));
    }

    // Debounce للبحث العالمي
    let searchTimeout;
    function debounceSearch(input, callback, delay = 300) {
        input.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => callback(e.target.value), delay);
        });
    }

    /* ==========================================================================
       3. تحسينات التفاعل (Interaction)
       ========================================================================== */
    
    // تأثيرات حركية سلسة عند التمرير
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId !== '#') {
                    e.preventDefault();
                    const target = document.querySelector(targetId);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });
    }

    // إشعارات Toast المحسنة
    window.showToast = function(message, type = 'info', duration = 4000) {
        const toastZone = document.querySelector('.toast-zone') || createToastZone();
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                ${getToastIcon(type)}
            </svg>
            <div class="toast-body">${escapeHtml(message)}</div>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        toastZone.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };

    function createToastZone() {
        const zone = document.createElement('div');
        zone.className = 'toast-zone';
        zone.style.cssText = 'position: fixed; right: 20px; bottom: 20px; z-index: 300; display: grid; gap: 10px; max-width: 380px;';
        document.body.appendChild(zone);
        return zone;
    }

    function getToastIcon(type) {
        const icons = {
            success: '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            error: '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
            info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
            warning: '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'
        };
        return icons[type] || icons.info;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /* ==========================================================================
       4. اختصارات لوحة المفاتيح
       ========================================================================== */
    
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+K للبحث السريع
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.getElementById('globalSearch');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // Escape لإغلاق المودالات — closeModal يعيشُ داخل app.js
            if (e.key === 'Escape') {
                const modal = document.querySelector('.modal-root:not([hidden])');
                if (modal && window.TaskFlow && typeof window.TaskFlow.closeModal === 'function') {
                    window.TaskFlow.closeModal();
                }
            }

            // اختصار "/" يتكفّل به app.js — لا نكرّره هنا حتى لا يتعارض التنفيذ.
        });
    }

    /* ==========================================================================
       5. تحسينات الجوال (Mobile UX)
       ========================================================================== */
    
    function initMobileUX() {
        // إغلاق القائمة الجانبية عند النقر على رابط في شاشات صغيرة
        if (window.innerWidth < 900) {
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    document.body.classList.remove('sidebar-open');
                });
            });
        }

        // تحسين مناطق اللمس للأزرار الصغيرة
        document.querySelectorAll('.icon-btn.sm, .chip').forEach(btn => {
            btn.style.minWidth = '44px';
            btn.style.minHeight = '44px';
        });
    }

    /* ==========================================================================
       6. تتبع مقاييس UX (Analytics Hooks)
       ========================================================================== */
    
    function trackUXMetrics() {
        // تتبع وقت التحميل
        if (window.performance) {
            const timing = performance.timing;
            const loadTime = timing.loadEventEnd - timing.navigationStart;
            
            // إرسال البيانات إذا كان هناك نظام تتبع
            if (window.appConfig && window.appConfig.trackMetrics) {
                console.log(`[UX Metric] Page Load Time: ${loadTime}ms`);
            }
        }

        // تتبع التفاعلات الهامة
        document.addEventListener('click', (e) => {
            const importantElements = ['.btn-primary', '.task-card', '.project-card'];
            const isImportant = importantElements.some(selector => 
                e.target.closest(selector)
            );
            
            if (isImportant && window.appConfig && window.appConfig.trackMetrics) {
                console.log('[UX Metric] Important interaction:', e.target.closest(importantElements.find(s => e.target.closest(s))));
            }
        });
    }

    /* ==========================================================================
       7. تحسينات نموذج الإدخال (Form UX)
       ========================================================================== */
    
    function initFormEnhancements() {
        // إضافة تحقق فوري للحقول
        document.querySelectorAll('.input[required], input[required]').forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                // إزالة حالة الخطأ عند البدء بالكتابة
                if (this.validity.valid) {
                    this.parentElement?.classList.remove('has-error');
                }
            });
        });

        // منع الإرسال المتكرر للنماذج (للنماذج العادية فقط).
        // ملاحظة مهمة: نماذج AJAX (data-ajax-form / .modal-form) يديرها app.js
        // عبر setBusy()، فلا نلمسها هنا إطلاقاً حتى لا نتلف نص الزر أو نعطّله.
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.hasAttribute('data-ajax-form')) return;
            if (form.classList.contains('modal-form')) return;
            if (form.hasAttribute('data-confirm-delete')) return;
            if (form.method && form.method.toLowerCase() === 'get') return;

            const submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn || submitBtn.disabled) return;

            // تعطيل مؤقت فقط — بدون أي تغيير على محتوى الزر.
            // نؤجّل التعطيل تكّة واحدة حتى لا تُفقد قيمة الزر من بيانات النموذج.
            setTimeout(() => {
                if (e.defaultPrevented) return;
                submitBtn.classList.add('is-busy');
                submitBtn.disabled = true;
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('is-busy');
                }, 5000);
            }, 0);
        }, true);
    }

    function validateField(field) {
        if (!field.validity.valid) {
            field.parentElement?.classList.add('has-error');
            
            const errorDiv = field.parentElement?.querySelector('.form-error');
            if (errorDiv) {
                errorDiv.textContent = getFieldErrorMessage(field);
            }
        }
    }

    function getFieldErrorMessage(field) {
        if (field.validity.valueMissing) return 'هذا الحقل مطلوب';
        if (field.validity.typeMismatch) return 'الرجاء إدخال قيمة صحيحة';
        if (field.validity.tooShort) return `الحد الأدنى ${field.minLength} أحرف`;
        if (field.validity.tooLong) return `الحد الأقصى ${field.maxLength} أحرف`;
        return 'قيمة غير صالحة';
    }

    /* ==========================================================================
       8. تحسينات البطاقات والعناصر (Card Enhancements)
       ========================================================================== */
    
    function initCardAnimations() {
        // احترام تفضيل تقليل الحركة، وتعطيل التأثير إن لم يدعم المتصفح IntersectionObserver
        const reduceMotion = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduceMotion || typeof IntersectionObserver === 'undefined') return;

        const reveal = (el) => {
            el.style.opacity = '1';
            el.style.transform = 'none';
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => reveal(entry.target), index * 50);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.01 });

        const cards = document.querySelectorAll('.card, .kpi-card, .project-card');
        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            observer.observe(card);
        });

        // شبكة أمان: أي بطاقة لم يطلقها المراقب (داخل حاوية مخفية، طباعة، بطاقة
        // أطول من الشاشة…) تظهر إجبارياً بعد ثانية بدل أن تبقى غير مرئية.
        setTimeout(() => cards.forEach(reveal), 1000);
    }

    /* ==========================================================================
       9. نظام التلميحات Tooltips
       ========================================================================== */
    
    function initTooltips() {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.style.cssText = `
            position: fixed;
            z-index: 1000;
            padding: 6px 10px;
            background: var(--text);
            color: var(--surface);
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s ease;
            white-space: nowrap;
        `;
        document.body.appendChild(tooltip);

        document.querySelectorAll('[data-tooltip]').forEach(el => {
            el.addEventListener('mouseenter', (e) => {
                tooltip.textContent = el.dataset.tooltip;
                tooltip.style.opacity = '1';
                
                const rect = el.getBoundingClientRect();
                tooltip.style.left = `${rect.left + rect.width / 2}px`;
                tooltip.style.top = `${rect.top - 8}px`;
                tooltip.style.transform = 'translate(-50%, -100%)';
            });
            
            el.addEventListener('mouseleave', () => {
                tooltip.style.opacity = '0';
            });
        });
    }

    /* ==========================================================================
       10. حفظ التفضيلات المحلية
       ========================================================================== */
    
    function saveUserPreference(key, value) {
        try {
            localStorage.setItem(`taskflow_${key}`, JSON.stringify(value));
        } catch (e) {
            console.warn('LocalStorage not available');
        }
    }

    function getUserPreference(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(`taskflow_${key}`);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            return defaultValue;
        }
    }

    /* ==========================================================================
       التهيئة عند تحميل الصفحة
       ========================================================================== */
    
    function init() {
        addKeyboardHints();
        enhanceContrast();
        initLazyLoad();
        initSmoothScroll();
        initKeyboardShortcuts();
        initMobileUX();
        initFormEnhancements();
        initCardAnimations();
        initTooltips();
        trackUXMetrics();
        
        // تطبيق إعدادات محفوظة
        const sidebarCollapsed = getUserPreference('sidebar_collapsed', false);
        if (sidebarCollapsed && window.innerWidth > 1080) {
            document.body.classList.add('sidebar-collapsed');
        }
        
        console.log('✅ TaskFlow UX Enhancements loaded');
    }

    // تشغيل التحسينات عند جاهزية DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // تعريض بعض الدوال عالمياً
    window.saveUserPreference = saveUserPreference;
    window.getUserPreference = getUserPreference;

})();
