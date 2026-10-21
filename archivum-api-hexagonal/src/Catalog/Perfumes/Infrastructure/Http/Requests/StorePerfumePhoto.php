<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Src\Catalog\Perfumes\Application\DTOs\UploadPerfumePhotoCommand;
use Src\Shared\Domain\ValueObjects\ImageType;
use Stringable;

final class StorePerfumePhoto extends FormRequest
{
    /**
     * The photo must be an image of an accepted type, recognized by its content, of at most 2 MB (the limits of the logo, until others are confirmed).
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'foto' => ['required', 'file', 'mimetypes:'.implode(',', ImageType::mimeTypes()), 'max:'.ImageType::MAX_KILOBYTES],
        ];
    }

    /**
     * One message in Spanish for any reason a file is refused.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.mimetypes' => __('files.photo_invalid'),
            'foto.max' => __('files.photo_invalid'),
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['foto' => 'foto'];
    }

    /** Map the uploaded file to the use case input, with the type detected in its content. */
    public function toCommand(string $perfumeId): UploadPerfumePhotoCommand
    {
        $photo = $this->file('foto');
        assert($photo instanceof UploadedFile);

        return new UploadPerfumePhotoCommand($perfumeId, (string) $photo->getRealPath(), (string) $photo->getMimeType());
    }
}
