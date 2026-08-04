<template>
    <div class="ag-notification-wrapper">
        <el-dropdown trigger="click" @visible-change="onDropdownVisible">
            <span class="el-dropdown-link notification-icon text-secondary">
                <el-badge :value="badgeCount" :hidden="!hasLoaded || badgeCount === 0" class="ag-bell-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bell"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>
                </el-badge>
            </span>
            <el-dropdown-menu slot="dropdown" class="ag-notification-menu">
                <li class="ag-notification-panel" @click.stop>
                    <div class="ag-notification-header">
                        <h4 class="ag-notification-title">Notificaciones</h4>
                        <button
                            v-if="hasUnreadNotifications"
                            type="button"
                            class="text-xs text-gray-400 hover:text-gray-600 font-medium ag-mark-all-read"
                            @click.stop="markAllAsRead"
                        >
                            Marcar todo como leído
                        </button>
                    </div>

                    <div class="ag-notification-filters-group">
                        <div class="ag-notification-filters ag-notification-filters--category">
                            <button
                                v-for="filter in filters"
                                :key="filter.id"
                                type="button"
                                class="ag-filter-chip"
                                :class="{ 'is-active': activeFilter === filter.id }"
                                @click.stop="setActiveFilter(filter.id)"
                            >
                                {{ filter.label }}
                            </button>
                        </div>

                        <div class="ag-notification-filters ag-notification-filters--read">
                            <button
                                v-for="readFilter in readFilters"
                                :key="readFilter.id"
                                type="button"
                                class="ag-filter-chip"
                                :class="{ 'is-active': activeFilter === readFilter.id }"
                                @click.stop="setActiveFilter(readFilter.id)"
                            >
                                {{ readFilter.label }}
                            </button>
                        </div>
                    </div>

                    <div class="ag-notification-list">
                        <transition-group
                            v-if="filteredNotifications.length"
                            name="ag-list-fade"
                            tag="div"
                            class="ag-notification-list-inner"
                        >
                            <a
                                v-for="notification in filteredNotifications"
                                :key="notification.id"
                                href="#"
                                class="ag-notification-card"
                                :class="{ 'is-unread': isUnread(notification) }"
                                @click.prevent="openNotification(notification)"
                            >
                                <transition name="ag-dot-fade">
                                    <span
                                        v-if="isUnread(notification)"
                                        class="ag-unread-dot w-2 h-2 bg-blue-600 rounded-full"
                                    ></span>
                                </transition>
                                <button
                                    v-if="isUnread(notification)"
                                    type="button"
                                    class="text-xs text-gray-400 hover:text-gray-600 font-medium ag-mark-read-btn"
                                    @click.stop="markAsRead(notification)"
                                >
                                    Marcar como leído
                                </button>
                                <div class="ag-notification-card__icon" :class="`is-${notification.icon_bg}`">
                                    <component :is="iconComponents[notification.icon]" />
                                </div>
                                <div class="ag-notification-card__body">
                                    <div class="ag-notification-card__title-row">
                                        <strong class="ag-notification-card__title">{{ notification.title }}</strong>
                                        <span v-if="notification.tag" class="ag-notification-tag">{{ notification.tag }}</span>
                                    </div>
                                    <p class="ag-notification-card__description">
                                        <template v-for="(part, index) in notification.description_parts">
                                            <strong v-if="part.bold" :key="'b-' + notification.id + '-' + index">{{ part.text }}</strong>
                                            <span v-else :key="'t-' + notification.id + '-' + index">{{ part.text }}</span>
                                        </template>
                                    </p>
                                    <span class="ag-notification-card__time">{{ notification.time_ago }}</span>
                                </div>
                            </a>
                        </transition-group>
                        <div v-else class="ag-notification-empty">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
                                <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
                            </svg>
                            <p>{{ emptyStateMessage }}</p>
                        </div>
                    </div>
                </li>
            </el-dropdown-menu>
        </el-dropdown>
    </div>
</template>

<script>
const IconSend = {
    template: `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
            <path d="M10 14l11 -11" />
            <path d="M21 3l-6.5 18a.55 .55 0 0 1 -1 0l-3.5 -7l-7 -3.5a.55 .55 0 0 1 0 -1l18 -6.5" />
        </svg>
    `
};

