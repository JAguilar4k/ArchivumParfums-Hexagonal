<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Recommendations\Recommender\Domain\Contracts\AiRecommender;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogVariant;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\Recommendations\RecordingAiRecommender;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\withServerVariables;

pest()->use(RefreshDatabaseAsOwner::class);

/*
 * What reaches the provider of the AI and the log when a signed-in customer asks for a recommendation (#121): only
 * the text, without the contact data it carries, and the catalog of the showcase (R51).
 */

const PRIVACY_NAME = 'Mariana Ficticia Rojas';

const PRIVACY_PHONE = '8765-4321';

const PRIVACY_IP = '203.0.113.77';

const PRIVACY_TEXT = 'Busco algo con oud para una noche fría. Escríbanme a '.TwoBusinesses::CUSTOMER.' o al '.PRIVACY_PHONE.'.';

const PRIVACY_MASKED_TEXT = 'Busco algo con oud para una noche fría. Escríbanme a [correo] o al [teléfono].';

/**
 * The customer of the first store, with a fictitious name and phone and a session token, a perfume in its showcase
 * and the AI adapter of the application wrapped to see what reaches it. The provider is simulated by each test.
 *
 * @return array{perfume: string, token: string, adapter: RecordingAiRecommender, personal: list<string>}
 */
function privacyScenario(): array
{
    config(['ai.providers.gemini.key' => 'gemini-test-key']);
    TwoBusinesses::create();
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $perfume = StoredPerfumes::create($first, 'Tom Ford', 'Oud Wood', 'EDP', variants: [[50, 185000, 185000]])->id->value;

    $user = UserModel::query()->where('email', TwoBusinesses::CUSTOMER)->sole();
    $user->update(['name' => PRIVACY_NAME]);
    $customer = app(BusinessTransaction::class)->run($first, function () use ($user): string {
        $customer = CustomerModel::query()->where('user_id', $user->id)->sole();
        $customer->update(['phone' => PRIVACY_PHONE]);

        return $customer->id;
    });
    $token = $user->createToken('Prueba', [Ability::CustomerOwn->value])->plainTextToken;
    Auth::forgetGuards();

    $adapter = new RecordingAiRecommender(app(AiRecommender::class));
    app()->instance(AiRecommender::class, $adapter);

    return [
        'perfume' => $perfume,
        'token' => $token,
        'adapter' => $adapter,
        // Everything of the person that the request carries or the database holds.
        'personal' => [PRIVACY_NAME, TwoBusinesses::CUSTOMER, PRIVACY_PHONE, str_replace('-', '', PRIVACY_PHONE), $user->id, $customer, PRIVACY_IP, $token, Str::after($token, '|')],
    ];
}

/**
 * What Gemini answers through its Interactions API when the output of the model is the given structured answer.
 *
 * @param  array<string, mixed>  $output
 * @return array<string, mixed>
 */
function privacyGeminiAnswer(array $output): array
{
    return [
        'id' => 'interaction-ficticia-1',
        'model' => 'gemini-3.5-flash-lite',
        'status' => 'completed',
        'steps' => [['type' => 'model_output', 'status' => 'done', 'content' => [['type' => 'text', 'text' => json_encode($output, JSON_THROW_ON_ERROR)]]]],
        'usage' => ['total_input_tokens' => 900, 'total_output_tokens' => 120, 'total_tokens' => 1_020],
    ];
}

/**
 * Ask for a recommendation as the customer, with the session token and from a fictitious IP address.
 *
 * @param  array{perfume: string, token: string, adapter: RecordingAiRecommender, personal: list<string>}  $scenario
 * @return TestResponse<JsonResponse>
 */
function askAsSignedInCustomer(array $scenario): TestResponse
{
    return withServerVariables(['REMOTE_ADDR' => PRIVACY_IP])
        ->withToken($scenario['token'])
        ->postJson('/api/v1/tiendas/'.TwoBusinesses::FIRST.'/recomendaciones', ['texto' => PRIVACY_TEXT]);
}

/**
 * Keep every entry written to the log from now on, as the code wrote it, before any redaction of the channels.
 *
 * @return ArrayObject<int, MessageLogged>
 */
function loggedEntries(): ArrayObject
{
    /** @var ArrayObject<int, MessageLogged> $entries */
    $entries = new ArrayObject;
    Event::listen(MessageLogged::class, fn (MessageLogged $entry) => $entries->append($entry));

    return $entries;
}

/**
 * The values of the list that appear in the text.
 *
 * @param  list<string>  $values
 * @return list<string>
 */
