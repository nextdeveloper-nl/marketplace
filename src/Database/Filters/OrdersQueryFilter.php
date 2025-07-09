<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
                    

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class OrdersQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;
    
    public function externalOrderId($value)
    {
        return $this->builder->where('external_order_id', 'like', '%' . $value . '%');
    }
    
    public function externalOrderNumber($value)
    {
        return $this->builder->where('external_order_number', 'like', '%' . $value . '%');
    }
    
    public function status($value)
    {
        return $this->builder->where('status', 'like', '%' . $value . '%');
    }
    
    public function orderType($value)
    {
        return $this->builder->where('order_type', 'like', '%' . $value . '%');
    }
    
    public function deliveryMethod($value)
    {
        return $this->builder->where('delivery_method', 'like', '%' . $value . '%');
    }
    
    public function syncErrorMessage($value)
    {
        return $this->builder->where('sync_error_message', 'like', '%' . $value . '%');
    }
    
    public function customerNote($value)
    {
        return $this->builder->where('customer_note', 'like', '%' . $value . '%');
    }
    
    public function externalLineId($value)
    {
        return $this->builder->where('external_line_id', 'like', '%' . $value . '%');
    }

    public function orderedAtStart($date)
    {
        return $this->builder->where('ordered_at', '>=', $date);
    }

    public function orderedAtEnd($date)
    {
        return $this->builder->where('ordered_at', '<=', $date);
    }

    public function acceptedAtStart($date)
    {
        return $this->builder->where('accepted_at', '>=', $date);
    }

    public function acceptedAtEnd($date)
    {
        return $this->builder->where('accepted_at', '<=', $date);
    }

    public function preparedAtStart($date)
    {
        return $this->builder->where('prepared_at', '>=', $date);
    }

    public function preparedAtEnd($date)
    {
        return $this->builder->where('prepared_at', '<=', $date);
    }

    public function dispatchedAtStart($date)
    {
        return $this->builder->where('dispatched_at', '>=', $date);
    }

    public function dispatchedAtEnd($date)
    {
        return $this->builder->where('dispatched_at', '<=', $date);
    }

    public function deliveredAtStart($date)
    {
        return $this->builder->where('delivered_at', '>=', $date);
    }

    public function deliveredAtEnd($date)
    {
        return $this->builder->where('delivered_at', '<=', $date);
    }

    public function cancelledAtStart($date)
    {
        return $this->builder->where('cancelled_at', '>=', $date);
    }

    public function cancelledAtEnd($date)
    {
        return $this->builder->where('cancelled_at', '<=', $date);
    }

    public function estimatedDeliveryTimeStart($date)
    {
        return $this->builder->where('estimated_delivery_time', '>=', $date);
    }

    public function estimatedDeliveryTimeEnd($date)
    {
        return $this->builder->where('estimated_delivery_time', '<=', $date);
    }

    public function lastSyncedAtStart($date)
    {
        return $this->builder->where('last_synced_at', '>=', $date);
    }

    public function lastSyncedAtEnd($date)
    {
        return $this->builder->where('last_synced_at', '<=', $date);
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

    public function marketplaceMarketId($value)
    {
            $marketplaceMarket = \NextDeveloper\Marketplace\Database\Models\Markets::where('uuid', $value)->first();

        if($marketplaceMarket) {
            return $this->builder->where('marketplace_market_id', '=', $marketplaceMarket->id);
        }
    }

    public function marketplaceProviderId($value)
    {
            $marketplaceProvider = \NextDeveloper\Marketplace\Database\Models\Providers::where('uuid', $value)->first();

        if($marketplaceProvider) {
            return $this->builder->where('marketplace_provider_id', '=', $marketplaceProvider->id);
        }
    }

    public function marketplaceProductId($value)
    {
            $marketplaceProduct = \NextDeveloper\Marketplace\Database\Models\Products::where('uuid', $value)->first();

        if($marketplaceProduct) {
            return $this->builder->where('marketplace_product_id', '=', $marketplaceProduct->id);
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
