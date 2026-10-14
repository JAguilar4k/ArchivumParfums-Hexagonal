<?php

declare(strict_types=1);

it('serves the api under the v1 prefix', function () {
    $this->get('/api/v1')
        ->assertOk()
        ->assertExactJson(['nombre' => 'ArchivumParfums', 'version' => 'v1']);
});

it('does not route unversioned api paths', function () {
    $this->get('/api')->assertNotFound();
});

it('serves the health check outside the prefix', function () {
    $this->get('/up')->assertOk();
    $this->get('/api/v1/up')->assertNotFound();
});

it('answers unknown api routes in json even without an accept header', function () {
    $this->get('/api/v1/desconocido')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

it('declares the version prefix only once', function () {
    $files = [base_path('routes/api.php'), ...glob(base_path('src/{*,*/*}/Infrastructure/Http/routes.php'), GLOB_BRACE) ?: []];

    foreach ($files as $file) {
        expect(file_get_contents($file))->not->toContain('api/v1', $file);
    }
});

it('only includes module route files from routes/api.php', function () {
    $statements = array_filter(
        array_map('trim', file(base_path('routes/api.php')) ?: []),
        fn (string $line): bool => ! in_array($line, ['', '<?php', 'declare(strict_types=1);'], true),
    );

    expect($statements)->not->toBeEmpty()
        ->each->toMatch("#^require base_path\\('src/.+/Infrastructure/Http/routes\\.php'\\);$#");
});
