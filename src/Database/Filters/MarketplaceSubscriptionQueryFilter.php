<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
use NextDeveloper\Accounts\Database\Models\User;
    

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class MarketplaceSubscriptionQueryFilter extends AbstractQueryFilter
{
    /**
    * @var Builder
    */
    protected $builder;

    public function isValid()
    {
        return $this->builder->where('is_valid', true);
    }
    
    public function subscriptionStartsAtStart($date) 
    {
        return $this->builder->where( 'subscription_starts_at', '>=', $date );
    }

    public function subscriptionStartsAtEnd($date) 
    {
        return $this->builder->where( 'subscription_starts_at', '<=', $date );
    }

    public function subscriptionEndsAtStart($date) 
    {
        return $this->builder->where( 'subscription_ends_at', '>=', $date );
    }

    public function subscriptionEndsAtEnd($date) 
    {
        return $this->builder->where( 'subscription_ends_at', '<=', $date );
    }

    public function createdAtStart($date) 
    {
        return $this->builder->where( 'created_at', '>=', $date );
    }

    public function createdAtEnd($date) 
    {
        return $this->builder->where( 'created_at', '<=', $date );
    }

    public function updatedAtStart($date) 
    {
        return $this->builder->where( 'updated_at', '>=', $date );
    }

    public function updatedAtEnd($date) 
    {
        return $this->builder->where( 'updated_at', '<=', $date );
    }

    public function deletedAtStart($date) 
    {
        return $this->builder->where( 'deleted_at', '>=', $date );
    }

    public function deletedAtEnd($date) 
    {
        return $this->builder->where( 'deleted_at', '<=', $date );
    }

    public function marketplaceProductCatalogId($value)
    {
        $marketplaceProductCatalog = MarketplaceProductCatalog::where('uuid', $value)->first();

        if($marketplaceProductCatalog) {
            return $this->builder->where('marketplace_product_catalog_id', '=', $marketplaceProductCatalog->id);
        }
    }

    public function iamAccountId($value)
    {
        $iamAccount = IamAccount::where('uuid', $value)->first();

        if($iamAccount) {
            return $this->builder->where('iam_account_id', '=', $iamAccount->id);
        }
    }

    public function iamUserId($value)
    {
        $iamUser = IamUser::where('uuid', $value)->first();

        if($iamUser) {
            return $this->builder->where('iam_user_id', '=', $iamUser->id);
        }
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n
}