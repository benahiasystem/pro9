<?php

namespace Tests\Unit;

use App\Models\Tenant\QuotationItem;
use Tests\TestCase;

class QuotationItemAttributesTest extends TestCase
{
    public function test_extra_attributes_are_stored_in_item_snapshot_not_as_columns(): void
    {
        $item = new QuotationItem([
            'item' => ['extra_attr_name' => 'Entrega', 'extra_attr_value' => 'Mañana'],
            'extra_attr_name' => 'Entrega',
            'extra_attr_value' => 'Mañana',
        ]);

        $this->assertArrayNotHasKey('extra_attr_name', $item->getAttributes());
        $this->assertArrayNotHasKey('extra_attr_value', $item->getAttributes());
        $this->assertSame('Entrega', $item->item->extra_attr_name);
        $this->assertSame('Mañana', $item->item->extra_attr_value);
    }
}
