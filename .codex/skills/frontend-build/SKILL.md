---
name: frontend-build
description: Controlar la compilación de assets de Pro9. Usar antes de ejecutar `npm run build`, `npm run dev`, `npm run watch`, Vite, Yarn, pnpm u otro comando de compilación, o antes de modificar `public/build/`. También usar cuando cambios en `resources/js`, `resources/sass` o archivos Vue requieran compilación para verse en el navegador.
---

# Controlar la compilación de assets

No ejecutar comandos de build por iniciativa propia: la compilación la ejecuta el usuario.

## Restricciones

- No ejecutar `npm run build`, `npm run dev`, `npm run watch`, `npm run dev-simple`, `vite build`, `npx vite`, `yarn build` ni `pnpm build`.
- No editar archivos generados en `public/build/`, incluidos `assets/*.js`, `assets/*.css` y `manifest.json`.
- No modificar bundles para reflejar cambios de fuentes.

El build tarda aproximadamente dos minutos, cambia hashes en el manifiesto y puede ensuciar el estado de Git con muchos assets. El usuario decide cuándo asumir ese costo, normalmente porque ya tiene un watcher en su terminal.

## Flujo

1. Editar únicamente fuentes en `resources/js/`, `resources/sass/` y los archivos Vue relacionados.
2. Completar y verificar el cambio sin compilar.
3. Informar los archivos modificados e indicar que falta compilar para verlo en el navegador.

Si el usuario solicita explícitamente compilar —por ejemplo, “compila”, “haz el build” o “corre Vite”— ejecutar sólo el comando solicitado.

## Lectura de bundles

Permitir lectura de `public/build/` para diagnóstico, por ejemplo buscar texto en assets o revisar `manifest.json`. La restricción aplica a compilar o editar, no a inspeccionar.