const IconInvoice = {
    template: `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
            <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
            <path d="M9 7l1 0" />
            <path d="M9 13l6 0" />
            <path d="M13 17l2 0" />
        </svg>
    `
};

const IconBox = {
    template: `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
            <path d="M12 3l8 4.5v9l-8 4.5l-8 -4.5v-9l8 -4.5" />
            <path d="M12 12l8 -4.5" />
            <path d="M12 12v9" />
            <path d="M12 12l-8 -4.5" />
        </svg>
    `
};

const IconBag = {
    template: `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
            <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
            <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
            <path d="M17 17h-11v-14h-2" />
            <path d="M6 5l14 1l-1 7h-13" />
        </svg>
    `
};

const IconCloudAlert = {
    template: `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
            <path d="M6.657 18c-2.572 0 -4.657 -2.007 -4.657 -4.483c0 -2.475 2.085 -4.482 4.657 -4.482c.393 -1.762 1.794 -3.2 3.675 -3.773c1.88 -.572 3.956 -.193 5.444 1c1.488 1.19 2.162 3.007 1.77 4.769h.993c1.913 0 3.464 1.567 3.464 3.5c0 1.933 -1.551 3.5 -3.464 3.5h-11.878" />
            <path d="M13 16l-2 2l2 2" />
            <path d="M11 18h4" />
        </svg>
    `
};

const READ_STORAGE_PREFIX = 'ag_header_notifications_read';
const POLL_INTERVAL_MS = 30000;
const POLL_INTERVAL_OPEN_MS = 15000;

