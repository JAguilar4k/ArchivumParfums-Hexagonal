<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * Translation keys of a language file, ignoring the attribute names and custom
 * messages that only the Spanish file defines.
 *
 * @return list<string>
 */
function translationKeys(string $locale, string $file): array
{
    $lines = Arr::dot(require lang_path("{$locale}/{$file}.php"));

    return array_values(array_filter(
        array_keys($lines),
        fn (string $key): bool => ! in_array(explode('.', $key)[0], ['attributes', 'custom'], true),
    ));
}

it('speaks spanish and falls back to english', function () {
    expect(config('app.locale'))->toBe('es')
        ->and(app()->getLocale())->toBe('es')
        ->and(app()->getFallbackLocale())->toBe('en');
});

it('keeps the same translation keys in spanish and english', function (string $file) {
    $english = translationKeys('en', $file);
    $spanish = translationKeys('es', $file);

    expect(array_values(array_diff($english, $spanish)))->toBe([], "Missing in lang/es/{$file}.php")
        ->and(array_values(array_diff($spanish, $english)))->toBe([], "Missing in lang/en/{$file}.php");
})->with(['auth', 'install', 'pagination', 'passwords', 'validation']);

it('writes validation messages in spanish', function () {
    $validator = Validator::make(['nombre' => null], ['nombre' => 'required']);

    expect($validator->errors()->first('nombre'))->toBe('El campo nombre es obligatorio.');
});

it('names the attributes in spanish', function () {
    $validator = Validator::make(['precio_venta' => -1], ['precio_venta' => 'integer|min:0']);

    $message = $validator->errors()->first('precio_venta');

    expect($message)->toContain('precio de venta')
        ->and(str_contains($message, 'precio_venta'))->toBeFalse();
});

it('has the messages of the shared domain exceptions', function () {
    expect(__('shared.invalid_identifier'))->toBe('El identificador no es válido.');
});
