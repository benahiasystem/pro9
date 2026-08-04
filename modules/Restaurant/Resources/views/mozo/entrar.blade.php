<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso a Mozo</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f4f5f6;
            color: #1d3a3a;
        }
        .box {
            text-align: center;
            padding: 2rem;
        }
    </style>
</head>
<body>
<div class="box">
    <p>Ingresando a Mozo...</p>
</div>
<script>
    (function () {
        var data = @json($sessionData);

        Object.keys(data).forEach(function (key) {
            if (data[key] !== null && data[key] !== undefined && data[key] !== '') {
                localStorage.setItem(key, data[key]);
            }
        });

        window.location.replace('/mozo/app');
    })();
</script>
</body>
</html>
