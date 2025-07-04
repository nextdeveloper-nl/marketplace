<?php

namespace NextDeveloper\Marketplace\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
            

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class OrderStatusHistoriesQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;
    
    public function oldStatus($value)
    {
        return $this->builder->where('old_status', 'like', '%' . $value . '%');
    }
    
    public function newStatus($value)
    {
        return $this->builder->where('new_status', 'like', '%' . $value . '%');
    }
    
    public function notes($value)
    {
        return $this->builder->where('notes', 'like', '%' . $value . '%');
    }

    public function changedAtStart($date)
    {
        return $this->builder->where('changed_at', '>=', $date);
    }

    public function changedAtEnd($date)
    {
        return $this->builder->where('changed_at', '<=', $date);
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

    public function marketplaceOrderId($value)
    {
            $marketplaceOrder = \NextDeveloper\Marketplace\Database\Models\Orders::where('uuid', $value)->first();

        if($marketplaceOrder) {
            return $this->builder->where('marketplace_order_id', '=', $marketplaceOrder->id);
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
