<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
                

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class OrderItemsPerspectiveQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;
    
    public function name($value)
    {
        return $this->builder->where('name', 'ilike', '%' . $value . '%');
    }

        
    public function specialInstructions($value)
    {
        return $this->builder->where('special_instructions', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of specialInstructions
    public function special_instructions($value)
    {
        return $this->specialInstructions($value);
    }
        
    public function sku($value)
    {
        return $this->builder->where('sku', 'ilike', '%' . $value . '%');
    }

        
    public function deliveryMethod($value)
    {
        return $this->builder->where('delivery_method', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of deliveryMethod
    public function delivery_method($value)
    {
        return $this->deliveryMethod($value);
    }
        
    public function productName($value)
    {
        return $this->builder->where('product_name', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of productName
    public function product_name($value)
    {
        return $this->productName($value);
    }
        
    public function providerName($value)
    {
        return $this->builder->where('provider_name', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of providerName
    public function provider_name($value)
    {
        return $this->providerName($value);
    }
        
    public function orderNumber($value)
    {
        return $this->builder->where('order_number', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of orderNumber
    public function order_number($value)
    {
        return $this->orderNumber($value);
    }
        
    public function status($value)
    {
        return $this->builder->where('status', 'ilike', '%' . $value . '%');
    }

        
    public function customerNote($value)
    {
        return $this->builder->where('customer_note', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of customerNote
    public function customer_note($value)
    {
        return $this->customerNote($value);
    }
    
    public function quantity($value)
    {
        $operator = substr($value, 0, 1);

        if ($operator != '<' || $operator != '>') {
            $operator = '=';
        } else {
            $value = substr($value, 1);
        }

        return $this->builder->where('quantity', $operator, $value);
    }

    
    public function quantityInInventory($value)
    {
        $operator = substr($value, 0, 1);

        if ($operator != '<' || $operator != '>') {
            $operator = '=';
        } else {
            $value = substr($value, 1);
        }

        return $this->builder->where('quantity_in_inventory', $operator, $value);
    }

        //  This is an alias function of quantityInInventory
    public function quantity_in_inventory($value)
    {
        return $this->quantityInInventory($value);
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

    public function orderedAtStart($date)
    {
        return $this->builder->where('ordered_at', '>=', $date);
    }

    public function orderedAtEnd($date)
    {
        return $this->builder->where('ordered_at', '<=', $date);
    }

    //  This is an alias function of orderedAt
    public function ordered_at_start($value)
    {
        return $this->orderedAtStart($value);
    }

    //  This is an alias function of orderedAt
    public function ordered_at_end($value)
    {
        return $this->orderedAtEnd($value);
    }

    public function acceptedAtStart($date)
    {
        return $this->builder->where('accepted_at', '>=', $date);
    }

    public function acceptedAtEnd($date)
    {
        return $this->builder->where('accepted_at', '<=', $date);
    }

    //  This is an alias function of acceptedAt
    public function accepted_at_start($value)
    {
        return $this->acceptedAtStart($value);
    }

    //  This is an alias function of acceptedAt
    public function accepted_at_end($value)
    {
        return $this->acceptedAtEnd($value);
    }

    public function marketplaceOrderId($value)
    {
            $marketplaceOrder = \NextDeveloper\Marketplace\Database\Models\Orders::where('uuid', $value)->first();

        if($marketplaceOrder) {
            return $this->builder->where('marketplace_order_id', '=', $marketplaceOrder->id);
        }
    }

        //  This is an alias function of marketplaceOrder
    public function marketplace_order_id($value)
    {
        return $this->marketplaceOrder($value);
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
