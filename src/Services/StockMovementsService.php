<?php

namespace NextDeveloper\Marketplace\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use NextDeveloper\Commons\Database\GlobalScopes\LimitScope;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Commons\Helpers\DatabaseHelper;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Marketplace\Database\Models\StockMovements;
use NextDeveloper\Marketplace\Helpers\StockLedger;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractStockMovementsService;
use Override;

/**
 * This class is responsible from managing the data for StockMovements
 *
 * Class StockMovementsService.
 */
class StockMovementsService extends AbstractStockMovementsService
{
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Group sizes the summary accepts, mapped to the date_trunc() field.
     */
    public const SUMMARY_INTERVALS = ['day' => 'day', 'week' => 'week', 'month' => 'month'];

    /**
     * Records a stock movement and moves the plan's stock with it, in one transaction.
     *
     * The plan's quantity_in_inventory is the running total: it is updated through the model (so the plan's
     * observer checks that the user may update the plan, and the update events still run - cache clearing and the
     * Shopify stock push), and the result is stored on the movement as quantity_after. The plan's stock_status
     * follows the stock when it has one (StockLedger::statusAfter).
     *
     * @param  array{marketplace_product_catalog_id: string, movement_type: string, quantity: int, moved_at?: string, notes?: ?string, crm_opportunity_id?: ?string, crm_quote_id?: ?string}  $data
     *
     * @throws ValidationException when the type is unknown, the quantity is 0, the plan or a linked record cannot be
     *                             found, or the movement would take the stock below zero
     */
    #[Override]
    public static function create(array $data)
    {
        $movementType = (string) ($data['movement_type'] ?? '');

        if (! in_array($movementType, StockLedger::movementTypes(), true)) {
            throw ValidationException::withMessages([
                'movement_type' => 'The movement type must be one of: '.implode(', ', StockLedger::movementTypes()).'.',
            ]);
        }

        $signedQuantity = StockLedger::signedQuantity($movementType, (int) ($data['quantity'] ?? 0));

        if ($signedQuantity === 0) {
            throw ValidationException::withMessages(['quantity' => 'The quantity cannot be 0.']);
        }

        //  Scoped read: the plan must be visible to the user. Whether they may change it is the plan observer's call.
        $catalog = ProductCatalogs::query()
            ->withoutGlobalScope(LimitScope::class)
            ->where('uuid', $data['marketplace_product_catalog_id'] ?? null)
            ->first();

        if (! $catalog) {
            throw ValidationException::withMessages(['marketplace_product_catalog_id' => 'The plan could not be found.']);
        }

        $links = self::resolveLinks($data);

        return DB::transaction(function () use ($catalog, $movementType, $signedQuantity, $data, $links) {
            $locked = ProductCatalogs::withoutGlobalScopes()
                ->whereKey($catalog->id)
                ->lockForUpdate()
                ->first();

            return self::applyToPlan($locked, $movementType, $signedQuantity, $data, $links);
        });
    }

