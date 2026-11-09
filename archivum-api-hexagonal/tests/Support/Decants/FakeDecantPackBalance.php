<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use Closure;
use DateTimeImmutable;
use Src\Decants\Packs\Domain\Contracts\DecantPackBalance;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;

/** The balance of the packs in the database, as the test chose it, taken like the conditional UPDATE takes it. */
final class FakeDecantPackBalance implements DecantPackBalance
{
    /** @var list<string> identifiers of the packs a decant was taken from, in order */
    public private(set) array $taken = [];

    /** @var (Closure(): void)|null what another transaction commits before the statement runs, such as the expiry of the pack */
    public ?Closure $meanwhile = null;

    /** @param  array<string, int>  $left  decants the database can still take from each pack; a pack not listed has none */
    public function __construct(private array $left = []) {}

    /** Take a decant only when the database still has one to take. */
    public function take(DecantPackId $id, DateTimeImmutable $at): bool
    {
        if ($this->meanwhile !== null) {
            ($this->meanwhile)();
        }

        if (($this->left[$id->value] ?? 0) < 1) {
            return false;
        }

        $this->left[$id->value]--;
        $this->taken[] = $id->value;

        return true;
    }
}
