<?php

declare(strict_types=1);

namespace Src\Businesses\Logo\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Src\Businesses\Logo\Application\DTOs\UploadBusinessLogoCommand;
use Src\Shared\Domain\ValueObjects\ImageType;
use Stringable;

final class StoreBusinessLogo extends FormRequest
{
    /**
     * The logo must be an image of an accepted type, recognized by its content, of at most 2 MB.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'file', 'mimetypes:'.implode(',', ImageType::mimeTypes()), 'max:'.ImageType::MAX_KILOBYTES],
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
            'logo.mimetypes' => __('files.logo_invalid'),
            'logo.max' => __('files.logo_invalid'),
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['logo' => 'logo'];
    }

    /** Map the uploaded file to the use case input, with the type detected in its content. */
    public function toCommand(): UploadBusinessLogoCommand
    {
        $logo = $this->file('logo');
        assert($logo instanceof UploadedFile);

        return new UploadBusinessLogoCommand((string) $logo->getRealPath(), (string) $logo->getMimeType());
    }
}
