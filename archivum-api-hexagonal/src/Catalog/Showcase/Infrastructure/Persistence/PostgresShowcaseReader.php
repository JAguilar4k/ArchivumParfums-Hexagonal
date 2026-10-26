<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Catalog\Showcase\Application\Contracts\ShowcaseReader;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseDecant;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseDetail;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseFilters;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseItem;
use Src\Catalog\Showcase\Application\DTOs\ShowcasePage;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseVariant;
use Src\Catalog\Showcase\Infrastructure\Persistence\Models\ShowcaseNoteModel;
use Src\Catalog\Showcase\Infrastructure\Persistence\Models\ShowcasePerfumeModel;
use Src\Catalog\Showcase\Infrastructure\Persistence\Models\ShowcaseTagModel;
use Src\Catalog\Showcase\Infrastructure\Persistence\Models\ShowcaseVariantModel;
use Src\Decants\Shared\Domain\ValueObjects\DecantPrice;
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

    public function find(string $perfumeId): ?ShowcaseDetail
    {
        $model = ShowcasePerfumeModel::query()
            ->visible()
            ->where('business_id', $this->context->current()->value)
            ->with(['sellableVariants', 'tags', 'notes'])
            ->find($perfumeId);

        if ($model === null) {
            return null;
        }

        $top = [];
        $heart = [];
        $base = [];

        foreach ($model->notes->sortBy('note') as $note) {
            /** @var ShowcaseNoteModel $note */
            match ($note->layer) {
                'top' => $top[] = $note->note,
                'heart' => $heart[] = $note->note,
                default => $base[] = $note->note,
            };
        }

        $item = $this->toItem($model);

        return new ShowcaseDetail(
            $item->id,
            $item->brand,
            $item->name,
            $item->concentration,
            $model->gender,
            $item->family,
            $model->description,
            $item->photoPath,
            null,
            ['top' => $top, 'heart' => $heart, 'base' => $base],
            $item->tags,
            $item->variants,
            $this->decantsOf($perfumeId),
        );
    }

    /**
     * Fetch every eligible bottle and the business settings together, never one query per bottle.
     *
     * @return list<ShowcaseDecant>
     */
    private function decantsOf(string $perfumeId): array
    {
        $rows = DB::table('decant_bottles as b')
            ->join('perfume_variants as v', fn ($join) => $join->on('v.id', '=', 'b.perfume_variant_id')->on('v.business_id', '=', 'b.business_id'))
            ->join('businesses as business', 'business.id', '=', 'b.business_id')
            ->where('b.business_id', $this->context->current()->value)
            ->where('v.perfume_id', $perfumeId)->where('v.is_active', true)->whereNotNull('v.sale_price')
            ->where('b.status', 'open')->whereRaw('b.remaining_ml > b.reserved_ml')
            ->orderBy('b.id')->get(['b.id', 'b.remaining_ml', 'b.reserved_ml', 'v.size_ml', 'v.sale_price', 'business.settings']);
        $offers = [];

        foreach ($rows as $row) {
            /** @var array{decant_sizes_ml?: list<int>, decant_rounding_colones?: int} $settings */
            $settings = json_decode((string) $row->settings, true, flags: JSON_THROW_ON_ERROR);
            $sizes = $settings['decant_sizes_ml'] ?? BusinessSettings::DEFAULT_DECANT_SIZES_ML;
            sort($sizes);

            foreach ($sizes as $ml) {
                if ($ml <= (int) $row->remaining_ml - (int) $row->reserved_ml) {
                    $offers[] = new ShowcaseDecant((string) $row->id, $ml,
                        DecantPrice::calculate((int) $row->sale_price, (int) $row->size_ml, $ml,
                            $settings['decant_rounding_colones'] ?? BusinessSettings::DEFAULT_DECANT_ROUNDING_COLONES));
                }
            }
        }

        return $offers;
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