    /**
     * Moves the plan's stock and writes the movement. Expects a plan row locked by the caller's transaction.
     *
     * @param  array<string, mixed>  $data  moved_at, notes
     * @param  array{crm_opportunity_id?: int, crm_quote_id?: int}  $links
     *
     * @throws ValidationException when the movement would take the stock below zero
     */
    public static function applyToPlan(ProductCatalogs $plan, string $movementType, int $signedQuantity, array $data = [], array $links = []): ?StockMovements
    {
        $quantityAfter = StockLedger::quantityAfter((int) $plan->quantity_in_inventory, $signedQuantity);

        if ($quantityAfter === null) {
            throw ValidationException::withMessages([
                'quantity' => 'Only '.max(0, (int) $plan->quantity_in_inventory).' in stock. A movement cannot take the stock below 0.',
            ]);
        }

        $status = StockLedger::statusAfter($plan->stock_status, $signedQuantity, $quantityAfter);

        $changes = ['quantity_in_inventory' => $quantityAfter];

        if ($status['stock_status'] !== $plan->stock_status) {
            $changes['stock_status'] = $status['stock_status'];
        }

        if ($status['clear_expected_arrival']) {
            $changes['expected_arrival_at'] = null;
        }

        $plan->update($changes);

        return parent::create(array_merge($links, [
            'marketplace_product_catalog_id' => (int) $plan->id,
            'movement_type' => $movementType,
            'quantity' => $signedQuantity,
            'quantity_after' => $quantityAfter,
            'moved_at' => $data['moved_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
            //  The history belongs to the account that owns the plan.
            'iam_account_id' => (int) $plan->iam_account_id,
        ]));
    }

    /**
     * Movements are a ledger: a mistake is corrected with an adjustment, so the history and the running total
     * always agree.
     */
    #[Override]
    public static function update($id, array $data)
    {
        throw new NotAllowedException('A stock movement cannot be changed. Record an adjustment instead.');
    }

    #[Override]
    public static function delete($id)
    {
        throw new NotAllowedException('A stock movement cannot be deleted. Record an adjustment instead.');
    }

    /**
     * Units moved per plan and movement type, for sales tracking: sold, rented out, returned, received, adjusted.
     * Reads only the movements the user can see (AuthorizationScope stays on), without the 20-row LimitScope.
     *
     * @param  array{marketplace_product_id?: string, marketplace_product_catalog_id?: string, moved_at_start?: string, moved_at_end?: string, interval?: string}  $params
     * @return list<array{marketplace_product_catalog_id: ?string, plan: ?string, period: ?string, incoming: int, sale: int, rental_out: int, rental_return: int, adjustment: int, movements: int}>
     */
    public static function summary(array $params): array
    {
        $interval = self::SUMMARY_INTERVALS[$params['interval'] ?? ''] ?? null;

        $query = StockMovements::query()->withoutGlobalScope(LimitScope::class);

        if (! empty($params['marketplace_product_id'])) {
            $productId = Products::query()->where('uuid', $params['marketplace_product_id'])->value('id');

            $query->whereIn(
                'marketplace_product_catalog_id',
                ProductCatalogs::withoutGlobalScopes()->where('marketplace_product_id', $productId ?? 0)->select('id')
            );
        }

        if (! empty($params['marketplace_product_catalog_id'])) {
            $query->where(
                'marketplace_product_catalog_id',
                DatabaseHelper::uuidToId(ProductCatalogs::class, $params['marketplace_product_catalog_id']) ?? 0
            );
        }

        if (! empty($params['moved_at_start'])) {
            $query->where('moved_at', '>=', $params['moved_at_start']);
        }

        if (! empty($params['moved_at_end'])) {
            $query->where('moved_at', '<=', $params['moved_at_end']);
        }

        $periodSql = $interval ? "date_trunc('{$interval}', moved_at)" : 'NULL';

        $rows = $query
            ->selectRaw("marketplace_product_catalog_id, movement_type, {$periodSql} AS period, SUM(quantity) AS units, COUNT(*) AS movements")
            ->groupByRaw("marketplace_product_catalog_id, movement_type, {$periodSql}")
            ->get();

        $catalogs = ProductCatalogs::withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('marketplace_product_catalog_id')->unique()->all())
            ->get(['id', 'uuid', 'name'])
            ->keyBy('id');

        $summary = [];

        foreach ($rows as $row) {
            $period = $row->period ? (string) $row->period : null;
            $key = $row->marketplace_product_catalog_id.'|'.$period;

            $summary[$key] ??= [
                'marketplace_product_catalog_id' => $catalogs[$row->marketplace_product_catalog_id]->uuid ?? null,
                'plan' => $catalogs[$row->marketplace_product_catalog_id]->name ?? null,
                'period' => $period,
                'incoming' => 0,
                'sale' => 0,
                'rental_out' => 0,
                'rental_return' => 0,
                'adjustment' => 0,
                'movements' => 0,
            ];

            //  Directional types are reported as positive units (3 sold, not -3). An adjustment stays net and signed.
            $units = (int) $row->units;
            $summary[$key][$row->movement_type] = $row->movement_type === 'adjustment' ? $units : abs($units);
            $summary[$key]['movements'] += (int) $row->movements;
        }

        return array_values($summary);
    }

    /**
     * The optional quote / opportunity links, resolved through their own (scoped) models so a user can only link a
     * record they can see. A uuid that does not resolve is an error rather than a silently dropped link.
     *
     * @param  array<string, mixed>  $data
     * @return array{crm_opportunity_id?: int, crm_quote_id?: int}
     */
    private static function resolveLinks(array $data): array
    {
        $models = [
            'crm_opportunity_id' => '\NextDeveloper\CRM\Database\Models\Opportunities',
            'crm_quote_id' => '\NextDeveloper\CRM\Database\Models\Quotes',
        ];

        $links = [];

        foreach ($models as $column => $model) {
            if (empty($data[$column])) {
                continue;
            }

            $id = DatabaseHelper::uuidToId($model, $data[$column]);

            if (! $id) {
                throw ValidationException::withMessages([$column => 'The linked record could not be found.']);
            }

            $links[$column] = (int) $id;
        }

        return $links;
    }
}
