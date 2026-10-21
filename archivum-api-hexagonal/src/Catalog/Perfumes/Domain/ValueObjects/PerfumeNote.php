<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class PerfumeNote
{
    public const int MAX_LENGTH = 80;

    public string $note;

    /** Trim the note and reject empty or oversized values. */
    public function __construct(public NoteLayer $layer, string $note)
    {
        $note = trim($note);

        if ($note === '' || mb_strlen($note) > self::MAX_LENGTH) {
            throw new InvalidValueException('catalog.note_invalid', ['max' => self::MAX_LENGTH]);
        }

        $this->note = $note;
    }

    /** Build a note from its stored layer, rejecting a layer that does not exist. */
    public static function fromStored(string $layer, string $note): self
    {
        return new self(
            NoteLayer::tryFrom($layer) ?? throw new InvalidValueException('catalog.note_layer_invalid'),
            $note,
        );
    }

    /** Compare two notes by layer and text, ignoring case. */
    public function equals(self $other): bool
    {
        return $other->layer === $this->layer && mb_strtolower($other->note) === mb_strtolower($this->note);
    }
}
