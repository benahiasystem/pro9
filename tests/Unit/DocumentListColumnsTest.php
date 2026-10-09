<?php

namespace Tests\Unit;

use App\Support\DocumentListColumns;
use PHPUnit\Framework\TestCase;

class DocumentListColumnsTest extends TestCase
{
    private function defaults(): array
    {
        return [
            'customer' => ['title' => 'Cliente', 'visible' => true, 'order' => 4],
            'document_type' => ['title' => 'Tipo de documento', 'visible' => true, 'order' => 4.5],
            'number' => ['title' => 'Número', 'visible' => true, 'order' => 5],
            'state_type' => ['title' => 'Estado', 'visible' => true, 'order' => 11],
            'personalized' => ['title' => 'Personalizados', 'visible' => true, 'order' => 12],
        ];
    }

    public function test_old_preferences_insert_document_type_after_customer_without_resetting_other_columns(): void
    {
        $columns = DocumentListColumns::merge($this->defaults(), [
            'customer' => (object) ['visible' => false, 'order' => 40],
            'number' => (object) ['visible' => false, 'order' => 0],
            'state_type' => (object) ['visible' => true, 'order' => 41],
            'hka_status' => (object) ['visible' => true, 'order' => 40.5],
            'downloads' => (object) ['visible' => true, 'order' => 29],
            'personalized' => (object) ['fields' => (object) ['reference' => false], 'order' => 42],
        ]);
        self::assertArrayNotHasKey('hka_status', $columns);
        self::assertArrayNotHasKey('downloads', $columns);
        self::assertFalse($columns['customer']['visible']);
        self::assertFalse($columns['number']['visible']);
        self::assertTrue($columns['document_type']['visible']);
        self::assertFalse($columns['personalized']['fields']->reference);
        uasort($columns, fn ($a, $b) => $a['order'] <=> $b['order']);
        self::assertSame(['number', 'customer', 'document_type', 'state_type', 'personalized'], array_keys($columns));
    }

    public function test_obsolete_download_defaults_are_ignored_without_resetting_visibility(): void
    {
        $columns = DocumentListColumns::merge($this->defaults() + [
            'downloads' => ['title' => 'Descargas PDF', 'visible' => true, 'order' => 29],
        ], ['number' => ['visible' => false]]);
        self::assertArrayNotHasKey('downloads', $columns);
        self::assertFalse($columns['number']['visible']);
    }

    public function test_total_cannot_be_hidden_by_old_preferences_and_keeps_its_saved_position(): void
    {
        $defaults = $this->defaults() + ['total' => ['title' => 'Total', 'visible' => true, 'order' => 26]];
        $columns = DocumentListColumns::merge($defaults, [
            'total' => (object) ['visible' => false, 'order' => 2],
            'number' => ['visible' => false],
            'document_type' => ['visible' => true, 'order' => 4.5],
        ]);
        self::assertTrue($columns['total']['visible']);
        self::assertSame(2, $columns['total']['order']);
        self::assertFalse($columns['number']['visible']);
        self::assertSame($columns, DocumentListColumns::merge($defaults, $columns));
    }

    public function test_explicit_visibility_and_order_survive_later_loads(): void
    {
        $columns = DocumentListColumns::merge($this->defaults(), [
            'document_type' => ['visible' => false, 'order' => 0],
        ]);
        self::assertFalse($columns['document_type']['visible']);
        self::assertSame(0, $columns['document_type']['order']);
        self::assertSame('Tipo de documento', $columns['document_type']['title']);
    }

    public function test_new_preferences_start_visible_after_customer_and_merge_is_stable(): void
    {
        $columns = DocumentListColumns::merge($this->defaults(), []);
        self::assertSame($columns['customer']['order'] + 1, $columns['document_type']['order']);
        self::assertSame($columns, DocumentListColumns::merge($this->defaults(), $columns));
    }
}
