<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Marketplace no disponible</title>
    <style>
        /* Standalone a propósito: esta pantalla debe renderizar aunque el
           bundle del marketplace no haya compilado. Los estilos definitivos
           llegan en la Fase 4 con el design system. */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
            background: #FAF7F2;
            color: #020F3C;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            text-align: center;
        }
        .box { max-width: 460px; }
        h1 { font-size: 24px; font-weight: 700; margin-bottom: 12px; letter-spacing: -0.01em; }
        p { font-size: 15px; line-height: 1.6; color: #5A6484; }
    </style>
</head>
<body>
    <div class="box">
        <h1>{{ $message ?? 'El marketplace no está disponible por ahora.' }}</h1>
        <p>Estamos preparando las tiendas de la comunidad. Vuelve en un rato.</p>
    </div>
</body>
</html>
