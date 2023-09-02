<?php

namespace NextDeveloper\Marketplace\Events\MarketplaceProductCatalog;

use Illuminate\Queue\SerializesModels;
use NextDeveloper\Marketplace\Database\Models\MarketplaceProductCatalog;

/**
 * Class MarketplaceProductCatalogDeletingEvent
 * @package NextDeveloper\Marketplace\Events
 */
class MarketplaceProductCatalogDeletingEvent
{
    use SerializesModels;

    /**
     * @var MarketplaceProductCatalog
     */
    public $_model;

    /**
     * @var int|null
     */
    protected $timestamp = null;

    public function __construct(MarketplaceProductCatalog $model = null) {
        $this->_model = $model;
    }

    /**
    * @param int $value
    *
    * @return AbstractEvent
    */
    public function setTimestamp($value) {
        $this->timestamp = $value;

        return $this;
    }

    /**
    * @return int|null
    */
    public function getTimestamp() {
        return $this->timestamp;
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}