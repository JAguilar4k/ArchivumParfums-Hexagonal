<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Payments\StoredPayments;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

use function Pest\Laravel\getJson;

pest()->use(RefreshDatabaseAsOwner::class);

/** Create another customer of a business with the abilities of a customer and answer its account. */
function anotherPayingCustomerOf(StaffedBusiness $business): UserModel
{
    $user = UserModel::factory()->create();
    app(BusinessTransaction::class)->run($business->business->id, fn () => CustomerModel::factory()->create([
        'business_id' => $business->business->id,
        'user_id' => $user->id,
    ]));

    return $user;
}

/** Ask PaymentPolicy about a payment from a request scoped to a business: 200 when it allows, else the refusal status. */
function paymentPolicyAnswer(UserModel $user, AccountRole $role, string $businessId, PaymentModel $payment): int
{
    Sanctum::actingAs($user, Ability::namesGrantedTo($role));
    $context = new FixedBusinessContext(new BusinessId($payment->business_id));
    app()->instance(BusinessContext::class, $context);

    return app(BusinessTransaction::class)->run($payment->business_id, function () use ($user, $payment, $context, $businessId): int {
        $entity = app(PaymentRepository::class)->find(new PaymentId($payment->id));
        assert($entity instanceof Payment);
        $context->switchTo(new BusinessId($businessId));
        $response = Gate::forUser($user)->inspect('view', $entity);

        return $response->allowed() ? 200 : (int) $response->status();
    });
}

it('lets only the customer who pays and the owner of the business see a payment, refusing with 404', function () {
    $first = StaffedBusiness::create();
    $second = StaffedBusiness::create();
    $payment = StoredPayments::create($first, StoredOrders::create($first)['order']);

    $answers = [
        'the customer who pays' => paymentPolicyAnswer($first->customer, AccountRole::Customer, $first->business->id, $payment),
        'another customer of the same business' => paymentPolicyAnswer(anotherPayingCustomerOf($first), AccountRole::Customer, $first->business->id, $payment),
        'a customer of another business' => paymentPolicyAnswer($second->customer, AccountRole::Customer, $first->business->id, $payment),
        'the owner of the business' => paymentPolicyAnswer($first->owner, AccountRole::Owner, $first->business->id, $payment),
        'the staff of the business' => paymentPolicyAnswer($first->staff, AccountRole::Staff, $first->business->id, $payment),
        'the owner of another business' => paymentPolicyAnswer($second->owner, AccountRole::Owner, $second->business->id, $payment),
    ];

    expect($answers)->toBe([
        'the customer who pays' => 200,
        'another customer of the same business' => 404,
        'a customer of another business' => 404,
        'the owner of the business' => 200,
        'the staff of the business' => 404,
        'the owner of another business' => 404,
    ]);
});

it('answers 404 to another customer on GET /cuenta/pagos/{pago} exactly as to a missing payment', function () {
    $business = StaffedBusiness::create();
    $payment = StoredPayments::create($business, StoredOrders::create($business)['order'], status: 'succeeded');
    Sanctum::actingAs(anotherPayingCustomerOf($business), Ability::namesGrantedTo(AccountRole::Customer));

    $foreign = getJson("/api/v1/cuenta/pagos/{$payment->id}")->assertNotFound();
    $missing = getJson('/api/v1/cuenta/pagos/'.Str::uuid7())->assertNotFound();

    expect([$foreign->json('type'), $foreign->json('title'), $foreign->json('detail')])
        ->toBe([$missing->json('type'), $missing->json('title'), $missing->json('detail')]);
});

it('lets the customer who pays read the payment', function () {
    $business = StaffedBusiness::create();
    $payment = StoredPayments::create($business, StoredOrders::create($business)['order'], status: 'succeeded');
    Sanctum::actingAs($business->customer, Ability::namesGrantedTo(AccountRole::Customer));

    getJson("/api/v1/cuenta/pagos/{$payment->id}")->assertOk()->assertJsonPath('data.id', $payment->id);
});
