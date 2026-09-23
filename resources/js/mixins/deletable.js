export const deletable = {
    methods: {
        destroy(url) {
            return new Promise((resolve) => {
                this.$confirm('¿Desea eliminar el registro?', 'Eliminar', {
                    confirmButtonText: 'Eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.$http.delete(url)
                        .then(res => {
                            if(res.data.success) {
                                this.$message.success(res.data.message)
                                resolve()
                            }else{
                                this.$message.error(res.data.message)
                                resolve()
                            }
                        })
                        .catch(error => {
                            if (error.response.status === 500) {
                                this.$message.error('Error al intentar eliminar');
                            } else {
                                console.log(error.response.data.message)
                            }
                        })
                }).catch(error => {
                    console.log(error)
                });
            })
        },
        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        async anular(url, method = 'get') {
            try {
                await this.$confirm('¿Desea anular el registro?', 'Anular', {
                    confirmButtonText: 'Anular', cancelButtonText: 'Cancelar', type: 'warning'
                });
            } catch (_) { return false; }
            try {
                const response = await this.$http[method](url);
                if (response.data.success) {
                    this.$message.success('Se anuló correctamente el registro');
                    return true;
                }
                this.$message.error(response.data.message || 'Error al intentar anular');
            } catch (error) {
                const data = (error.response && error.response.data) || {};
                const first = data.errors && Object.values(data.errors)[0];
                this.$message.error((Array.isArray(first) ? first[0] : first) || data.message || 'Error al intentar anular');
            }
            return false;
        },
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
        delete(url) {
            return new Promise((resolve) => {
                this.$confirm('¿Desea eliminar permanentemente el registro?', 'Anular', {
                    confirmButtonText: 'Eliminar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.$http.get(url)
                        .then(res => {
                            if (res.data.success) {
                                this.$message.success('Se anuló correctamente el registro')
                                resolve()
                            }
                        })
                        .catch(error => {
                            if (error.response.status === 500) {
                                this.$message.error('Error al intentar anular');
                            } else {
                                console.log(error.response.data.message)
                            }
                        })
                }).catch(error => {
                    console.log(error)
                });
            })
        },
        disable(url) {
            return new Promise((resolve) => {
                this.$confirm('¿Desea inhabilitar el registro?', 'Inhabilitar', {
                    confirmButtonText: 'Inhabilitar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.$http.get(url)
                        .then(res => {
                            if(res.data.success) {
                                this.$message.success(res.data.message)
                                resolve()
                            }else{
                                this.$message.error(res.data.message)
                                resolve()
                            }
                        })
                        .catch(error => {
                            if (error.response.status === 500) {
                                this.$message.error('Error al intentar inhabilitar');
                            } else {
                                console.log(error.response.data.message)
                            }
                        })
                }).catch(error => {
                    console.log(error)
                });
            })
        },
        enable(url) {
            return new Promise((resolve) => {
                this.$confirm('¿Desea habilitar el registro?', 'Habilitar', {
                    confirmButtonText: 'Habilitar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.$http.get(url)
                        .then(res => {
                            if(res.data.success) {
                                this.$message.success(res.data.message)
                                resolve()
                            }else{
                                this.$message.error(res.data.message)
                                resolve()
                            }
                        })
                        .catch(error => {
                            if (error.response.status === 500) {
                                this.$message.error('Error al intentar habilitar');
                            } else {
                                console.log(error.response.data.message)
                            }
                        })
                }).catch(error => {
                    console.log(error)
                });
            })
        },
        voided(url) {
            return new Promise((resolve) => {
                this.$confirm('¿Desea anular el registro?', 'Anular', {
                    confirmButtonText: 'Anular',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.$http.get(url)
                        .then(res => {
                            if (res.data.success) {
                                this.$message.success(res.data.message)
                                resolve()
                            }
                        })
                        .catch(error => {
                            if (error.response.status === 500) {
                                this.$message.error('Error al intentar anular');
                            } else {
                                console.log(error.response.data.message)
                            }
                        })
                }).catch(error => {
                    console.log(error)
                });
            })
        },
        updateStateType(url) {
            return new Promise((resolve) => {
                this.$confirm('¿Desea modificar el estado del registro?', 'Modificar', {
                    confirmButtonText: 'Modificar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.$http.get(url)
                        .then(res => {
                            if (res.data.success) {
                                this.$message.success(res.data.message)
                                resolve()
                            }
                        })
                        .catch(error => {
                            if (error.response.status === 500) {
                                this.$message.error('Error al intentar modificar');
                            } else {
                                console.log(error.response.data.message)
                            }
                        })
                }).catch(error => {
                    console.log(error)
                    this.$eventHub.$emit('reloadData')
                });
            })
        },

        changeActive(url, params)
        {
            const title = params.active ? '¿Desea inhabilitar el registro?' : '¿Desea habilitar el registro?'
            const action = params.active ? 'Inhabilitar' : 'Habilitar'

            return new Promise((resolve) => {
                this.$confirm(title, action, {
                    confirmButtonText: action,
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.$http.post(url, params)
                        .then(res => {
                            if(res.data.success) {
                                this.$message.success(res.data.message)
                                resolve()
                            }else{
                                this.$message.error(res.data.message)
                                resolve()
                            }
                        })
                        .catch(error => {
                            if (error.response.status === 500) {
                                this.$message.error('Error al intentar habilitar/inhabilitar');
                            } else {
                                console.log(error.response.data.message)
                            }
                        })
                }).catch(error => {
                    console.log(error)
                });
            })
        },

    }
}