export default {
    components: {
        IconSend,
        IconInvoice,
        IconBox,
        IconBag,
        IconCloudAlert
    },
    props: {
        initialCount: {
            type: Number,
            default: 0
        }
    },
    data() {
        return {
            notifications: [],
            readSnapshots: {},
            hasLoaded: false,
            activeFilter: 'todas',
            polling: null,
            loading: false,
            pollingInFlight: false,
            pendingRefresh: false,
            dropdownOpen: false,
            requestToken: 0,
            boundVisibilityHandler: null,
            boundFocusHandler: null,
            filters: [
                { id: 'todas', label: 'Todas' },
                { id: 'comprobantes', label: 'Comprobantes' },
                { id: 'pagos', label: 'Pagos' },
                { id: 'inventario', label: 'Inventario' },
                { id: 'cotizaciones', label: 'Cotizaciones' },
                { id: 'pedidos', label: 'Pedidos' },
                { id: 'sistema', label: 'Sistema' }
            ],
            readFilters: [
                { id: 'no-leidas', label: 'No leídas' },
                { id: 'leidas', label: 'Leídas' }
            ],
            iconComponents: {
                send: 'IconSend',
                invoice: 'IconInvoice',
                box: 'IconBox',
                bag: 'IconBag',
                'cloud-alert': 'IconCloudAlert'
            }
        };
    },
    computed: {
        badgeCount() {
            if (!this.hasLoaded) {
                return 0;
            }

            return this.unreadCount;
        },
        unreadCount() {
            return this.notifications.filter((notification) => this.isUnread(notification)).length;
        },
        hasUnreadNotifications() {
            return this.unreadCount > 0;
        },
        filteredNotifications() {
            if (this.activeFilter === 'no-leidas') {
                return this.notifications.filter((notification) => this.isUnread(notification));
            }

            if (this.activeFilter === 'leidas') {
                return this.notifications.filter((notification) => !this.isUnread(notification));
            }

            if (this.activeFilter === 'todas') {
                return [...this.notifications].sort((first, second) => {
                    const firstUnread = this.isUnread(first) ? 0 : 1;
                    const secondUnread = this.isUnread(second) ? 0 : 1;

                    return firstUnread - secondUnread;
                });
            }

            if (this.activeFilter === 'pedidos') {
                return this.notifications.filter((notification) => notification.type === 'pedidos');
            }

            return this.notifications.filter((notification) => notification.type === this.activeFilter);
        },
        emptyStateMessage() {
            if (this.activeFilter === 'no-leidas') {
                return 'No hay notificaciones no leídas';
            }

            if (this.activeFilter === 'leidas') {
                return 'Aún no hay notificaciones leídas';
            }

            if (this.activeFilter !== 'todas') {
                return `No hay notificaciones en ${this.getCategoryLabel(this.activeFilter)}`;
            }

            return '¡Todo al día! No hay pendientes';
        }
    },
    created() {
        this.readSnapshots = this.loadReadSnapshots();
    },
    mounted() {
        this.fetchNotifications();
        this.startPolling();
        this.bindRealtimeListeners();
    },
    beforeDestroy() {
        this.stopPolling();
        this.unbindRealtimeListeners();
    },
    methods: {
        setActiveFilter(filterId) {
            this.activeFilter = filterId;
        },
        getCategoryLabel(categoryId) {
            const category = this.filters.find((filter) => filter.id === categoryId);

            return category ? category.label.toLowerCase() : 'esta categoría';
        },
        readStorageKey() {
            return `${READ_STORAGE_PREFIX}_${window.location.hostname}`;
        },
        loadReadSnapshots() {
            try {
                const stored = localStorage.getItem(this.readStorageKey());

                if (!stored) {
                    return {};
                }

                const parsed = JSON.parse(stored);

                if (Array.isArray(parsed)) {
                    return parsed.reduce((snapshots, id) => {
                        snapshots[id] = '__legacy__';

                        return snapshots;
                    }, {});
                }

                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (error) {
                return {};
            }
        },
        persistReadSnapshots() {
            try {
                localStorage.setItem(this.readStorageKey(), JSON.stringify(this.readSnapshots));
            } catch (error) {
                console.log('No se pudo guardar el estado de lectura de notificaciones.', error);
            }
        },
        getNotificationFingerprint(notification) {
            if (!notification) {
                return '';
            }

            return [
                notification.id,
                notification.title || '',
                notification.tag || '',
                String(notification.count ?? ''),
                JSON.stringify(notification.description_parts || []),
            ].join('|');
        },
        reconcileLegacySnapshots(notifications) {
            let changed = false;

            notifications.forEach((notification) => {
                if (this.readSnapshots[notification.id] !== '__legacy__') {
                    return;
                }

                this.$set(
                    this.readSnapshots,
                    notification.id,
                    this.getNotificationFingerprint(notification)
                );
                changed = true;
            });

            if (changed) {
                this.persistReadSnapshots();
            }
        },
        isUnread(notification) {
            if (!notification || notification.unread === false) {
                return false;
            }

            const fingerprint = this.getNotificationFingerprint(notification);
            const readFingerprint = this.readSnapshots[notification.id];

            if (!readFingerprint || readFingerprint === '__legacy__') {
                return true;
            }

            return readFingerprint !== fingerprint;
        },
        applyReadState(notifications) {
            this.reconcileLegacySnapshots(notifications);

            return notifications.map((notification) => ({
                ...notification,
                unread: this.isUnread(notification),
            }));
        },
        notificationsHaveChanged(currentNotifications, nextNotifications) {
            if (currentNotifications.length !== nextNotifications.length) {
                return true;
            }

            return nextNotifications.some((notification, index) => {
                const current = currentNotifications[index];

                if (!current || current.id !== notification.id) {
                    return true;
                }

                return (
                    current.title !== notification.title
                    || String(current.count ?? '') !== String(notification.count ?? '')
                    || JSON.stringify(current.description_parts) !== JSON.stringify(notification.description_parts)
                );
            });
        },
        syncNotificationsSilently(nextNotifications) {
            if (!this.notificationsHaveChanged(this.notifications, nextNotifications)) {
                return false;
            }

            this.notifications = nextNotifications;

            return true;
        },
        markAsRead(notification) {
            if (!notification || !this.isUnread(notification)) {
                return;
            }

            this.$set(
                this.readSnapshots,
                notification.id,
                this.getNotificationFingerprint(notification)
            );
            this.persistReadSnapshots();

            const index = this.notifications.findIndex((item) => item.id === notification.id);

            if (index !== -1) {
                this.$set(this.notifications[index], 'unread', false);
            }
        },
        markAllAsRead() {
            let changed = false;

            this.notifications.forEach((notification) => {
                if (!this.isUnread(notification)) {
                    return;
                }

                this.$set(
                    this.readSnapshots,
                    notification.id,
                    this.getNotificationFingerprint(notification)
                );
                changed = true;
                this.$set(notification, 'unread', false);
            });

            if (changed) {
                this.persistReadSnapshots();
            }
        },
        onDropdownVisible(visible) {
            this.dropdownOpen = visible;

            if (visible) {
                this.fetchNotifications();
            }

            this.restartPolling();
        },
        openNotification(notification) {
            const url = notification && notification.url;

            if (!url || url === '#') {
                return;
            }

            window.location.assign(url);
        },
        async fetchNotifications(options = {}) {
            const silent = options.silent === true || options.background === true;

            if (silent) {
                if (this.pollingInFlight) {
                    this.pendingRefresh = true;
                    return;
                }

                this.pollingInFlight = true;
            } else if (this.loading) {
                return;
            } else {
                this.loading = true;
            }

            const requestToken = ++this.requestToken;

            try {
                const response = await this.$http.get('/notifications/header');
                const data = response.data || {};

                if (requestToken !== this.requestToken) {
                    return;
                }

                const nextNotifications = this.applyReadState(
                    Array.isArray(data.notifications) ? data.notifications : []
                );

                if (silent) {
                    this.syncNotificationsSilently(nextNotifications);
                } else {
                    this.notifications = nextNotifications;
                }

                this.hasLoaded = true;
            } catch (error) {
                if (requestToken === this.requestToken) {
                    console.log('No se pudieron actualizar las notificaciones.', error);

                    if (!silent || !this.hasLoaded) {
                        this.notifications = [];
                    }
                }
            } finally {
                if (requestToken === this.requestToken) {
                    if (silent) {
                        this.pollingInFlight = false;
                    } else {
                        this.loading = false;
                        this.hasLoaded = true;
                    }
                }

                if (this.pendingRefresh) {
                    this.pendingRefresh = false;
                    this.fetchNotifications({ silent: true });
                }
            }
        },
        bindRealtimeListeners() {
            this.boundVisibilityHandler = () => {
                if (!document.hidden) {
                    this.fetchNotifications({ silent: true });
                }
            };
            this.boundFocusHandler = () => {
                this.fetchNotifications({ silent: true });
            };

            document.addEventListener('visibilitychange', this.boundVisibilityHandler);
            window.addEventListener('focus', this.boundFocusHandler);
        },
        unbindRealtimeListeners() {
            if (this.boundVisibilityHandler) {
                document.removeEventListener('visibilitychange', this.boundVisibilityHandler);
                this.boundVisibilityHandler = null;
            }

            if (this.boundFocusHandler) {
                window.removeEventListener('focus', this.boundFocusHandler);
                this.boundFocusHandler = null;
            }
        },
        getPollInterval() {
            return this.dropdownOpen ? POLL_INTERVAL_OPEN_MS : POLL_INTERVAL_MS;
        },
        restartPolling() {
            this.stopPolling();
            this.startPolling();
        },
        startPolling() {
            this.polling = setInterval(() => {
                this.fetchNotifications({ silent: true });
            }, this.getPollInterval());
        },
        stopPolling() {
            if (this.polling) {
                clearInterval(this.polling);
                this.polling = null;
            }
        }
    }
};
</script>

<style scoped>
.ag-notification-wrapper {
    display: inline-block;
    vertical-align: middle;
}

.ag-bell-badge :deep(.el-badge__content) {
    background-color: #2563eb !important;
    top: 0 !important;
    right: 2px !important;
    border: none;
    padding: 0 5px;
    font-size: 10px;
    height: 16px;
    line-height: 16px;
    min-width: 18px;
    text-align: center;
    border-radius: 3px !important;
    font-weight: bold;
}

.ag-notification-menu {
    background-color: #ffffff !important;
    z-index: 5000 !important;
    border: none !important;
    box-shadow: 0 8px 24px rgba(5, 12, 38, 0.12) !important;
    border-radius: 12px !important;
    padding: 0 !important;
    margin-top: 10px !important;
    min-width: 420px !important;
    max-width: 420px !important;
}

.ag-notification-panel {
    list-style: none;
    margin: 0;
    padding: 0;
    width: 420px;
}

.ag-notification-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px 12px;
    border-bottom: 1px solid #f0f2f5;
}

