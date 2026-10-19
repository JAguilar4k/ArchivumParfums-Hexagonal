<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Roles
|--------------------------------------------------------------------------
|
| Each value is a role named as GET /api/v1/cuenta names it, for
| actingAsRole(). "roles" covers the three role columns of the permission
| matrix in section 9 of the specification plus the visitor of section 3,
| who reaches the recommender row (#105). "staff of another
| business" signs in a person of a new business, so a test that created
| its own business first reaches into it from outside (#31).
|
*/

dataset('roles', [
    'owner' => ['dueno'],
    'staff' => ['colaborador'],
    'customer' => ['cliente'],
    'visitor' => ['visitante'],
]);

dataset('staff of another business', [
    'owner of another business' => ['dueno'],
    'staff of another business' => ['colaborador'],
]);
