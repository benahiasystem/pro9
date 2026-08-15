# Dashboard de widgets — cómo añadir una fuente desde cualquier módulo

Una **fuente** es una métrica que el usuario puede añadir como widget al
dashboard (con cualquier gráfica compatible). El módulo Dashboard trae las
suyas en `Widgets/DefaultWidgetSources.php`; cualquier otro módulo puede
registrar más sin tocar este módulo.

## Checklist

1. **Crear la fuente** — extender `Modules\Dashboard\Widgets\WidgetSource`
   (o instanciar `CallbackWidgetSource` para algo compacto):

```php
use Modules\Dashboard\Widgets\WidgetContext;
use Modules\Dashboard\Widgets\WidgetDataset;
use Modules\Dashboard\Widgets\WidgetSource;

class FlujoMesasSource extends WidgetSource
{
    public function key() { return 'restaurante.flujo_mesas'; }
    public function label() { return 'Flujo de mesas'; }
    public function module() { return 'restaurante'; }
    public function moduleLabel() { return 'Restaurante'; }
    public function icon() { return 'ti-tools-kitchen-2'; }
    public function unit() { return WidgetDataset::UNIT_COUNT; }
    public function focus() { return 'serie'; }          // 'serie' | 'categorias'
    public function defaultType() { return 'line'; }

    public function visible()
    {
        // opcional: flags de configuración, permisos, etc.
        return true;
    }

    public function resolve(WidgetContext $context, array $options = [])
    {
        $filters = $context->filters(); // establishment_id, period, date_start...

        return WidgetDataset::make($this->unit())
            ->serie($labels, [['name' => 'Mesas', 'data' => $values]])
            ->totals($total, $totalAnterior);   // delta se calcula solo
    }
}
```

2. **Registrarla** en el `boot()` del ServiceProvider del módulo:

```php
use Modules\Dashboard\Widgets\WidgetSourceRegistry;

$this->app->afterResolving(WidgetSourceRegistry::class, function ($registry) {
    $registry->register(new FlujoMesasSource());
});
```

Con eso la fuente aparece automáticamente en el catálogo
(`GET /dashboard/widgets/catalog`), en el modal "Añadir widget" y pasa la
validación de layouts guardados.

## El contrato `WidgetDataset`

Todo `resolve()` devuelve el mismo shape; los renderers del frontend no
conocen la fuente:

| Campo       | Uso                                                        |
|-------------|------------------------------------------------------------|
| `serie()`   | labels del eje X + series con nombre (line/area/bar/kpi)   |
| `breakdown()` | categorías etiqueta→valor (donut/pie/barh/ranking/table) |
| `totals()`  | total actual y anterior; el delta % se calcula solo (KPI)  |
| `meta()`    | extras libres para componentes custom                      |

Llenar `serie` y/o `breakdown` según lo que la métrica pueda expresar: cuanto
más completo el dataset, más tipos de gráfica podrá elegir el usuario
(`recFor` en `Resources/assets/js/widgets/registry.js` marca legibilidad).

## Vista custom (opcional)

Si la métrica necesita un card interactivo propio (tabs, acordeones), la
fuente declara `customComponent()` con un nombre y el módulo registra el
componente Vue en el frontend:

```js
import { registerCustomComponent } from '@dashboard/widgets/WidgetCard.vue'
registerCustomComponent('widget-flujo-mesas', FlujoMesas)
```

La vista custom entra al picker como un tipo más ("Vista original") junto a
las gráficas genéricas compatibles con el dataset; no es un widget fijo.

## Opciones por widget

`options()` declara filtros propios que se guardan por instancia en el layout
y llegan a `resolve()` como `$options` (ej.: `enabled_expense` en
`finanzas.utilidades`).

## Batch y rendimiento

Todos los widgets de una carga se resuelven en un solo
`POST /dashboard/widgets/data`. El `WidgetContext` memoiza las llamadas a
helpers dentro del batch: fuentes que comparten origen (p. ej. las cuatro
de `data()`) disparan una sola consulta. Al implementar una fuente nueva,
usar `$context->remember($clave, fn)` para cachear trabajo compartido.
