<?php

namespace NextDeveloper\Marketplace\Services;

use NextDeveloper\Commons\Helpers\DatabaseHelper;
use NextDeveloper\Marketplace\Services\AbstractServices\AbstractProductCatalogsService;
use Override;

/**
 * This class is responsible from managing the data for ProductCatalogs
 *
 * Class ProductCatalogsService.
 *
 * @package NextDeveloper\Marketplace\Database\Models
 */
class ProductCatalogsService extends AbstractProductCatalogsService
{

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    #[Override]
    public static function update($id, array $data)
    {
        if (array_key_exists('common_currency_id', $data)) {
            $data['common_currency_id'] = DatabaseHelper::uuidToId(
                '\NextDeveloper\Commons\Database\Models\Currencies',
                $data['common_currency_id']
            );
        }

        return parent::update($id, $data);
    }
}
