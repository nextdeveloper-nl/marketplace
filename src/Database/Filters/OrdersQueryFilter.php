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
    
    public function externalOrderId($value)
    {
        return $this->builder->where('external_order_id', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of externalOrderId
    public function external_order_id($value)
    {
        return $this->externalOrderId($value);
    }
        
    public function externalOrderNumber($value)
    {
        return $this->builder->where('external_order_number', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of externalOrderNumber
    public function external_order_number($value)
    {
        return $this->externalOrderNumber($value);
    }
        
    public function status($value)
    {
        return $this->builder->where('status', 'ilike', '%' . $value . '%');
    }

        
    public function orderType($value)
    {
        return $this->builder->where('order_type', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of orderType
    public function order_type($value)
    {
        return $this->orderType($value);
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
        
    public function syncErrorMessage($value)
    {
        return $this->builder->where('sync_error_message', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of syncErrorMessage
    public function sync_error_message($value)
    {
        return $this->syncErrorMessage($value);
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
        
    public function externalLineId($value)
    {
        return $this->builder->where('external_line_id', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of externalLineId
    public function external_line_id($value)
    {
        return $this->externalLineId($value);
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

    public function preparedAtStart($date)
    {
        return $this->builder->where('prepared_at', '>=', $date);
    }

    public function preparedAtEnd($date)
    {
        return $this->builder->where('prepared_at', '<=', $date);
    }

    //  This is an alias function of preparedAt
    public function prepared_at_start($value)
    {
        return $this->preparedAtStart($value);
    }

    //  This is an alias function of preparedAt
    public function prepared_at_end($value)
    {
        return $this->preparedAtEnd($value);
    }

    public function dispatchedAtStart($date)
    {
        return $this->builder->where('dispatched_at', '>=', $date);
    }

    public function dispatchedAtEnd($date)
    {
        return $this->builder->where('dispatched_at', '<=', $date);
    }

    //  This is an alias function of dispatchedAt
    public function dispatched_at_start($value)
    {
        return $this->dispatchedAtStart($value);
    }

    //  This is an alias function of dispatchedAt
    public function dispatched_at_end($value)
    {
        return $this->dispatchedAtEnd($value);
    }

    public function deliveredAtStart($date)
    {
        return $this->builder->where('delivered_at', '>=', $date);
    }

    public function deliveredAtEnd($date)
    {
        return $this->builder->where('delivered_at', '<=', $date);
    }

    //  This is an alias function of deliveredAt
    public function delivered_at_start($value)
    {
        return $this->deliveredAtStart($value);
    }

    //  This is an alias function of deliveredAt
    public function delivered_at_end($value)
    {
        return $this->deliveredAtEnd($value);
    }

    public function cancelledAtStart($date)
    {
        return $this->builder->where('cancelled_at', '>=', $date);
    }

    public function cancelledAtEnd($date)
    {
        return $this->builder->where('cancelled_at', '<=', $date);
    }

    //  This is an alias function of cancelledAt
    public function cancelled_at_start($value)
    {
        return $this->cancelledAtStart($value);
    }

    //  This is an alias function of cancelledAt
    public function cancelled_at_end($value)
    {
        return $this->cancelledAtEnd($value);
    }

    public function estimatedDeliveryTimeStart($date)
    {
        return $this->builder->where('estimated_delivery_time', '>=', $date);
    }

    public function estimatedDeliveryTimeEnd($date)
    {
        return $this->builder->where('estimated_delivery_time', '<=', $date);
    }

    //  This is an alias function of estimatedDeliveryTime
    public function estimated_delivery_time_start($value)
    {
        return $this->estimatedDeliveryTimeStart($value);
    }

    //  This is an alias function of estimatedDeliveryTime
    public function estimated_delivery_time_end($value)
    {
        return $this->estimatedDeliveryTimeEnd($value);
    }

    public function lastSyncedAtStart($date)
    {
        return $this->builder->where('last_synced_at', '>=', $date);
    }

    public function lastSyncedAtEnd($date)
    {
        return $this->builder->where('last_synced_at', '<=', $date);
    }

    //  This is an alias function of lastSyncedAt
    public function last_synced_at_start($value)
    {
        return $this->lastSyncedAtStart($value);
    }

    //  This is an alias function of lastSyncedAt
    public function last_synced_at_end($value)
    {
        return $this->lastSyncedAtEnd($value);
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
    
    public function marketplaceProviderId($value)
    {
            $marketplaceProvider = \NextDeveloper\Marketplace\Database\Models\Providers::where('uuid', $value)->first();

        if($marketplaceProvider) {
            return $this->builder->where('marketplace_provider_id', '=', $marketplaceProvider->id);
        }
    }

        //  This is an alias function of marketplaceProvider
    public function marketplace_provider_id($value)
    {
        return $this->marketplaceProvider($value);
    }
    
    public function marketplaceProductId($value)
    {
            $marketplaceProduct = \NextDeveloper\Marketplace\Database\Models\Products::where('uuid', $value)->first();

        if($marketplaceProduct) {
            return $this->builder->where('marketplace_product_id', '=', $marketplaceProduct->id);
        }
    }

        //  This is an alias function of marketplaceProduct
    public function marketplace_product_id($value)
    {
        return $this->marketplaceProduct($value);
    }
    
    public function externalOrderId($value)
    {
            $externalOrder = \NextDeveloper\\Database\Models\ExternalOrders::where('uuid', $value)->first();

        if($externalOrder) {
            return $this->builder->where('external_order_id', '=', $externalOrder->id);
        }
    }

        //  This is an alias function of externalOrder
    public function external_order_id($value)
    {
        return $this->externalOrder($value);
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

    
    public function externalLineId($value)
    {
            $externalLine = \NextDeveloper\\Database\Models\ExternalLines::where('uuid', $value)->first();

        if($externalLine) {
            return $this->builder->where('external_line_id', '=', $externalLine->id);
        }
    }

        //  This is an alias function of externalLine
    public function external_line_id($value)
    {
        return $this->externalLine($value);
    }
    
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE






}
