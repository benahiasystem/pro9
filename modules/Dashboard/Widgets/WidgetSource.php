<?php

namespace Modules\Dashboard\Widgets;

/**
 * Fuente de datos de un widget. Cualquier módulo puede aportar fuentes
 * implementando esta clase y registrándolas en WidgetSourceRegistry desde
 * su ServiceProvider:
 *
 *     app(WidgetSourceRegistry::class)->register(new FlujoMesasSource());
 *
 * La fuente declara su metadata (módulo, unidad, foco, tipos) y resuelve
 * un WidgetDataset a partir del contexto compartido de la petición.
 */
abstract class WidgetSource
{
    /** Clave única, con prefijo de módulo: 'ventas.notas_venta'. */
    abstract public function key();

    /** Etiqueta visible de la métrica. */
    abstract public function label();

    /** Clave y etiqueta del módulo al que pertenece. */
    abstract public function module();

    abstract public function moduleLabel();

    /** Resuelve el dataset normalizado para los filtros del contexto. */
    abstract public function resolve(WidgetContext $context, array $options = []);

    /** money | count | percent */
    public function unit()
    {
        return WidgetDataset::UNIT_MONEY;
    }

    /** 'serie' (evolución temporal) o 'categorias' (desglose). */
    public function focus()
    {
        return 'serie';
    }

    /** Subtítulo/descripción corta mostrada bajo el título del card. */
    public function description()
    {
        return null;
    }

    /** Icono tabler del módulo (el front lo usa en el modal). */
    public function icon()
    {
        return null;
    }

    /**
     * Tipos de gráfica permitidos. null = el front los deriva del focus.
     * Devolver lista explícita (['donut','table',...]) para restringir.
     */
    public function types()
    {
        return null;
    }

    /** Tipo por defecto al añadir el widget. */
    public function defaultType()
    {
        return $this->focus() === 'serie' ? 'line' : 'donut';
    }

    /**
     * Nombre del componente Vue custom registrado en el front, o null.
     * Cuando existe, entra al picker como un tipo más ('custom').
     */
    public function customComponent()
    {
        return null;
    }

    /**
     * Declaración de opciones propias del widget (se guardan por instancia
     * en el layout y llegan a resolve() como $options).
     * Ej.: [['key' => 'enabled_expense', 'label' => 'Considerar gastos', 'type' => 'boolean', 'default' => true]]
     */
    public function options()
    {
        return [];
    }

    /**
     * Visibilidad según configuración del tenant (flags dashboard_*).
     * Fuentes no visibles no aparecen en catálogo ni resuelven datos.
     */
    public function visible()
    {
        return true;
    }

    /** Serialización para GET /dashboard/widgets/catalog. */
    public function toCatalog()
    {
        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'module' => $this->module(),
            'module_label' => $this->moduleLabel(),
            'description' => $this->description(),
            'icon' => $this->icon(),
            'unit' => $this->unit(),
            'focus' => $this->focus(),
            'types' => $this->types(),
            'default_type' => $this->defaultType(),
            'custom_component' => $this->customComponent(),
            'options' => $this->options(),
        ];
    }
}
