<template>
    <div class="ag-notification-wrapper">
        <el-dropdown trigger="click" @visible-change="onDropdownVisible">
            <span class="el-dropdown-link notification-icon text-secondary">
                <el-badge :value="badgeCount" :hidden="badgeCount === 0" class="ag-bell-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-bell"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>
                </el-badge>
            </span>
            <el-dropdown-menu slot="dropdown" class="ag-notification-menu">
                <li class="ag-notification-panel" @click.stop>
                    <div class="ag-notification-header">
                        <h4 class="ag-notification-title">Notificaciones</h4>
                    </div>

                    <div class="ag-notification-filters">
                        <button
                            v-for="filter in filters"
                            :key="filter.id"
                            type="button"
                            class="ag-filter-chip"
                            :class="{ 'is-active': activeFilter === filter.id }"
                            @click.stop="activeFilter = filter.id"
                        >
                            {{ filter.label }}
                        </button>
                    </div>

                    <div class="ag-notification-list">
                        <template v-if="filteredNotifications.length">
                            <a
                                v-for="notification in filteredNotifications"
                                :key="notification.id"
                                href="#"
                                class="ag-notification-card"
                                :class="{ 'is-unread': notification.unread }"
                                @click.prevent="openNotification(notification)"
                            >
                                <div class="ag-notification-card__icon" :class="`is-${notification.icon_bg}`">
                                    <component :is="iconComponents[notification.icon]" />
                                </div>
                                <div class="ag-notification-card__body">
                                    <div class="ag-notification-card__title-row">
                                        <span v-if="notification.unread" class="ag-unread-dot"></span>
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
                        </template>
                        <div v-else class="ag-notification-empty">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
                                <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
                            </svg>
                            <p>¡Todo al día! No hay pendientes</p>
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
            hasLoaded: false,
            activeFilter: 'todas',
            polling: null,
            loading: false,
            filters: [
                { id: 'todas', label: 'Todas' },
                { id: 'comprobantes', label: 'Comprobantes' },
                { id: 'pagos', label: 'Pagos' },
                { id: 'inventario', label: 'Inventario' },
                { id: 'sistema', label: 'Sistema' }
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
            if (this.hasLoaded) {
                return this.notifications.length;
            }

            return this.initialCount;
        },
        filteredNotifications() {
            if (this.activeFilter === 'todas') {
                return this.notifications;
            }

            if (this.activeFilter === 'pedidos') {
                return this.notifications.filter((notification) => notification.type === 'pedidos');
            }

            return this.notifications.filter((notification) => notification.type === this.activeFilter);
        }
    },
    mounted() {
        this.fetchNotifications();
        this.startPolling();
    },
    beforeDestroy() {
        this.stopPolling();
    },
    methods: {
        onDropdownVisible(visible) {
            if (visible) {
                this.fetchNotifications();
            }
        },
        openNotification(notification) {
            const url = notification && notification.url;

            if (!url || url === '#') {
                return;
            }

            window.location.assign(url);
        },
        async fetchNotifications() {
            if (this.loading) {
                return;
            }

            this.loading = true;

            try {
                const response = await this.$http.get('/notifications/header');
                const data = response.data || {};

                this.notifications = Array.isArray(data.notifications) ? data.notifications : [];
            } catch (error) {
                console.log('No se pudieron actualizar las notificaciones.', error);
                this.notifications = [];
            } finally {
                this.hasLoaded = true;
                this.loading = false;
            }
        },
        startPolling() {
            this.polling = setInterval(() => {
                this.fetchNotifications();
            }, 60000);
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
    padding: 18px 20px 12px;
    border-bottom: 1px solid #f0f2f5;
}

.ag-notification-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #050C26;
    line-height: 1.2;
}

.ag-notification-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 12px 16px;
    border-bottom: 1px solid #f0f2f5;
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

.ag-notification-card {
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
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #2563EB;
    flex-shrink: 0;
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
