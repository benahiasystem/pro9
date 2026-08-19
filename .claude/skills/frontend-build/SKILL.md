---
name: frontend-build
description: Regla de compilación de assets en este proyecto - NO ejecutar builds. Leer antes de correr npm run build, npm run dev, npm run watch, vite build, npm run prod, yarn build o cualquier comando que compile assets, y antes de tocar archivos en public/build/. También aplica cuando un cambio en resources/js, resources/sass o cualquier .vue "necesita compilarse" para verse en el navegador.
---

# Compilación de assets

**No ejecutes ningún comando de build.** La compilación la corre el usuario.

## Prohibido

- `npm run build` / `npm run dev` / `npm run watch` / `npm run dev-simple`
- `vite build`, `npx vite`, `yarn build`, `pnpm build`
- Editar archivos generados en [public/build/](public/build/) (`assets/*.js`, `assets/*.css`, `manifest.json`)

Por qué: el build de este proyecto tarda ~2 minutos, reescribe todos los hashes de
[public/build/manifest.json](public/build/manifest.json) y ensucia el `git status` con decenas
de assets borrados y creados. El usuario decide cuándo pagar ese costo, normalmente porque ya
tiene `npm run watch` corriendo en su propia terminal.

## Qué hacer en su lugar

1. Edita únicamente el código fuente: [resources/js/](resources/js/), [resources/sass/](resources/sass/), los `.vue`.
2. Termina el cambio completo en el fuente.
3. Al reportar, di qué archivos tocaste y que falta compilar para verlo en el navegador. Nada más.

Si el usuario pide explícitamente compilar ("compila", "haz el build", "corre vite"), entonces sí
ejecútalo — la regla es no hacerlo por iniciativa propia.

## Leer assets compilados sí está permitido

Para diagnosticar (`grep` sobre `public/build/assets/*.js` para confirmar que un cambio quedó en el
bundle, revisar `manifest.json`) no hay problema: eso es lectura, no compilación.
