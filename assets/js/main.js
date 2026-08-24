(function () {
    'use strict';

    const CONFIG = {
        apiBase: 'api.php',
        endpoints: {
            favorite: 'favorite',
            watchProgress: 'watch-progress',
            search: 'search',
            feedback: 'feedback',
            feedbackReply: 'feedback-reply',
            feedbackLike: 'feedback-like',
            announcementDismiss: 'announcement-dismiss',
            verifyCode: 'send-code',
            userBan: 'user-ban',
            userUnban: 'user-unban',
            source: 'source',
            announcement: 'announcement',
            sendEmail: 'send-email',
            theme: 'theme',
            feedbackList: 'feedback-list',
            feedbackResolve: 'feedback-resolve',
            feedbackDelete: 'feedback-delete',
            sourceDelete: 'source-delete',
            sourceAdd: 'source-add',
            sourceEdit: 'source-edit',
            announcementAdd: 'announcement-add',
            announcementEdit: 'announcement-edit',
            announcementDelete: 'announcement-delete',
            announcementToggle: 'announcement-toggle',
            emailSend: 'send-email',
            themeSave: 'theme-save'
        },
        selectors: {
            mobileMenuBtn: '[data-mobile-menu]',
            mobileMenu: '[data-mobile-menu-panel]',
            header: '[data-header]',
            backToTop: '[data-back-to-top]',
            userDropdownBtn: '[data-user-dropdown]',
            userDropdownMenu: '[data-user-dropdown-menu]',
            announcementModal: '[data-announcement-modal]',
            announcementDismiss: '[data-announcement-dismiss]',
            loginModal: '[data-login-modal]',
            loginRequired: '[data-login-required]',
            searchForm: '[data-search-form]',
            searchInput: '[data-search-input]',
            searchResults: '[data-search-results]',
            movieCard: '[data-movie-card]',
            horizontalRow: '[data-horizontal-row]',
            scrollLeft: '[data-scroll-left]',
            scrollRight: '[data-scroll-right]',
            tabBtn: '[data-tab-btn]',
            tabContent: '[data-tab-content]',
            episodeBtn: '[data-episode]',
            seasonSelect: '[data-season-select]',
            dubbingSelect: '[data-dubbing-select]',
            favoriteBtn: '[data-favorite]',
            feedbackForm: '[data-feedback-form]',
            feedbackReplyForm: '[data-feedback-reply-form]',
            feedbackLikeBtn: '[data-feedback-like]',
            replyToggle: '[data-reply-toggle]',
            themeColorPicker: '[data-theme-color]',
            chartBar: '[data-chart-bar]',
            toastContainer: '[data-toast-container]',
            passwordInput: '[data-password]',
            passwordStrength: '[data-password-strength]',
            verifyCodeBtn: '[data-verify-code-btn]',
            userBanBtn: '[data-user-ban]',
            sourceItem: '[data-source-item]',
            announcementItem: '[data-announcement-item]',
            emailForm: '[data-email-form]',
            feedbackItem: '[data-feedback-item]',
            smoothScrollLink: 'a[href^="#"]',
            lazyImage: '[data-lazy]',
            carousel: '[data-carousel]'
        }
    };

    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function ajax(url, options = {}) {
        const { method = 'GET', data = null, headers = {} } = options;
        const defaultHeaders = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        let fullUrl = url;
        if (url.charAt(0) !== '/' && url.indexOf('http') !== 0 && url.indexOf('?') !== 0) {
            fullUrl = CONFIG.apiBase + url;
        } else if (url.charAt(0) === '?') {
            fullUrl = CONFIG.apiBase + url;
        }
        return fetch(fullUrl, {
            method,
            headers: { ...defaultHeaders, ...headers },
            credentials: 'same-origin',
            body: data ? JSON.stringify(data) : null
        }).then(res => {
            if (!res.ok) throw new Error('Request failed: ' + res.status);
            const ct = res.headers.get('content-type');
            return ct && ct.includes('application/json') ? res.json() : res.text();
        });
    }

    function api(endpoint) {
        return CONFIG.apiBase + '?action=' + CONFIG.endpoints[endpoint];
    }

    function apiUrl(endpoint, params) {
        var url = api(endpoint);
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function(key) {
                url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
            });
        }
        return url;
    }

    function showToast(message, type = 'info', duration = 3000) {
        let container = $(CONFIG.selectors.toastContainer);
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            container.setAttribute('data-toast-container', '');
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
        toast.innerHTML = `<span class="toast__icon">${icons[type] || icons.info}</span><span class="toast__msg">${message}</span>`;
        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));
        setTimeout(() => {
            toast.classList.remove('is-visible');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    function showSpinner(target) {
        if (!target) return;
        const existing = target.querySelector('.spinner');
        if (existing) return;
        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        spinner.setAttribute('role', 'status');
        spinner.setAttribute('aria-label', '加载中');
        target.appendChild(spinner);
    }

    function hideSpinner(target) {
        if (!target) return;
        const spinner = target.querySelector('.spinner');
        if (spinner) spinner.remove();
    }

    function showSkeleton(container, count = 6) {
        if (!container || container.querySelector('.skeleton')) return;
        container.innerHTML = '';
        for (let i = 0; i < count; i++) {
            const sk = document.createElement('div');
            sk.className = 'skeleton skeleton--card';
            container.appendChild(sk);
        }
    }

    function hideSkeleton(container, originalHTML) {
        if (!container) return;
        container.innerHTML = originalHTML;
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');
        const backdrop = modal.querySelector('.modal__backdrop') || modal;
        backdrop.classList.remove('is-visible');
        document.body.style.overflow = '';
    }

    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        const backdrop = modal.querySelector('.modal__backdrop') || modal;
        backdrop.classList.add('is-visible');
        document.body.style.overflow = 'hidden';
    }

    const Handlers = {
        mobileMenu: {
            init() {
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest(CONFIG.selectors.mobileMenuBtn);
                    if (btn) {
                        const panel = $(btn.getAttribute('data-mobile-menu') || CONFIG.selectors.mobileMenu);
                        if (panel) {
                            panel.classList.toggle('is-open');
                            btn.classList.toggle('is-active');
                            const expanded = btn.classList.contains('is-active');
                            btn.setAttribute('aria-expanded', expanded);
                            document.body.classList.toggle('menu-open', expanded);
                        }
                    }
                });
                const menu = $(CONFIG.selectors.mobileMenu);
                if (menu) {
                    menu.addEventListener('click', (e) => {
                        if (e.target.matches('a, button')) {
                            menu.classList.remove('is-open');
                            const btn = $(CONFIG.selectors.mobileMenuBtn);
                            if (btn) {
                                btn.classList.remove('is-active');
                                btn.setAttribute('aria-expanded', 'false');
                            }
                            document.body.classList.remove('menu-open');
                        }
                    });
                }
            }
        },

        headerScroll: {
            init() {
                const header = $(CONFIG.selectors.header);
                if (!header) return;
                let lastScroll = 0;
                window.addEventListener('scroll', () => {
                    const currentScroll = window.pageYOffset;
                    header.classList.toggle('is-scrolled', currentScroll > 50);
                    if (currentScroll > lastScroll && currentScroll > 100) {
                        header.classList.add('is-hidden');
                    } else {
                        header.classList.remove('is-hidden');
                    }
                    lastScroll = currentScroll;
                }, { passive: true });
            }
        },

        backToTop: {
            init() {
                const btn = $(CONFIG.selectors.backToTop);
                if (!btn) return;
                window.addEventListener('scroll', () => {
                    btn.classList.toggle('is-visible', window.pageYOffset > 400);
                }, { passive: true });
                btn.addEventListener('click', () => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
        },

        userDropdown: {
            init() {
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest(CONFIG.selectors.userDropdownBtn);
                    if (btn) {
                        const menu = $(btn.getAttribute('data-user-dropdown-menu') || btn.parentElement.querySelector(CONFIG.selectors.userDropdownMenu));
                        if (menu) {
                            document.querySelectorAll('[data-user-dropdown-menu].is-open').forEach(m => {
                                if (m !== menu) m.classList.remove('is-open');
                            });
                            menu.classList.toggle('is-open');
                        }
                    } else {
                        document.querySelectorAll('[data-user-dropdown-menu].is-open').forEach(m => m.classList.remove('is-open'));
                    }
                });
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        document.querySelectorAll('[data-user-dropdown-menu].is-open').forEach(m => m.classList.remove('is-open'));
                    }
                });
            }
        },

        announcementModal: {
            init() {
                const modal = $(CONFIG.selectors.announcementModal);
                if (!modal) return;
                const annId = modal.dataset.announcementId;
                const dismissedKey = 'announcement_dismissed_' + annId;
                const dismissed = localStorage.getItem(dismissedKey);
                if (dismissed === 'true') return;
                openModal(modal);
                const dismissBtn = modal.querySelector(CONFIG.selectors.announcementDismiss);
                const checkbox = modal.querySelector('[data-announcement-dont-show]');
                if (dismissBtn) {
                    dismissBtn.addEventListener('click', () => {
                        if (checkbox && checkbox.checked) {
                            if (annId) {
                                ajax(api('announcementDismiss'), {
                                    method: 'POST',
                                    data: { announcement_id: annId }
                                }).catch(() => {});
                            }
                            localStorage.setItem(dismissedKey, 'true');
                        }
                        closeModal(modal);
                    });
                }
                modal.addEventListener('click', (e) => {
                    if (e.target.classList.contains('announcement-overlay')) {
                        closeModal(modal);
                    }
                });
            }
        },

        loginRequired: {
            init() {
                document.addEventListener('click', (e) => {
                    const trigger = e.target.closest(CONFIG.selectors.loginRequired);
                    if (trigger) {
                        e.preventDefault();
                        const modal = $(CONFIG.selectors.loginModal);
                        if (modal) openModal(modal);
                        else showToast('请先登录后再操作', 'warning');
                    }
                });
                const modal = $(CONFIG.selectors.loginModal);
                if (modal) {
                    modal.querySelectorAll('[data-modal-close]').forEach(btn => {
                        btn.addEventListener('click', () => closeModal(modal));
                    });
                    modal.addEventListener('click', (e) => {
                        if (e.target.classList.contains('modal__backdrop')) closeModal(modal);
                    });
                }
            }
        },

        search: {
            init() {
                const form = $(CONFIG.selectors.searchForm);
                if (!form) return;
                const input = form.querySelector(CONFIG.selectors.searchInput);
                const results = $(CONFIG.selectors.searchResults);
                let debounceTimer;
                input.addEventListener('input', () => {
                    clearTimeout(debounceTimer);
                    const q = input.value.trim();
                    if (q.length < 2) {
                        if (results) results.innerHTML = '';
                        return;
                    }
                    debounceTimer = setTimeout(() => {
                        ajax(api('search') + '&q=' + encodeURIComponent(q))
                            .then(data => {
                                if (!results) return;
                                const items = Array.isArray(data) ? data : (data.results || []);
                                if (!items.length) {
                                    results.innerHTML = '<p class="search__empty">未找到相关结果</p>';
                                    return;
                                }
                                results.innerHTML = items.map(item => `
                                    <a href="/watch/${item.slug || item.id}" class="search__item">
                                        <img src="${item.poster || ''}" alt="" />
                                        <div>
                                            <strong>${item.title}</strong>
                                            <span>${item.type || ''} · ${item.year || ''}</span>
                                        </div>
                                    </a>
                                `).join('');
                            }).catch(() => {
                                if (results) results.innerHTML = '<p class="search__error">搜索失败，请重试</p>';
                            });
                    }, 300);
                });
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const q = input.value.trim();
                    if (q) window.location.href = `/search?q=${encodeURIComponent(q)}`;
                });
            }
        },

        movieCard: {
            init() {
                document.addEventListener('mouseenter', (e) => {
                    const card = e.target.closest(CONFIG.selectors.movieCard);
                    if (!card) return;
                    document.querySelectorAll(`${CONFIG.selectors.movieCard}.is-hovered`).forEach(c => {
                        if (c !== card) c.classList.remove('is-hovered');
                    });
                    card.classList.add('is-hovered');
                }, true);
                document.addEventListener('mouseleave', (e) => {
                    const card = e.target.closest(CONFIG.selectors.movieCard);
                    if (card) card.classList.remove('is-hovered');
                }, true);
                $$(CONFIG.selectors.movieCard).forEach(card => {
                    const img = card.querySelector('img');
                    if (img && img.dataset.lazy) {
                        movieCard._loadImage(img);
                    }
                });
            },
            _loadImage(img) {
                const src = img.dataset.src || img.dataset.lazy;
                if (!src) return;
                img.dataset.lazyLoaded = 'true';
                const loader = new Image();
                loader.onload = () => {
                    img.src = src;
                    img.classList.add('is-loaded');
                };
                loader.onerror = () => {
                    img.classList.add('is-error');
                };
                loader.src = src;
            }
        },

        horizontalScroll: {
            init() {
                document.addEventListener('click', (e) => {
                    const leftBtn = e.target.closest(CONFIG.selectors.scrollLeft);
                    const rightBtn = e.target.closest(CONFIG.selectors.scrollRight);
                    if (leftBtn) {
                        const row = leftBtn.closest(CONFIG.selectors.horizontalRow);
                        if (row) row.scrollBy({ left: -320, behavior: 'smooth' });
                    }
                    if (rightBtn) {
                        const row = rightBtn.closest(CONFIG.selectors.horizontalRow);
                        if (row) row.scrollBy({ left: 320, behavior: 'smooth' });
                    }
                });
                $$(CONFIG.selectors.horizontalRow).forEach(row => {
                    row.addEventListener('scroll', () => {
                        const leftBtn = row.parentElement.querySelector(CONFIG.selectors.scrollLeft);
                        const rightBtn = row.parentElement.querySelector(CONFIG.selectors.scrollRight);
                        const maxScroll = row.scrollWidth - row.clientWidth;
                        const atStart = row.scrollLeft <= 10;
                        const atEnd = row.scrollLeft >= maxScroll - 10;
                        if (leftBtn) leftBtn.style.opacity = atStart ? '0.3' : '1';
                        if (rightBtn) rightBtn.style.opacity = atEnd ? '0.3' : '1';
                    });
                });
            }
        },

        tabSwitching: {
            init() {
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest(CONFIG.selectors.tabBtn);
                    if (!btn) return;
                    const group = btn.dataset.tabGroup;
                    const target = btn.dataset.tabTarget;
                    const parent = btn.closest('[data-tab-group-wrapper]') || btn.parentElement.parentElement;
                    if (parent) {
                        parent.querySelectorAll(CONFIG.selectors.tabBtn).forEach(b => b.classList.remove('is-active'));
                        parent.querySelectorAll(CONFIG.selectors.tabContent).forEach(c => c.classList.remove('is-active'));
                    }
                    btn.classList.add('is-active');
                    const panel = document.getElementById(target) || parent.querySelector(`[data-tab-content="${target}"]`);
                    if (panel) panel.classList.add('is-active');
                });
            }
        },

        episodeSelection: {
            init() {
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest(CONFIG.selectors.episodeBtn);
                    if (!btn) return;
                    const epList = btn.closest('[data-episode-list]');
                    if (epList) epList.querySelectorAll(CONFIG.selectors.episodeBtn).forEach(b => b.classList.remove('is-active'));
                    btn.classList.add('is-active');
                    const epId = btn.dataset.episode;
                    const player = $('[data-player]');
                    if (player && epId) {
                        player.dataset.episodeId = epId;
                        Handlers.watchHistory.startTracking(epId);
                    }
                });
            }
        },

        seasonSelection: {
            init() {
                const select = $(CONFIG.selectors.seasonSelect);
                if (!select) return;
                select.addEventListener('change', () => {
                    const seasonId = select.value;
                    const epContainer = $('[data-episode-container]');
                    if (!epContainer) return;
                    showSkeleton(epContainer, 12);
                    ajax(`${CONFIG.apiBase}/episodes?season=${seasonId}`)
                        .then(data => {
                            const eps = Array.isArray(data) ? data : (data.episodes || []);
                            hideSkeleton(epContainer, eps.map(ep => `
                                <button class="episode-btn" data-episode="${ep.id}">
                                    ${ep.episode_number}. ${ep.title}
                                </button>
                            `).join(''));
                        })
                        .catch(() => {
                            hideSkeleton(epContainer, '<p class="error">加载失败</p>');
                        });
                });
            }
        },

        dubbingSelection: {
            init() {
                const select = $(CONFIG.selectors.dubbingSelect);
                if (!select) return;
                select.addEventListener('change', () => {
                    const dubId = select.value;
                    const player = $('[data-player]');
                    if (player) {
                        player.dataset.dubId = dubId;
                        const sourceUrl = select.options[select.selectedIndex].dataset.src;
                        if (sourceUrl) {
                            player.src = sourceUrl;
                        }
                    }
                    showToast('已切换配音', 'success', 2000);
                });
            }
        },

        favorite: {
            init() {
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest(CONFIG.selectors.favoriteBtn);
                    if (!btn) return;
                    e.preventDefault();
                    const type = btn.dataset.favorite;
                    const id = btn.dataset.id;
                    const isFav = btn.classList.contains('is-active');
                    ajax(api('favorite'), {
                        method: isFav ? 'DELETE' : 'POST',
                        data: { type, id }
                    }).then(data => {
                        if (data.success) {
                            btn.classList.toggle('is-active');
                            const icon = btn.querySelector('svg, i');
                            if (icon) {
                                icon.textContent = isFav ? '♡' : '♥';
                            }
                            showToast(isFav ? '已取消收藏' : '已添加到收藏', 'success', 2000);
                        }
                    }).catch(err => {
                        if (err.message.includes('401')) {
                            $(CONFIG.selectors.loginRequired)?.click();
                        } else {
                            showToast('操作失败', 'error');
                        }
                    });
                });
            }
        },

        watchHistory: {
            _timer: null,
            _startTime: 0,
            startTracking(episodeId) {
                this._startTime = Date.now();
                if (this._timer) clearInterval(this._timer);
                this._timer = setInterval(() => this._saveProgress(episodeId), 30000);
            },
            _saveProgress(episodeId) {
                const player = $('[data-player]');
                if (!player || !episodeId) return;
                const progress = player.currentTime || 0;
                const duration = player.duration || 0;
                ajax(api('watchProgress'), {
                    method: 'POST',
                    data: { episode_id: episodeId, progress, duration }
                }).catch(() => {});
            },
            init() {
                window.addEventListener('beforeunload', () => {
                    if (this._timer) clearInterval(this._timer);
                });
            }
        },

        feedback: {
            init() {
                document.addEventListener('submit', (e) => {
                    const form = e.target.closest(CONFIG.selectors.feedbackForm);
                    if (!form) return;
                    e.preventDefault();
                    const data = {
                        title: form.querySelector('[data-feedback-title]')?.value,
                        content: form.querySelector('[data-feedback-content]')?.value,
                        type: form.querySelector('[data-feedback-type]')?.value
                    };
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.disabled = true;
                    showSpinner(form);
                    ajax(api('feedback'), {
                        method: 'POST',
                        data
                    }).then(result => {
                        showToast('反馈提交成功，感谢您的意见！', 'success');
                        form.reset();
                        if (result.id) this._addFeedbackItem(result);
                    }).catch(() => {
                        showToast('提交失败，请稍后重试', 'error');
                    }).finally(() => {
                        if (submitBtn) submitBtn.disabled = false;
                        hideSpinner(form);
                    });
                });
            },
            _addFeedbackItem(item) {
                const list = $('[data-feedback-list]');
                if (!list) return;
                const div = document.createElement('div');
                div.className = 'feedback-item';
                div.innerHTML = `
                    <h3>${item.title}</h3>
                    <p>${item.content}</p>
                    <span class="feedback-item__meta">刚刚 · ${item.type || '建议'}</span>
                `;
                list.prepend(div);
            }
        },

        feedbackReply: {
            init() {
                document.addEventListener('click', (e) => {
                    const replyBtn = e.target.closest('[data-reply-btn]');
                    if (!replyBtn) return;
                    e.preventDefault();
                    const item = replyBtn.closest(CONFIG.selectors.feedbackItem);
                    if (!item) return;
                    let form = item.querySelector('.reply-form');
                    if (form) {
                        form.remove();
                        return;
                    }
                    form = document.createElement('form');
                    form.className = 'reply-form';
                    form.setAttribute('data-feedback-reply-form', '');
                    form.innerHTML = `
                        <textarea name="content" placeholder="写下您的回复..." required></textarea>
                        <button type="submit" class="btn btn--primary">发送回复</button>
                        <button type="button" class="btn btn--ghost" data-reply-cancel>取消</button>
                    `;
                    replyBtn.after(form);
                    form.querySelector('[data-reply-cancel]').addEventListener('click', () => form.remove());
                });
                document.addEventListener('submit', (e) => {
                    const form = e.target.closest(CONFIG.selectors.feedbackReplyForm);
                    if (!form) return;
                    e.preventDefault();
                    const item = form.closest(CONFIG.selectors.feedbackItem);
                    const id = item?.dataset.feedbackId;
                    const content = form.querySelector('textarea').value.trim();
                    if (!id || !content) return;
                    ajax(api('feedbackReply'), {
                        method: 'POST',
                        data: { feedback_id: id, content }
                    }).then(reply => {
                        showToast('回复成功', 'success');
                        form.remove();
                        const repliesContainer = item.querySelector('[data-replies]');
                        if (repliesContainer) {
                            const div = document.createElement('div');
                            div.className = 'reply-item';
                            div.innerHTML = `<p>${reply.content}</p><span>刚刚</span>`;
                            repliesContainer.appendChild(div);
                        }
                    }).catch(() => showToast('回复失败', 'error'));
                });
            }
        },

        feedbackLike: {
            init() {
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest(CONFIG.selectors.feedbackLikeBtn);
                    if (!btn) return;
                    const targetId = btn.dataset.likeId;
                    const type = btn.dataset.likeType || 'feedback';
                    ajax(api('feedbackLike'), {
                        method: 'POST',
                        data: { target_id: targetId, type }
                    }).then(data => {
                        const count = btn.querySelector('[data-like-count]');
                        if (count) count.textContent = data.likes;
                        btn.classList.toggle('is-liked');
                    }).catch(() => showToast('操作失败', 'error'));
                });
            }
        },

        replyCollapse: {
            init() {
                document.addEventListener('click', (e) => {
                    const toggle = e.target.closest(CONFIG.selectors.replyToggle);
                    if (!toggle) return;
                    const container = toggle.closest('[data-replies]');
                    if (!container) return;
                    container.classList.toggle('is-expanded');
                    const span = toggle.querySelector('span');
                    if (span) {
                        span.textContent = container.classList.contains('is-expanded') ? '收起' : '展开更多回复';
                    }
                });
                $$('[data-replies]').forEach(container => {
                    const items = container.querySelectorAll('.reply-item');
                    if (items.length > 3) {
                        items.forEach((item, i) => {
                            if (i >= 3) item.style.display = 'none';
                        });
                        const toggle = document.createElement('button');
                        toggle.className = 'reply-toggle';
                        toggle.setAttribute('data-reply-toggle', '');
                        toggle.innerHTML = '<span>展开更多回复</span>';
                        container.appendChild(toggle);
                    }
                });
            }
        },

        themeColor: {
            init() {
                document.addEventListener('input', (e) => {
                    const picker = e.target.closest(CONFIG.selectors.themeColorPicker);
                    if (!picker) return;
                    const color = picker.value;
                    const varName = picker.dataset.themeVar || '--primary-color';
                    document.documentElement.style.setProperty(varName, color);
                    localStorage.setItem('theme_' + varName, color);
                });
                $$(CONFIG.selectors.themeColorPicker).forEach(picker => {
                    const varName = picker.dataset.themeVar || '--primary-color';
                    const saved = localStorage.getItem('theme_' + varName);
                    if (saved) {
                        document.documentElement.style.setProperty(varName, saved);
                        picker.value = saved;
                    }
                });
            }
        },

        adminCharts: {
            init() {
                $$(CONFIG.selectors.chartBar).forEach(bar => {
                    const value = bar.dataset.value;
                    const max = bar.dataset.max || 100;
                    if (value && max) {
                        const pct = (parseFloat(value) / parseFloat(max)) * 100;
                        bar.style.height = pct + '%';
                    }
                });
                $$('[data-chart-animate]').forEach(chart => {
                    chart.classList.add('is-loaded');
                });
            }
        },

        passwordStrength: {
            init() {
                const input = $(CONFIG.selectors.passwordInput);
                if (!input) return;
                const indicator = $(CONFIG.selectors.passwordStrength);
                if (!indicator) return;
                input.addEventListener('input', () => {
                    const val = input.value;
                    let score = 0;
                    if (val.length >= 8) score++;
                    if (/[A-Z]/.test(val)) score++;
                    if (/[0-9]/.test(val)) score++;
                    if (/[^A-Za-z0-9]/.test(val)) score++;
                    const levels = ['太弱', '弱', '一般', '强', '非常强'];
                    const colors = ['#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#27ae60'];
                    const level = Math.min(score, 4);
                    indicator.innerHTML = `
                        <div class="strength-bar">
                            ${[0,1,2,3,4].map(i => `<div class="strength-segment ${i <= level ? 'is-active' : ''}" style="background:${i <= level ? colors[level] : '#ddd'}"></div>`).join('')}
                        </div>
                        <span class="strength-text" style="color:${colors[level]}">${levels[level]}</span>
                    `;
                });
            }
        },

        verifyCodeCountdown: {
            init() {
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest(CONFIG.selectors.verifyCodeBtn);
                    if (!btn || btn.disabled) return;
                    const email = btn.dataset.email || $('[data-verify-email]')?.value;
                    if (!email) { showToast('请先输入邮箱', 'warning'); return; }
                    btn.disabled = true;
                    ajax(api('verifyCode'), {
                        method: 'POST',
                        data: { email }
                    }).then(() => {
                        showToast('验证码已发送', 'success');
                        let count = 60;
                        const originalText = btn.textContent;
                        const timer = setInterval(() => {
                            btn.textContent = `${count}s 后重新发送`;
                            if (count <= 0) {
                                clearInterval(timer);
                                btn.textContent = originalText;
                                btn.disabled = false;
                            }
                            count--;
                        }, 1000);
                    }).catch(() => {
                        showToast('发送失败，请稍后重试', 'error');
                        btn.disabled = false;
                    });
                });
            }
        },

        adminUserManagement: {
            init() {
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest(CONFIG.selectors.userBanBtn);
                    if (!btn) return;
                    const userId = btn.dataset.userId;
                    const action = btn.dataset.banAction;
                    if (!userId) return;
                    if (action === 'ban') {
                        this._showBanDialog(userId, btn);
                    } else if (action === 'unban') {
                        this._confirmAction(btn, userId, 'unban');
                    }
                });
            },
            _showBanDialog(userId, btn) {
                const duration = prompt('请输入封禁时长（小时），留空为永久封禁：', '24');
                if (duration === null) return;
                this._executeBan(btn, userId, duration === '' ? null : parseInt(duration, 10));
            },
            _executeBan(btn, userId, duration) {
                showSpinner(btn);
                ajax(api('userBan'), {
                    method: 'POST',
                    data: { user_id: userId, duration_hours: duration }
                }).then(() => {
                    showToast('封禁成功', 'success');
                    btn.textContent = '解封';
                    btn.dataset.banAction = 'unban';
                    btn.classList.toggle('is-banned', true);
                }).catch(() => showToast('操作失败', 'error'))
                    .finally(() => hideSpinner(btn));
            },
            _confirmAction(btn, userId, action) {
                if (!confirm('确定要执行此操作吗？')) return;
                showSpinner(btn);
                ajax(api('userUnban'), {
                    method: 'POST',
                    data: { user_id: userId }
                }).then(() => {
                    showToast('解封成功', 'success');
                    btn.textContent = '封禁';
                    btn.dataset.banAction = 'ban';
                    btn.classList.toggle('is-banned', false);
                }).catch(() => showToast('操作失败', 'error'))
                    .finally(() => hideSpinner(btn));
            }
        },

        adminSourceManagement: {
            init() {
                document.addEventListener('click', (e) => {
                    const addBtn = e.target.closest('[data-source-add]');
                    const editBtn = e.target.closest('[data-source-edit]');
                    const delBtn = e.target.closest('[data-source-delete]');
                    if (addBtn) this._openSourceForm();
                    else if (editBtn) {
                        const id = editBtn.dataset.sourceId;
                        this._openSourceForm(id);
                    } else if (delBtn) {
                        const id = delBtn.dataset.sourceId;
                        if (confirm('确定删除此资源源？')) {
                            ajax(apiUrl('sourceDelete', { id: id }), { method: 'DELETE' })
                                .then(() => { showToast('删除成功', 'success'); delBtn.closest(CONFIG.selectors.sourceItem)?.remove(); })
                                .catch(() => showToast('删除失败', 'error'));
                        }
                    }
                });
                const form = $('[data-source-form]');
                if (form) {
                    form.addEventListener('submit', (e) => {
                        e.preventDefault();
                        const id = form.dataset.sourceId;
                        const data = {
                            name: form.querySelector('[data-source-name]').value,
                            url: form.querySelector('[data-source-url]').value,
                            quality: form.querySelector('[data-source-quality]').value
                        };
                        showSpinner(form);
                        const method = id ? 'PUT' : 'POST';
                        const url = id ? apiUrl('sourceEdit', { id: id }) : api('source');
                        ajax(url, { method, data })
                            .then(() => { showToast(id ? '更新成功' : '添加成功', 'success'); form.reset(); location.reload(); })
                            .catch(() => showToast('操作失败', 'error'))
                            .finally(() => hideSpinner(form));
                    });
                }
            },
            _openSourceForm(id) {
                const form = $('[data-source-form]');
                if (!form) return;
                if (id) {
                    ajax(apiUrl('sourceEdit', { id: id })).then(data => {
                        form.dataset.sourceId = id;
                        form.querySelector('[data-source-name]').value = data.name || '';
                        form.querySelector('[data-source-url]').value = data.url || '';
                        form.querySelector('[data-source-quality]').value = data.quality || 'HD';
                    });
                } else {
                    delete form.dataset.sourceId;
                    form.reset();
                }
                const modal = form.closest('.modal');
                if (modal) openModal(modal);
            }
        },

        adminAnnouncement: {
            init() {
                document.addEventListener('click', (e) => {
                    const addBtn = e.target.closest('[data-announcement-add]');
                    const editBtn = e.target.closest('[data-announcement-edit]');
                    const delBtn = e.target.closest('[data-announcement-delete]');
                    if (addBtn || editBtn) {
                        const id = editBtn?.dataset.announcementId;
                        this._openForm(id);
                    } else if (delBtn) {
                        const id = delBtn.dataset.announcementId;
                        if (confirm('确定删除此公告？')) {
                            ajax(apiUrl('announcementDelete', { id: id }), { method: 'DELETE' })
                                .then(() => { showToast('删除成功', 'success'); delBtn.closest(CONFIG.selectors.announcementItem)?.remove(); })
                                .catch(() => showToast('删除失败', 'error'));
                        }
                    }
                });
                const form = $('[data-announcement-form]');
                if (form) {
                    form.addEventListener('submit', (e) => {
                        e.preventDefault();
                        const id = form.dataset.announcementId;
                        const data = {
                            title: form.querySelector('[data-ann-title]').value,
                            content: form.querySelector('[data-ann-content]').value,
                            type: form.querySelector('[data-ann-type]').value
                        };
                        showSpinner(form);
                        const method = id ? 'PUT' : 'POST';
                        const url = id ? apiUrl('announcementEdit', { id: id }) : api('announcement');
                        ajax(url, { method, data })
                            .then(() => { showToast(id ? '更新成功' : '发布成功', 'success'); form.reset(); location.reload(); })
                            .catch(() => showToast('操作失败', 'error'))
                            .finally(() => hideSpinner(form));
                    });
                }
            },
            _openForm(id) {
                const form = $('[data-announcement-form]');
                if (!form) return;
                if (id) {
                    ajax(apiUrl('announcementEdit', { id: id })).then(data => {
                        form.dataset.announcementId = id;
                        form.querySelector('[data-ann-title]').value = data.title || '';
                        form.querySelector('[data-ann-content]').value = data.content || '';
                        form.querySelector('[data-ann-type]').value = data.type || 'info';
                    });
                } else {
                    delete form.dataset.announcementId;
                    form.reset();
                }
                const modal = form.closest('.modal');
                if (modal) openModal(modal);
            }
        },

        adminEmail: {
            init() {
                const form = $(CONFIG.selectors.emailForm);
                if (!form) return;
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const data = {
                        to: form.querySelector('[data-email-to]').value,
                        subject: form.querySelector('[data-email-subject]').value,
                        content: form.querySelector('[data-email-content]').value
                    };
                    const btn = form.querySelector('button[type="submit"]');
                    if (btn) btn.disabled = true;
                    showSpinner(form);
                    ajax(api('sendEmail'), {
                        method: 'POST',
                        data
                    }).then(() => showToast('邮件发送成功', 'success'))
                        .catch(() => showToast('发送失败', 'error'))
                        .finally(() => {
                            if (btn) btn.disabled = false;
                            hideSpinner(form);
                        });
                });
            }
        },

        adminFeedback: {
            init() {
                document.addEventListener('click', (e) => {
                    const actBtn = e.target.closest('[data-feedback-action]');
                    if (!actBtn) return;
                    const id = actBtn.dataset.feedbackId;
                    const action = actBtn.dataset.feedbackAction;
                    if (!id || !action) return;
                    showSpinner(actBtn);
                    ajax(apiUrl('feedbackResolve', { id: id, action: action }), { method: 'POST' })
                        .then(() => { showToast('操作成功', 'success'); actBtn.closest(CONFIG.selectors.feedbackItem)?.remove(); })
                        .catch(() => showToast('操作失败', 'error'))
                        .finally(() => hideSpinner(actBtn));
                });
            }
        },

        adminTheme: {
            init() {
                const form = $('[data-admin-theme-form]');
                if (!form) return;
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const data = {};
                    form.querySelectorAll('[data-admin-theme-var]').forEach(input => {
                        data[input.dataset.adminThemeVar] = input.value;
                    });
                    showSpinner(form);
                    ajax(api('theme'), { method: 'POST', data })
                        .then(() => showToast('主题已保存', 'success'))
                        .catch(() => showToast('保存失败', 'error'))
                        .finally(() => hideSpinner(form));
                });
            }
        },

        smoothScroll: {
            init() {
                document.addEventListener('click', (e) => {
                    const link = e.target.closest(CONFIG.selectors.smoothScrollLink);
                    if (!link) return;
                    const href = link.getAttribute('href');
                    if (!href || href === '#') return;
                    const target = document.querySelector(href);
                    if (!target) return;
                    e.preventDefault();
                    const header = $(CONFIG.selectors.header);
                    const offset = header ? header.offsetHeight : 0;
                    const top = target.getBoundingClientRect().top + window.pageYOffset - offset - 20;
                    window.scrollTo({ top, behavior: 'smooth' });
                });
            }
        },

        lazyImages: {
            init() {
                if (!('IntersectionObserver' in window)) {
                    $$(CONFIG.selectors.lazyImage).forEach(img => {
                        img.src = img.dataset.src;
                    });
                    return;
                }
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.add('is-loaded');
                            observer.unobserve(img);
                        }
                    });
                }, { rootMargin: '50px' });
                $$(CONFIG.selectors.lazyImage).forEach(img => observer.observe(img));
            }
        },

        swipeSupport: {
            init() {
                $$(CONFIG.selectors.carousel).forEach(carousel => {
                    let startX = 0, endX = 0;
                    carousel.addEventListener('touchstart', (e) => {
                        startX = e.touches[0].clientX;
                        endX = startX;
                    }, { passive: true });
                    carousel.addEventListener('touchmove', (e) => {
                        endX = e.touches[0].clientX;
                    }, { passive: true });
                    carousel.addEventListener('touchend', () => {
                        const diff = startX - endX;
                        const threshold = 50;
                        if (Math.abs(diff) < threshold) return;
                        if (diff > 0) {
                            const nextBtn = carousel.querySelector('[data-carousel-next]');
                            nextBtn?.click();
                        } else {
                            const prevBtn = carousel.querySelector('[data-carousel-prev]');
                            prevBtn?.click();
                        }
                    });
                });
                $$(CONFIG.selectors.horizontalRow).forEach(row => {
                    let startX = 0, scrollLeft = 0;
                    row.addEventListener('touchstart', (e) => {
                        startX = e.touches[0].clientX;
                        scrollLeft = row.scrollLeft;
                    }, { passive: true });
                    row.addEventListener('touchmove', (e) => {
                        const diff = startX - e.touches[0].clientX;
                        row.scrollLeft = scrollLeft + diff;
                    }, { passive: true });
                });
            }
        }
    };

    ready(() => {
        Object.values(Handlers).forEach(h => {
            if (typeof h.init === 'function') {
                try { h.init(); } catch (err) { console.error('Init error:', err); }
            }
        });
    });

    window.MovieApp = { Handlers, CONFIG, showToast, openModal, closeModal };
})();