<?php

namespace NextDeveloper\Marketplace\Events\MarketplaceSubscription;

use Illuminate\Queue\SerializesModels;
use NextDeveloper\Marketplace\Database\Models\MarketplaceSubscription;

/**
 * Class MarketplaceSubscriptionUpdatingEvent
 * @package NextDeveloper\Marketplace\Events
 */
class MarketplaceSubscriptionUpdatingEvent
{
    use SerializesModels;

    /**
     * @var MarketplaceSubscription
     */
    public $_model;

    /**
     * @var int|null
     */
    protected $timestamp = null;

    public function __construct(MarketplaceSubscription $model = null) {
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