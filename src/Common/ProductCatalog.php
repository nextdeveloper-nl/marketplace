<?php

namespace NextDeveloper\Marketplace\Common;

abstract class ProductCatalog
{
    //  This is mandatory
    public $slug;

    public $name;
    public $description;
    //...

    //  Default true
    public $isInvisible = true;

    private $catalog = [];

    public function __construct(){}
}
