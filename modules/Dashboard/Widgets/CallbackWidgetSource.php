<?php

namespace Modules\Dashboard\Widgets;

/**
 * Fuente definida por configuración + closure, para declarar fuentes
 * compactas sin una clase dedicada. Las fuentes propias del módulo
 * Dashboard usan esta vía (ver DefaultWidgetSources); módulos externos
 * pueden usarla igual o extender WidgetSource directamente.
 */
class CallbackWidgetSource extends WidgetSource
{
    private $config;
    private $resolver;
    private $visibility;

    /**
     * @param array $config keys: key, label, module, module_label, unit,
     *                      focus, description, icon, types, default_type,
     *                      custom_component, options
     * @param callable $resolver fn(WidgetContext $ctx, array $options): WidgetDataset
     * @param callable|null $visibility fn(): bool
     */
    public function __construct(array $config, callable $resolver, callable $visibility = null)
    {
        $this->config = $config;
        $this->resolver = $resolver;
        $this->visibility = $visibility;
    }

    public function key()
    {
        return $this->config['key'];
    }

    public function label()
    {
        return $this->config['label'];
    }

    public function module()
    {
        return $this->config['module'];
    }

    public function moduleLabel()
    {
        return $this->config['module_label'];
    }

    public function unit()
    {
        return $this->config['unit'] ?? parent::unit();
    }

    public function focus()
    {
        return $this->config['focus'] ?? parent::focus();
    }

    public function description()
    {
        return $this->config['description'] ?? parent::description();
    }

    public function icon()
    {
        return $this->config['icon'] ?? parent::icon();
    }

    public function types()
    {
        return $this->config['types'] ?? parent::types();
    }

    public function defaultType()
    {
        return $this->config['default_type'] ?? parent::defaultType();
    }

    public function customComponent()
    {
        return $this->config['custom_component'] ?? parent::customComponent();
    }

    public function options()
    {
        return $this->config['options'] ?? parent::options();
    }

    public function visible()
    {
        return $this->visibility ? (bool) call_user_func($this->visibility) : true;
    }

    public function resolve(WidgetContext $context, array $options = [])
    {
        return call_user_func($this->resolver, $context, $options);
    }
}
