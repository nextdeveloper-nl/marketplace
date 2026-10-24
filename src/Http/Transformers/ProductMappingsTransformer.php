<?php

namespace NextDeveloper\Marketplace\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Marketplace\Database\Models\ProductMappings;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers\AbstractProductMappingsTransformer;

/**
 * Class ProductMappingsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class ProductMappingsTransformer extends AbstractProductMappingsTransformer
{

    /**
     * @param ProductMappings $model
     *
     * @return array
     */
    public function transform(ProductMappings $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('ProductMappings', $model->uuid, 'Transformed')
        );

        if(!$transformed) {
            $transformed = parent::transform($model);

            Cache::set(
                CacheHelper::getKey('ProductMappings', $model->uuid, 'Transformed'),
                $transformed
            );
        }

        // Added: sync bookkeeping and the eBay listing link. These are merged in AFTER the cache
        // on purpose: the sync services write the mapping with quiet saves, which do not clear the
        // cached transform, so caching them would show a stale "last synced".
        return $transformed + [
            'external_listing_id' => $model->external_listing_id,
            'listing_url' => $this->listingUrl($model->external_listing_id),
            'last_synced_at' => $model->last_synced_at,
        ];
    }

    /**
     * Public eBay page of a listing, or null when the mapping has no listing id (every non-eBay
     * mapping). Sandbox listings live on the sandbox site.
     */
    private function listingUrl(?string $listingId): ?string
    {
        if (!$listingId) {
            return null;
        }

        $host = strtolower((string) config('marketplace.ebay.environment', 'production')) === 'sandbox'
            ? 'www.sandbox.ebay.com'
            : 'www.ebay.com';

        return 'https://'.$host.'/itm/'.rawurlencode($listingId);
    }
}
