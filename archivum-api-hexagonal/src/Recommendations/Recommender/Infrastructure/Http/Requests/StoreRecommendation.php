<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRecommendation extends FormRequest
{
    /** The longest text a customer may write, in characters: it bounds what travels to the AI on every request. */
    public const int MAX_TEXT_LENGTH = 1000;

    /**
     * Only the text of the customer: the store comes from the address and nothing of the account is read (R51).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'max:'.self::MAX_TEXT_LENGTH],
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
            'texto' => 'texto',
        ];
    }

    /** The text the customer wrote, as validated. */
    public function text(): string
    {
        return (string) $this->validated('texto');
    }
}
