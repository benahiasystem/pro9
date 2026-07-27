<template>
    <div>
        <header class="page-header">
            <h2>
                <a href="/dashboard" aria-label="Volver al dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-building-store" style="margin-top: -2px;"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M3 21l18 0" /><path d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4" /><path d="M5 21l0 -10.15" /><path d="M19 21l0 -10.15" /><path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4" /></svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Mozo y Vendeya</span></li>
            </ol>
        </header>

        <div class="row mt-3 branding-grid">
            <div class="col-xl-6 col-12 mb-3">
                <branding-panel
                    app-name="Mozo"
                    endpoint="/configurations/mozo"
                    :initial-colors="mozoColors"
                    :palettes="mozoPalettes"
                />
            </div>
            <div class="col-xl-6 col-12 mb-3">
                <branding-panel
                    app-name="Vendeya"
                    endpoint="/configurations/vendeya"
                    :initial-colors="vendeyaColors"
                    :palettes="vendeyaPalettes"
                />
            </div>
        </div>
    </div>
</template>

<script>
import BrandingPanel from './brandingPanel.vue'

export default {
    name: 'SystemMozoIndex',

    components: { BrandingPanel },

    data() {
        return {
            mozoColors: {
                Primary: { label: 'Color Primario', hex: '#32a56a' },
                Secondary: { label: 'Color Secundario', hex: '#f58f00' },
                Background: { label: 'Background', hex: '#f4f5f6' },
                Text: { label: 'Texto', hex: '#1d3a3a' },
                lightText: { label: 'Texto Claro', hex: '#a2a5b9' },
                darkPrimary: { label: 'Dark Primary', hex: '#222225' },
                darkLightText: { label: 'Dark Light Text', hex: '#d0d2dc' }
            },
            vendeyaColors: {
                Primary: { label: 'Color Primario', hex: '#ff7d00' },
                Secondary: { label: 'Color Secundario', hex: '#d5e8e8' },
                Background: { label: 'Background', hex: '#eef5f5' },
                Text: { label: 'Texto', hex: '#004850' },
                lightText: { label: 'Texto Claro', hex: '#a2a5b9' },
                darkPrimary: { label: 'Dark Primary', hex: '#121c22' },
                darkLightText: { label: 'Dark Light Text', hex: '#a9a9b2' }
            },
            mozoPalettes: [
                {
                    name: 'Océano',
                    colors: {
                        Primary: '#2563eb',
                        Secondary: '#22b8cf',
                        Background: '#f3f6fb',
                        Text: '#16324c',
                        lightText: '#9db1c7',
                        darkPrimary: '#1c2534',
                        darkLightText: '#c9d6e6'
                    }
                },
                {
                    name: 'Terracota',
                    colors: {
                        Primary: '#d96846',
                        Secondary: '#f0a868',
                        Background: '#faf5f0',
                        Text: '#43302a',
                        lightText: '#bba79d',
                        darkPrimary: '#2b211d',
                        darkLightText: '#ddcec4'
                    }
                },
                {
                    name: 'Uva',
                    colors: {
                        Primary: '#7a5af8',
                        Secondary: '#b197fc',
                        Background: '#f6f5fb',
                        Text: '#322c4a',
                        lightText: '#a8a4c0',
                        darkPrimary: '#24213a',
                        darkLightText: '#cfccdf'
                    }
                }
            ],
            vendeyaPalettes: [
                {
                    name: 'Coral',
                    colors: {
                        Primary: '#ef5466',
                        Secondary: '#fde3e7',
                        Background: '#fdf3f4',
                        Text: '#55232c',
                        lightText: '#c0a3a9',
                        darkPrimary: '#251a1e',
                        darkLightText: '#d9c6cb'
                    }
                },
                {
                    name: 'Esmeralda',
                    colors: {
                        Primary: '#10a37f',
                        Secondary: '#d3f2e7',
                        Background: '#eef8f4',
                        Text: '#0d4a3d',
                        lightText: '#9fb8b0',
                        darkPrimary: '#14231f',
                        darkLightText: '#b9cdc6'
                    }
                },
                {
                    name: 'Índigo',
                    colors: {
                        Primary: '#4f5be7',
                        Secondary: '#dde1fb',
                        Background: '#f2f3fc',
                        Text: '#272c55',
                        lightText: '#a6aac6',
                        darkPrimary: '#1b1e33',
                        darkLightText: '#c3c6dd'
                    }
                }
            ]
        }
    }
}
</script>

<style scoped>
.branding-grid > div { display: flex; }
.branding-grid .branding-panel { width: 100%; }
</style>
