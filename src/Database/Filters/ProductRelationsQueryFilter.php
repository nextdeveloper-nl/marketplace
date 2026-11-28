<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
            

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class ProductRelationsQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;

    public function relationType($value)
    {
        return $this->builder->where('relation_type', '=', $value);
    }

        //  This is an alias function of relationType
    public function relation_type($value)
    {
        return $this->relationType($value);
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

        //  A product the user cannot see returns no rows instead of dropping the filter.
        if(! $marketplaceProduct) {
            return $this->builder->whereRaw('1 = 0');
        }

        return $this->builder->where('marketplace_product_id', '=', $marketplaceProduct->id);
    }

        //  This is an alias function of marketplaceProductId
    public function marketplace_product_id($value)
    {
        return $this->marketplaceProductId($value);
    }

    public function relatedMarketplaceProductId($value)
    {
            $relatedMarketplaceProduct = \NextDeveloper\Marketplace\Database\Models\Products::where('uuid', $value)->first();

        //  A product the user cannot see returns no rows instead of dropping the filter.
        if(! $relatedMarketplaceProduct) {
            return $this->builder->whereRaw('1 = 0');
        }

        return $this->builder->where('related_marketplace_product_id', '=', $relatedMarketplaceProduct->id);
    }

        //  This is an alias function of relatedMarketplaceProductId
    public function related_marketplace_product_id($value)
    {
        return $this->relatedMarketplaceProductId($value);
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
