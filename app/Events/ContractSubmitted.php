<?php

namespace App\Events;

use App\Models\Contract;
use Illuminate\Foundation\Events\Dispatchable;

/** Echivalentul hook-ului hv_abon_submission_ready: contractul, PDF-ul si animalul sunt salvate. */
class ContractSubmitted
{
    use Dispatchable;

    public function __construct(public Contract $contract, public bool $accountCreated) {}
}
