<?php

namespace App\Support;

final class DocumentListColumns
{
    public static function merge(array $defaults, array $saved): array
    {
        $columns = [];
        foreach ($defaults as $key => $column) {
            if (in_array($key, ['hka_status', 'downloads'], true)) continue;
            $default = (array) $column;
            $columns[$key] = array_replace($default, (array) ($saved[$key] ?? []), ['title' => $default['title']]);
        }

        if (isset($columns['document_type']) && !array_key_exists('document_type', $saved)) {
            $ordered = array_filter(array_keys($columns), fn ($key) => $key !== 'document_type');
            usort($ordered, fn ($a, $b) => ($columns[$a]['order'] ?? 0) <=> ($columns[$b]['order'] ?? 0));
            $customer = array_search('customer', $ordered, true);
            array_splice($ordered, $customer === false ? count($ordered) : $customer + 1, 0, ['document_type']);
            foreach ($ordered as $index => $key) $columns[$key]['order'] = $index;
        }

        if (isset($columns['total'])) $columns['total']['visible'] = true;

        return $columns;
    }
}
