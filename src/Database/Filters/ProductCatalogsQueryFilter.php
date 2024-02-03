<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
        

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class ProductCatalogsQueryFilter extends AbstractQueryFilter
{
    /**
     * @var Builder
     */
    protected $builder;
    
    public function agreement($value)
    {
        return $this->builder->where('agreement', 'like', '%' . $value . '%');
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

    public function commonCurrencyCodeId($value)
    {
            $commonCurrencyCode = \NextDeveloper\Commons\Database\Models\CurrencyCodes::where('uuid', $value)->first();

        if($commonCurrencyCode) {
            return $this->builder->where('common_currency_code_id', '=', $commonCurrencyCode->id);
        }
    }

    public function marketplaceProductId($value)
    {
            $marketplaceProduct = \NextDeveloper\Marketplace\Database\Models\Products::where('uuid', $value)->first();

        if($marketplaceProduct) {
            return $this->builder->where('marketplace_product_id', '=', $marketplaceProduct->id);
        }
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n
}