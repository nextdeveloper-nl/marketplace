<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
        

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class OrderItemsQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;
    
    public function specialInstructions($value)
    {
        return $this->builder->where('special_instructions', 'like', '%' . $value . '%');
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

    public function createdAtStart($date)
    {
        return $this->builder->where('created_at', '>=', $date);
    }

    public function createdAtEnd($date)
    {
        return $this->builder->where('created_at', '<=', $date);
    }

    public function updatedAtStart($date)
    {
        return $this->builder->where('updated_at', '>=', $date);
    }

    public function updatedAtEnd($date)
    {
        return $this->builder->where('updated_at', '<=', $date);
    }

    public function deletedAtStart($date)
    {
        return $this->builder->where('deleted_at', '>=', $date);
    }

    public function deletedAtEnd($date)
    {
        return $this->builder->where('deleted_at', '<=', $date);
    }

    public function marketplaceOrderId($value)
    {
            $marketplaceOrder = \NextDeveloper\Marketplace\Database\Models\Orders::where('uuid', $value)->first();

        if($marketplaceOrder) {
            return $this->builder->where('marketplace_order_id', '=', $marketplaceOrder->id);
        }
    }

    public function marketplaceProductCatalogId($value)
    {
            $marketplaceProductCatalog = \NextDeveloper\Marketplace\Database\Models\ProductCatalogs::where('uuid', $value)->first();

        if($marketplaceProductCatalog) {
            return $this->builder->where('marketplace_product_catalog_id', '=', $marketplaceProductCatalog->id);
        }
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE



}
