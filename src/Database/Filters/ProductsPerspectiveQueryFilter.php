<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
                

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class ProductsPerspectiveQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;
    
    public function name($value)
    {
        return $this->builder->where('name', 'like', '%' . $value . '%');
    }
    
    public function description($value)
    {
        return $this->builder->where('description', 'like', '%' . $value . '%');
    }
    
    public function content($value)
    {
        return $this->builder->where('content', 'like', '%' . $value . '%');
    }
    
    public function slug($value)
    {
        return $this->builder->where('slug', 'like', '%' . $value . '%');
    }
    
    public function version($value)
    {
        return $this->builder->where('version', 'like', '%' . $value . '%');
    }
    
    public function category($value)
    {
        return $this->builder->where('category', 'like', '%' . $value . '%');
    }
    
    public function marketplace($value)
    {
        return $this->builder->where('marketplace', 'like', '%' . $value . '%');
    }
    
    public function maintainer($value)
    {
        return $this->builder->where('maintainer', 'like', '%' . $value . '%');
    }
    
    public function responsible($value)
    {
        return $this->builder->where('responsible', 'like', '%' . $value . '%');
    }

    public function productCatalogCount($value)
    {
        $operator = substr($value, 0, 1);

        if ($operator != '<' || $operator != '>') {
            $operator = '=';
        } else {
            $value = substr($value, 1);
        }

        return $this->builder->where('product_catalog_count', $operator, $value);
    }

    public function isService($value)
    {
        if(!is_bool($value)) {
            $value = false;
        }

        return $this->builder->where('is_service', $value);
    }

    public function isInMaintenance($value)
    {
        if(!is_bool($value)) {
            $value = false;
        }

        return $this->builder->where('is_in_maintenance', $value);
    }

    public function isPublic($value)
    {
        if(!is_bool($value)) {
            $value = false;
        }

        return $this->builder->where('is_public', $value);
    }

    public function isInvisible($value)
    {
        if(!is_bool($value)) {
            $value = false;
        }

        return $this->builder->where('is_invisible', $value);
    }

    public function isActive($value)
    {
        if(!is_bool($value)) {
            $value = false;
        }

        return $this->builder->where('is_active', $value);
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

    public function commonCategoryId($value)
    {
            $commonCategory = \NextDeveloper\Commons\Database\Models\Categories::where('uuid', $value)->first();

        if($commonCategory) {
            return $this->builder->where('common_category_id', '=', $commonCategory->id);
        }
    }

    public function marketplaceMarketId($value)
    {
            $marketplaceMarket = \NextDeveloper\Marketplace\Database\Models\Markets::where('uuid', $value)->first();

        if($marketplaceMarket) {
            return $this->builder->where('marketplace_market_id', '=', $marketplaceMarket->id);
        }
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
