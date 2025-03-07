<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
            

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class SubscriptionsQueryFilter extends AbstractQueryFilter
{
    /**
     * Filter by tags
     *
     * @param  $values
     * @return Builder
     */
    public function tags($values)
    {
        $tags = explode(',', $values);

        $search = '';

        for($i = 0; $i < count($tags); $i++) {
            $search .= "'" . trim($tags[$i]) . "',";
        }

        $search = substr($search, 0, -1);

        return $this->builder->whereRaw('tags @> ARRAY[' . $search . ']');
    }

    /**
     * @var Builder
     */
    protected $builder;

    public function isValid($value)
    {
        return $this->builder->where('is_valid', $value);
    }

        //  This is an alias function of isValid
    public function is_valid($value)
    {
        return $this->isValid($value);
    }
     
    public function subscriptionStartsAtStart($date)
    {
        return $this->builder->where('subscription_starts_at', '>=', $date);
    }

    public function subscriptionStartsAtEnd($date)
    {
        return $this->builder->where('subscription_starts_at', '<=', $date);
    }

    //  This is an alias function of subscriptionStartsAt
    public function subscription_starts_at_start($value)
    {
        return $this->subscriptionStartsAtStart($value);
    }

    //  This is an alias function of subscriptionStartsAt
    public function subscription_starts_at_end($value)
    {
        return $this->subscriptionStartsAtEnd($value);
    }

    public function subscriptionEndsAtStart($date)
    {
        return $this->builder->where('subscription_ends_at', '>=', $date);
    }

    public function subscriptionEndsAtEnd($date)
    {
        return $this->builder->where('subscription_ends_at', '<=', $date);
    }

    //  This is an alias function of subscriptionEndsAt
    public function subscription_ends_at_start($value)
    {
        return $this->subscriptionEndsAtStart($value);
    }

    //  This is an alias function of subscriptionEndsAt
    public function subscription_ends_at_end($value)
    {
        return $this->subscriptionEndsAtEnd($value);
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
    
    public function iamAccountId($value)
    {
            $iamAccount = \NextDeveloper\IAM\Database\Models\Accounts::where('uuid', $value)->first();

        if($iamAccount) {
            return $this->builder->where('iam_account_id', '=', $iamAccount->id);
        }
    }

    
    public function iamUserId($value)
    {
            $iamUser = \NextDeveloper\IAM\Database\Models\Users::where('uuid', $value)->first();

        if($iamUser) {
            return $this->builder->where('iam_user_id', '=', $iamUser->id);
        }
    }

    
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE





























}
