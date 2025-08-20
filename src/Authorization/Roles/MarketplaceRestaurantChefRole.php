<?php

namespace NextDeveloper\Marketplace\Authorization\Roles;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NextDeveloper\CRM\Database\Models\AccountManagers;
use NextDeveloper\IAM\Authorization\Roles\AbstractRole;
use NextDeveloper\IAM\Authorization\Roles\IAuthorizationRole;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;
use NextDeveloper\IAM\Helpers\UserHelper;
use NextDeveloper\Marketplace\Database\Models\Markets;

class MarketplaceRestaurantChefRole extends AbstractRole implements IAuthorizationRole
{
    public const NAME = 'marketplace-user';

    public const LEVEL = 150;

    public const DESCRIPTION = 'Marketplace user can see and subscribe to marketplace items.';

    public const DB_PREFIX = 'marketplace';

    /**
     * Applies basic member role sql for Eloquent
     *
     * @param Builder $builder
     * @param Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        //  If the request is a GET request, we will allow the user to see the public markets

        if($model->getTable() == 'marketplace_products' || $model->getTable() == 'marketplace_products_perspective') {
            $publicMarkets = Markets::withoutGlobalScope(AuthorizationScope::class)
                ->where('is_public', '=', 'true')
                ->pluck('id');

            $builder->where([
                'iam_account_id'    =>  UserHelper::currentAccount()->id,
                'iam_user_id'       =>  UserHelper::me()->id
            ])->orWhereIn('marketplace_market_id', $publicMarkets)
            ->where('is_active', true);

            return;
        }

        if($model->getTable() == 'marketplace_markets' || $model->getTable() == 'marketplace_markets_perspective') {
            $builder->where([
                'is_public' => true,
                'is_active' => true
            ])->orWhere([
                'iam_account_id' => UserHelper::currentAccount()->id,
            ]);

            return;
        }

        if($model->getTable() == 'marketplace_product_catalogs' || $model->getTable() == 'marketplace_product_catalogs_perspective') {
            $builder->where([
                'is_public' => true
            ])->orWhere([
                'iam_account_id' => UserHelper::currentAccount()->id,
            ]);

            return;
        }

        $builder->where('iam_account_id', '=', UserHelper::currentAccount()->id);
    }

    public function getModule()
    {
        return 'marketplace';
    }

    public function allowedOperations() :array
    {
        return [
            'marketplace_markets_perspective:read',
            'marketplace_order_items_perspective:read',
            'marketplace_product_catalogs_perspective:read',
            'marketplace_products_perspective:read',

            'marketplace_markets:read',
            'marketplace_markets:create',
            'marketplace_products:read',
            'marketplace_product_catalogs:read',
            'marketplace_subscriptions:read',
            'marketplace_subscriptions:update',
            'marketplace_subscriptions:create',
            'marketplace_providers:read',
            'marketplace_producers:read',
            'marketplace_product_catalogs:read',
            'marketplace_orders:read',
            'marketplace_orders:create',
            'marketplace_orders:update',
            'marketplace_orders:delete',
            'marketplace_order_items:read',
            'marketplace_order_items:create',
            'marketplace_order_items:update',
            'marketplace_order_items:delete',
        ];
    }

    public function getLevel(): int
    {
        return self::LEVEL;
    }

    public function getDescription(): string
    {
        return self::DESCRIPTION;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function canBeApplied($column)
    {
        if(self::DB_PREFIX === '*') {
            return true;
        }

        if(Str::startsWith($column, self::DB_PREFIX)) {
            return true;
        }

        return false;
    }

    public function getDbPrefix()
    {
        return self::DB_PREFIX;
    }

    public function checkRules(Users $users): bool
    {
        // TODO: Implement checkRules() method.
    }
}
