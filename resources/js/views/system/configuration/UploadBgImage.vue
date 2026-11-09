<template>
  <div>
    <div class="image-container" style="max-height: 150px;">
      <img
        :src="imageUrl"
        alt="Vista previa"
        class="img-fluid img-thumbnail img-thumbnail-system w-100"
      />
      <div class="overlay">
        <el-button
          type="primary"
          class="change-btn"
          @click="onShowFilePicker"
          :loading="loading"
          :disabled="loading"
          >{{ btnText }}</el-button
        >
      </div>
    </div>
    <input
      type="file"
      accept=".jpg,.jpeg,.png,.gif,.svg,.webp,.avif"
      @change="onGeneratePreview"
      ref="inputFile"
      class="hidden"
    />
    <small class="form-control-feedback mt-2 d-block"
      >{{ helpText }}</small
    >
  </div>
</template>

<script>
export default {
  props: {
    type: {
      type: String,
      required: true,
    },
    config: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      imageUrl: "",
      loading: false,
      btnText: '',
      helpText: ''
    };
  },
  mounted() {
      if (this.type === 'bg') {
        this.imageUrl = this.config.image;
        this.helpText = 'Se recomienda una imagen de 1000x1000px. Formatos: JPG, PNG, GIF, SVG, WebP y AVIF. Máximo 2 MB.';
      } else {
          this.imageUrl = this.config.logo || '/logo/tulogo.png';
          this.helpText = 'Se recomienda un logo de 600x300px. Formatos: JPG, PNG, GIF, SVG, WebP y AVIF. Máximo 2 MB.';
      }
    if (this.type === 'bg') {
        this.btnText = 'Cambiar imagen de fondo';
    } else {
        this.btnText = 'Cambiar logo';
    }
  },
  methods: {
    onShowFilePicker() {
      this.$refs.inputFile.click();
    },
    onGeneratePreview(event) {
      const files = event.target.files;
      if (files.length > 0) {
        const image = files[0];
        if (!/\.(jpe?g|png|gif|svg|webp|avif)$/i.test(image.name)) {
          this.$message.error('Seleccione una imagen JPG, PNG, GIF, SVG, WebP o AVIF.');
          event.target.value = '';
          return;
        }
        if (image.size > 2 * 1024 * 1024) {
          this.$message.error('La imagen no puede superar 2 MB.');
          event.target.value = '';
          return;
        }
        const payload = new FormData();
        payload.append("image", image);
        payload.append("type", this.type);
        this.loading = true;
        return this.$http
          .post("/configurations/bg", payload)
          .then((response) => {
            this.imageUrl = response.data.url;
            this.$message({
              message: response.data.message,
              type: "success",
            });
          })
          .catch(error => {
            const data = error.response && error.response.data;
            const errors = data && (data.errors || (typeof data.message === 'object' ? data.message : data));
            const message = errors && errors.image
              ? errors.image[0]
              : data && typeof data.message === 'string' && data.message;
            this.$message.error(message || 'No se pudo subir la imagen. Intente nuevamente.');
          })
          .finally(() => {
            this.loading = false;
            event.target.value = '';
          });
      }
    },
  },
};
</script>

<style scoped>
.image-container {
  position: relative;
  display: inline-block;
  width: 100%;
}

.overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color:rgb(255 255 255 / 70%);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s ease;
  border-radius: 0.375rem; /* Para coincidir con img-thumbnail */
}

.image-container:hover .overlay {
  opacity: 1;
}

.change-btn:active {
  transform: translateY(0);
}
.img-thumbnail-system {
  height: 160px;
  object-fit: contain;
}
</style>
