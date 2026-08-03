<template>
    <div class="mkt-item" :class="{ 'is-blocked': item.status === 'blocked' }">
        <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="mkt-item__img" loading="lazy">
        <span v-else class="mkt-item__img mkt-item__img--placeholder">{{ item.name.charAt(0) }}</span>

        <div class="mkt-item__body">
            <span class="mkt-item__name" :title="item.name">{{ item.name }}</span>
            <span class="mkt-item__code">{{ item.internal_code || 'sin código' }}</span>

            <div class="mkt-item__tags">
                <span v-if="item.reports_count" class="badge badge-pill badge-danger"
                      :title="item.reports_count + ' denuncia(s)'">
                    <i class="fas fa-flag"></i> {{ item.reports_count }}
                </span>
                <span v-if="item.recommendations_count" class="badge badge-pill badge-success"
                      :title="item.recommendations_count + ' recomendación(es)'">
                    <i class="fas fa-heart"></i> {{ item.recommendations_count }}
                </span>
                <span v-if="item.status !== 'active'" class="badge badge-pill"
                      :class="item.status === 'blocked' ? 'badge-danger' : 'badge-secondary'"
                      :title="item.blocked_reason || ''">
                    {{ item.status === 'blocked' ? 'Bloqueado' : 'Inactivo' }}
                </span>
            </div>
        </div>

        <div class="mkt-item__action">
            <el-button v-if="item.status === 'blocked'" size="mini" type="primary" plain
                       @click="$emit('unblock', item)">
                Desbloquear
            </el-button>
            <el-button v-else size="mini" type="danger" plain @click="$emit('block', item)">
                Bloquear
            </el-button>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        item: { type: Object, required: true },
    },
}
</script>

<style scoped>
.mkt-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: var(--light-color);
    border: 1px solid var(--accent-color);
    border-radius: 8px;
    transition: background-color .15s ease, border-color .15s ease;
}
.mkt-item.is-blocked {
    border-color: var(--danger);
}

.mkt-item__img {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    object-fit: cover;
    flex: none;
}
.mkt-item__img--placeholder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--accent-color);
    color: var(--dark-color);
    font-weight: 700;
    font-size: 16px;
    text-transform: uppercase;
}

.mkt-item__body {
    flex: 1;
    min-width: 0;
}
.mkt-item__name {
    display: block;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.3;
    color: var(--dark-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mkt-item__code {
    display: block;
    margin-top: 1px;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 11px;
    color: var(--muted);
}
.mkt-item__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 5px;
}
.mkt-item__tags:empty {
    margin-top: 0;
}
.mkt-item__tags .badge {
    padding: 0.35em 0.6em;
    font-size: 10px;
}

.mkt-item__action {
    flex: none;
}
.mkt-item__action >>> .el-button {
    opacity: .55;
    transition: opacity .15s ease;
}
.mkt-item:hover .mkt-item__action >>> .el-button,
.mkt-item.is-blocked .mkt-item__action >>> .el-button {
    opacity: 1;
}
</style>
