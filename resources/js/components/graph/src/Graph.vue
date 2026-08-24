<template>
    <div class="chart-container">
        <canvas ref="canvas"></canvas>
    </div>
</template>

<style>
    .chart-container {
        position: relative;
        margin: auto;
        height: 220px;
        width: 220px;
    }
    .chart-container .chartjs-render-monitor {
        height: inherit!important;
        width: inherit!important;
    }
    /* Tooltip Chart.js: forzar contraste legible */
    .chartjs-tooltip {
        background: rgba(33, 37, 41, 0.96) !important;
        color: #fff !important;
        border-radius: 6px !important;
        opacity: 1 !important;
    }
    .chartjs-tooltip *,
    .chartjs-tooltip-key {
        color: #fff !important;
    }
</style>

<script>
import Chart from 'chart.js';

export default {
  props: ['type', 'allData'],
  data() {
    // defaults seguros para evitar undefined en data()
    const defaultColors = {
      primary: '#0d6efd',
      danger: '#dc3545',
      warning: '#ffc107',
    };
    return {
      chart: null,
      // colores disponibles inmediatamente
      colors: { ...defaultColors },
      observer: null,
  _themeChangeHandler: null,
      options: {
        maintainAspectRatio: false,
        cutoutPercentage: 75,
        rotation: -0.5 * Math.PI,
        circumference: 2 * Math.PI,
        legend: { display: false },
        // Chart.js v2: texto del tooltip legible (evita negro sobre fondo oscuro/naranja).
        tooltips: {
          enabled: true,
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
              const label = (data.labels && data.labels[tooltipItem.index]) || ''
              const raw = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index]
              const value = Number(raw)
              const amount = isNaN(value) ? raw : value.toFixed(2)
              return ` ${label}: S/ ${amount}`
            },
          },
        },
        plugins: {
          doughnutlabel: {
            labels: [
              {
                text: this.getTotal(),
                font: { size: '20', family: "'Arial', sans-serif", weight: 'bold' },
                color: this.getTextColor(),
              },
              {
                text: 'Total',
                font: { size: '16', family: "'Arial', sans-serif" },
                color: '#9B9B9B',
              },
            ],
          },
        },
        elements: {
          arc: { borderWidth: 2, borderColor: '#FFFFFF', borderAlign: 'center', borderRadius: 15, spacing: 10 },
        },
      },
      colorMap: []
    }
  },
  created() {
    this.title = 'Balance';
  },
  mounted() {
    this.refreshColors();
    this.registerDoughnutLabelPlugin();
    this.setupThemeObserver();

    // crear gráfico inicial si ya hay datos
    if (this.allData && this.allData.datasets) this.createChart();
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
    registerDoughnutLabelPlugin() {
      if (Chart._doughnutLabelRegistered) {
        return;
      }

      Chart.pluginService.register({
        beforeDraw: function(chart) {
          if (chart.config.options.plugins.doughnutlabel) {
            const width = chart.width,
              height = chart.height,
              ctx = chart.ctx;

            // save/restore balanceados: el restore() previo sin save
            // corrompía el contexto y el tooltip salía negro/ilegible.
            ctx.save();

            const mainFontSize = Math.min(height / 10, 16);
            ctx.font = `bold ${mainFontSize}px Arial, sans-serif`;
            ctx.textBaseline = "middle";
            ctx.textAlign = "center";

            const text = chart.config.options.plugins.doughnutlabel.labels[0].text;
            ctx.fillStyle = chart.config.options.plugins.doughnutlabel.labels[0].color;
            ctx.fillText(text, width / 2, height / 2 - 10);

            const subFontSize = Math.min(height / 16, 12);
            ctx.font = `${subFontSize}px Arial, sans-serif`;
            ctx.fillStyle = chart.config.options.plugins.doughnutlabel.labels[1].color;
            ctx.fillText('Total', width / 2, height / 2 + 15);

            ctx.restore();
          }
        }
      });

      Chart._doughnutLabelRegistered = true;
    },
    refreshColors() {
      const style = getComputedStyle(document.documentElement);
      const fallback = {
        primary: '#0d6efd',
        danger: '#dc3545',
        warning: '#ffc107',
      };

      this.colors = {
        primary: style.getPropertyValue('--primary-color').trim() || this.colors.primary || fallback.primary,
        danger: style.getPropertyValue('--danger').trim() || this.colors.danger || fallback.danger,
        warning: style.getPropertyValue('--warning').trim() || this.colors.warning || fallback.warning,
      };
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
      this.refreshColors();

      if (this.allData && this.allData.datasets && this.allData.datasets.length) {
        this.createChart();
      }
    },
    getTotal() {
      if (!this.allData || !this.allData.datasets || !this.allData.datasets[0]) return 'S/ 0.00';
      const total = this.allData.datasets[0].data.reduce((a, b) => a + b, 0);
      return (total < 0 ? 'S/ -' : 'S/ ') + Math.abs(total).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    },
    getTextColor() {
      const isDarkMode = document.documentElement.classList.contains('dark');
      if (!this.allData || !this.allData.datasets || !this.allData.datasets[0]) {
        return isDarkMode ? '#ffffff' : '#4A4A4A';
      }
      const total = this.allData.datasets[0].data.reduce((a, b) => a + b, 0);
      if (isDarkMode) return '#ffffff';
      const colors = this.colors || { primary: '#0d6efd', danger: '#dc3545' };
      return total < 0 ? colors.danger : colors.primary;
    },
    hexToRgba(hex, alpha = 1) {
      let r = 0, g = 0, b = 0;

      if (hex.startsWith('#')) hex = hex.slice(1);

      if (hex.length === 3) {
        r = parseInt(hex[0] + hex[0], 16);
        g = parseInt(hex[1] + hex[1], 16);
        b = parseInt(hex[2] + hex[2], 16);
      } else if (hex.length === 6) {
        r = parseInt(hex.slice(0, 2), 16);
        g = parseInt(hex.slice(2, 4), 16);
        b = parseInt(hex.slice(4, 6), 16);
      }

      return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    },
    getSegmentColors(data) {
      const baseColors = [this.colors.primary, this.colors.warning, this.colors.danger];

      return data.map((value, index) => {
        const color = baseColors[index % baseColors.length];
        return {
          backgroundColor: this.hexToRgba(color, 0.6),
          borderColor: this.hexToRgba(color, 1)
        };
      });
    },
    createChart() {
      if (!this.allData || !this.allData.datasets) {
        return;
      }

      if (this.chart) {
        this.chart.destroy();
      }

      this.options.plugins.doughnutlabel.labels[0].text = this.getTotal();
      this.options.plugins.doughnutlabel.labels[0].color = this.getTextColor();

      const datasets = this.allData.datasets.map(dataset => {
        const segmentColors = this.getSegmentColors(dataset.data);

        return {
          ...dataset,
          backgroundColor: segmentColors.map(c => c.backgroundColor),
          borderColor: segmentColors.map(c => c.borderColor),
          borderWidth: 2,
        };
      });

      this.chart = new Chart(this.$refs.canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: this.allData.labels,
          datasets: datasets
        },
        options: this.options,
      });
    }
  }
}
</script>