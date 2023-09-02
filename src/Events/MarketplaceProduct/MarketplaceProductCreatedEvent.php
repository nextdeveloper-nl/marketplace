<?php

namespace NextDeveloper\Marketplace\Events\MarketplaceProduct;

use Illuminate\Queue\SerializesModels;
use NextDeveloper\Marketplace\Database\Models\MarketplaceProduct;

/**
 * Class MarketplaceProductCreatedEvent
 * @package NextDeveloper\Marketplace\Events
 */
class MarketplaceProductCreatedEvent
{
    use SerializesModels;

    /**
     * @var MarketplaceProduct
     */
    public $_model;

    /**
     * @var int|null
     */
    protected $timestamp = null;

    public function __construct(MarketplaceProduct $model = null) {
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