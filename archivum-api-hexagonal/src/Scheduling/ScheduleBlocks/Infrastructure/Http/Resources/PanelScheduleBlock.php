<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Scheduling\ScheduleBlocks\Domain\Entities\ScheduleBlock;

/**
 * @property-read ScheduleBlock $resource
 */
final class PanelScheduleBlock extends JsonResource
{
    /** Wrap a block of the agenda of the business of the session. */
    public function __construct(private readonly ScheduleBlock $block)
    {
        parent::__construct($block);
    }

    /**
     * Expose the block with the vocabulary of the business; the origin is read only and the business never leaves the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->block->id->value,
            'persona_id' => $this->block->memberId?->value,
            'inicia_en' => $this->block->startsAt->format(DATE_ATOM),
            'termina_en' => $this->block->endsAt->format(DATE_ATOM),
            'motivo' => $this->block->reason,
            'origen' => $this->block->origin->value,
        ];
    }
}
