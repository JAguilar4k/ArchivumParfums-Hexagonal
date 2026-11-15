<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Payments\Payments\Application\DTOs\ReceivedPaymentEvent;

/**
 * @property-read ReceivedPaymentEvent $resource
 */
final class PaymentNoticeResource extends JsonResource
{
    /** Wrap the event of a verified notice. */
    public function __construct(private readonly ReceivedPaymentEvent $event)
    {
        parent::__construct($event);
    }

    /**
     * Expose only the identifier of the event and whether it was kept now or had already arrived, never the payment or
     * the order it names.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'evento' => $this->event->eventId,
            'estado' => $this->event->repeated ? 'repetido' : 'recibido',
        ];
    }
}
