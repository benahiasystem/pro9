<template>
    <div class="chart-line-container">
        <canvas ref="canvas"></canvas>
    </div>
</template>

<style>
    .chart-line-container {
        position: relative;
        height: 99%;
        width: 99%;
    }
</style>

<script>
import Chart from 'chart.js';
// ######## INICIO CALCULOS EXACTOS PRO9 ########
import { graphOriginals, chartCoordinate, formatUnitOriginal } from '../../../helpers/dashboard-originals';

export default {
    props: ['allData'],
    data() {
        return {
            chart: null,
            observer: null,
            _themeChangeHandler: null,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false,
                },
                scales: {
                    xAxes: [{
                    gridLines: {
                        display: false
                    }
                    }],
                    yAxes: [{
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        count: 6,
                        autoSkip: false
                    }
                    }]
                }
            }
        }
    },
    mounted() {
        this.setupThemeObserver();
        this.createChart();
    },
    beforeDestroy() {
        this.teardownThemeObserver();

        if (this.chart) {
            this.chart.destroy();
        }
    },
    watch: {
        allData() {
            this.createChart();
        }
    },
    methods: {
        getCSSVariable(name) {
            const styles = getComputedStyle(document.documentElement);
            const value = styles.getPropertyValue(name).trim();
            if (value) return value;
            const defaults = {'--info': '#00cfe8', '--danger': '#fe006c', '--primary-color': '#2447e8'};
            return (name === '--primary-color' && styles.getPropertyValue('--primary').trim()) || defaults[name] || '';
        },
        setupThemeObserver() {
            this.teardownThemeObserver();

            const handler = () => {
                this.handleThemeChange();
            };

            window.addEventListener('theme:change', handler);

            this._themeChangeHandler = handler;
            this.observer = new MutationObserver(() => {
                this.handleThemeChange();
            });

            this.observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class', 'style'],
            });
        },
        teardownThemeObserver() {
            if (this.observer) {
                this.observer.disconnect();
                this.observer = null;
            }

            if (this._themeChangeHandler) {
                window.removeEventListener('theme:change', this._themeChangeHandler);
                this._themeChangeHandler = null;
            }
        },
        handleThemeChange() {
            if (this.allData && this.allData.datasets && this.allData.datasets.length) {
                this.createChart();
            }
        },
        createChart() {
            if (!this.allData || !this.allData.datasets) {
                return;
            }

            if (this.chart) {
                this.chart.destroy();
            }

            const ctx = this.$refs.canvas.getContext('2d');
            
            const cssColors = {
                info: this.getCSSVariable('--info'),
                danger: this.getCSSVariable('--danger'),
                primary: this.getCSSVariable('--primary-color')
            };
            
            const gradientColors = [
                { 
                    base: cssColors.info,
                    start: this.hexToRgba(cssColors.info, 0.2),
                    end: this.hexToRgba(cssColors.info, 0.05)
                },
                { 
                    base: cssColors.danger,
                    start: this.hexToRgba(cssColors.danger, 0.2),
                    end: this.hexToRgba(cssColors.danger, 0.05)
                },
                { 
                    base: cssColors.primary,
                    start: this.hexToRgba(cssColors.primary, 0.2),
                    end: this.hexToRgba(cssColors.primary, 0.05)
                }
            ];
        
            const coloredDatasets = this.allData.datasets.map((dataset, index) => {
                const colorIndex = index % gradientColors.length;
                const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);

                gradient.addColorStop(0, gradientColors[colorIndex].start);
                gradient.addColorStop(1, gradientColors[colorIndex].end);

                return {
                    ...dataset,
                    data: dataset.data_fractions || dataset.calculation_snapshot ? graphOriginals(dataset).map(chartCoordinate) : dataset.data,
                    borderColor: gradientColors[colorIndex].base,
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: gradientColors[colorIndex].base,
                    pointRadius: 0,
                    pointHoverRadius: 5
                };
            });
        
            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.allData.labels,
                    datasets: coloredDatasets,
                },
                options: {
                    ...this.options,
                    elements: {
                        line: {
                            borderJoinStyle: 'round',
                            borderWidth: 3
                        }
                    },
                    // Chart.js v2: tooltips legibles (texto blanco sobre fondo oscuro)
                    tooltips: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(33, 37, 41, 0.96)',
                        titleFontColor: '#ffffff',
                        bodyFontColor: '#ffffff',
                        footerFontColor: '#ffffff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        xPadding: 12,
                        yPadding: 10,
                        displayColors: true,
                        callbacks: {
                            label: function (tooltipItem, data) {
                                const ds = data.datasets[tooltipItem.datasetIndex]
                                const label = (ds && ds.label) || ''
                                if (ds && (ds.data_fractions || ds.calculation_snapshot)) {
                                    return ` ${label}: ${formatUnitOriginal(graphOriginals(ds)[tooltipItem.index], 'money', 'en-US')}`
                                }
                                const value = Number(tooltipItem.yLabel)
                                const amount = isNaN(value) ? tooltipItem.yLabel : value.toFixed(2)
                                return ` ${label}: Bs. ${amount}`
                            },
                        },
                    },
                },
            });
        },
        hexToRgba(hex, alpha) {
            if (hex.length === 7) {
                const r = parseInt(hex.slice(1, 3), 16);
                const g = parseInt(hex.slice(3, 5), 16);
                const b = parseInt(hex.slice(5, 7), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }
            else if (hex.length === 4) {
                const r = parseInt(hex.slice(1, 2).repeat(2), 16);
                const g = parseInt(hex.slice(2, 3).repeat(2), 16);
                const b = parseInt(hex.slice(3, 4).repeat(2), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }
            return hex;
        }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
</script>
