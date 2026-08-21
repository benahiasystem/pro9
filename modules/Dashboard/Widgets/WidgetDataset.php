<?php

namespace Modules\Dashboard\Widgets;

/**
 * Shape normalizado que toda fuente de widget entrega al frontend.
 * Los renderers genéricos (kpi, line, bar, donut, table, ranking, etc.)
 * consumen únicamente este contrato; `meta` transporta extras libres
 * para los componentes custom.
 */
class WidgetDataset
{
    const UNIT_MONEY = 'money';
    const UNIT_COUNT = 'count';
    const UNIT_PERCENT = 'percent';

    private $unit;
    private $labels = [];
    private $series = [];
    private $breakdown = ['labels' => [], 'values' => []];
    private $totals = ['current' => null, 'previous' => null, 'delta' => null];
    private $meta = [];

    public function __construct($unit = self::UNIT_MONEY)
    {
        $this->unit = $unit;
    }

    public static function make($unit = self::UNIT_MONEY)
    {
        return new static($unit);
    }

    /**
     * Serie temporal: etiquetas del eje X + una o más series con nombre.
     *
     * @param array $labels
     * @param array $series [['name' => string, 'data' => float[]], ...]
     */
    public function serie(array $labels, array $series)
    {
        $this->labels = array_values($labels);
        $this->series = array_map(function ($s) {
            return [
                'name' => (string) ($s['name'] ?? ''),
                'data' => array_map(function ($v) {
                    return round((float) $v, 2);
                }, array_values($s['data'] ?? [])),
            ];
        }, array_values($series));

        return $this;
    }

    /**
     * Desglose categórico (donut, barras horizontales, ranking, tabla).
     */
    public function breakdown(array $labels, array $values)
    {
        $this->breakdown = [
            'labels' => array_map('strval', array_values($labels)),
            'values' => array_map(function ($v) {
                return round((float) $v, 2);
            }, array_values($values)),
        ];

        return $this;
    }

    public function totals($current, $previous = null)
    {
        $current = is_null($current) ? null : round((float) $current, 2);
        $previous = is_null($previous) ? null : round((float) $previous, 2);

        $delta = null;
        if (!is_null($current) && !is_null($previous)) {
            $delta = $previous == 0.0
                ? ($current > 0 ? 100.0 : 0.0)
                : round((($current - $previous) / $previous) * 100, 1);
        }

        $this->totals = ['current' => $current, 'previous' => $previous, 'delta' => $delta];

        return $this;
    }

    /**
     * Extras libres para componentes custom (filas de deudores, meta del mes, etc.).
     */
    public function meta(array $meta)
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function toArray()
    {
        return [
            'unit' => $this->unit,
            'labels' => $this->labels,
            'series' => $this->series,
            'breakdown' => $this->breakdown,
            'totals' => $this->totals,
            'meta' => $this->meta,
        ];
    }
}
