<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Problem;

enum ProblemType: string
{
    case BadRequest = 'solicitud-invalida';
    case Unauthenticated = 'no-autenticado';
    case InvalidCredentials = 'credenciales-invalidas';
    case Forbidden = 'prohibido';
    case NotFound = 'no-encontrado';
    case MethodNotAllowed = 'metodo-no-permitido';
    case Conflict = 'conflicto';
    case InvalidData = 'datos-invalidos';
    case TooManyRequests = 'demasiadas-solicitudes';
    case InternalError = 'error-interno';
    case ServiceUnavailable = 'servicio-no-disponible';

    /** HTTP status the problem type answers with. */
    public function status(): int
    {
        return match ($this) {
            self::BadRequest => 400,
            self::Unauthenticated, self::InvalidCredentials => 401,
            self::Forbidden => 403,
            self::NotFound => 404,
            self::MethodNotAllowed => 405,
            self::Conflict => 409,
            self::InvalidData => 422,
            self::TooManyRequests => 429,
            self::InternalError => 500,
            self::ServiceUnavailable => 503,
        };
    }

    /** Resolve the first type of an HTTP status, defaulting by error class. */
    public static function fromStatus(int $status): self
    {
        return array_find(self::cases(), fn (self $type): bool => $type->status() === $status)
            ?? ($status >= 500 ? self::InternalError : self::BadRequest);
    }
}
