<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Sales\StoredOrders;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

/** Create another customer of a business and answer its account. */
function anotherCustomerOf(StaffedBusiness $business): UserModel
{
    $user = UserModel::factory()->create();
    app(BusinessTransaction::class)->run($business->business->id, fn () => CustomerModel::factory()->create([
        'business_id' => $business->business->id,
        'user_id' => $user->id,
    ]));

    return $user;
}

/** Sign in an account with the abilities of a role. */
function actAsRoleAccount(UserModel $user, AccountRole $role): void
{
    Sanctum::actingAs($user, Ability::namesGrantedTo($role));
}

/**
 * Ask OrderPolicy about an order, from a request scoped to a business, and answer 200 when it allows or the status
 * of the refusal.
 */
function orderPolicyAnswer(UserModel $user, AccountRole $role, string $businessId, string $ability, OrderModel $order): int
{
    actAsRoleAccount($user, $role);
    $context = new FixedBusinessContext(new BusinessId($order->business_id));
    app()->instance(BusinessContext::class, $context);

    return app(BusinessTransaction::class)->run($order->business_id, function () use ($user, $ability, $order, $context, $businessId): int {
        $entity = app(OrderRepository::class)->find(new OrderId($order->id));
        assert($entity instanceof Order);
        // The order is loaded in its own business; the policy then answers for the business of the request.
        $context->switchTo(new BusinessId($businessId));
        $response = Gate::forUser($user)->inspect($ability, $entity);

        return $response->allowed() ? 200 : (int) $response->status();
    });
}

/**
 * The people who may try to reach an order of the first business, with the business their request is scoped to.
 *
 * @return array<string, array{UserModel, AccountRole, string}>
 */
function orderActors(StaffedBusiness $first, StaffedBusiness $second): array
{
    return [
        'the customer who placed it' => [$first->customer, AccountRole::Customer, $first->business->id],
        'another customer of the same business' => [anotherCustomerOf($first), AccountRole::Customer, $first->business->id],
        'a customer of another business' => [$second->customer, AccountRole::Customer, $first->business->id],
        'the owner of the business' => [$first->owner, AccountRole::Owner, $first->business->id],
        'the staff of the business' => [$first->staff, AccountRole::Staff, $first->business->id],
        'the owner of another business' => [$second->owner, AccountRole::Owner, $second->business->id],
    ];
}

it('decides each ability of OrderPolicy by role and ownership, refusing with 404', function (string $ability, array $allowed) {
    $first = StaffedBusiness::create();
    $second = StaffedBusiness::create();
    $order = StoredOrders::create($first)['order'];

    $answers = [];

    foreach (orderActors($first, $second) as $actor => [$user, $role, $businessId]) {
        $answers[$actor] = orderPolicyAnswer($user, $role, $businessId, $ability, $order);
    }

    $expected = array_map(fn (string $actor): int => in_array($actor, $allowed, true) ? 200 : 404, array_keys($answers));

    expect($answers)->toBe(array_combine(array_keys($answers), $expected));
})->with([
    'view' => ['view', ['the customer who placed it', 'the owner of the business']],
    'cancel' => ['cancel', ['the customer who placed it']],
    'pay' => ['pay', ['the customer who placed it']],
    'manage' => ['manage', ['the owner of the business']],
]);

it('answers 404 to another customer of the same business exactly as to a missing order, and changes nothing', function () {
    $business = StaffedBusiness::create();
    $order = StoredOrders::create($business)['order'];
    actAsRoleAccount(anotherCustomerOf($business), AccountRole::Customer);

    $foreign = getJson("/api/v1/cuenta/pedidos/{$order->id}")->assertNotFound();
    $missing = getJson('/api/v1/cuenta/pedidos/'.Str::uuid7())->assertNotFound();

    expect([$foreign->json('type'), $foreign->json('title'), $foreign->json('detail')])
        ->toBe([$missing->json('type'), $missing->json('title'), $missing->json('detail')])
        ->and($foreign->json('type'))->toEndWith('/problemas/no-encontrado');

    postJson("/api/v1/cuenta/pedidos/{$order->id}/cancelacion")->assertNotFound();
    postJson("/api/v1/cuenta/pedidos/{$order->id}/pagos", ['medio' => 'tarjeta'])->assertNotFound();

    app(BusinessTransaction::class)->scope($business->business->id);
    expect(OrderModel::query()->whereKey($order->id)->value('status'))->toBe('pending_payment')
        ->and(DB::table('payments')->where('order_id', $order->id)->count())->toBe(0);
});

it('answers 404 to a customer of another business on an order and changes nothing', function () {
    $first = StaffedBusiness::create();
    $second = StaffedBusiness::create();
    $order = StoredOrders::create($first)['order'];
    actAsRoleAccount($second->customer, AccountRole::Customer);

    getJson("/api/v1/cuenta/pedidos/{$order->id}")->assertNotFound();
    postJson("/api/v1/cuenta/pedidos/{$order->id}/cancelacion")->assertNotFound();

    app(BusinessTransaction::class)->scope($first->business->id);
    expect(OrderModel::query()->whereKey($order->id)->value('status'))->toBe('pending_payment');
});

it('never lists the orders of another customer in the account', function () {
    $business = StaffedBusiness::create();
    StoredOrders::create($business);
    actAsRoleAccount(anotherCustomerOf($business), AccountRole::Customer);

    getJson('/api/v1/cuenta/pedidos')->assertOk()->assertJsonCount(0, 'data');
});

it('lets the customer read and the owner manage the order, and answers 404 to the owner of another business', function () {
    $first = StaffedBusiness::create();
    $second = StaffedBusiness::create();
    $order = StoredOrders::create($first, 'confirmed')['order'];

    actAsRoleAccount($first->customer, AccountRole::Customer);
    getJson("/api/v1/cuenta/pedidos/{$order->id}")->assertOk()->assertJsonPath('data.id', $order->id);

    actAsRoleAccount($first->owner, AccountRole::Owner);
    getJson("/api/v1/panel/pedidos/{$order->id}")->assertOk();

    actAsRoleAccount($second->owner, AccountRole::Owner);
    getJson("/api/v1/panel/pedidos/{$order->id}")->assertNotFound()->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado');
    postJson("/api/v1/panel/pedidos/{$order->id}/listo")->assertNotFound();

    app(BusinessTransaction::class)->scope($first->business->id);
    expect(OrderModel::query()->whereKey($order->id)->value('status'))->toBe('confirmed');
});