function valuesIn(string $text, array $values): array
{
    return array_values(array_filter($values, fn (string $value): bool => str_contains($text, $value)));
}

it('R51: sends the provider only the text without its contact data and the catalog of the showcase, also for a signed-in customer', function () {
    $scenario = privacyScenario();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(privacyGeminiAnswer([
        'preferences' => ['notes' => ['oud'], 'time_of_day' => 'night', 'climate' => 'cold'],
        'perfumes' => [['id' => $scenario['perfume'], 'reason' => 'Su oud abriga una noche fría.']],
    ]))]);

    askAsSignedInCustomer($scenario)->assertOk()->assertJsonPath('data.origen', 'ia');

    // What the adapter received: the text with the contact data replaced, and the showcase of the store.
    expect($scenario['adapter']->received)->toEqual([[
        'text' => PRIVACY_MASKED_TEXT,
        'catalog' => [new CatalogPerfume($scenario['perfume'], 'Tom Ford', 'Oud Wood', 'EDP', 'Amaderada', ['Cardamomo', 'Oud'],
            ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => ['Cena']], [new CatalogVariant(50, 185000)])],
    ]])->and(valuesIn((string) json_encode($scenario['adapter']->received, JSON_UNESCAPED_UNICODE), $scenario['personal']))->toBe([]);

    // What the provider received: that text as the message and, in the instructions, the catalog with the fields of
    // the showcase and nothing else, so no account, order, appointment, cost or setting of the store travels.
    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($scenario): bool {
        $body = $request->data();

        expect($body['input'])->toBe([['type' => 'user_input', 'content' => [['type' => 'text', 'text' => PRIVACY_MASKED_TEXT]]]])
            ->and(json_decode(Str::after($body['system_instruction'], "Catalog, as JSON:\n"), true))->toBe([[
                'id' => $scenario['perfume'],
                'brand' => 'Tom Ford',
                'name' => 'Oud Wood',
                'concentration' => 'EDP',
                'family' => 'Amaderada',
                'notes' => ['Cardamomo', 'Oud'],
                'tags' => ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => ['Cena']],
                'presentations' => [['ml' => 50, 'price' => 185000]],
            ]])
            ->and(valuesIn(urldecode($request->url()).json_encode($request->headers()).json_encode($body, JSON_UNESCAPED_UNICODE), $scenario['personal']))->toBe([]);

        return true;
    });
});

it('R51: logs the cause and the duration of a failure of the provider, never the text nor the data of the customer', function () {
    $scenario = privacyScenario();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 500, 'message' => 'Internal error.']], 500)]);
    $entries = loggedEntries();

    askAsSignedInCustomer($scenario)->assertOk()->assertJsonPath('data.origen', 'reglas');

    $logged = array_map(fn (MessageLogged $entry): array => [$entry->level, $entry->message, array_keys($entry->context)], $entries->getArrayCopy());
    $written = (string) json_encode(array_map(fn (MessageLogged $entry): array => [$entry->message, $entry->context], $entries->getArrayCopy()), JSON_UNESCAPED_UNICODE);

    expect($logged)->toBe([['warning', 'The AI recommender is unavailable.', ['cause', 'duration_ms']]])
        ->and(valuesIn($written, [PRIVACY_TEXT, PRIVACY_MASKED_TEXT, 'oud', ...$scenario['personal']]))->toBe([]);
});

it('logs how many values and perfumes the validation discarded and why, never the text nor the suggestion', function () {
    $scenario = privacyScenario();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(privacyGeminiAnswer([
        'preferences' => ['notes' => ['oud'], 'time_of_day' => 'night'],
        'perfumes' => [
            ['id' => '019a0000-0000-7000-8000-000000000000', 'reason' => 'Un perfume de otra tienda.'],
            ['id' => $scenario['perfume'], 'reason' => 'Su oud abriga una noche fría.'],
            ['id' => $scenario['perfume'], 'reason' => 'Repetido.'],
        ],
    ]))]);
    $entries = loggedEntries();

    askAsSignedInCustomer($scenario)->assertOk()->assertJsonPath('data.origen', 'ia');

    $logged = array_map(fn (MessageLogged $entry): array => [$entry->level, $entry->message, $entry->context], $entries->getArrayCopy());

    expect($logged)->toBe([['info', 'The validation discarded part of the AI suggestion.', ['discarded' => 2, 'causes' => ['not_in_catalog' => 1, 'repeated' => 1]]]])
        ->and(valuesIn((string) json_encode($logged, JSON_UNESCAPED_UNICODE), [PRIVACY_MASKED_TEXT, 'otra tienda', $scenario['perfume'], ...$scenario['personal']]))->toBe([]);
});
