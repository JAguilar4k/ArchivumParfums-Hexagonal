<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Decants;

use DateTimeImmutable;
use Illuminate\Validation\ValidationException;
use LogicException;
use Src\Decants\Packs\Application\DTOs\RecordPackRedemptionCommand;
use Src\Decants\Packs\Application\DTOs\RedeemPackDecantCommand;
use Src\Decants\Packs\Application\DTOs\ReturnPackDecantCommand;
use Src\Decants\Packs\Application\UseCases\RecordPackRedemption;
use Src\Decants\Packs\Application\UseCases\RedeemPackDecant;
use Src\Decants\Packs\Application\UseCases\ReturnPackDecant;
use Src\Decants\Packs\Domain\Exceptions\DecantPackNotFoundException;
use Src\Sales\Orders\Application\Contracts\DecantPackRedemption;
use Src\Sales\Orders\Domain\Entities\OrderLine;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class PackDecantRedemption implements DecantPackRedemption
{
    /** Receive the use cases of the packs that take a decant, keep its line and give it back. */
    public function __construct(private RedeemPackDecant $redeem, private RecordPackRedemption $record, private ReturnPackDecant $return) {}

    /**
     * A pack that is not of the customer, other ml or a presentation above the cap point at the field of the line
     * that is wrong; a conflict of the pack, such as an expired one, stays a 409.
     */
    public function redeem(OrderLine $line, string $customerId, int $salePrice, int $presentationMl, DateTimeImmutable $at, int $lineIndex): void
    {
        try {
            $this->redeem->execute(new RedeemPackDecantCommand($this->packOf($line), $customerId, (int) $line->ml, $salePrice, $presentationMl, $at->format(DATE_ATOM)));
        } catch (DecantPackNotFoundException $exception) {
            throw $this->invalidLine($lineIndex, 'paquete_id', $exception);
        } catch (InvalidValueException $exception) {
            throw $this->invalidLine($lineIndex, match ($exception->translationKey()) {
                'packs.ml_mismatch' => 'ml', 'packs.over_price_cap' => 'botella_id', default => 'paquete_id',
            }, $exception);
        }
    }

    public function record(OrderLine $line): void
    {
        $this->record->execute(new RecordPackRedemptionCommand($this->packOf($line), $line->id));
    }

    public function giveBack(OrderLine $line, DateTimeImmutable $at): void
    {
        $this->return->execute(new ReturnPackDecantCommand($this->packOf($line), $line->id, $at->format(DATE_ATOM)));
    }

    private function packOf(OrderLine $line): string
    {
        return $line->decantPackId ?? throw new LogicException('Only a decant line paid with a pack redeems a decant of it.');
    }

    private function invalidLine(int $lineIndex, string $field, DomainException $exception): ValidationException
    {
        return ValidationException::withMessages(['lineas.'.$lineIndex.'.'.$field => __($exception->translationKey(), $exception->parameters())]);
    }
}
