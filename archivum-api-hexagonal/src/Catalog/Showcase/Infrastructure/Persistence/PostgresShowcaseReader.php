<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Src\Catalog\Showcase\Application\Contracts\ShowcaseReader;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseFilters;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseItem;
use Src\Catalog\Showcase\Application\DTOs\ShowcasePage;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseVariant;
use Src\Catalog\Showcase\Infrastructure\Persistence\Models\ShowcasePerfumeModel;
use Src\Catalog\Showcase\Infrastructure\Persistence\Models\ShowcaseTagModel;
use Src\Catalog\Showcase\Infrastructure\Persistence\Models\ShowcaseVariantModel;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class PostgresShowcaseReader implements ShowcaseReader
{
    /** Take the business of the address, which every query filters by; the row-level policy is the second barrier. */
    public function __construct(private BusinessContext $context) {}

    public function page(ShowcaseFilters $filters): ShowcasePage
    {
        $query = ShowcasePerfumeModel::query()
            ->visible()
            ->where('business_id', $this->context->current()->value);

        $this->filter($query, $filters);

        $total = $query->count();
        $models = $query->with(['sellableVariants', 'tags'])
            ->orderByRaw('lower(brand), lower(name), id')
            ->offset(($filters->page - 1) * $filters->perPage)
            ->limit($filters->perPage)
            ->get();

        return new ShowcasePage(
            array_values($models->map(fn (ShowcasePerfumeModel $model): ShowcaseItem => $this->toItem($model))->all()),
            $total,
            $filters->page,
            $filters->perPage,
        );
    }

    /**
     * Apply every filter that was sent, all of them together.
     *
     * @param  Builder<ShowcasePerfumeModel>  $query
     */
    private function filter(Builder $query, ShowcaseFilters $filters): void
    {
        if ($filters->text !== null) {
            $pattern = $this->contains($filters->text);
            $query->where(fn (Builder $match): Builder => $match->whereRaw('brand ILIKE ?', [$pattern])->orWhereRaw('name ILIKE ?', [$pattern]));
        }

        if ($filters->family !== null) {
            $query->whereRaw('lower(family) = lower(?)', [$filters->family]);
        }

        if ($filters->note !== null) {
            $pattern = $this->contains($filters->note);
            $query->whereExists(
                fn (QueryBuilder $notes) => $notes->selectRaw('1')
                    ->from('perfume_notes as n')
                    ->whereColumn('n.perfume_id', 'perfumes.id')
                    ->whereColumn('n.business_id', 'perfumes.business_id')
                    ->whereRaw('n.note ILIKE ?', [$pattern]),
            );
        }

        foreach (['time_of_day' => $filters->moment, 'climate' => $filters->climate, 'occasion' => $filters->occasion] as $kind => $value) {
            if ($value !== null) {
                $query->whereExists(
                    fn (QueryBuilder $tags) => $tags->selectRaw('1')
                        ->from('perfume_tags as t')
                        ->whereColumn('t.perfume_id', 'perfumes.id')
                        ->whereColumn('t.business_id', 'perfumes.business_id')
                        ->where('t.kind', $kind)
                        ->whereRaw('lower(t.value) = lower(?)', [$value]),
                );
            }
        }

        if ($filters->maxPrice !== null) {
            $query->whereExists(
                fn (QueryBuilder $variants) => $variants->selectRaw('1')
                    ->from('perfume_variants as priced')
                    ->whereColumn('priced.perfume_id', 'perfumes.id')
                    ->whereColumn('priced.business_id', 'perfumes.business_id')
                    ->where('priced.is_active', true)
                    ->whereNotNull('priced.sale_price')
                    ->where('priced.sale_price', '<=', $filters->maxPrice),
            );
        }
    }

    /** A LIKE pattern that finds the text anywhere, with the wildcards of the text escaped so it is searched as typed. */
    private function contains(string $text): string
    {
        return '%'.addcslashes($text, '\\%_').'%';
    }

    private function toItem(ShowcasePerfumeModel $model): ShowcaseItem
    {
        $timeOfDay = [];
        $climate = [];
        $occasion = [];

        foreach ($model->tags as $tag) {
            /** @var ShowcaseTagModel $tag */
            match ($tag->kind) {
                'time_of_day' => $timeOfDay[] = $tag->value,
                'climate' => $climate[] = $tag->value,
                default => $occasion[] = $tag->value,
            };
        }

        return new ShowcaseItem(
            $model->id,
            $model->brand,
            $model->name,
            $model->concentration,
            $model->family,
            $model->image_path,
            null,
            ['time_of_day' => $timeOfDay, 'climate' => $climate, 'occasion' => $occasion],
            array_values($model->sellableVariants->map(
                fn (ShowcaseVariantModel $variant): ShowcaseVariant => new ShowcaseVariant($variant->size_ml, (int) $variant->sale_price),
            )->all()),
        );
    }
}
