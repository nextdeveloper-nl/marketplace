<?php

namespace NextDeveloper\Marketplace\Http\Transformers\AbstractTransformers;

use NextDeveloper\Commons\Database\Models\Addresses;
use NextDeveloper\Commons\Database\Models\Comments;
use NextDeveloper\Commons\Database\Models\Meta;
use NextDeveloper\Commons\Database\Models\PhoneNumbers;
use NextDeveloper\Commons\Database\Models\SocialMedia;
use NextDeveloper\Commons\Database\Models\Votes;
use NextDeveloper\Commons\Database\Models\Media;
use NextDeveloper\Commons\Http\Transformers\MediaTransformer;
use NextDeveloper\Commons\Database\Models\AvailableActions;
use NextDeveloper\Commons\Http\Transformers\AvailableActionsTransformer;
use NextDeveloper\Commons\Database\Models\States;
use NextDeveloper\Commons\Http\Transformers\StatesTransformer;
use NextDeveloper\Commons\Http\Transformers\CommentsTransformer;
use NextDeveloper\Commons\Http\Transformers\SocialMediaTransformer;
use NextDeveloper\Commons\Http\Transformers\MetaTransformer;
use NextDeveloper\Commons\Http\Transformers\VotesTransformer;
use NextDeveloper\Commons\Http\Transformers\AddressesTransformer;
use NextDeveloper\Commons\Http\Transformers\PhoneNumbersTransformer;
use NextDeveloper\Marketplace\Database\Models\Products;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;

/**
 * Class ProductsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class AbstractProductsTransformer extends AbstractTransformer
{

    /**
     * @var array
     */
    protected array $availableIncludes = [
        'states',
        'actions',
        'media',
        'comments',
        'votes',
        'socialMedia',
        'phoneNumbers',
        'addresses',
        'meta'
    ];

    /**
     * @param Products $model
     *
     * @return array
     */
    public function transform(Products $model)
    {
                                                $commonCategoryId = \NextDeveloper\Commons\Database\Models\Categories::where('id', $model->common_category_id)->first();
                                                            $iamAccountId = \NextDeveloper\IAM\Database\Models\Accounts::where('id', $model->iam_account_id)->first();
                                                            $iamUserId = \NextDeveloper\IAM\Database\Models\Users::where('id', $model->iam_user_id)->first();
                                                            $marketplaceMarketId = \NextDeveloper\Marketplace\Database\Models\Markets::where('id', $model->marketplace_market_id)->first();
                                                            $marketplaceProviderId = \NextDeveloper\Marketplace\Database\Models\Providers::where('id', $model->marketplace_provider_id)->first();
                                                            $parentMarketplaceProductId = \NextDeveloper\Marketplace\Database\Models\Products::where('id', $model->parent_marketplace_product_id)->first();
                        
        return $this->buildPayload(
            [
            'id'  =>  $model->uuid,
            'name'  =>  $model->name,
            'description'  =>  $model->description,
            'content'  =>  $model->content,
            'highlights'  =>  $model->highlights,
            'after_sales_introduction'  =>  $model->after_sales_introduction,
            'support_content'  =>  $model->support_content,
            'refund_policy'  =>  $model->refund_policy,
            'eula'  =>  $model->eula,
            'subscription_type'  =>  $model->subscription_type,
            'slug'  =>  $model->slug,
            'version'  =>  $model->version,
            'product_type'  =>  $model->product_type,
            'is_in_maintenance'  =>  $model->is_in_maintenance,
            'is_public'  =>  $model->is_public,
            'is_invisible'  =>  $model->is_invisible,
            'is_active'  =>  $model->is_active,
            'common_category_id'  =>  $commonCategoryId ? $commonCategoryId->uuid : null,
            'iam_account_id'  =>  $iamAccountId ? $iamAccountId->uuid : null,
            'iam_user_id'  =>  $iamUserId ? $iamUserId->uuid : null,
            'tags'  =>  $model->tags,
            'created_at'  =>  $model->created_at,
            'updated_at'  =>  $model->updated_at,
            'deleted_at'  =>  $model->deleted_at,
            'is_service'  =>  $model->is_service,
            'marketplace_market_id'  =>  $marketplaceMarketId ? $marketplaceMarketId->uuid : null,
            'sales_pitch'  =>  $model->sales_pitch,
            'is_approved'  =>  $model->is_approved,
            'marketplace_provider_id'  =>  $marketplaceProviderId ? $marketplaceProviderId->uuid : null,
            'payment_gateway_mappings'  =>  $model->payment_gateway_mappings,
            'is_additional_product'  =>  $model->is_additional_product,
            'parent_marketplace_product_id'  =>  $parentMarketplaceProductId ? $parentMarketplaceProductId->uuid : null,
            ]
        );
    }

    public function includeStates(Products $model)
    {
        $states = States::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($states, new StatesTransformer());
    }

    public function includeActions(Products $model)
    {
        $input = get_class($model);
        $input = str_replace('\\Database\\Models', '', $input);

        $actions = AvailableActions::withoutGlobalScope(AuthorizationScope::class)
            ->where('input', $input)
            ->get();

        return $this->collection($actions, new AvailableActionsTransformer());
    }

    public function includeMedia(Products $model)
    {
        $media = Media::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($media, new MediaTransformer());
    }

    public function includeSocialMedia(Products $model)
    {
        $socialMedia = SocialMedia::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($socialMedia, new SocialMediaTransformer());
    }

    public function includeComments(Products $model)
    {
        $comments = Comments::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($comments, new CommentsTransformer());
    }

    public function includeVotes(Products $model)
    {
        $votes = Votes::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($votes, new VotesTransformer());
    }

    public function includeMeta(Products $model)
    {
        $meta = Meta::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($meta, new MetaTransformer());
    }

    public function includePhoneNumbers(Products $model)
    {
        $phoneNumbers = PhoneNumbers::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($phoneNumbers, new PhoneNumbersTransformer());
    }

    public function includeAddresses(Products $model)
    {
        $addresses = Addresses::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($addresses, new AddressesTransformer());
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE





}
