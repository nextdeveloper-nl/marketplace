<?php

namespace NextDeveloper\Marketplace\Product;

use NextDeveloper\Marketplace\Common\Services;

class ProductHighlight extends Services
{
    public function __construct()
    {
        $this->slug = 'marketplace-as-a-service';

        $this->name = 'Marketplace Service';
        $this->description = 'Marketplace as a service';

        parent::__construct();
    }
}
