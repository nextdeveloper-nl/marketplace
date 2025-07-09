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
use NextDeveloper\Marketplace\Database\Models\ProductsPerspective;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;

/**
 * Class ProductsPerspectiveTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class AbstractProductsPerspectiveTransformer extends AbstractTransformer
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
     * @param ProductsPerspective $model
     *
     * @return array
     */
    public function transform(ProductsPerspective $model)
    {
                                                $commonCategoryId = \NextDeveloper\Commons\Database\Models\Categories::where('id', $model->common_category_id)->first();
                                                            $marketplaceMarketId = \NextDeveloper\Marketplace\Database\Models\Markets::where('id', $model->marketplace_market_id)->first();
                                                            $iamAccountId = \NextDeveloper\IAM\Database\Models\Accounts::where('id', $model->iam_account_id)->first();
                                                            $iamUserId = \NextDeveloper\IAM\Database\Models\Users::where('id', $model->iam_user_id)->first();
                        
        return $this->buildPayload(
            [
            'id'  =>  $model->uuid,
            'name'  =>  $model->name,
            'description'  =>  $model->description,
            'content'  =>  $model->content,
            'highlights'  =>  $model->highlights,
            'subscription_type'  =>  $model->subscription_type,
            'slug'  =>  $model->slug,
            'version'  =>  $model->version,
            'sales_pitch'  =>  $model->sales_pitch,
            'is_service'  =>  $model->is_service,
            'is_in_maintenance'  =>  $model->is_in_maintenance,
            'is_public'  =>  $model->is_public,
            'is_invisible'  =>  $model->is_invisible,
            'is_active'  =>  $model->is_active,
            'is_approved'  =>  $model->is_approved,
            'category'  =>  $model->category,
            'common_category_id'  =>  $commonCategoryId ? $commonCategoryId->uuid : null,
            'marketplace'  =>  $model->marketplace,
            'marketplace_market_id'  =>  $marketplaceMarketId ? $marketplaceMarketId->uuid : null,
            'maintainer'  =>  $model->maintainer,
            'about_maintainer'  =>  $model->about_maintainer,
            'responsible'  =>  $model->responsible,
            'product_catalog_count'  =>  $model->product_catalog_count,
            'has_free_trial'  =>  $model->has_free_trial,
            'starting_from'  =>  $model->starting_from,
            'currency_code'  =>  $model->currency_code,
            'partner_meeting_link'  =>  $model->partner_meeting_link,
            'refund_policy'  =>  $model->refund_policy,
            'after_sales_introduction'  =>  $model->after_sales_introduction,
            'support_content'  =>  $model->support_content,
            'eula'  =>  $model->eula,
            'tags'  =>  $model->tags,
            'iam_account_id'  =>  $iamAccountId ? $iamAccountId->uuid : null,
            'iam_user_id'  =>  $iamUserId ? $iamUserId->uuid : null,
            'created_at'  =>  $model->created_at,
            'updated_at'  =>  $model->updated_at,
            'deleted_at'  =>  $model->deleted_at,
            ]
        );
    }

    public function includeStates(ProductsPerspective $model)
    {
        $states = States::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($states, new StatesTransformer());
    }

    public function includeActions(ProductsPerspective $model)
    {
        $input = get_class($model);
        $input = str_replace('\\Database\\Models', '', $input);

        $actions = AvailableActions::withoutGlobalScope(AuthorizationScope::class)
            ->where('input', $input)
            ->get();

        return $this->collection($actions, new AvailableActionsTransformer());
    }

    public function includeMedia(ProductsPerspective $model)
    {
        $media = Media::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($media, new MediaTransformer());
    }

    public function includeSocialMedia(ProductsPerspective $model)
    {
        $socialMedia = SocialMedia::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($socialMedia, new SocialMediaTransformer());
    }

    public function includeComments(ProductsPerspective $model)
    {
        $comments = Comments::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($comments, new CommentsTransformer());
    }

    public function includeVotes(ProductsPerspective $model)
    {
        $votes = Votes::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($votes, new VotesTransformer());
    }

    public function includeMeta(ProductsPerspective $model)
    {
        $meta = Meta::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($meta, new MetaTransformer());
    }

    public function includePhoneNumbers(ProductsPerspective $model)
    {
        $phoneNumbers = PhoneNumbers::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($phoneNumbers, new PhoneNumbersTransformer());
    }

    public function includeAddresses(ProductsPerspective $model)
    {
        $addresses = Addresses::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($addresses, new AddressesTransformer());
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE











}
