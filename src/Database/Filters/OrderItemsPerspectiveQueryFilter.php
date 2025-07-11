<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
        

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class OrderItemsPerspectiveQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;
    
    public function name($value)
    {
        return $this->builder->where('name', 'ilike', '%' . $value . '%');
    }

        
    public function specialInstructions($value)
    {
        return $this->builder->where('special_instructions', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of specialInstructions
    public function special_instructions($value)
    {
        return $this->specialInstructions($value);
    }
        
    public function sku($value)
    {
        return $this->builder->where('sku', 'ilike', '%' . $value . '%');
    }

    
    public function quantity($value)
    {
        $operator = substr($value, 0, 1);

        if ($operator != '<' || $operator != '>') {
            $operator = '=';
        } else {
            $value = substr($value, 1);
        }

        return $this->builder->where('quantity', $operator, $value);
    }

    
    public function quantityInInventory($value)
    {
        $operator = substr($value, 0, 1);

        if ($operator != '<' || $operator != '>') {
            $operator = '=';
        } else {
            $value = substr($value, 1);
        }

        return $this->builder->where('quantity_in_inventory', $operator, $value);
    }

        //  This is an alias function of quantityInInventory
    public function quantity_in_inventory($value)
    {
        return $this->quantityInInventory($value);
    }
    
    public function createdAtStart($date)
    {
        return $this->builder->where('created_at', '>=', $date);
    }

    public function createdAtEnd($date)
    {
        return $this->builder->where('created_at', '<=', $date);
    }

    //  This is an alias function of createdAt
    public function created_at_start($value)
    {
        return $this->createdAtStart($value);
    }

    //  This is an alias function of createdAt
    public function created_at_end($value)
    {
        return $this->createdAtEnd($value);
    }

    public function updatedAtStart($date)
    {
        return $this->builder->where('updated_at', '>=', $date);
    }

    public function updatedAtEnd($date)
    {
        return $this->builder->where('updated_at', '<=', $date);
    }

    //  This is an alias function of updatedAt
    public function updated_at_start($value)
    {
        return $this->updatedAtStart($value);
    }

    //  This is an alias function of updatedAt
    public function updated_at_end($value)
    {
        return $this->updatedAtEnd($value);
    }

    public function deletedAtStart($date)
    {
        return $this->builder->where('deleted_at', '>=', $date);
    }

    public function deletedAtEnd($date)
    {
        return $this->builder->where('deleted_at', '<=', $date);
    }

    //  This is an alias function of deletedAt
    public function deleted_at_start($value)
    {
        return $this->deletedAtStart($value);
    }

    //  This is an alias function of deletedAt
    public function deleted_at_end($value)
    {
        return $this->deletedAtEnd($value);
    }

    public function marketplaceOrderId($value)
    {
            $marketplaceOrder = \NextDeveloper\Marketplace\Database\Models\Orders::where('uuid', $value)->first();

        if($marketplaceOrder) {
            return $this->builder->where('marketplace_order_id', '=', $marketplaceOrder->id);
        }
    }

        //  This is an alias function of marketplaceOrder
    public function marketplace_order_id($value)
    {
        return $this->marketplaceOrder($value);
    }
    
    public function marketplaceProductCatalogId($value)
    {
            $marketplaceProductCatalog = \NextDeveloper\Marketplace\Database\Models\ProductCatalogs::where('uuid', $value)->first();

        if($marketplaceProductCatalog) {
            return $this->builder->where('marketplace_product_catalog_id', '=', $marketplaceProductCatalog->id);
        }
    }

        //  This is an alias function of marketplaceProductCatalog
    public function marketplace_product_catalog_id($value)
    {
        return $this->marketplaceProductCatalog($value);
    }
    
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
