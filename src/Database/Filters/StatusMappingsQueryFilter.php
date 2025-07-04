<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
    

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class StatusMappingsQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;
    
    public function externalStatus($value)
    {
        return $this->builder->where('external_status', 'like', '%' . $value . '%');
    }
    
    public function normalizedStatus($value)
    {
        return $this->builder->where('normalized_status', 'like', '%' . $value . '%');
    }
    
    public function description($value)
    {
        return $this->builder->where('description', 'like', '%' . $value . '%');
    }

    public function createdAtStart($date)
    {
        return $this->builder->where('created_at', '>=', $date);
    }

    public function createdAtEnd($date)
    {
        return $this->builder->where('created_at', '<=', $date);
    }

    public function marketplaceProviderId($value)
    {
            $marketplaceProvider = \NextDeveloper\Marketplace\Database\Models\Providers::where('uuid', $value)->first();

        if($marketplaceProvider) {
            return $this->builder->where('marketplace_provider_id', '=', $marketplaceProvider->id);
        }
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
