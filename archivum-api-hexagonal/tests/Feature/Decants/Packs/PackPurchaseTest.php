<?php

declare(strict_types=1);

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackModel;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;
use function Pest\Laravel\travelTo;

pest()->use(RefreshDatabaseAsOwner::class);

beforeEach(function (): void {
    config(['payments.onvo.secret_key' => 'onvo_test_fake', 'payments.onvo.webhook_secret' => 'webhook_secret_fictitious']);
    Http::preventStrayRequests();
});

/**
 * An offer of the business, on sale and of 5 decants of 5 ml for ₡40 000 and 180 days unless said otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function packOfferOf(StaffedBusiness $business, array $attributes = []): DecantPackPlanModel
{
    return DecantPackPlanModel::factory()->create(['business_id' => $business->business->id, ...$attributes]);
}

/**
 * Buy the offer in the store of the address as the account signed in, with the key and the body given.
 *
 * @param  array<string, mixed>  $body
 * @return TestResponse<JsonResponse>
 */
function buyPack(StaffedBusiness $business, string $offerId, ?string $key = null, array $body = []): TestResponse
{
    return postJson('/api/v1/tiendas/'.$business->business->slug.'/paquetes/'.$offerId.'/compra', $body, $key === null ? [] : ['Idempotency-Key' => $key]);
}

/**
 * ONVO creating a checkout for each new charge, named after the payment, and answering the server's own queries about
 * it with the checkout in $sessionStatus and the intent in $intentStatus.
 */
function fakeOnvoForPacks(string $intentStatus = 'succeeded', string $sessionStatus = 'complete'): void
{
    Http::fake([
        'api.onvopay.com/v1/checkout/sessions/one-time-link' => fn (Request $request): PromiseInterface => Http::response([
            'id' => 'session-'.$request['metadata']['paymentId'], 'mode' => 'test',
            'url' => 'https://checkout.onvopay.com/pay/session-'.$request['metadata']['paymentId'], 'paymentIntentId' => 'intent-'.$request['metadata']['paymentId'],
        ], 201),
        'api.onvopay.com/v1/checkout/sessions/*' => fn (Request $request): PromiseInterface => Http::response([
            'id' => basename($request->url()), 'mode' => 'test', 'status' => $sessionStatus, 'paymentIntentId' => 'intent-'.Str::after(basename($request->url()), 'session-'),
        ]),
        'api.onvopay.com/v1/payment-intents/*' => fn (Request $request): PromiseInterface => Http::response([
            'id' => basename($request->url()), 'mode' => 'test', 'amount' => 4_000_000, 'currency' => 'CRC', 'status' => $intentStatus,
        ]),
    ]);
}

/**
 * The notice ONVO sends when the checkout of a payment was paid, with the metadata Archivum sent with the charge.
 *
 * @return TestResponse<JsonResponse>
 */
function noticeOfPaidPack(StaffedBusiness $business, string $paymentId, string $secret = 'webhook_secret_fictitious'): TestResponse
{
    return postJson('/api/v1/webhooks/onvo', [
        'type' => 'checkout-session.succeeded',
        'data' => ['mode' => 'test', 'id' => 'session-'.$paymentId, 'status' => 'complete', 'paymentStatus' => 'paid', 'amountTotal' => 4_000_000,
            'currency' => 'CRC', 'metadata' => ['businessId' => $business->business->id, 'paymentId' => $paymentId]],
    ], ['X-Webhook-Secret' => $secret]);
}

/**
 * The rows of the packs and their payments, read in the business.
 *
 * @return array{array<int, stdClass>, array<int, stdClass>}
 */
function packRowsOf(StaffedBusiness $business): array
{
    app(BusinessTransaction::class)->scope($business->business->id);

    return [DB::table('decant_packs')->orderBy('id')->get()->all(), DB::table('payments')->orderBy('id')->get()->all()];
}

