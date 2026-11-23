<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\call;

/** Notices as ONVO sends them to the webhook: an event in test mode, signed in `X-Onvo-Signature`. */
final class OnvoWebhook
{
    /** The signing secret that the tests configure in `payments.onvo.webhook_secret`. */
    public const string SECRET = 'webhook_secret_fictitious';

    /**
     * An event about an object of ONVO. Its identifier defaults to its type and its object, so every attempt of the
     * same event carries the same one, as ONVO does.
     *
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    public static function notice(string $type, array $object, ?string $id = null): array
    {
        $object = ['mode' => 'test', 'currency' => 'CRC', ...$object];

        return ['id' => $id ?? $type.':'.(is_string($object['id'] ?? null) ? $object['id'] : 'none'), 'object' => 'event', 'mode' => 'test', 'type' => $type, 'data' => ['object' => $object]];
    }

    /**
     * The JSON body of a notice, exactly as it travels and is signed.
     *
     * @param  array<string, mixed>  $notice
     */
    public static function body(array $notice): string
    {
        return json_encode($notice, JSON_THROW_ON_ERROR);
    }

    /** The signature header of ONVO for a body: `t=<unix seconds>,v1=<hex HMAC-SHA256 of "t.body">`. */
    public static function signature(string $body, string $secret = self::SECRET, ?int $time = null): string
    {
        $time ??= Carbon::now()->getTimestamp();

        return 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, $secret);
    }

    /**
     * The server variables of a signed JSON request, for a test that builds its own Request.
     *
     * @return array<string, string>
     */
    public static function server(string $body, ?string $secret = self::SECRET): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        return $secret === null ? $server : [...$server, 'HTTP_X_ONVO_SIGNATURE' => self::signature($body, $secret)];
    }

    /**
     * Send a notice to the ONVO webhook, signed with the secret or unsigned.
     *
     * @param  array<string, mixed>  $notice
     * @return TestResponse<JsonResponse>
     */
    public static function post(array $notice, ?string $secret = self::SECRET): TestResponse
    {
        $body = self::body($notice);

        return call('POST', '/api/v1/webhooks/onvo', [], [], [], self::server($body, $secret), $body);
    }
}
