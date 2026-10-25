<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\UseCases;

use DateTimeImmutable;
use Src\Decants\Bottles\Application\Contracts\BottleReader;
use Src\Decants\Bottles\Application\DTOs\BottleView;
use Src\Decants\Bottles\Application\DTOs\OpenBottleCommand;
use Src\Decants\Bottles\Domain\Contracts\DecantBottleRepository;
use Src\Decants\Bottles\Domain\Entities\DecantBottle;
use Src\Decants\Bottles\Domain\Exceptions\BottleNotFoundException;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class OpenDecantBottle
{
    public function __construct(private DecantBottleRepository $bottles, private BottleReader $reader, private BusinessContext $context) {}

    public function execute(OpenBottleCommand $command): BottleView
    {
        $capacity = $this->reader->presentationMl($command->variantId) ?? throw new InvalidValueException('bottles.presentation_invalid');

        if ($command->totalMl !== null && $command->totalMl !== $capacity) {
            throw new InvalidValueException('bottles.capacity_mismatch');
        }

        if ($command->remainingMl !== null && $command->remainingMl > $capacity) {
            throw new InvalidValueException('bottles.remaining_invalid');
        }

        $bottle = DecantBottle::create($this->bottles->nextId(), $this->context->current(), $command->variantId, $capacity,
            $command->remainingMl ?? $capacity, new DateTimeImmutable($command->openedAt), $command->notes);
        $this->bottles->add($bottle);

        return $this->reader->find($bottle->id->value) ?? throw BottleNotFoundException::create();
    }
}
