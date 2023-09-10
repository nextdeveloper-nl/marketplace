<?php

namespace NextDeveloper\Marketplace\Events\Subscription;

use Illuminate\Queue\SerializesModels;
use NextDeveloper\Marketplace\Database\Models\Subscription;

/**
 * Class MarketplaceSubscriptionUpdatingEvent
 * @package NextDeveloper\Marketplace\Events
 */
class MarketplaceSubscriptionUpdatingEvent
{
    use SerializesModels;

    /**
     * @var Subscription
     */
    public $_model;

    /**
     * @var int|null
     */
    protected $timestamp = null;

    public function __construct(Subscription $model = null) {
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