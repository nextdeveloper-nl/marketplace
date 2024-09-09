<?php

namespace NextDeveloper\Marketplace\Product;

use NextDeveloper\Marketplace\Common\Products;

class Marketplace extends Products
{
    public function __construct()
    {
        $this->slug = 'marketplace-as-a-service';

        $this->name = 'Marketplace Service';
        $this->description = 'Marketplace as a service';

        parent::__construct();
    }
}
