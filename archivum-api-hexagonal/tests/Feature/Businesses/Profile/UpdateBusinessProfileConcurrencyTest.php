<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\SerializableClosure\SerializableClosure;
use Src\Businesses\Profile\Application\DTOs\UpdateBusinessProfileCommand;
use Src\Businesses\Profile\Application\UseCases\UpdateBusinessProfile;
use Symfony\Component\Process\Process;

it('preserves independent profile changes made by two concurrent connections', function () {
    Artisan::call('migrate:fresh', ['--database' => 'pgsql_admin', '--force' => true]);
    $id = (string) DB::connection('pgsql_admin')->table('businesses')->insertGetId([
        'name' => 'ArchivumParfums', 'slug' => 'concurrent-profile', 'location_text' => '',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $operation = static function () use ($id): void {
        DB::statement("select set_config('application_name', 'archivum-profile-concurrency', false)");
        app(UpdateBusinessProfile::class)->execute(new UpdateBusinessProfileCommand($id, [
            'settings' => ['reminderHours' => [48, 2]],
        ]));
    };
    $operation = Closure::bind($operation, null, null);
    $process = new Process([PHP_BINARY, base_path('artisan'), 'invoke-serialized-closure'], base_path(), [
        'LARAVEL_INVOKABLE_CLOSURE' => base64_encode(serialize(new SerializableClosure($operation))),
        'APP_ENV' => 'testing',
        'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
        'DB_HOST' => (string) config('database.connections.pgsql.host'),
        'DB_PORT' => (string) config('database.connections.pgsql.port'),
        'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
        'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
    ], timeout: 30);

    try {
        DB::beginTransaction();
        app(UpdateBusinessProfile::class)->execute(new UpdateBusinessProfileCommand($id, [
            'settings' => ['bottleMarginColones' => 6000],
        ]));
        $parent = (int) DB::scalar('select pg_backend_pid()');
        $process->start();
        $deadline = microtime(true) + 20;
        $blocked = false;

        while ($process->isRunning() && microtime(true) < $deadline) {
            DB::select('select pg_stat_clear_snapshot()');
            $blocked = (bool) DB::scalar(
                'select exists (select 1 from pg_stat_activity where application_name = ? and ? = any(pg_blocking_pids(pid)))',
                ['archivum-profile-concurrency', $parent],
            );
            if ($blocked) {
                break;
            }
            usleep(10_000);
        }

        expect($blocked)->toBeTrue('The second connection must wait for the first profile update.');
        DB::commit();
        expect($process->wait())->toBe(0, $process->getErrorOutput());
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($result['successful'])->toBeTrue($process->getOutput());
        $settings = json_decode((string) DB::table('businesses')->where('id', $id)->value('settings'), true);

        expect($settings['bottle_margin_colones'])->toBe(6000)
            ->and($settings['reminder_hours'])->toBe([48, 2]);
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if ($process->isRunning()) {
            $process->stop();
        }
        DB::connection('pgsql_admin')->table('businesses')->where('id', $id)->delete();
    }
});
