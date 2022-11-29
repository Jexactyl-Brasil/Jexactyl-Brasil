<?php

namespace Pterodactyl\Console\Commands\Schedule;

use Carbon\Carbon;
use Pterodactyl\Models\Coupon;
use Illuminate\Console\Command;

class CouponCommand extends Command
{
    protected $signature = 'p:schedule:coupon';
    protected $description = 'Process coupon expirations.';

    public function handle(): void
    {
        $this->line('Beginning check for expired coupons.');
        $time = Carbon::now()->timestamp;
        $coupons = Coupon::query()->get();
        foreach ($coupons as $coupon) {
            $carbon = new Carbon($coupon->expires);
            $expires = $carbon->timestamp;
            if ($time >= $expires) {
                $coupon->update(['expired' => true]);
                $this->line('Coupon #'.$coupon->id.' has been set as expired.');
            }
        }
        $this->line('Completed check for expired coupons.');
    }
}
