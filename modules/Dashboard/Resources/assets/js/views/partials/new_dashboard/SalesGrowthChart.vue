<!-- ######## INICIO MIGRACIÓN MONEDA VENEZUELA ######## -->
<template>
    <apexchart
        type="line"
        height="260"
        :options="chartOptions"
        :series="series"
    ></apexchart>
</template>

<script>
import { readVector, chartCoordinate, formatUnitOriginal } from "../../../../../../../../resources/js/helpers/dashboard-originals";
// ######## INICIO CALCULOS EXACTOS SAB ########
export default {
    name: "SalesGrowthChart",
    props: {
        categories: {
            type: Array,
            default: () => [],
        },
        current: {
            type: Array,
            default: () => [],
        },
        previous: {
            type: Array,
            default: () => [],
        },
        currentFractions: {type: Array, default: () => []},
        previousFractions: {type: Array, default: () => []},
        currentExact: { type: Array, default: () => [] },
        previousExact: { type: Array, default: () => [] },
        currentLabel: { type: String, default: "" },
        previousLabel: { type: String, default: "" },
    },
    computed: {
        series() {
            return [
                { name: this.currentLabel, data: readVector(this, 'currentFractions').map(chartCoordinate) },
                { name: this.previousLabel, data: readVector(this, 'previousFractions').map(chartCoordinate) },
            ];
        },
        chartOptions() {
            const exact = [readVector(this, 'currentFractions'), readVector(this, 'previousFractions')];
            return {
                chart: {
                    toolbar: { show: false },
                    fontFamily: "inherit",
                    zoom: { enabled: false },
                },
                colors: ["#2DD4BF", "#cbd5e1"],
                stroke: {
                    curve: "smooth",
                    width: [4, 3],
                    dashArray: [0, 8],
                },
                dataLabels: { enabled: false },
                legend: { show: false },
                grid: { show: false, padding: { left: 8, right: 8 } },
                markers: { size: 0, hover: { size: 5 } },
                xaxis: {
                    categories: this.categories,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: {
                        style: { colors: "#9ca3af", fontSize: "12px" },
                    },
                },
                yaxis: { show: false },
                tooltip: {
                    y: {
                        formatter: (_coordinate, context) => formatUnitOriginal(exact[context?.seriesIndex]?.[context?.dataPointIndex], 'money', 'en-US'),
                    },
                },
            };
        },
    },
};
// ######## FIN CALCULOS EXACTOS SAB ########
</script>

<!-- ######## FIN MIGRACIÓN MONEDA VENEZUELA ######## -->
