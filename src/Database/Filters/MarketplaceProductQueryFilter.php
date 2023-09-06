<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
use NextDeveloper\Accounts\Database\Models\User;
    

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class MarketplaceProductQueryFilter extends AbstractQueryFilter
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
    
    public function highlights($value)
    {
        return $this->builder->where('highlights', 'like', '%' . $value . '%');
    }
    
    public function afterSalesIntroduction($value)
    {
        return $this->builder->where('after_sales_introduction', 'like', '%' . $value . '%');
    }
    
    public function supportContent($value)
    {
        return $this->builder->where('support_content', 'like', '%' . $value . '%');
    }
    
    public function refundPolicy($value)
    {
        return $this->builder->where('refund_policy', 'like', '%' . $value . '%');
    }
    
    public function eula($value)
    {
        return $this->builder->where('eula', 'like', '%' . $value . '%');
    }
    
    public function slug($value)
    {
        return $this->builder->where('slug', 'like', '%' . $value . '%');
    }
    
    public function version($value)
    {
        return $this->builder->where('version', 'like', '%' . $value . '%');
    }
    
    public function managementClass($value)
    {
        return $this->builder->where('management_class', 'like', '%' . $value . '%');
    }

    public function discountRate($value)
    {
        $operator = substr($value, 0, 1);

        if ($operator != '<' || $operator != '>') {
           $operator = '=';
        } else {
            $value = substr($value, 1);
        }

        return $this->builder->where('discount_rate', $operator, $value);
    }
    
    public function isMaintenance()
    {
        return $this->builder->where('is_maintenance', true);
    }
    
    public function isPublic()
    {
        return $this->builder->where('is_public', true);
    }
    
    public function isInvisible()
    {
        return $this->builder->where('is_invisible', true);
    }
    
    public function isActive()
    {
        return $this->builder->where('is_active', true);
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

    public function commonCategoryId($value)
    {
        $commonCategory = CommonCategory::where('uuid', $value)->first();

        if($commonCategory) {
            return $this->builder->where('common_category_id', '=', $commonCategory->id);
        }
    }

    public function commonCountryId($value)
    {
        $commonCountry = CommonCountry::where('uuid', $value)->first();

        if($commonCountry) {
            return $this->builder->where('common_country_id', '=', $commonCountry->id);
        }
    }

    public function commonLanguageId($value)
    {
        $commonLanguage = CommonLanguage::where('uuid', $value)->first();

        if($commonLanguage) {
            return $this->builder->where('common_language_id', '=', $commonLanguage->id);
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

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE\n\n\n\n
}