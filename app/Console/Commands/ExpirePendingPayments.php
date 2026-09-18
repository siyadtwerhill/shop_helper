<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

class ExpirePendingPayments extends Command
{
    protected $signature = 'payments:expire';
    protected $description = 'Expire payment requests left unverified past their window';

    public function handle(): void
    {
        $count = Payment::where('status', 'pending_verification')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} stale payment requests.");
    }
}
