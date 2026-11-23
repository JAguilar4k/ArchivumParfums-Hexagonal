<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Src\Businesses\Profile\Application\DTOs\RegisterBusinessCommand;
use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;

final class StorePlatformBusinessRequest extends FormRequest
{
    /**
     * Validation rules for a new business and its owner. Any other field is ignored.
     *
     * @return array<string, list<string|Password>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:'.BusinessName::MAX_LENGTH],
            'slug' => ['required', 'string', 'max:'.BusinessSlug::MAX_LENGTH, 'regex:/^[a-z0-9-]+$/'],
            'zona_horaria' => ['sometimes', 'required', 'string', 'timezone:all'],
            'lugar_cita' => ['sometimes', 'nullable', 'string', 'max:'.AppointmentPlace::MAX_LENGTH],
            'nombre_dueno' => ['required', 'string', 'max:'.UserName::MAX_LENGTH],
            'correo_dueno' => ['required', 'string', 'email:filter', 'max:'.Email::MAX_LENGTH],
            'contrasena_dueno' => ['required', 'string', Password::defaults()],
            'dueno_atiende_citas' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la tienda',
            'slug' => 'slug',
            'zona_horaria' => 'zona horaria',
            'lugar_cita' => 'lugar de las citas',
            'nombre_dueno' => 'nombre del dueño',
            'correo_dueno' => 'correo del dueño',
            'contrasena_dueno' => 'contraseña del dueño',
            'dueno_atiende_citas' => 'el dueño atiende citas',
        ];
    }

    /** Map the validated payload to the use case input, with the default time zone when none is given. */
    public function toCommand(): RegisterBusinessCommand
    {
        $place = $this->input('lugar_cita');

        return new RegisterBusinessCommand(
            $this->string('nombre')->toString(),
            $this->string('slug')->toString(),
            $this->string('nombre_dueno')->toString(),
            $this->string('correo_dueno')->toString(),
            $this->string('contrasena_dueno')->toString(),
            $this->string('zona_horaria', BusinessTimezone::DEFAULT)->toString(),
            is_string($place) && trim($place) !== '' ? $place : null,
            $this->boolean('dueno_atiende_citas'),
        );
    }
}
