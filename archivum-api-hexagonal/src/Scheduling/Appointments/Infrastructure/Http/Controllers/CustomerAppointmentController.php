<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Src\Accounts\Users\Infrastructure\Tenancy\CustomerBusinessContext;
use Src\Scheduling\Appointments\Application\DTOs\AppointmentView;
use Src\Scheduling\Appointments\Application\UseCases\BookAppointment;
use Src\Scheduling\Appointments\Application\UseCases\GetMyAppointment;
use Src\Scheduling\Appointments\Application\UseCases\ListMyAppointments;
use Src\Scheduling\Appointments\Application\UseCases\RescheduleAppointment;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentPeriod;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\IndexAppointments;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\RescheduleAppointmentRequest;
use Src\Scheduling\Appointments\Infrastructure\Http\Requests\StoreAppointment;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\BookedAppointmentResource;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\CustomerAppointmentResource;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class CustomerAppointmentController
{
    /**
     * Book a trial appointment of the customer of the session in the store of the address.
     *
     * @throws ValidationException when a field of the request cannot be booked
     */
    public function store(StoreAppointment $request, BookAppointment $bookAppointment): JsonResponse
    {
        try {
            $appointment = $bookAppointment->execute($request->toCommand());
        } catch (InvalidValueException $exception) {
            throw $this->invalid($exception);
        }

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

    /**
     * Move one appointment of the customer to another free slot. The middleware resolved the store of that appointment
     * only because it belongs to the session, so one of another customer is not found before the use case runs.
     *
     * @throws ValidationException when the new start cannot be booked
     */
    public function reschedule(RescheduleAppointmentRequest $request, string $cita, RescheduleAppointment $reschedule): CustomerAppointmentResource
    {
        try {
            return new CustomerAppointmentResource($reschedule->execute($request->toCommand($cita)));
        } catch (InvalidValueException $exception) {
            throw $this->invalid($exception);
        }
    }

    /**
     * The 422 of a value the booking or the move refused, under the field of the request it comes from; an invalid
     * value of any other kind keeps its own answer.
     */
    private function invalid(InvalidValueException $exception): ValidationException
    {
        $field = match ($exception->translationKey()) {
            'scheduling.booking.invalid_start', 'scheduling.booking.outside_window',
            'scheduling.availability.invalid_date' => 'inicia_en',
            'scheduling.booking.service_unavailable', 'scheduling.availability.service_not_bookable' => 'servicio_id',
            'scheduling.booking.person_unavailable', 'scheduling.availability.person_not_available' => 'persona_id',
            'scheduling.booking.perfume_unavailable', 'scheduling.appointment.repeated_perfume',
            'scheduling.appointment.too_many_perfumes' => 'perfumes',
            default => throw $exception,
        };

        return ValidationException::withMessages([$field => [__($exception->translationKey(), $exception->parameters())]]);
    }
}
