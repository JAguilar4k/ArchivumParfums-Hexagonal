<?php

declare(strict_types=1);

use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;

/*
 * The permission matrix of section 9 of the specification, row by row, with the ability of each row. A role has the
 * ability of a row when its cell is anything but "No"; whether the resource is its own is up to the policies (#104).
 */
it('grants each role exactly the abilities of its column in the permission matrix', function (AccountRole $role, array $expected) {
    expect(Ability::namesGrantedTo($role))->toEqualCanonicalizing($expected);
})->with([
    'owner' => [AccountRole::Owner, [
        'business:configure',               // Configurar tienda, horarios, servicios y personal: Sí
        'catalog:manage',                   // Catálogo, importación, botellas, proveedores y órdenes de compra: Sí
        'appointments:view-all',            // Ver citas: todas las de la tienda
        'appointments:attendance',          // Marcar atendida o no asistió: Sí
        'appointments:book-for-customer',   // Reservar, reprogramar o cancelar citas: en nombre de un cliente
        'orders:manage',                    // Pedidos: todos los de la tienda
        'payments:record',                  // Registrar pagos en la entrega y reembolsos: Sí
        'packs:manage',                     // Paquetes de decants: definir ofertas y ver todos
        'reports:view',                     // Reportes e ingresos: Sí
    ]],
    'staff' => [AccountRole::Staff, [
        'appointments:view-own',            // Ver citas: solo las suyas
        'appointments:attendance',          // Marcar atendida o no asistió: solo las suyas
    ]],
    'customer' => [AccountRole::Customer, [
        'customer:own',                     // Sus citas, su lista de espera, sus pedidos, sus pagos y sus paquetes
    ]],
]);

it('keeps the functions of the owner away from the staff and the customer', function (AccountRole $role) {
    $ownerOnly = array_diff(Ability::namesGrantedTo(AccountRole::Owner), [Ability::AppointmentsAttendance->value]);

    expect(array_intersect(Ability::namesGrantedTo($role), $ownerOnly))->toBe([]);
})->with([AccountRole::Staff, AccountRole::Customer]);

it('gives every ability to some role and repeats none within a role', function () {
    $granted = [];

    foreach (AccountRole::cases() as $role) {
        $names = Ability::namesGrantedTo($role);
        expect($names)->toBe(array_values(array_unique($names)));
        $granted = [...$granted, ...$names];
    }

    expect(array_values(array_unique($granted)))->toEqualCanonicalizing(array_map(fn (Ability $ability): string => $ability->value, Ability::cases()));
});

it('never grants the wildcard of Sanctum', function (AccountRole $role) {
    expect(Ability::namesGrantedTo($role))->not->toContain('*');
})->with(AccountRole::cases());