it('R33: buys the offer for the customer, waiting for its payment, and starts the payment of its full price', function () {
    travelTo(new DateTimeImmutable('2026-11-10T15:00:00-06:00'));
    $business = actingAsCustomer();
    $offer = packOfferOf($business);
    fakeOnvoForPacks();

    $response = buyPack($business, $offer->id, body: [
        'monto' => 1, 'precio' => 1, 'estado' => 'activo', 'decants_restantes' => 99, 'pasarela' => 'paypal', 'business_id' => (string) Str::uuid7(),
    ]);

    $packId = (string) $response->json('data.id');
    $paymentId = (string) $response->json('data.pago.id');
    $response->assertCreated()->assertExactJson(['data' => [
        'id' => $packId, 'paquete_id' => $offer->id, 'estado' => 'pendiente_de_pago', 'decants_totales' => 5, 'decants_restantes' => 5,
        'ml_por_decant' => 5, 'precio_maximo_por_ml' => 2500, 'vence_en' => '2026-11-10T15:30:00-06:00',
        'pago' => [
            'id' => $paymentId, 'monto' => 40000, 'moneda_cobrada' => 'CRC', 'monto_cobrado' => 40000, 'pasarela' => 'onvo', 'estado' => 'pendiente',
            'url_pago' => 'https://checkout.onvopay.com/pay/session-'.$paymentId, 'medio' => 'tarjeta', 'consulta_pendiente' => false,
        ],
    ]]);
    $customerId = CustomerModel::query()->where('business_id', $business->business->id)->where('user_id', $business->customer->id)->value('id');
    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('decant_packs', ['id' => $packId, 'business_id' => $business->business->id, 'customer_id' => $customerId, 'decant_pack_plan_id' => $offer->id,
        'status' => 'pending_payment', 'remaining_decants' => 5, 'idempotency_key' => null]);
    assertDatabaseHas('payments', ['id' => $paymentId, 'business_id' => $business->business->id, 'decant_pack_id' => $packId, 'order_id' => null,
        'purpose' => 'full', 'amount' => 40000, 'status' => 'pending', 'provider_reference' => 'session-'.$paymentId]);
    Http::assertSent(fn (Request $request): bool => $request['lineItems'][0]['unitAmount'] === 4_000_000 && $request['metadata'] === ['paymentId' => $paymentId, 'businessId' => $business->business->id]);
    Http::assertSentCount(1);
});

it('R33: charges the price of the offer as it is stored when the pack is bought', function () {
    $business = actingAsCustomer();
    $offer = packOfferOf($business, ['price' => 32500]);
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::response(['id' => 'session-1', 'mode' => 'test', 'url' => 'https://checkout.onvopay.com/pay/session-1'], 201)]);

    buyPack($business, $offer->id, body: ['medio' => 'sinpe_movil', 'precio' => 1])->assertCreated()
        ->assertJsonPath('data.pago.monto', 32500)->assertJsonPath('data.pago.medio', 'sinpe_movil');

    Http::assertSent(fn (Request $request): bool => $request['lineItems'][0]['unitAmount'] === 3_250_000 && $request['paymentMethodTypes'] === ['mobile_number']);
});

it('makes the account a customer of the store with its first purchase there', function () {
    $business = StaffedBusiness::create();
    $account = UserModel::factory()->create();
    Sanctum::actingAs($account, Ability::namesGrantedTo(AccountRole::Customer));
    $offer = packOfferOf($business);
    fakeOnvoForPacks();

    $packId = buyPack($business, $offer->id)->assertCreated()->json('data.id');

    app(BusinessTransaction::class)->scope($business->business->id);
    $customer = CustomerModel::query()->where('business_id', $business->business->id)->where('user_id', $account->id)->sole();
    assertDatabaseHas('decant_packs', ['id' => $packId, 'customer_id' => $customer->id]);
});

it('answers 200 with the same pack and its payment when the purchase is sent again with the same key, with one charge', function () {
    $business = actingAsCustomer();
    $offer = packOfferOf($business);
    fakeOnvoForPacks();
    $first = buyPack($business, $offer->id, 'compra-1')->assertCreated();

    $again = buyPack($business, $offer->id, 'compra-1')->assertOk();

    expect($again->json('data'))->toBe($first->json('data'));
    [$packs, $payments] = packRowsOf($business);
    expect($packs)->toHaveCount(1)->and($packs[0]->idempotency_key)->toBe('compra-1')
        ->and($payments)->toHaveCount(1);
    Http::assertSentCount(1);
});

it('buys a new pack with another key or without one, even with a pack of the same offer still unpaid', function (?string $firstKey, ?string $secondKey) {
    $business = actingAsCustomer();
    $offer = packOfferOf($business);
    fakeOnvoForPacks();

    $first = buyPack($business, $offer->id, $firstKey)->assertCreated();
    $second = buyPack($business, $offer->id, $secondKey)->assertCreated();

    expect($second->json('data.id'))->not->toBe($first->json('data.id'))
        ->and($second->json('data.pago.id'))->not->toBe($first->json('data.pago.id'));
    [$packs, $payments] = packRowsOf($business);
    expect($packs)->toHaveCount(2)->and($payments)->toHaveCount(2);
})->with([
    'two keys' => ['compra-1', 'compra-2'],
    'a key and then none' => ['compra-1', null],
    'none twice' => [null, null],
]);

