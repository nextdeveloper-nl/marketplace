<?php

namespace NextDeveloper\Marketplace\Authorization\Roles;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NextDeveloper\CRM\Database\Models\AccountManagers;
use NextDeveloper\IAM\Authorization\Roles\AbstractRole;
use NextDeveloper\IAM\Authorization\Roles\IAuthorizationRole;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Helpers\UserHelper;

class MarketplaceAdminRole extends AbstractRole implements IAuthorizationRole
{
    public const NAME = 'marketplace-admin';

    public const LEVEL = 100;

    public const DESCRIPTION = 'Marketplace Admin';

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

    }

    public function checkPolicy($method, Model $model, Users $user) : bool
    {
        return true;
    }

    public function checkPrivileges(Users $users = null)
    {
        //return UserHelper::hasRole(self::NAME, $users);
    }

    public function getModule()
    {
        return 'marketplace';
    }

    public function allowedOperations() :array
    {
        return [
            'marketplace_markets:read',
            'marketplace_markets:update',
            'marketplace_markets:create',
            'marketplace_markets:delete',
            'marketplace_products:read',
            'marketplace_products:update',
            'marketplace_products:create',
            'marketplace_products:delete',
            'marketplace_product_catalogs:read',
            'marketplace_product_catalogs:update',
            'marketplace_product_catalogs:create',
            'marketplace_product_catalogs:delete',
            'marketplace_subscriptions:read',
            'marketplace_subscriptions:update',
            'marketplace_subscriptions:create',
            'marketplace_subscriptions:delete',
            'marketplace_providers:read',
            'marketplace_providers:update',
            'marketplace_providers:create',
            'marketplace_providers:delete',
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
}
