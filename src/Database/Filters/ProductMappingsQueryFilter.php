<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
            

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class ProductMappingsQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;
    
    public function externalProductId($value)
    {
        return $this->builder->where('external_product_id', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of externalProductId
    public function external_product_id($value)
    {
        return $this->externalProductId($value);
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

    public function marketplaceProductId($value)
    {
            $marketplaceProduct = \NextDeveloper\Marketplace\Database\Models\Products::where('uuid', $value)->first();

        if($marketplaceProduct) {
            return $this->builder->where('marketplace_product_id', '=', $marketplaceProduct->id);
        }
    }

        //  This is an alias function of marketplaceProduct
    public function marketplace_product_id($value)
    {
        return $this->marketplaceProduct($value);
    }
    
    public function marketplaceProviderId($value)
    {
            $marketplaceProvider = \NextDeveloper\Marketplace\Database\Models\Providers::where('uuid', $value)->first();

        if($marketplaceProvider) {
            return $this->builder->where('marketplace_provider_id', '=', $marketplaceProvider->id);
        }
    }

        //  This is an alias function of marketplaceProvider
    public function marketplace_provider_id($value)
    {
        return $this->marketplaceProvider($value);
    }
    
    public function externalProductId($value)
    {
            $externalProduct = \NextDeveloper\\Database\Models\ExternalProducts::where('uuid', $value)->first();

        if($externalProduct) {
            return $this->builder->where('external_product_id', '=', $externalProduct->id);
        }
    }

        //  This is an alias function of externalProduct
    public function external_product_id($value)
    {
        return $this->externalProduct($value);
    }
    
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE






}
