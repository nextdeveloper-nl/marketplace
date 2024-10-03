<?php

namespace NextDeveloper\Marketplace\Common;

abstract class ProductCatalog
{
    //  Here name is mandatory
    public $name;

    public $price;
    public $marketplaceProduct;
    public $quantity;

    public $sku;
    public $trialData;
    public $feature;
    public $isPublic;
    public $account;
    public $user;

    public $currency;

    public function __construct(){}
}
