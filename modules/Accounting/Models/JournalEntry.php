<?php

namespace Modules\Accounting\Models;

final class JournalEntry extends OwnedModel
{
    protected $casts = ['version' => 'integer', 'approved_version' => 'integer', 'rule_snapshot' => 'array', 'metadata' => 'array', 'posted_at' => 'datetime', 'approved_at' => 'datetime', 'accounting_date' => 'date:Y-m-d', 'total_debit' => 'decimal:8', 'total_credit' => 'decimal:8'];
    public function lines()
    {
        return $this->hasMany(JournalLine::class)->orderBy('position');
    }
    public function reversal()
    {
        return $this->hasOne(self::class, 'reversal_of');
    }
    public function reviews()
    {
        return $this->hasMany(EntryReview::class)->orderBy('created_at');
    }
}
