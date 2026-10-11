<?php

namespace NextDeveloper\Marketplace\Services;

use NextDeveloper\Commons\Helpers\DatabaseHelper;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractProvidersService;

/**
 * This class is responsible from managing the data for Providers
 *
 * Class ProvidersService.
 *
 * @package NextDeveloper\Marketplace\Database\Models
 */
class ProvidersService extends AbstractProvidersService
{

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Added: the generated abstract service does not convert marketplace_market_id (public UUID)
     * to the internal bigint id, which made the insert fail with SQLSTATE[22P02]. We convert it
     * here and delegate to the generated create().
     */
    public static function create(array $data)
    {
        $data = self::resolveMarketId($data);

        return parent::create($data);
    }

    /**
     * Added: same UUID -> id conversion for marketplace_market_id on update.
     */
    public static function update($id, array $data)
    {
        $data = self::resolveMarketId($data);

        return parent::update($id, $data);
    }

    /**
     * Converts marketplace_market_id from UUID to the internal id (null stays null).
     */
    private static function resolveMarketId(array $data): array
    {
        if (!empty($data['marketplace_market_id'])) {
            $data['marketplace_market_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Marketplace\Database\Models\Markets',
                $data['marketplace_market_id']
            );
        }

        return $data;
    }
}