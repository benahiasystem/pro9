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
                                                   │  Builder::build(cash, options) → ['header','data',…]
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

Los métodos y rutas legacy se conservan porque la **app móvil** (`modules/MobileApp`) los consume; ahora solo delegan en `CashReportRenderer` y entregan la respuesta igual que antes (inline o descarga).

| Reporte (botón anterior) | Ruta legacy | Tipo nuevo | Builder | Vista nueva | Origen de la lógica |
|---|---|---|---|---|---|
| Reporte → PDF A4 | `cash/report-a4/{cash}` (Pos `reportA4`) | `cash_summary` `paper=a4` | `CashSummaryBuilder` | `reports/cash_summary_a4` | Pos `CashController@setDataToReport` + `pos::cash.report_pdf_a4` |
| Reporte → PDF Ticket | `cash/report-ticket/{cash}/80/0` (`reportTicket`) | `cash_summary` `paper=ticket80` | `CashSummaryBuilder` | `reports/cash_summary_ticket` | `pos::cash.report_pdf_ticket` |
| Reporte → PDF Ticket 58 | `cash/report-ticket/{cash}/58/0` | `cash_summary` `paper=ticket58` | `CashSummaryBuilder` | `reports/cash_summary_ticket` | idem |
| Reporte → PDF Ticket Resumen | `cash/report-ticket/{cash}/80/1` | `cash_summary` `paper=ticket80&summary=1` | `CashSummaryBuilder` | `reports/cash_summary_ticket` | idem |
| Reporte → Simple A4 | `cash/simple/report-a4/{cash}` (`reportSimpleA4`) | `cash_simple` | `CashSimpleBuilder` | `reports/cash_simple_a4` | `pos::cash.report_pdf_simple_a4` |
| Reporte → Excel | `cash/report-excel/{cash}` (`reportExcel`) | `cash_summary` `format=excel` | `CashSummaryBuilder` | `reports/cash_summary_excel` | `pos::cash.report_excel` + `ReportCashExport` |
| Reporte → Resumen de Operaciones Diarias | `cash-reports/summary-daily-operations/{cash}` | `summary_daily_operations` | `SummaryDailyOperationsBuilder` | `reports/summary_daily_operations_a4` | Pos `CashReportTrait` |
| Reporte → Reporte general caja V2 | `cash-reports/general-with-payments/{cash}` | `general_with_payments` | `GeneralWithPaymentsBuilder` | `reports/general_with_payments_a4` | Pos `CashReportTrait` |
| Reporte Efectivo → Excel | `cash-reports/cash-payment-report-excel/{cash}` | `payments_associated` `format=excel` | `PaymentsAssociatedCashBuilder` | `reports/payments_associated_excel` | Pos `CashReportTrait@setDataCashPaymentReportExcel` |
| Reporte Efectivo → Ingresos y egresos | `cash/report-cash-income-egress/{cash}` (`reportCashIncomeEgress`) | `income_egress` | `IncomeEgressBuilder` | `reports/income_egress_a4` | Pos `CashController@reportCashIncomeEgress` |
| Reporte Efectivo → Pagos asociados a caja | `cash-reports/payments-associated-cash/{cash}` | `payments_associated` | `PaymentsAssociatedCashBuilder` | `reports/payments_associated_a4` | Pos `CashReportTrait@getDataPaymentsAssociatedCash` |
| Reporte Productos → Punto de venta PDF | `cash/report/products/{cash}/false` (app `report_products`) | `products` | `ProductsBuilder` | `reports/products_a4` | app `CashController@getDataReport` + `tenant.cash.report_product_pdf` |
| Reporte Productos → Punto de venta Excel | `cash/report/products-excel/{cash}` | `products` `format=excel` | `ProductsBuilder` | `reports/products_excel` | `tenant.cash.report_product_excel` + `CashProductExport` |
| Reporte Productos → Venta rápida PDF | `cash/report/products/{cash}/true` | `products_garage` | `ProductsBuilder` (`is_garage=true`) | `reports/products_a4` | idem + `partials.data_garage` |
| R. Ingreso | `cash/report/income-summary/{cash}` (Report `pdf`) | `income_summary` | `IncomeSummaryBuilder` | `reports/income_summary_a4` | Report `ReportIncomeSummaryController` + `report::income_summary.report_pdf` |

Fuera del módulo, sin cambios:

- **Reporte general** de cabecera (`cash/report` → `Tenant\CashController@report_general`, `tenant.cash.report_general_pdf`, DomPDF, descarga). Consolida las cajas abiertas del día.
- `modules/Restaurant` tiene su propia copia de la lógica de productos y sigue usando `tenant.cash.report_pdf`, `tenant.cash.report_product_pdf`, `tenant.cash.report_product_excel`, sus partials y `App\Exports\CashProductExport`; por eso esos archivos se conservan.

Eliminado en la migración: `tenant.cash.report_cash_excel` + `CashPaymentExport` + ruta `cash/report/cash-excel`, ruta `cash/report/{cash}` (`report`), vistas `pos::cash.report_*`, `pos::cash.reports.*`, `pos::cash_revision.*`, `CashControllerRevision`, `ReportCashExport`, `CashReportTrait`, vistas `report::income_summary.*`.
