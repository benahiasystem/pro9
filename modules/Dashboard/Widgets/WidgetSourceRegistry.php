<?php

namespace Modules\Dashboard\Widgets;

/**
 * Catálogo central de fuentes de widgets. Singleton de aplicación:
 * el módulo Dashboard registra sus fuentes por defecto y cualquier otro
 * módulo puede sumar las suyas desde su ServiceProvider:
 *
 *     app(WidgetSourceRegistry::class)->register($miFuente);
 */
// ######## INICIO CALCULOS EXACTOS SAB ########
class WidgetSourceRegistry
{
    /** @var WidgetSource[] indexadas por key */
    private $sources = [];

    private $defaultsLoaded = false;

    public function register(WidgetSource $source)
    {
        $this->sources[$source->key()] = $source;

        return $this;
    }

    /**
     * @return WidgetSource|null
     */
    public function get($key)
    {
        $this->loadDefaults();

        return $this->sources[$key] ?? null;
    }

    /** Solo fuentes visibles para el tenant actual. */
    public function visible()
    {
        $this->loadDefaults();

        return array_filter($this->sources, function (WidgetSource $source) {
            return $source->visible();
        });
    }

    public function has($key)
    {
        $this->loadDefaults();

        return isset($this->sources[$key]);
    }

    /**
     * Catálogo para el frontend: módulos + fuentes visibles.
     */
    public function catalog()
    {
        $modules = [];
        $sources = [];

        foreach ($this->visible() as $source) {
            $sources[] = $source->toCatalog();

            if (!isset($modules[$source->module()])) {
                $modules[$source->module()] = [
                    'key' => $source->module(),
                    'label' => $source->moduleLabel(),
                    'icon' => $source->icon(),
                ];
            }
        }

        return [
            'modules' => array_values($modules),
            'sources' => $sources,
        ];
    }

    /**
     * Resuelve un batch de widgets compartiendo contexto (memoización de
     * helpers). $requests = [['source' => key, 'options' => [], 'key' => id], ...].
     * La respuesta se indexa por 'key' (o por source si no se envía), de modo
     * que dos widgets de la misma fuente con opciones distintas no colisionen.
     * Una fuente que falla no tumba el batch: devuelve error por widget.
     */
    public function resolveBatch(array $requests, array $filters)
    {
        $connection=(new \App\Models\Tenant\Document)->getConnection();
        return \App\Support\Exact\ReadSnapshot::run($connection,function () use ($requests,$filters,$connection) {
            $context=new WidgetContext($filters);$results=[];
            foreach ($requests as $request) {
                $sourceKey=$request['source'] ?? null;$resultKey=(string)($request['key'] ?? $sourceKey ?? '');
                $options=(array)($request['options'] ?? []);
                try {
                    // A failed SQL statement must roll back its savepoint before the next source reads.
                    $results[$resultKey]=$connection->transaction(function () use ($sourceKey,$context,$options) {
                        $source=$sourceKey ? $this->get($sourceKey) : null;
                        if (!$source || !$source->visible()) return ['error'=>'source_not_found'];
                        $dataset=$source->resolve($context,$options);
                        return $dataset instanceof WidgetDataset ? $dataset->toArray() : $dataset;
                    });
                } catch (\Throwable $error) {
                    report($error);$results[$resultKey]=['error'=>'resolve_failed'];
                }
            }
            return $results;
        });
    }

    private function loadDefaults()
    {
        if ($this->defaultsLoaded) {
            return;
        }

        // Marcar antes de registrar para evitar recursión vía register().
        $this->defaultsLoaded = true;

        foreach (DefaultWidgetSources::all() as $source) {
            // Las fuentes por defecto no pisan una key ya registrada por otro módulo.
            if (!isset($this->sources[$source->key()])) {
                $this->register($source);
            }
        }
    }
}
// ######## FIN CALCULOS EXACTOS SAB ########