.text-xs {
    font-size: 12px;
    line-height: 1;
}

.text-gray-400 {
    color: #9CA3AF;
}

.hover\:text-gray-600:hover {
    color: #4B5563;
}

.font-medium {
    font-weight: 500;
}

.w-2 {
    width: 8px;
}

.h-2 {
    height: 8px;
}

.bg-blue-600 {
    background-color: #2563EB;
}

.rounded-full {
    border-radius: 9999px;
}

.ag-mark-all-read {
    border: none;
    background: transparent;
    padding: 0;
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.2s ease;
}

.ag-mark-all-read:hover {
    color: #4B5563;
}

.ag-notification-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #050C26;
    line-height: 1.2;
}

.ag-notification-filters-group {
    border-bottom: 1px solid #f0f2f5;
}

.ag-notification-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.ag-notification-filters--category {
    padding: 12px 16px 6px;
}

.ag-notification-filters--read {
    padding: 0 16px 12px;
}

.ag-filter-chip {
    border: none;
    background: #f3f4f6;
    color: #6b7280;
    font-size: 12px;
    font-weight: 500;
    line-height: 1;
    padding: 7px 12px;
    border-radius: 999px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.ag-filter-chip:hover {
    background: #e5e7eb;
}

.ag-filter-chip.is-active {
    background: #050C26;
    color: #ffffff;
}

.ag-notification-list {
    max-height: 420px;
    overflow-y: auto;
}

.ag-notification-list-inner {
    position: relative;
}

.ag-list-fade-move {
    transition: transform 0.25s ease;
}

.ag-list-fade-enter-active,
.ag-list-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.ag-list-fade-enter,
.ag-list-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.ag-list-fade-leave-active {
    position: absolute;
    left: 0;
    right: 0;
}

.ag-notification-card {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    text-decoration: none !important;
    border-bottom: 1px solid #f0f2f5;
    transition: background-color 0.2s ease;
}

.ag-notification-card:last-child {
    border-bottom: none;
}

.ag-notification-card:hover {
    background-color: #f8fafc;
}

.ag-notification-card__icon {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ag-notification-card__icon.is-yellow {
    background: #FEF3C7;
    color: #B45309;
}

.ag-notification-card__icon.is-blue {
    background: #DBEAFE;
    color: #2563EB;
}

.ag-notification-card__icon.is-red {
    background: #FEE2E2;
    color: #DC2626;
}

.ag-notification-card__icon.is-green {
    background: #D1FAE5;
    color: #059669;
}

.ag-notification-card__body {
    flex: 1;
    min-width: 0;
}

.ag-notification-card__title-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 4px;
}

.ag-unread-dot {
    position: absolute;
    top: 14px;
    right: 16px;
    flex-shrink: 0;
    pointer-events: none;
}

.ag-mark-read-btn {
    position: absolute;
    top: 10px;
    right: 30px;
    border: none;
    background: transparent;
    padding: 2px 0;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.2s ease, color 0.2s ease;
    z-index: 1;
}

.ag-notification-card:hover .ag-mark-read-btn {
    opacity: 1;
}

.ag-dot-fade-enter-active,
.ag-dot-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.ag-dot-fade-enter,
.ag-dot-fade-leave-to {
    opacity: 0;
    transform: scale(0.5);
}

.ag-notification-card__title {
    font-size: 14px;
    font-weight: 700;
    color: #050C26;
    line-height: 1.3;
}

.ag-notification-tag {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    background: #FEF3C7;
    color: #92400E;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.ag-notification-card__description {
    margin: 0 0 6px;
    font-size: 13px;
    line-height: 1.45;
    color: #6B7280;
}

.ag-notification-card__description strong {
    color: #050C26;
    font-weight: 700;
}

.ag-notification-card__time {
    font-size: 12px;
    color: #9CA3AF;
}

.ag-notification-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 40px 24px;
    color: #9CA3AF;
    text-align: center;
}

.ag-notification-empty p {
    margin: 0;
    font-size: 14px;
    color: #6B7280;
}
</style>
