<?php

namespace NextDeveloper\Marketplace\Events\Product;

use Illuminate\Queue\SerializesModels;
use NextDeveloper\Marketplace\Database\Models\Product;

/**
 * Class MarketplaceProductUpdatedEvent
 * @package NextDeveloper\Marketplace\Events
 */
class MarketplaceProductUpdatedEvent
{
    use SerializesModels;

    /**
     * @var Product
     */
    public $_model;

    /**
     * @var int|null
     */
    protected $timestamp = null;

    public function __construct(Product $model = null) {
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