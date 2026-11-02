# Módulo CashReport

Generación centralizada de los reportes de **caja chica** (PDF y Excel).

Es un **módulo técnico**: no tiene menú, permisos ni activación propia por tenant. Su visibilidad depende del listado de cajas (`/cash`): admin siempre; vendedor solo si `configurations.available_cash_report_seller` está activo. Debe figurar como `"CashReport": true` en `modules_statuses.json` (la caché de `laravel-modules` obliga a `php artisan module:enable CashReport` la primera vez).

## Flujo

```
index.vue (botón "Reportes") ──▶ partials/reports.vue (modal)
        │  GET /cash-reports/catalog           ← categorías y reportes disponibles
        └─ GET /cash-reports/generate/{type}/{cash}?format&paper&summary&is_garage&action
                     │
             CashReportController  ── permiso ──▶ CashReportRenderer
                                                   │  CashReportRegistry::get(type)
                                                   │  ClassifiedCashReport::build(cash, type, options) / ProductsBuilder
                                                   ├─ PDF  : mPDF + layouts/a4|ticket + partials/header|footer
                                                   └─ Excel: GeneralFormatExport + layouts/excel
```

Parámetros de `generate`:

| Parámetro | Valores | Defecto |
|---|---|---|
| `format` | `pdf`, `excel` | `pdf` |
| `paper` | `a4`, `ticket80`, `ticket58` (solo PDF) | `a4` |
| `action` | `preview` (inline, pestaña nueva), `download` (adjunto) | `preview` |
| `summary` | `1` = solo resumen (tickets del reporte de caja) | `0` |
| `is_garage` | forzado a `true` por `products_garage` | `false` |

Nombre de archivo: `Caja_{type}_{vendedor}_{YYYYMMDD_HHmm}.{pdf|xlsx}`.

## Estructura

```
modules/CashReport/
├─ Http/Controllers/CashReportController.php   catalog() + generate(); validación única de permiso (401)
├─ Routes/web.php                               prefijo cash-reports (auth + locked.tenant)
├─ Services/
│   ├─ CashReportRegistry.php                   catálogo: type → label, category, builder, views, formats, papers, options, defaults
│   ├─ CashReportRenderer.php                   render() / pdfContent() / excelExport() / filename()
│   ├─ HeaderDataBuilder.php                    datos comunes de cabecera (empresa, establecimiento, vendedor, apertura, cierre)
│   ├─ Contracts/CashReportBuilderInterface.php build(Cash $cash, array $options): array
│   └─ Builders/                                un builder por reporte (lógica trasladada, no reescrita)
└─ Resources/views/
    ├─ layouts/a4 · ticket · excel              header + footer (solo A4: fecha y paginación)
    ├─ partials/header · footer
    └─ reports/                                 cuerpo de cada reporte (@extends layout) + partials compartidos
```

### Añadir un reporte

1. Crear `Services/Builders/XBuilder.php` implementando `CashReportBuilderInterface`. Devolver al menos `['header' => HeaderDataBuilder::build($cash), 'data' => …]`; cualquier clave extra llega a la vista.
2. Crear `Resources/views/reports/x_a4.blade.php` con `@extends('cashreport::layouts.a4')`, `<?php $title = '…'; ?>`, `@section('styles')` y `@section('content')`. (Usar `<?php ?>` para `$title`: un `@php(...)` sin `@endphp` rompe la compilación si el cuerpo tiene bloques `@php`.)
3. Registrar la entrada en `CashReportRegistry::reports()`. El modal la muestra automáticamente.

## Equivalencia con los reportes anteriores

Las rutas de caja, restaurante y móvil conservan sus contratos y delegan en `CashReportRenderer`. Los reportes financieros del catálogo usan `ClassifiedCashReport` y las vistas `classified_a4`, `classified_ticket` y `classified_excel`; los reportes `products` y `products_garage` usan `ProductsBuilder` y añaden el bloque financiero clasificado. La comparación de rendimiento anterior se conserva únicamente en `tests/Support/LegacyCashSummaryBuilder.php`.

Eliminado en la migración: `tenant.cash.report_cash_excel` + `CashPaymentExport` + ruta `cash/report/cash-excel`, ruta `cash/report/{cash}` (`report`), vistas `pos::cash.report_*`, `pos::cash.reports.*`, `pos::cash_revision.*`, `CashControllerRevision`, `ReportCashExport`, `CashReportTrait`, vistas `report::income_summary.*`.
