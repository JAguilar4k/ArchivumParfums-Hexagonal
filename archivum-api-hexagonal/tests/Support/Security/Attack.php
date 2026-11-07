<?php

declare(strict_types=1);

namespace Tests\Support\Security;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\Accounts\DemoAccounts;

use function Pest\Laravel\json;

/**
 * Requests of the security tests (#107), with real tokens of POST /api/v1/sesiones and the accounts of the seeders.
 */
final class Attack
{
    /** A second customer of ArchivumParfums, who does not own the order of MatrixResources. */
    public const string OTHER_CUSTOMER = 'carlos@clientes.test';

    /**
     * Send a request as a demo account; a null account is a visitor.
     *
     * @param  array<string, mixed>  $body
     * @return TestResponse<JsonResponse>
     */
    public static function as(?string $email, string $method, string $uri, array $body = []): TestResponse
    {
        $headers = $email === null ? [] : DemoAccounts::signIn($email);
        Auth::forgetGuards();

        return json($method, $uri, $body, $headers);
    }

    /**
     * The URI with the resources of MatrixResources in its parameters.
     *
     * @param  array<string, string>  $resources
     */
    public static function uri(string $uri, array $resources): string
    {
        foreach ($resources as $name => $value) {
            $uri = str_replace("{{$name}}", $value, $uri);
        }

        return $uri;
    }

    /** Rows inserted, updated or deleted so far in the transaction of the test, besides the tokens Sanctum touches. */
    public static function rowsWritten(): int
    {
        return (int) DB::scalar(
            "select coalesce(sum(n_tup_ins + n_tup_upd + n_tup_del), 0) from pg_stat_xact_user_tables
              where relname <> 'personal_access_tokens'",
        );
    }
}
