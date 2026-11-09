<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;

/**
 * @property-read PaymentEvent $resource
 */
final class PaymentNoticeResource extends JsonResource
{
    /** Wrap the event of a verified notice and whether it was processed. */
    public function __construct(private readonly PaymentEvent $event, private readonly bool $processed)
    {
        parent::__construct($event);
    }

    /**
     * Expose only the identifier of the event and what became of it, never the payment or the order it names.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'evento' => $this->event->eventId,
            'estado' => $this->processed ? 'procesado' : 'fallido',
        ];
    }
}