it('answers 409 to the same key for another offer, and buys nothing more', function () {
    $business = actingAsCustomer();
    $offer = packOfferOf($business);
    $other = packOfferOf($business, ['name' => 'Diez de 2 ml', 'decants_count' => 10, 'ml_per_decant' => 2, 'price' => 25000]);
    fakeOnvoForPacks();
    buyPack($business, $offer->id, 'compra-1')->assertCreated();

    buyPack($business, $other->id, 'compra-1')->assertConflict()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/conflicto')
        ->assertJsonPath('detail', 'Esa clave de idempotencia ya se usó para comprar otro paquete.');

    [$packs, $payments] = packRowsOf($business);
    expect($packs)->toHaveCount(1)->and($payments)->toHaveCount(1);
});

it('answers 400 to a key sent blank or longer than 100 characters, and buys nothing', function (string $key) {
    $business = actingAsCustomer();
    $offer = packOfferOf($business);

    buyPack($business, $offer->id, $key)->assertBadRequest()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/solicitud-invalida');

    assertDatabaseCount('decant_packs', 0);
    Http::assertNothingSent();
})->with([
    'empty' => [''],
    'only spaces' => ['   '],
    'one character too long' => [str_repeat('k', 101)],
]);

it('accepts a key of 100 characters', function () {
    $business = actingAsCustomer();
    $offer = packOfferOf($business);
    fakeOnvoForPacks();

    buyPack($business, $offer->id, str_repeat('k', 100))->assertCreated();
});

it('R32: answers 404 to an offer off sale, one that does not exist or one of another store, and buys nothing', function (Closure $offerOf) {
    $business = actingAsCustomer();

    buyPack($business, $offerOf($business))->assertNotFound()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado')
        ->assertJsonPath('detail', 'Ese paquete no existe en esta tienda.');

    assertDatabaseCount('decant_packs', 0);
    Http::assertNothingSent();
})->with([
    'an offer off sale' => [fn (StaffedBusiness $business): string => packOfferOf($business, ['is_active' => false])->id],
    'an offer that does not exist' => [fn (StaffedBusiness $business): string => (string) Str::uuid7()],
    'an offer of another store' => [fn (StaffedBusiness $business): string => packOfferOf(StaffedBusiness::create())->id],
]);

it('answers 401 without a session and 403 to the staff and the owner, and buys nothing (section 9)', function (string $role, int $status) {
    $business = actingAsRole($role);
    $offer = packOfferOf($business);

    buyPack($business, $offer->id, 'compra-1')->assertStatus($status);

    assertDatabaseCount('decant_packs', 0);
    Http::assertNothingSent();
})->with([
    'a visitor' => ['visitante', 401],
    'the staff' => ['colaborador', 403],
    'the owner' => ['dueno', 403],
]);

it('answers 202 and asks to come back with the same key when ONVO cannot be reached', function () {
    $business = actingAsCustomer();
    $offer = packOfferOf($business);
    Http::fake(['api.onvopay.com/v1/checkout/sessions/one-time-link' => Http::failedConnection()]);

    $response = buyPack($business, $offer->id, 'compra-1');

    $response->assertAccepted()->assertHeader('Retry-After', '30')
        ->assertJsonPath('data.estado', 'pendiente_de_pago')
        ->assertJsonPath('data.pago.url_pago', null)->assertJsonPath('data.pago.consulta_pendiente', true);
    // The same key answers the same attempt, which still does not know whether its charge was created.
    buyPack($business, $offer->id, 'compra-1')->assertAccepted()->assertJsonPath('data.pago.id', $response->json('data.pago.id'));
    [$packs, $payments] = packRowsOf($business);
    expect($packs)->toHaveCount(1)->and($payments)->toHaveCount(1);
});

it('R33: activates the pack when a verified notice of ONVO confirms its payment, valid for the days of its offer from then', function () {
    travelTo(new DateTimeImmutable('2026-11-10T15:00:00-06:00'));
    $business = actingAsCustomer();
    $offer = packOfferOf($business);
    fakeOnvoForPacks();
    $bought = buyPack($business, $offer->id, 'compra-1')->assertCreated();
    travelTo(new DateTimeImmutable('2026-11-10T15:05:00-06:00'));

    noticeOfPaidPack($business, (string) $bought->json('data.pago.id'))->assertOk()->assertJsonPath('data.estado', 'recibido');

    app(BusinessTransaction::class)->scope($business->business->id);
    $pack = DecantPackModel::query()->findOrFail((string) $bought->json('data.id'));
    expect($pack->status)->toBe('active')
        ->and($pack->remaining_decants)->toBe(5)
        ->and($pack->expires_at->equalTo(new DateTimeImmutable('2027-05-09T15:05:00-06:00')))->toBeTrue();
    assertDatabaseHas('payments', ['id' => $bought->json('data.pago.id'), 'status' => 'succeeded']);
    // The purchase sent again answers the active pack and its confirmed payment, without a new charge.
    buyPack($business, $offer->id, 'compra-1')->assertOk()
        ->assertJsonPath('data.estado', 'activo')->assertJsonPath('data.vence_en', '2027-05-09T15:05:00-06:00')
        ->assertJsonPath('data.pago.id', $bought->json('data.pago.id'))->assertJsonPath('data.pago.estado', 'confirmado');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/payment-intents/intent-'.$bought->json('data.pago.id')));
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/one-time-link')))->toHaveCount(1);
});

