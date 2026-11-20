<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Notifications\CustomerNotices\Domain\Contracts\WhatsAppSender;
use Src\Notifications\CustomerNotices\Infrastructure\Jobs\SendCustomerNoticeJob;
use Src\Shared\Domain\Contracts\CustomerNotifier;
use Src\Shared\Domain\ValueObjects\CustomerNotice;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\mock;

uses(RefreshDatabaseAsOwner::class);

it('discards the queued notice when the transaction rolls back', function () {
    config(['queue.default' => 'database']);
    mock(WhatsAppSender::class)->shouldNotReceive('send');

    DB::beginTransaction();
    try {
        app(CustomerNotifier::class)->schedule(CustomerNotice::OrderPlaced, (string) Str::uuid7(), (string) Str::uuid7(), (string) Str::uuid7());

        assertDatabaseCount('jobs', 0);
    } finally {
        DB::rollBack();
    }

    assertDatabaseCount('jobs', 0);
});

it('skips a customer belonging to another business', function () {
    $business = StaffedBusiness::create();
    $other = StaffedBusiness::create();
    $transaction = app(BusinessTransaction::class);
    $customerId = $transaction->run($other->business->id, function () use ($other): string {
        $customer = CustomerModel::query()->where('user_id', $other->customer->id)->firstOrFail();
        $customer->update(['phone' => '+50688880001']);

        return $customer->id;
    });
    $sender = mock(WhatsAppSender::class);
    $sender->shouldNotReceive('send');

    $job = new SendCustomerNoticeJob(CustomerNotice::OrderPlaced, $business->business->id, $customerId, (string) Str::uuid7(), []);
    $job->handle($transaction, app(WhatsAppSender::class));
});

it('sends a notice at most once when the same job is replayed', function () {
    $business = StaffedBusiness::create();
    $transaction = app(BusinessTransaction::class);
    $customerId = $transaction->run($business->business->id, function () use ($business): string {
        $customer = CustomerModel::query()->where('user_id', $business->customer->id)->firstOrFail();
        $customer->update(['phone' => '+50688880001']);

        return $customer->id;
    });
    $sender = mock(WhatsAppSender::class);
    $sender->shouldReceive('send')->once();

    $job = new SendCustomerNoticeJob(CustomerNotice::OrderPlaced, $business->business->id, $customerId, (string) Str::uuid7(), []);
    $job->handle($transaction, app(WhatsAppSender::class));
    $job->handle($transaction, app(WhatsAppSender::class));
});
