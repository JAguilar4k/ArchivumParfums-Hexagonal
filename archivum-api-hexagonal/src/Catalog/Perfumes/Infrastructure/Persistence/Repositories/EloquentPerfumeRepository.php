<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Entities\PerfumeVariant;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicatePerfumeException;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicateVariantSizeException;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Catalog\Perfumes\Domain\ValueObjects\SizeMl;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeNoteModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeTagModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeVariantModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

final readonly class EloquentPerfumeRepository implements PerfumeRepository
{
    /** Take the business that every query of the repository filters by. */
    public function __construct(private BusinessContext $context) {}

    /** Generate a time-ordered UUID for a new perfume. */
    public function nextId(): PerfumeId
    {
        return new PerfumeId((string) Str::uuid7());
    }

    /** Generate a time-ordered UUID for a new presentation. */
    public function nextVariantId(): PerfumeVariantId
    {
        return new PerfumeVariantId((string) Str::uuid7());
    }

    /** Write the perfume, its notes, its tags and its presentations in one transaction. */
    public function save(Perfume $perfume): void
    {
        try {
            DB::transaction(function () use ($perfume): void {
                PerfumeModel::query()->updateOrCreate(
                    ['id' => $perfume->id->value, 'business_id' => $perfume->businessId->value],
                    [
                        'brand' => $perfume->brand->value,
                        'name' => $perfume->name->value,
                        'concentration' => $perfume->details->concentration,
                        'gender' => $perfume->details->gender,
                        'family' => $perfume->details->family,
                        'description' => $perfume->details->description,
                        'is_active' => $perfume->isActive,
                    ],
                );

                $this->writeNotes($perfume);
                $this->writeTags($perfume);
                $this->writeVariants($perfume);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw str_contains($exception->getMessage(), 'perfumes_business_brand_name_unique')
                ? DuplicatePerfumeException::create()
                : DuplicateVariantSizeException::create();
        }
    }

    /** Find a perfume of the current business, with its notes, tags and presentations loaded up front. */
    public function find(PerfumeId $id): ?Perfume
    {
        $model = $this->ofCurrentBusiness()->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /** Lock the perfume before reading the state that a change will rewrite. */
    public function findForUpdate(PerfumeId $id): ?Perfume
    {
        $model = $this->ofCurrentBusiness()->lockForUpdate()->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /** @return Builder<PerfumeModel> */
    private function ofCurrentBusiness(): Builder
    {
        return PerfumeModel::query()
            ->with(['notes', 'tags', 'variants'])
            ->where('business_id', $this->context->current()->value);
    }

    /** Insert the notes the perfume gained and delete the ones it lost. */
    private function writeNotes(Perfume $perfume): void
    {
        $stored = PerfumeNoteModel::query()
            ->where('business_id', $perfume->businessId->value)
            ->where('perfume_id', $perfume->id->value)
            ->get()
            ->keyBy(fn (PerfumeNoteModel $row): string => $row->layer.'|'.$row->note);

        $wanted = [];

        foreach ($perfume->notes as $note) {
            $key = $note->layer->value.'|'.$note->note;
            $wanted[$key] = true;

            if (! $stored->has($key)) {
                PerfumeNoteModel::query()->create([
                    'business_id' => $perfume->businessId->value,
                    'perfume_id' => $perfume->id->value,
                    'layer' => $note->layer->value,
                    'note' => $note->note,
                ]);
            }
        }

        $stored->reject(fn (PerfumeNoteModel $row, string $key): bool => isset($wanted[$key]))
            ->each(fn (PerfumeNoteModel $row): ?bool => $row->delete());
    }

    /** Insert the tags the perfume gained and delete the ones it lost. */
    private function writeTags(Perfume $perfume): void
    {
        $stored = PerfumeTagModel::query()
            ->where('business_id', $perfume->businessId->value)
            ->where('perfume_id', $perfume->id->value)
            ->get()
            ->keyBy(fn (PerfumeTagModel $row): string => $row->kind.'|'.$row->value);

        $wanted = [];

        foreach ($perfume->tags as $tag) {
            $key = $tag->kind->value.'|'.$tag->value;
            $wanted[$key] = true;

            if (! $stored->has($key)) {
                PerfumeTagModel::query()->create([
                    'business_id' => $perfume->businessId->value,
                    'perfume_id' => $perfume->id->value,
                    'kind' => $tag->kind->value,
                    'value' => $tag->value,
                ]);
            }
        }

        $stored->reject(fn (PerfumeTagModel $row, string $key): bool => isset($wanted[$key]))
            ->each(fn (PerfumeTagModel $row): ?bool => $row->delete());
    }

    /** Update each presentation by its id, never deleting or recreating it, because bottles and order lines reference it. */
    private function writeVariants(Perfume $perfume): void
    {
        foreach ($perfume->variants as $variant) {
            PerfumeVariantModel::query()->updateOrCreate(
                [
                    'id' => $variant->id->value,
                    'business_id' => $perfume->businessId->value,
                    'perfume_id' => $perfume->id->value,
                ],
                [
                    'size_ml' => $variant->size->value,
                    'sale_price' => $variant->salePrice?->amount,
                    'sale_price_override' => $variant->manualPrice?->amount,
                    'is_active' => $variant->isActive,
                ],
            );
        }
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(PerfumeModel $model): Perfume
    {
        return Perfume::reconstitute(
            new PerfumeId($model->id),
            new BusinessId($model->business_id),
            new Brand($model->brand),
            new PerfumeName($model->name),
            new PerfumeDetails($model->concentration, $model->gender, $model->family, $model->description),
            array_values($model->notes->map(fn (PerfumeNoteModel $row): PerfumeNote => PerfumeNote::fromStored($row->layer, $row->note))->all()),
            array_values($model->tags->map(fn (PerfumeTagModel $row): PerfumeTag => PerfumeTag::fromStored($row->kind, $row->value))->all()),
            $model->is_active,
            array_values($model->variants->map(fn (PerfumeVariantModel $row): PerfumeVariant => PerfumeVariant::reconstitute(
                new PerfumeVariantId($row->id),
                new SizeMl($row->size_ml),
                $row->sale_price === null ? null : new Colones($row->sale_price),
                $row->sale_price_override === null ? null : new Colones($row->sale_price_override),
                $row->is_active,
            ))->all()),
        );
    }
}
