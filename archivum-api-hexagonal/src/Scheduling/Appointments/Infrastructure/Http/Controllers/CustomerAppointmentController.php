<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Accounts\Users\Infrastructure\Tenancy\CustomerBusinessContext;
use Src\Scheduling\Appointments\Application\DTOs\AppointmentView;
use Src\Scheduling\Appointments\Application\UseCases\BookAppointment;
use Src\Scheduling\Appointments\Application\UseCases\GetMyAppointment;
use Src\Scheduling\Appointments\Application\UseCases\ListMyAppointments;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentPeriod;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\IndexAppointments;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\StoreAppointment;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\BookedAppointmentResource;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\CustomerAppointmentResource;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class CustomerAppointmentController
{
    /** Book a trial appointment of the customer of the session in the store of the address. */
    public function store(StoreAppointment $request, BookAppointment $bookAppointment): JsonResponse
    {
        $appointment = $bookAppointment->execute($request->toCommand());

        return (new BookedAppointmentResource($appointment))->response($request)
            ->setStatusCode(201)
            ->header('Location', url('/api/v1/cuenta/citas/'.$appointment->id));
    }

    /** List the appointments of the customer, one business at a time under its own row-level scope. */
    public function index(IndexAppointments $request, CustomerBusinessContext $context): AnonymousResourceCollection
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        $period = $request->period();

        app()->instance(BusinessContext::class, $context);

        $parts = $context->eachBusiness(fn (): array => app(ListMyAppointments::class)->execute($userId, $period));
        $appointments = $parts === [] ? [] : array_merge(...$parts);
        $sign = $period === AppointmentPeriod::Upcoming ? 1 : -1;

        usort($appointments, fn (AppointmentView $left, AppointmentView $right): int => $sign * ([$left->startsAt, $left->id] <=> [$right->startsAt, $right->id]));

        $page = $request->integer('pagina', 1);
        $perPage = $request->integer('por_pagina', 15);

        return CustomerAppointmentResource::collection(new LengthAwarePaginator(
            array_slice($appointments, ($page - 1) * $perPage, $perPage),
            count($appointments),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'pagina'],
        ));
    }

    /** Read one appointment of the customer; one of another customer is not found. */
    public function show(Request $request, string $cita, GetMyAppointment $getAppointment): CustomerAppointmentResource
    {
        return new CustomerAppointmentResource($getAppointment->execute($cita, (string) $request->user()?->getAuthIdentifier()));
    }
}
