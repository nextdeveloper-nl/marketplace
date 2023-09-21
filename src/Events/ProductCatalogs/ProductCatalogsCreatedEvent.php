<?php

namespace NextDeveloper\Marketplace\Events\ProductCatalogs;

use Illuminate\Queue\SerializesModels;
use NextDeveloper\Marketplace\Database\Models\ProductCatalogs;

/**
 * Class ProductCatalogsCreatedEvent
 *
 * @package NextDeveloper\Marketplace\Events
 */
class ProductCatalogsCreatedEvent
{
    use SerializesModels;

    /**
     * @var ProductCatalogs
     */
    public $_model;

    /**
     * @var int|null
     */
    protected $timestamp = null;

    public function __construct(ProductCatalogs $model = null)
    {
        $this->_model = $model;
    }

    /**
     * @param int $value
     *
     * @return AbstractEvent
     */
    public function setTimestamp($value)
    {
        $this->timestamp = $value;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}