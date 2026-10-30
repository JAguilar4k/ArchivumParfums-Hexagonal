<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Encrypt the phones already stored; CustomerModel writes the new ones encrypted. */
    public function up(): void
    {
        $this->changePhones(fn (string $phone): string => Crypt::encryptString($phone));
    }

    /** Leave the phones in clear again. */
    public function down(): void
    {
        $this->changePhones(fn (string $phone): string => Crypt::decryptString($phone));
    }

    /**
     * Rewrite the phones of each business with that business pinned, because the policy of customers binds the owner too.
     *
     * @param  Closure(string): string  $change
     */
    private function changePhones(Closure $change): void
    {
        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            DB::statement("select set_config('app.business_id', ?, true)", [$businessId]);

            foreach (DB::table('customers')->where('business_id', $businessId)->whereNotNull('phone')->get(['id', 'phone']) as $customer) {
                DB::table('customers')->where('id', $customer->id)->update(['phone' => $change($customer->phone)]);
            }
        }

        DB::statement("select set_config('app.business_id', '', true)");
    }
};
