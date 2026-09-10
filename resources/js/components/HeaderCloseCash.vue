<template>
    <li v-if="hasOpenCash">
        <a
            v-if="variant === 'desktop'"
            href="#"
            class="dropdown-item d-flex align-items-center header-close-cash"
            :class="{ disabled: loading }"
            @click.prevent="clickCloseCash"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="icon icon-tabler icons-tabler-outline icon-tabler-cash me-2"
            >
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M3 5m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" />
                <path d="M7 15v.01" />
                <path d="M7 12v.01" />
                <path d="M7 9v.01" />
                <path d="M15 15v.01" />
                <path d="M15 12v.01" />
                <path d="M15 9v.01" />
            </svg>
            <span>Cerrar caja</span>
        </a>

        <a
            v-else
            href="#"
            class="notification-icon text-secondary navigation-options header-close-cash"
            @click.prevent="clickCloseCash"
        >
            <span>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="icon icon-tabler icons-tabler-outline icon-tabler-cash me-2"
                >
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M3 5m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z" />
                    <path d="M7 15v.01" />
                    <path d="M7 12v.01" />
                    <path d="M7 9v.01" />
                    <path d="M15 15v.01" />
                    <path d="M15 12v.01" />
                    <path d="M15 9v.01" />
                </svg>
                Cerrar caja
            </span>
        </a>
    </li>
</template>

<script>
export default {
    props: {
        variant: {
            type: String,
            default: "desktop",
            validator(value) {
                return ["desktop", "mobile"].includes(value);
            },
        },
    },
    data() {
        return {
            resource: "cash",
            cash: null,
            loading: false,
        };
    },
    computed: {
        hasOpenCash() {
            return !!this.cash;
        },
    },
    created() {
        this.fetchOpeningCash();
        this.$eventHub.$on("openCash", this.fetchOpeningCash);
        this.$eventHub.$on("closeCash", this.onCashClosed);
        this.$eventHub.$on("reloadData", this.fetchOpeningCash);
    },
    beforeDestroy() {
        this.$eventHub.$off("openCash", this.fetchOpeningCash);
        this.$eventHub.$off("closeCash", this.onCashClosed);
        this.$eventHub.$off("reloadData", this.fetchOpeningCash);
    },
    methods: {
        fetchOpeningCash() {
            return this.$http
                .get(`/${this.resource}/opening_cash`)
                .then((response) => {
                    this.cash = response.data.cash || null;
                })
                .catch(() => {
                    this.cash = null;
                });
        },
        onCashClosed() {
            this.cash = null;
        },
        clickCloseCash() {
            if (!this.cash || this.loading) {
                return;
            }

            const h = this.$createElement;
            this.$msgbox({
                title: "Cerrar caja chica POS",
                type: "warning",
                message: h("p", null, [
                    h(
                        "p",
                        { style: "text-align: justify; font-size:15px" },
                        "¿Está seguro de cerrar la caja?"
                    ),
                ]),
                showCancelButton: true,
                confirmButtonText: "Cerrar caja",
                cancelButtonText: "Cancelar",
                beforeClose: (action, instance, done) => {
                    if (action === "confirm") {
                        this.closeCash(instance, done);
                    } else {
                        done();
                    }
                },
            }).catch(() => {});
        },
        closeCash(instance, done) {
            instance.confirmButtonLoading = true;
            instance.confirmButtonText = "Cerrando caja...";
            this.loading = true;

            this.$http
                .get(`/${this.resource}/close/${this.cash.id}`)
                .then((response) => {
                    if (response.data.success) {
                        this.$message.success(response.data.message);
                        this.onCashClosed();
                        this.$eventHub.$emit("closeCash");
                        this.$eventHub.$emit("reloadData");
                    } else {
                        this.$message.warning(
                            response.data.message || "No se pudo cerrar la caja."
                        );
                    }
                })
                .catch((error) => {
                    const message =
                        error.response &&
                        error.response.data &&
                        error.response.data.message
                            ? error.response.data.message
                            : "Ocurrió un error al cerrar la caja.";
                    this.$message.error(message);
                })
                .then(() => {
                    instance.confirmButtonLoading = false;
                    instance.confirmButtonText = "Cerrar caja";
                    this.loading = false;
                    done();
                });
        },
    },
};
</script>

<style scoped>
.header-close-cash {
    color: #ea580c !important;
    font-weight: 600;
}

.header-close-cash:hover,
.header-close-cash:focus {
    background-color: #fff7ed !important;
    color: #c2410c !important;
}
</style>
