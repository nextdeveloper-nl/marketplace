<?php

namespace NextDeveloper\Marketplace\Events\Products;

use Illuminate\Queue\SerializesModels;
use NextDeveloper\Marketplace\Database\Models\Products;

/**
 * Class ProductsRestoringEvent
 * @package NextDeveloper\Marketplace\Events
 */
class ProductsRestoringEvent
{
    use SerializesModels;

    /**
     * @var Products
     */
    public $_model;

    /**
     * @var int|null
     */
    protected $timestamp = null;

    public function __construct(Products $model = null) {
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