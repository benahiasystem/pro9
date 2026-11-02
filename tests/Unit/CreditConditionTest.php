<?php

namespace Tests\Unit;

use App\Models\Tenant\Document;
use App\Models\Tenant\PaymentMethodType;
use App\Models\Tenant\SaleNote;
use App\Services\Payments\CreditCondition;
use PHPUnit\Framework\TestCase;

class CreditConditionTest extends TestCase
{
    public function test_saved_condition_controls_classification_even_if_method_was_reconfigured(): void
    {
        $note = new SaleNote(['payment_condition_id'=>'01', 'payment_method_type_id'=>'09']);
        $note->setRelation('payment_method_type', new PaymentMethodType(['is_credit'=>true]));
        self::assertFalse(CreditCondition::isCredit($note));
        $note->payment_condition_id = '02';
        $note->setRelation('payment_method_type', new PaymentMethodType(['is_credit'=>false]));
        self::assertTrue(CreditCondition::isCredit($note));
        $note->payment_condition_id = '03';
        self::assertTrue(CreditCondition::isCredit($note));
        self::assertTrue(CreditCondition::isCredit(new Document(['payment_condition_id'=>'02'])));
        self::assertFalse(CreditCondition::isCredit(new Document(['payment_condition_id'=>'01'])));
    }

    public function test_legacy_note_without_condition_uses_the_method_classification(): void
    {
        $note = new SaleNote(['payment_method_type_id'=>'14']);
        $note->setRelation('payment_method_type', new PaymentMethodType(['collection_kind'=>'account', 'is_credit'=>true]));
        self::assertFalse(CreditCondition::isCredit($note));
        $note->setRelation('payment_method_type', new PaymentMethodType(['collection_kind'=>'credit', 'is_credit'=>false]));
        self::assertTrue(CreditCondition::isCredit($note));
        $note->setRelation('payment_method_type', new PaymentMethodType(['collection_kind'=>'cash', 'is_credit'=>true]));
        self::assertFalse(CreditCondition::isCredit($note));
    }
}
