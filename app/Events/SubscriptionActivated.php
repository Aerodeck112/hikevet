<?php

namespace App\Events;

use App\Models\Contract;
use Illuminate\Foundation\Events\Dispatchable;

class SubscriptionActivated
{
    use Dispatchable;

    public function __construct(public Contract $contract, public int $points, public float $amount) {}
}
