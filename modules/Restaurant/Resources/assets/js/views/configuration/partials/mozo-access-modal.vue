<template>
    <el-dialog
        title="Acceso a Mozo"
        :visible.sync="dialogVisible"
        width="520px"
        @open="onOpen"
        custom-class="mozo-access-dialog"
    >
        <p class="mozo-access-subtitle">
            Genera un enlace por usuario y envíaselo. Al abrirlo, entra directo sin iniciar sesión.
        </p>

        <div v-loading="loading" class="mozo-access-list">
            <div
                v-for="user in restaurantUsers"
                :key="user.id"
                class="mozo-access-item"
            >
                <div class="mozo-access-user">
                    <div class="mozo-access-avatar">{{ userInitials(user.name) }}</div>
                    <div>
                        <div class="mozo-access-name">{{ user.name }}</div>
                        <div class="mozo-access-role">{{ user.restaurant_role_name || 'Sin rol' }}</div>
                    </div>
                </div>

                <div class="mozo-access-action">
                    <template v-if="generatedLinks[user.id]">
                        <div class="mozo-access-link-box" @click="copyLink(user.id)">
                            <span class="mozo-access-link-text">{{ truncateLink(generatedLinks[user.id]) }}</span>
                            <i class="far fa-copy mozo-access-copy-icon"></i>
                        </div>
                    </template>
                    <el-button
                        v-else
                        size="small"
                        :loading="generatingUserId === user.id"
                        @click="generateLink(user.id)"
                    >
                        <i class="fas fa-link me-1"></i> Generar enlace
                    </el-button>
                </div>
            </div>

            <div v-if="!loading && restaurantUsers.length === 0" class="text-muted text-center py-4">
                No hay usuarios de restaurante registrados.
            </div>
        </div>
    </el-dialog>
</template>

<style>
.mozo-access-dialog .el-dialog__body {
    padding-top: 0;
}

.mozo-access-subtitle {
    color: #6c757d;
    margin: 0 0 1.25rem;
    line-height: 1.5;
}

.mozo-access-list {
    min-height: 120px;
}

.mozo-access-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 0;
    border-bottom: 1px solid #eef0f2;
}

.mozo-access-item:last-child {
    border-bottom: none;
}

.mozo-access-user {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.mozo-access-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #eef2ff;
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.mozo-access-name {
    font-weight: 600;
    color: #1f2937;
}

.mozo-access-role {
    font-size: 0.85rem;
    color: #9ca3af;
}

.mozo-access-action {
    flex-shrink: 0;
    min-width: 180px;
    text-align: right;
}

.mozo-access-link-box {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #f3f4f6;
    border-radius: 8px;
    padding: 0.45rem 0.65rem;
    cursor: pointer;
    max-width: 220px;
}

.mozo-access-link-text {
    font-size: 0.78rem;
    color: #6b7280;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mozo-access-copy-icon {
    color: #6b7280;
}
</style>

<script>
export default {
    props: {
        showDialog: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            resource: 'restaurant',
            loading: false,
            generatingUserId: null,
            users: [],
            generatedLinks: {},
        };
    },
    computed: {
        dialogVisible: {
            get() {
                return this.showDialog;
            },
            set(value) {
                this.$emit('update:showDialog', value);
                if (!value && typeof window.setMozoAccessNavActive === 'function') {
                    window.setMozoAccessNavActive(false);
                }
            },
        },
        restaurantUsers() {
            return this.users.filter(user => user.restaurant_role_id);
        },
    },
    methods: {
        onOpen() {
            this.generatedLinks = {};
            this.loadUsers();
        },
        loadUsers() {
            this.loading = true;
            this.$http.get(`/${this.resource}/get-users`)
                .then(response => {
                    this.users = response.data.data || [];
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        userInitials(name) {
            if (!name) {
                return '?';
            }

            return name
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map(part => part.charAt(0).toUpperCase())
                .join('');
        },
        truncateLink(url) {
            if (!url) {
                return '';
            }

            return url.length > 34 ? `${url.slice(0, 34)}...` : url;
        },
        generateLink(userId) {
            this.generatingUserId = userId;

            this.$http.post(`/${this.resource}/mozo-access/generate-link`, { user_id: userId })
                .then(response => {
                    if (response.data.success) {
                        this.$set(this.generatedLinks, userId, response.data.url);
                        this.copyLink(userId);
                    }
                })
                .catch(error => {
                    const message = error.response?.data?.message || 'No se pudo generar el enlace.';
                    this.$message.error(message);
                })
                .finally(() => {
                    this.generatingUserId = null;
                });
        },
        copyLink(userId) {
            const url = this.generatedLinks[userId];

            if (!url) {
                return;
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url)
                    .then(() => this.$message.success('Enlace copiado al portapapeles'))
                    .catch(() => this.fallbackCopy(url));
                return;
            }

            this.fallbackCopy(url);
        },
        fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                this.$message.success('Enlace copiado al portapapeles');
            } catch (e) {
                this.$message.error('No se pudo copiar el enlace');
            }

            document.body.removeChild(textarea);
        },
    },
};
</script>
