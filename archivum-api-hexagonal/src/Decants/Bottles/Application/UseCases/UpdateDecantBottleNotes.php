<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\UseCases;

use Src\Decants\Bottles\Application\Contracts\BottleReader;
use Src\Decants\Bottles\Application\DTOs\BottleView;
use Src\Decants\Bottles\Domain\Contracts\DecantBottleRepository;
use Src\Decants\Bottles\Domain\Exceptions\BottleNotFoundException;
use Src\Decants\Bottles\Domain\ValueObjects\DecantBottleId;

final readonly class UpdateDecantBottleNotes
{
    public function __construct(private DecantBottleRepository $bottles, private BottleReader $reader) {}

    public function execute(string $id, ?string $notes): BottleView
    {
        $this->bottles->updateNotes(new DecantBottleId($id), $notes);

        return $this->reader->find($id) ?? throw BottleNotFoundException::create();
    }
}
