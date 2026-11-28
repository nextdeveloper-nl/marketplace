<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
            

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class StockMovementsQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;

    public function movementType($value)
    {
        return $this->builder->where('movement_type', '=', $value);
    }

        //  This is an alias function of movementType
    public function movement_type($value)
    {
        return $this->movementType($value);
    }

    public function notes($value)
    {
        return $this->builder->where('notes', 'ilike', '%' . $value . '%');
    }

    public function quantity($value)
    {
        $operator = substr($value, 0, 1);

        if ($operator != '<' && $operator != '>') {
            $operator = '=';
        } else {
            $value = substr($value, 1);
        }

        return $this->builder->where('quantity', $operator, $value);
    }

    public function movedAtStart($date)
    {
        return $this->builder->where('moved_at', '>=', $date);
    }

    public function movedAtEnd($date)
    {
        return $this->builder->where('moved_at', '<=', $date);
    }

    //  This is an alias function of movedAt
    public function moved_at_start($value)
    {
        return $this->movedAtStart($value);
    }

    //  This is an alias function of movedAt
    public function moved_at_end($value)
    {
        return $this->movedAtEnd($value);
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

        //  This is an alias function of marketplaceProductCatalogId
    public function marketplace_product_catalog_id($value)
    {
        return $this->marketplaceProductCatalogId($value);
    }

    public function crmOpportunityId($value)
    {
            $crmOpportunity = \NextDeveloper\CRM\Database\Models\Opportunities::where('uuid', $value)->first();

        if($crmOpportunity) {
            return $this->builder->where('crm_opportunity_id', '=', $crmOpportunity->id);
        }
    }

        //  This is an alias function of crmOpportunityId
    public function crm_opportunity_id($value)
    {
        return $this->crmOpportunityId($value);
    }

    public function crmQuoteId($value)
    {
            $crmQuote = \NextDeveloper\CRM\Database\Models\Quotes::where('uuid', $value)->first();

        if($crmQuote) {
            return $this->builder->where('crm_quote_id', '=', $crmQuote->id);
        }
    }

        //  This is an alias function of crmQuoteId
    public function crm_quote_id($value)
    {
        return $this->crmQuoteId($value);
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

    /**
     * Movements of every plan of a product, for the product page's stock tab.
     */
    public function marketplaceProductId($value)
    {
        $product = \NextDeveloper\Marketplace\Database\Models\Products::where('uuid', $value)->first();

        if (! $product) {
            return $this->builder->whereRaw('1 = 0');
        }

        return $this->builder->whereIn(
            'marketplace_product_catalog_id',
            \NextDeveloper\Marketplace\Database\Models\ProductCatalogs::withoutGlobalScopes()
                ->where('marketplace_product_id', $product->id)
                ->select('id')
        );
    }

    //  This is an alias function of marketplaceProductId
    public function marketplace_product_id($value)
    {
        return $this->marketplaceProductId($value);
    }









}
