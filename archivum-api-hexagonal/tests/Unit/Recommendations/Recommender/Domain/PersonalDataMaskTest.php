<?php

declare(strict_types=1);

use Src\Recommendations\Recommender\Domain\Services\PersonalDataMask;

it('R51: replaces the emails and phones a customer writes in the text', function (string $text, string $masked) {
    expect((new PersonalDataMask)->apply($text))->toBe($masked);
})->with([
    'email' => ['Escríbame a ana.perez+perfumes@correo.example.cr si hay algo', 'Escríbame a [correo] si hay algo'],
    'email that ends the sentence' => ['Mi correo es ana@clientes.test.', 'Mi correo es [correo].'],
    'phone with a hyphen' => ['Llámeme al 8888-7777, por favor', 'Llámeme al [teléfono], por favor'],
    'phone with a space' => ['WhatsApp 6000 1234', 'WhatsApp [teléfono]'],
    'phone that ends the sentence' => ['Mi número es 88887777.', 'Mi número es [teléfono].'],
    'landline' => ['En la tienda, al 2460-1234', 'En la tienda, al [teléfono]'],
    'phone with 506' => ['506 7000 1234', '[teléfono]'],
    'phone with +506' => ['+506 6000-1234', '[teléfono]'],
    'phone with +506 and no spaces' => ['+50688887777', '[teléfono]'],
    'several of them' => ['ana@clientes.test o 8888-7777 o luis@clientes.test', '[correo] o [teléfono] o [correo]'],
]);

it('keeps the rest of the text as the customer wrote it, amounts and sizes included', function (string $text) {
    expect((new PersonalDataMask)->apply($text))->toBe($text);
})->with([
    'amount with a space' => ['Algo con vainilla, hasta ₡85 000.'],
    'amount without separators' => ['No más de 120000 colones'],
    'amount in millions' => ['Un regalo de ₡1.500.000'],
    'amount in thousands' => ['Hasta 90 mil'],
    'sizes and years' => ['Un decant de 10 ml, como el de 2025'],
    'nothing to hide' => ['Busco algo fresco para el día, con cítricos.'],
]);
