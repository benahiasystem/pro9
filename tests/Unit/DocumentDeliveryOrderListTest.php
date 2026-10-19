<?php

namespace Tests\Unit;

use App\Http\Resources\Tenant\DocumentCollection;
use App\Models\Tenant\Dispatch;
use App\Models\Tenant\Document;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class DocumentDeliveryOrderListTest extends TestCase
{
    public function test_same_order_in_both_relations_appears_once_without_mutating_relations(): void
    {
        $order = $this->order(2, '', 2);
        $document = $this->document([$order], clone $order);

        self::assertSame([['description' => '00000002']], $this->listedOrders($document));
        self::assertSame([['description' => '00000002']], $this->listedOrders($document));
        self::assertSame([$order], $document->reference_guides->all());
    }

    public function test_distinct_orders_keep_reference_order_even_with_matching_visible_numbers(): void
    {
        $document = $this->document([
            $this->order(3, 'OE-01', 12),
            $this->order(2, '', 2),
        ], $this->order(4, '', 2));

        self::assertSame([
            ['description' => 'OE-01-00000012'],
            ['description' => '00000002'],
            ['description' => '00000002'],
        ], $this->listedOrders($document));
    }

    public function test_single_relation_and_missing_orders(): void
    {
        $order = $this->order(2, '', 2);
        self::assertSame([['description' => '00000002']], $this->listedOrders($this->document([$order])));
        self::assertSame([['description' => '00000002']], $this->listedOrders($this->document([], $order)));
        self::assertSame([], $this->listedOrders($this->document([])));
    }

    public function test_other_document_types_keep_an_empty_order_list(): void
    {
        $order = $this->order(2, '', 2);
        $document = $this->document([$order], $order);
        $document->document_type_id = '07';

        self::assertSame([], $this->listedOrders($document));
    }

    private function order(int $id, string $series, int $number): Dispatch
    {
        $order = new Dispatch();
        $order->setRawAttributes(compact('id', 'series', 'number'));

        return $order;
    }

    private function document(array $orders, ?Dispatch $directOrder = null): Document
    {
        $document = new Document();
        $document->setRawAttributes(['document_type_id' => '01']);
        $document->setRelation('reference_guides', new Collection($orders));
        $document->setRelation('dispatch', $directOrder);

        return $document;
    }

    private function listedOrders(Document $document): array
    {
        $collection = new DocumentCollection(new Collection());
        $method = new \ReflectionMethod($collection, 'getDispatches');
        $method->setAccessible(true);
        $orders = $method->invoke($collection, $document);

        return json_decode(json_encode($orders), true);
    }
}
