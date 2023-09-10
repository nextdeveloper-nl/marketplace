<?php

namespace NextDeveloper\Marketplace\Events\Subscriptions;

use Illuminate\Queue\SerializesModels;
use NextDeveloper\Marketplace\Database\Models\Subscriptions;

/**
 * Class SubscriptionsUpdatingEvent
 * @package NextDeveloper\Marketplace\Events
 */
class SubscriptionsUpdatingEvent
{
    use SerializesModels;

    /**
     * @var Subscriptions
     */
    public $_model;

    /**
     * @var int|null
     */
    protected $timestamp = null;

    public function __construct(Subscriptions $model = null) {
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