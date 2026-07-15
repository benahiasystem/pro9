<template>
  <div class="py-4">
    <p class="mb-4 text-muted">
      Puedes solicitar este servicio desde nuestra plataforma.
    </p>
    <el-button type="primary" @click="onAcquire">
      Adquirir servicio
    </el-button>
  </div>
</template>

<script>
export default {
  name: 'ApidocsVenta',
  props: {
    url: {
      type: String,
      default: '',
    },
  },
  methods: {
    onAcquire() {
      if (!this.url) {
        this.$message({
          message: 'El enlace de adquisición no está configurado',
          type: 'warning',
        });
        return;
      }
      window.open(this.url, '_blank');
    },
  },
};
</script>
