<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class ProviderSyncStatesQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;

    public function entityType($value)
    {
        return $this->builder->where('entity_type', 'ilike', '%' . $value . '%');
    }

    //  This is an alias function of entityType
    public function entity_type($value)
    {
        return $this->entityType($value);
    }

    public function lastError($value)
    {
        return $this->builder->where('last_error', 'ilike', '%' . $value . '%');
    }

    //  This is an alias function of lastError
    public function last_error($value)
    {
        return $this->lastError($value);
    }

    public function consecutiveFailures($value)
    {
        return $this->builder->where('consecutive_failures', '=', $value);
    }

    //  This is an alias function of consecutiveFailures
    public function consecutive_failures($value)
    {
        return $this->consecutiveFailures($value);
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

    public function lastRunAtStart($date)
    {
        return $this->builder->where('last_run_at', '>=', $date);
    }

    public function lastRunAtEnd($date)
    {
        return $this->builder->where('last_run_at', '<=', $date);
    }

    //  This is an alias function of lastRunAt
    public function last_run_at_start($value)
    {
        return $this->lastRunAtStart($value);
    }

    //  This is an alias function of lastRunAt
    public function last_run_at_end($value)
    {
        return $this->lastRunAtEnd($value);
    }

    public function marketplaceProviderId($value)
    {
        $marketplaceProvider = \NextDeveloper\Marketplace\Database\Models\Providers::where('uuid', $value)->first();

        if($marketplaceProvider) {
            return $this->builder->where('marketplace_provider_id', '=', $marketplaceProvider->id);
        }
    }

    //  This is an alias function of marketplaceProviderId
    public function marketplace_provider_id($value)
    {
        return $this->marketplaceProviderId($value);
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