it('R38: leaves the pack waiting for its payment when a notice of ONVO comes without a verified origin', function () {
    $business = actingAsCustomer();
    $offer = packOfferOf($business);
    fakeOnvoForPacks();
    $bought = buyPack($business, $offer->id)->assertCreated();
    $before = packRowsOf($business);

    noticeOfPaidPack($business, (string) $bought->json('data.pago.id'), 'webhook_secret_other')->assertUnauthorized()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/credenciales-invalidas');

    expect(packRowsOf($business))->toEqual($before);
    assertDatabaseHas('decant_packs', ['id' => $bought->json('data.id'), 'status' => 'pending_payment']);
    // Only the creation of the charge reached ONVO: the server never asked about a notice it could not verify.
    Http::assertSentCount(1);
});

it('R33: activates the pack through the reconciliation of #96 when no notice of its payment arrives', function () {
    travelTo(new DateTimeImmutable('2026-11-10T15:00:00-06:00'));
    $business = actingAsCustomer();
    $offer = packOfferOf($business);
    fakeOnvoForPacks();
    $bought = buyPack($business, $offer->id)->assertCreated();
    travel(Payment::MINUTES_FOR_THE_NOTICE)->minutes();

    $command = artisan('archivum:reconcile-payments');
    assert($command instanceof PendingCommand);
    // A pending command runs when it is destroyed; run() runs it here, before the assertions.
    $command->assertSuccessful()->run();

    app(BusinessTransaction::class)->scope($business->business->id);
    $pack = DecantPackModel::query()->findOrFail((string) $bought->json('data.id'));
    expect($pack->status)->toBe('active')
        ->and($pack->expires_at->equalTo(new DateTimeImmutable('2027-05-09T15:05:00-06:00')))->toBeTrue();
    assertDatabaseHas('payments', ['id' => $bought->json('data.pago.id'), 'status' => 'succeeded']);
});

it('starts another attempt when the purchase comes again with its key after its payment failed', function () {
    $business = actingAsCustomer();
    $offer = packOfferOf($business);
    fakeOnvoForPacks('failed', sessionStatus: 'expired');
    $bought = buyPack($business, $offer->id, 'compra-1')->assertCreated();
    noticeOfPaidPack($business, (string) $bought->json('data.pago.id'))->assertOk();
    assertDatabaseHas('payments', ['id' => $bought->json('data.pago.id'), 'status' => 'failed']);

    $again = buyPack($business, $offer->id, 'compra-1')->assertOk();

    expect($again->json('data.id'))->toBe($bought->json('data.id'))
        ->and($again->json('data.pago.id'))->not->toBe($bought->json('data.pago.id'))
        ->and($again->json('data.pago.estado'))->toBe('pendiente');
    [$packs, $payments] = packRowsOf($business);
    expect($packs)->toHaveCount(1)->and($packs[0]->status)->toBe('pending_payment')
        ->and(array_column($payments, 'status'))->toEqualCanonicalizing(['failed', 'pending']);
});

it('P11: keeps a charge confirmed for a pack that no longer waits for it, without activating the pack', function () {
    $business = actingAsCustomer();
    $offer = packOfferOf($business);
    fakeOnvoForPacks();
    $bought = buyPack($business, $offer->id)->assertCreated();
    app(BusinessTransaction::class)->run($business->business->id, fn (): int => DB::table('decant_packs')->where('id', $bought->json('data.id'))->update(['status' => 'expired']));

    noticeOfPaidPack($business, (string) $bought->json('data.pago.id'))->assertOk();

    app(BusinessTransaction::class)->scope($business->business->id);
    assertDatabaseHas('payments', ['id' => $bought->json('data.pago.id'), 'status' => 'succeeded']);
    assertDatabaseHas('decant_packs', ['id' => $bought->json('data.id'), 'status' => 'expired']);
    expect(PaymentModel::query()->count())->toBe(1);
});
