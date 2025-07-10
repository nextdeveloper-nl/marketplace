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
use NextDeveloper\Marketplace\Database\Models\Orders;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\IAM\Database\Scopes\AuthorizationScope;

/**
 * Class OrdersTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Marketplace\Http\Transformers
 */
class AbstractOrdersTransformer extends AbstractTransformer
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
     * @param Orders $model
     *
     * @return array
     */
    public function transform(Orders $model)
    {
                                                $marketplaceMarketId = \NextDeveloper\Marketplace\Database\Models\Markets::where('id', $model->marketplace_market_id)->first();
                                                            $marketplaceProviderId = \NextDeveloper\Marketplace\Database\Models\Providers::where('id', $model->marketplace_provider_id)->first();
                                                            $marketplaceProductId = \NextDeveloper\Marketplace\Database\Models\Products::where('id', $model->marketplace_product_id)->first();
                                                            $iamAccountId = \NextDeveloper\IAM\Database\Models\Accounts::where('id', $model->iam_account_id)->first();
                                                            $iamUserId = \NextDeveloper\IAM\Database\Models\Users::where('id', $model->iam_user_id)->first();

        return $this->buildPayload(
            [
            'id'  =>  $model->uuid,
            'marketplace_market_id'  =>  $marketplaceMarketId ? $marketplaceMarketId->uuid : null,
            'marketplace_provider_id'  =>  $marketplaceProviderId ? $marketplaceProviderId->uuid : null,
            'marketplace_product_id'  =>  $marketplaceProductId ? $marketplaceProductId->uuid : null,
            'external_order_id'  =>  $model->external_order_id,
            'external_order_number'  =>  $model->external_order_number,
            'status'  =>  $model->status,
            'ordered_at'  =>  $model->ordered_at,
            'accepted_at'  =>  $model->accepted_at,
            'prepared_at'  =>  $model->prepared_at,
            'dispatched_at'  =>  $model->dispatched_at,
            'delivered_at'  =>  $model->delivered_at,
            'cancelled_at'  =>  $model->cancelled_at,
            'customer_data'  =>  $model->customer_data,
            'delivery_address'  =>  $model->delivery_address,
            'marketplace_metadata'  =>  $model->marketplace_metadata,
            'subtotal_amount'  =>  $model->subtotal_amount,
            'delivery_fee'  =>  $model->delivery_fee,
            'service_fee'  =>  $model->service_fee,
            'tax_amount'  =>  $model->tax_amount,
            'discount_amount'  =>  $model->discount_amount,
            'total_amount'  =>  $model->total_amount,
            'order_type'  =>  $model->order_type,
            'delivery_method'  =>  $model->delivery_method,
            'estimated_delivery_time'  =>  $model->estimated_delivery_time,
            'raw_order_data'  =>  $model->raw_order_data,
            'last_synced_at'  =>  $model->last_synced_at,
            'sync_error_message'  =>  $model->sync_error_message,
            'iam_account_id'  =>  $iamAccountId ? $iamAccountId->uuid : null,
            'iam_user_id'  =>  $iamUserId ? $iamUserId->uuid : null,
            'created_at'  =>  $model->created_at,
            'updated_at'  =>  $model->updated_at,
            'deleted_at'  =>  $model->deleted_at,
            'customer_note'  =>  $model->customer_note,
            'external_line_id'  =>  $model->external_line_id,
            'tags'  =>  $model->tags,
            ]
        );
    }

    public function includeStates(Orders $model)
    {
        $states = States::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($states, new StatesTransformer());
    }

    public function includeActions(Orders $model)
    {
        $input = get_class($model);
        $input = str_replace('\\Database\\Models', '', $input);

        $actions = AvailableActions::withoutGlobalScope(AuthorizationScope::class)
            ->where('input', $input)
            ->get();

        return $this->collection($actions, new AvailableActionsTransformer());
    }

    public function includeMedia(Orders $model)
    {
        $media = Media::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($media, new MediaTransformer());
    }

    public function includeSocialMedia(Orders $model)
    {
        $socialMedia = SocialMedia::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($socialMedia, new SocialMediaTransformer());
    }

    public function includeComments(Orders $model)
    {
        $comments = Comments::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($comments, new CommentsTransformer());
    }

    public function includeVotes(Orders $model)
    {
        $votes = Votes::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($votes, new VotesTransformer());
    }

    public function includeMeta(Orders $model)
    {
        $meta = Meta::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($meta, new MetaTransformer());
    }

    public function includePhoneNumbers(Orders $model)
    {
        $phoneNumbers = PhoneNumbers::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($phoneNumbers, new PhoneNumbersTransformer());
    }

    public function includeAddresses(Orders $model)
    {
        $addresses = Addresses::where('object_type', get_class($model))
            ->where('object_id', $model->id)
            ->get();

        return $this->collection($addresses, new AddressesTransformer());
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE






}
