<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
    <div class="nd-chart">
        <button
            v-if="canScroll"
            type="button"
            class="nd-chart-arrow nd-chart-arrow--left"
            :disabled="!canPrev"
            @click="prev"
            aria-label="Anterior"
        >
            <i class="el-icon-arrow-left"></i>
        </button>

        <div class="nd-chart-body" ref="body">
            <apexchart
                type="bar"
                height="260"
                :options="chartOptions"
                :series="series"
            ></apexchart>
        </div>

        <button
            v-if="canScroll"
            type="button"
            class="nd-chart-arrow nd-chart-arrow--right"
            :disabled="!canNext"
            @click="next"
            aria-label="Siguiente"
        >
            <i class="el-icon-arrow-right"></i>
        </button>
    </div>
</template>

<script>
import { readVector, chartCoordinate, compareOriginal, formatUnitOriginal, escapeDashboardText } from "../../../../../../../../resources/js/helpers/dashboard-originals";
// ######## INICIO CALCULOS EXACTOS PRO9 ########
export default {
    name: "MonthlyComparisonChart",
    props: {
        categories: {
            type: Array,
            default: () => [],
        },
        sales: {
            type: Array,
            default: () => [],
        },
        purchases: {
            type: Array,
            default: () => [],
        },
        salesFractions: {type: Array, default: () => []},
        purchasesFractions: {type: Array, default: () => []},
        salesExact: { type: Array, default: () => [] },
        purchasesExact: { type: Array, default: () => [] },
        emptyBarRatio: {
            type: Number,
            default: 0.04,
        },
        maxVisible: {
            type: Number,
            default: 12,
        },
    },
    data() {
        return {
            offset: 0,
            bodyWidth: 0,
        };
    },
    watch: {
        categories() {
            this.offset = 0;
        },
    },
    mounted() {
        this.measure();
        window.addEventListener("resize", this.measure);
    },
    beforeDestroy() {
        window.removeEventListener("resize", this.measure);
    },
    computed: {
        canScroll() {
            return this.categories.length > this.maxVisible;
        },
        canPrev() {
            return this.offset > 0;
        },
        canNext() {
            return this.offset + this.maxVisible < this.categories.length;
        },
        visibleCategories() {
            return this.categories.slice(this.offset, this.offset + this.maxVisible);
        },
        visibleSales() {
            return readVector(this, 'salesFractions').slice(this.offset, this.offset + this.maxVisible);
        },
        visiblePurchases() {
            return readVector(this, 'purchasesFractions').slice(this.offset, this.offset + this.maxVisible);
        },
        coordinates() {
            return [...this.visibleSales, ...this.visiblePurchases].map(chartCoordinate).filter(value => value !== null);
        },
        maxValue() { return Math.max(0, ...this.coordinates.map(value => Math.abs(value))); },
        minValue() { return Math.min(0, ...this.coordinates); },
        maxPositive() { return Math.max(0, ...this.coordinates); },
        baseScale() { return this.maxValue > 0 ? this.maxValue : 1; },
        stubValue() { return this.baseScale * this.emptyBarRatio; },
        yAxisMax() { return this.maxPositive > 0 ? this.maxPositive * 1.08 : this.minValue < 0 ? 0 : this.baseScale * 1.08; },
        yAxisMin() { return this.minValue * 1.08; },
        barRadius() {
            const count = this.visibleCategories.length || 1;
            if (!this.bodyWidth) return 2;
            const band = this.bodyWidth / count;
            const barWidth = (band * 0.55) / 2;
            return Math.max(1, Math.min(8, Math.floor(barWidth / 2)));
        },
        displaySales() {
            return this.buildSeriesData(this.visibleSales, "#2DD4BF", "rgba(45, 212, 191, 0.22)");
        },
        displayPurchases() {
            return this.buildSeriesData(this.visiblePurchases, "#4F46E5", "rgba(79, 70, 229, 0.22)");
        },
        series() {
            return [
                { name: "Ventas", data: this.displaySales },
                { name: "Compras", data: this.displayPurchases },
            ];
        },
        chartOptions() {
            const realSales = this.visibleSales, realPurchases = this.visiblePurchases;
            return {
                chart: {
                    toolbar: { show: false },
                    fontFamily: "inherit",
                    animations: { enabled: true },
                },
                colors: ["#2DD4BF", "#4F46E5"],
                plotOptions: {
                    bar: {
                        columnWidth: "55%",
                        borderRadius: this.barRadius,
                        borderRadiusApplication: "end",
                    },
                },
                dataLabels: { enabled: false },
                legend: { show: false },
                grid: { show: false, padding: { left: 0, right: 0 } },
                xaxis: {
                    type: "category",
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: {
                        style: { colors: "#9ca3af", fontSize: "12px" },
                    },
                },
                yaxis: { show: false, min: this.yAxisMin, max: this.yAxisMax },
                tooltip: {
                    custom: function ({ seriesIndex, dataPointIndex, w }) {
                        const realValue = seriesIndex === 0
                            ? realSales[dataPointIndex]
                            : realPurchases[dataPointIndex];
                        const name = escapeDashboardText(w.config.series[seriesIndex].name);
                        const label = escapeDashboardText(w.globals.labels[dataPointIndex]);
                        const value = formatUnitOriginal(realValue, 'money', 'en-US');
                        return (
                            '<div style="padding:6px 10px;font-size:12px;">' +
                            '<strong>' + label + '</strong><br/>' +
                            name + ': ' + value +
                            '</div>'
                        );
                    },
                },
            };
        },
    },
    methods: {
        measure() {
            this.bodyWidth = this.$refs.body ? this.$refs.body.clientWidth : 0;
        },
        prev() {
            this.offset = Math.max(0, this.offset - this.maxVisible);
        },
        next() {
            const maxOffset = Math.max(0, this.categories.length - this.maxVisible);
            this.offset = Math.min(maxOffset, this.offset + this.maxVisible);
        },
        buildSeriesData(values, solidColor, fadedColor) {
            return values.map((v, i) => {
                const value = chartCoordinate(v);
                const isStub = v !== null && compareOriginal(v, '0') === 0;
                return {
                    x: this.visibleCategories[i],
                    y: isStub ? this.stubValue : value,
                    fillColor: isStub ? fadedColor : solidColor,
                };
            });
        },
    },
};
// ######## FIN CALCULOS EXACTOS PRO9 ########
</script>

<style scoped>
.nd-chart {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.nd-chart-body {
    flex: 1;
    min-width: 0;
}
.nd-chart-arrow {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #6b7280;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease, color 0.15s ease;
}
.nd-chart-arrow:hover:not(:disabled) {
    background: #f3f4f6;
    color: #374151;
}
.nd-chart-arrow:disabled {
    opacity: 0.35;
    cursor: default;
}
</style>

<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
