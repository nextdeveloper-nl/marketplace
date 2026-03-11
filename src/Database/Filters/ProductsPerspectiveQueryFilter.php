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
    
    public function name($value)
    {
        return $this->builder->where('name', 'ilike', '%' . $value . '%');
    }

        
    public function description($value)
    {
        return $this->builder->where('description', 'ilike', '%' . $value . '%');
    }

        
    public function content($value)
    {
        return $this->builder->where('content', 'ilike', '%' . $value . '%');
    }

        
    public function slug($value)
    {
        return $this->builder->where('slug', 'ilike', '%' . $value . '%');
    }

        
    public function version($value)
    {
        return $this->builder->where('version', 'ilike', '%' . $value . '%');
    }

        
    public function salesPitch($value)
    {
        return $this->builder->where('sales_pitch', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of salesPitch
    public function sales_pitch($value)
    {
        return $this->salesPitch($value);
    }
        
    public function category($value)
    {
        return $this->builder->where('category', 'ilike', '%' . $value . '%');
    }

        
    public function marketplace($value)
    {
        return $this->builder->where('marketplace', 'ilike', '%' . $value . '%');
    }

        
    public function maintainer($value)
    {
        return $this->builder->where('maintainer', 'ilike', '%' . $value . '%');
    }

        
    public function aboutMaintainer($value)
    {
        return $this->builder->where('about_maintainer', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of aboutMaintainer
    public function about_maintainer($value)
    {
        return $this->aboutMaintainer($value);
    }
        
    public function responsible($value)
    {
        return $this->builder->where('responsible', 'ilike', '%' . $value . '%');
    }

        
    public function currencyCode($value)
    {
        return $this->builder->where('currency_code', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of currencyCode
    public function currency_code($value)
    {
        return $this->currencyCode($value);
    }
        
    public function partnerMeetingLink($value)
    {
        return $this->builder->where('partner_meeting_link', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of partnerMeetingLink
    public function partner_meeting_link($value)
    {
        return $this->partnerMeetingLink($value);
    }
        
    public function refundPolicy($value)
    {
        return $this->builder->where('refund_policy', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of refundPolicy
    public function refund_policy($value)
    {
        return $this->refundPolicy($value);
    }
        
    public function afterSalesIntroduction($value)
    {
        return $this->builder->where('after_sales_introduction', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of afterSalesIntroduction
    public function after_sales_introduction($value)
    {
        return $this->afterSalesIntroduction($value);
    }
        
    public function supportContent($value)
    {
        return $this->builder->where('support_content', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of supportContent
    public function support_content($value)
    {
        return $this->supportContent($value);
    }
        
    public function eula($value)
    {
        return $this->builder->where('eula', 'ilike', '%' . $value . '%');
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

        //  This is an alias function of productCatalogCount
    public function product_catalog_count($value)
    {
        return $this->productCatalogCount($value);
    }
    
    public function isService($value)
    {
        return $this->builder->where('is_service', $value);
    }

        //  This is an alias function of isService
    public function is_service($value)
    {
        return $this->isService($value);
    }
     
    public function isInMaintenance($value)
    {
        return $this->builder->where('is_in_maintenance', $value);
    }

        //  This is an alias function of isInMaintenance
    public function is_in_maintenance($value)
    {
        return $this->isInMaintenance($value);
    }
     
    public function isPublic($value)
    {
        return $this->builder->where('is_public', $value);
    }

        //  This is an alias function of isPublic
    public function is_public($value)
    {
        return $this->isPublic($value);
    }
     
    public function isInvisible($value)
    {
        return $this->builder->where('is_invisible', $value);
    }

        //  This is an alias function of isInvisible
    public function is_invisible($value)
    {
        return $this->isInvisible($value);
    }
     
    public function isActive($value)
    {
        return $this->builder->where('is_active', $value);
    }

        //  This is an alias function of isActive
    public function is_active($value)
    {
        return $this->isActive($value);
    }
     
    public function isApproved($value)
    {
        return $this->builder->where('is_approved', $value);
    }

        //  This is an alias function of isApproved
    public function is_approved($value)
    {
        return $this->isApproved($value);
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

    public function commonCategoryId($value)
    {
            $commonCategory = \NextDeveloper\Commons\Database\Models\Categories::where('uuid', $value)->first();

        if($commonCategory) {
            return $this->builder->where('common_category_id', '=', $commonCategory->id);
        }
    }

        //  This is an alias function of commonCategory
    public function common_category_id($value)
    {
        return $this->commonCategory($value);
    }
    
    public function marketplaceMarketId($value)
    {
            $marketplaceMarket = \NextDeveloper\Marketplace\Database\Models\Markets::where('uuid', $value)->first();

        if($marketplaceMarket) {
            return $this->builder->where('marketplace_market_id', '=', $marketplaceMarket->id);
        }
    }

        //  This is an alias function of marketplaceMarket
    public function marketplace_market_id($value)
    {
        return $this->marketplaceMarket($value);
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
