<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'email',
        'primary_phone',
        'secondary_phone',
        'address',
        'latitude',
        'longitude',
        'credit_limit',
        'clv',
        'risk_score',
        'churn_probability',
        'next_best_action'
    ];

    public function debts()
    {
        return $this->hasMany(Debt::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
