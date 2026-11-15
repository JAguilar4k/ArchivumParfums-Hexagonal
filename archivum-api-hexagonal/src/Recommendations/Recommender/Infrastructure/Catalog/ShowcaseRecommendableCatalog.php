<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Infrastructure\Catalog;

use Src\Catalog\Showcase\Infrastructure\Persistence\Models\ShowcaseNoteModel;
use Src\Catalog\Showcase\Infrastructure\Persistence\Models\ShowcasePerfumeModel;
use Src\Catalog\Showcase\Infrastructure\Persistence\Models\ShowcaseTagModel;
use Src\Catalog\Showcase\Infrastructure\Persistence\Models\ShowcaseVariantModel;
use Src\Recommendations\Recommender\Application\Contracts\RecommendableCatalog;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogVariant;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

/**
 * Reads the catalog of the recommender from the read models of the showcase (#48), so both apply the one rule of what a
 * visitor may see: active perfumes with at least one active presentation that has a sale price, and only those
 * presentations. Its fields are the ones the showcase already publishes, plus the notes; never costs or settings.
 */
final readonly class ShowcaseRecommendableCatalog implements RecommendableCatalog
{
    /** Take the store of the address, which the query filters by; the row-level policy is the second barrier. */
    public function __construct(private BusinessContext $context, private BusinessTransaction $transaction) {}

    /**
     * The AI may take up to its limit to answer, so the request does not run inside a transaction (business:route,context):
     * the catalog is read in a short transaction of its own, pinned to the store for the row-level policies.
     */
    public function perfumes(): array
    {
        $businessId = $this->context->current()->value;

        return $this->transaction->run($businessId, fn (): array => array_values(
            ShowcasePerfumeModel::query()
                ->visible()
                ->where('business_id', $businessId)
                ->with(['sellableVariants', 'tags', 'notes'])
                ->orderByRaw('lower(brand), lower(name), id')
                ->get()
                ->map(fn (ShowcasePerfumeModel $model): CatalogPerfume => $this->toPerfume($model))
                ->all(),
        ));
    }

    private function toPerfume(ShowcasePerfumeModel $model): CatalogPerfume
    {
        $tags = ['time_of_day' => [], 'climate' => [], 'occasion' => []];
        foreach ($model->tags->sortBy('value') as $tag) {
            /** @var ShowcaseTagModel $tag */
            match ($tag->kind) {
                'time_of_day' => $tags['time_of_day'][] = $tag->value,
                'climate' => $tags['climate'][] = $tag->value,
                default => $tags['occasion'][] = $tag->value,
            };
        }

        // A note may be in more than one layer of the pyramid; the recommender only needs its name once.
        $notes = [];
        foreach ($model->notes->sortBy('note') as $note) {
            /** @var ShowcaseNoteModel $note */
            $notes[mb_strtolower($note->note)] ??= $note->note;
        }

        return new CatalogPerfume(
            $model->id,
            $model->brand,
            $model->name,
            $model->concentration,
            $model->family,
            array_values($notes),
            $tags,
            array_values($model->sellableVariants->map(
                fn (ShowcaseVariantModel $variant): CatalogVariant => new CatalogVariant($variant->size_ml, (int) $variant->sale_price),
            )->all()),
        );
    }
}
