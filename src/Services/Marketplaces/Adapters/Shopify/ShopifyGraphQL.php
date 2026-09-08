<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify;

use Illuminate\Support\Carbon;

/**
 * GraphQL documents for the Shopify Admin API.
 *
 * Kept apart from the adapter so the queries can be read, diffed and version-
 * bumped on their own. Shopify ships breaking changes quarterly and de-lists
 * apps that keep calling removed fields, so this file is the first place to
 * look when bumping ShopifyClient::DEFAULT_API_VERSION.
 *
 * Conventions:
 *  - every list query takes $cursor and exposes pageInfo, for ShopifyClient::paginate()
 *  - every list query sorts by UPDATED_AT so delta syncs are monotonic
 *  - money is always read as a MoneyBag: the shop-currency scalar alone is what
 *    makes integrations report wrong revenue on international orders
 */
class ShopifyGraphQL
{
    /**
     * Products changed since a cursor, with their variants.
     *
     * Variants are capped at 250 per page. Shopify allows 2048 per product, so
     * a product beyond that needs its own variant walk; the adapter logs when
     * it sees one rather than silently syncing a partial variant set.
     */
    public const PRODUCTS_DELTA = <<<'GQL'
    query ProductsDelta($query: String!, $cursor: String) {
      products(first: 50, after: $cursor, query: $query, sortKey: UPDATED_AT) {
        pageInfo { hasNextPage endCursor }
        edges {
          node {
            id
            title
            handle
            descriptionHtml
            productType
            vendor
            status
            tags
            createdAt
            updatedAt
            featuredImage { url altText }
            images(first: 10) { edges { node { url altText } } }
            variants(first: 250) {
              pageInfo { hasNextPage }
              edges {
                node {
                  id
                  title
                  sku
                  price
                  compareAtPrice
                  inventoryQuantity
                  updatedAt
                  selectedOptions { name value }
                  inventoryItem { id tracked measurement { weight { value unit } } }
                }
              }
            }
          }
        }
      }
    }
    GQL;

    /**
     * Inventory levels changed since a cursor, for one location.
     */
    public const INVENTORY_DELTA = <<<'GQL'
    query InventoryDelta($locationId: ID!, $cursor: String) {
      location(id: $locationId) {
        id
        name
        inventoryLevels(first: 100, after: $cursor) {
          pageInfo { hasNextPage endCursor }
          edges {
            node {
              id
              updatedAt
              quantities(names: ["available", "on_hand", "committed"]) { name quantity }
              item { id sku variant { id } }
            }
          }
        }
      }
    }
    GQL;

    /**
     * Orders changed since a cursor.
     *
     * Without an approved read_all_orders scope this only ever returns the last
     * 60 days, however far back the query reaches.
     */
    public const ORDERS_DELTA = <<<'GQL'
    query OrdersDelta($query: String!, $cursor: String) {
      orders(first: 25, after: $cursor, query: $query, sortKey: UPDATED_AT) {
        pageInfo { hasNextPage endCursor }
        edges {
          node {
            id
            name
            createdAt
            updatedAt
            cancelledAt
            processedAt
            displayFinancialStatus
            displayFulfillmentStatus
            note
            currentTotalPriceSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
            currentSubtotalPriceSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
            totalTaxSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
            totalDiscountsSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
            totalShippingPriceSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
            customer { id email phone firstName lastName updatedAt }
            shippingAddress { name phone address1 address2 city province country countryCodeV2 zip }
            billingAddress { name phone address1 address2 city province country countryCodeV2 zip }
            lineItems(first: 100) {
              edges {
                node {
                  id
                  title
                  quantity
                  sku
                  variant { id sku }
                  originalUnitPriceSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
                  discountedTotalSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
                }
              }
            }
          }
        }
      }
    }
    GQL;

    /**
     * Customers changed since a cursor.
     */
    public const CUSTOMERS_DELTA = <<<'GQL'
    query CustomersDelta($query: String!, $cursor: String) {
      customers(first: 50, after: $cursor, query: $query, sortKey: UPDATED_AT) {
        pageInfo { hasNextPage endCursor }
        edges {
          node {
            id
            email
            phone
            firstName
            lastName
            createdAt
            updatedAt
            numberOfOrders
            amountSpent { amount currencyCode }
            defaultAddress { address1 address2 city province country countryCodeV2 zip phone }
            tags
          }
        }
      }
    }
    GQL;

    /**
     * Every product and variant id, for the deletion reconciliation sweep.
     *
     * Deliberately minimal: delete webhooks carry only an id and Shopify has no
     * deleted_at, so a missed delete is invisible to any delta query. Diffing
     * the full id set is the only way to notice, and ids alone are cheap enough
     * to pull nightly through a bulk operation.
     */
    /**
     * One product by id, with the same field set as the delta query.
     *
     * The webhook processor re-fetches instead of reading the delivered body:
     * webhook payloads are REST-shaped while the normalizers speak GraphQL, and
     * deliveries are neither ordered nor guaranteed, so the body may already be
     * stale. Fetching current state costs one call and removes both problems.
     */
    public const PRODUCT_BY_ID = <<<'GQL'
    query ProductById($id: ID!) {
      product(id: $id) {
        id
        title
        handle
        descriptionHtml
        productType
        vendor
        status
        tags
        createdAt
        updatedAt
        featuredImage { url altText }
        images(first: 10) { edges { node { url altText } } }
        variants(first: 250) {
          pageInfo { hasNextPage }
          edges {
            node {
              id
              title
              sku
              price
              compareAtPrice
              inventoryQuantity
              updatedAt
              selectedOptions { name value }
              inventoryItem { id tracked measurement { weight { value unit } } }
            }
          }
        }
      }
    }
    GQL;

    public const ORDER_BY_ID = <<<'GQL'
    query OrderById($id: ID!) {
      order(id: $id) {
        id
        name
        createdAt
        updatedAt
        cancelledAt
        processedAt
        displayFinancialStatus
        displayFulfillmentStatus
        note
        currentTotalPriceSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
        currentSubtotalPriceSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
        totalTaxSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
        totalDiscountsSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
        totalShippingPriceSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
        customer { id email phone firstName lastName updatedAt }
        shippingAddress { name phone address1 address2 city province country countryCodeV2 zip }
        billingAddress { name phone address1 address2 city province country countryCodeV2 zip }
        lineItems(first: 100) {
          edges {
            node {
              id
              title
              quantity
              sku
              variant { id sku }
              originalUnitPriceSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
              discountedTotalSet { shopMoney { amount currencyCode } presentmentMoney { amount currencyCode } }
            }
          }
        }
      }
    }
    GQL;

    public const CUSTOMER_BY_ID = <<<'GQL'
    query CustomerById($id: ID!) {
      customer(id: $id) {
        id
        email
        phone
        firstName
        lastName
        createdAt
        updatedAt
        numberOfOrders
        amountSpent { amount currencyCode }
        defaultAddress { address1 address2 city province country countryCodeV2 zip phone }
        tags
      }
    }
    GQL;

    /**
     * The level of one inventory item at one location.
     *
     * inventory_levels webhooks carry numeric inventory_item_id / location_id
     * and an "available" figure that may already be superseded, so the level is
     * re-read for the pinned location only.
     */
    public const INVENTORY_LEVEL_BY_ITEM = <<<'GQL'
    query InventoryLevelByItem($itemId: ID!, $locationId: ID!) {
      inventoryItem(id: $itemId) {
        id
        sku
        variant { id }
        inventoryLevel(locationId: $locationId) {
          id
          updatedAt
          quantities(names: ["available", "on_hand", "committed"]) { name quantity }
        }
      }
    }
    GQL;

    /**
     * Every product id with its variant ids, paginated.
     *
     * The deletion sweep needs the full remote id set to diff against our
     * mappings. ALL_PRODUCT_IDS is the bulk-operation form of the same query;
     * this cursor-paginated variant is what the sweep uses until bulk
     * operations are wired up, and it is the part to swap out first when a
     * catalogue outgrows paginated reads.
     */
    public const PRODUCT_IDS_PAGE = <<<'GQL'
    query ProductIdsPage($cursor: String) {
      products(first: 250, after: $cursor, sortKey: ID) {
        pageInfo { hasNextPage endCursor }
        edges {
          node {
            id
            updatedAt
            variants(first: 250) { edges { node { id } } }
          }
        }
      }
    }
    GQL;

    public const ALL_PRODUCT_IDS = <<<'GQL'
    {
      products {
        edges {
          node {
            id
            updatedAt
            variants { edges { node { id sku } } }
          }
        }
      }
    }
    GQL;

    /**
     * Set absolute inventory with compare-and-swap.
     *
     * `changeFromQuantity` replaces the removed `compareQuantity`. When the
     * remote value has moved the mutation fails with CHANGE_FROM_QUANTITY_STALE
     * instead of silently overwriting a concurrent sale.
     */
    /**
     * Push catalogue fields back to Shopify.
     *
     * Deliberately narrow. Handle, status and images are not pushed: the handle
     * is the storefront URL, status controls whether the product is publicly
     * visible, and images are the merchant's own media. Getting any of those
     * wrong is destructive in a way a title is not, and Shopify has no undo.
     */
    public const PRODUCT_PUSH = <<<'GQL'
    mutation ProductPush($product: ProductUpdateInput!) {
      productUpdate(product: $product) {
        product { id title updatedAt }
        userErrors { field message }
      }
    }
    GQL;

    /**
     * Push variant pricing. SKU is not pushed — it is the merchant's own key
     * into their warehouse and other channels, and rewriting it from our side
     * would break joins we do not own.
     */
    public const PRODUCT_VARIANTS_PUSH = <<<'GQL'
    mutation VariantsPush($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
      productVariantsBulkUpdate(productId: $productId, variants: $variants) {
        productVariants { id price updatedAt }
        userErrors { field message }
      }
    }
    GQL;

    public const INVENTORY_SET = <<<'GQL'
    mutation InventorySet($input: InventorySetQuantitiesInput!) {
      inventorySetQuantities(input: $input) {
        inventoryAdjustmentGroup { createdAt reason changes { name delta quantityAfterChange } }
        userErrors { field message code }
      }
    }
    GQL;

    public const FULFILLMENT_CREATE = <<<'GQL'
    mutation FulfillmentCreate($fulfillment: FulfillmentV2Input!) {
      fulfillmentCreateV2(fulfillment: $fulfillment) {
        fulfillment { id status createdAt }
        userErrors { field message }
      }
    }
    GQL;

    public const ORDER_CANCEL = <<<'GQL'
    mutation OrderCancel($orderId: ID!, $reason: OrderCancelReason!, $refund: Boolean!, $restock: Boolean!, $notifyCustomer: Boolean) {
      orderCancel(orderId: $orderId, reason: $reason, refund: $refund, restock: $restock, notifyCustomer: $notifyCustomer) {
        job { id done }
        orderCancelUserErrors { field message code }
      }
    }
    GQL;

    public const REFUND_CREATE = <<<'GQL'
    mutation RefundCreate($input: RefundInput!) {
      refundCreate(input: $input) {
        refund { id createdAt totalRefundedSet { shopMoney { amount currencyCode } } }
        userErrors { field message }
      }
    }
    GQL;

    /**
     * Fulfillment orders for an order — the modern fulfillment entry point.
     */
    public const FULFILLMENT_ORDERS = <<<'GQL'
    query FulfillmentOrders($orderId: ID!) {
      order(id: $orderId) {
        id
        fulfillmentOrders(first: 10) {
          edges {
            node {
              id
              status
              lineItems(first: 100) { edges { node { id remainingQuantity lineItem { id sku } } } }
            }
          }
        }
      }
    }
    GQL;

    public const WEBHOOK_LIST = <<<'GQL'
    query WebhookList($cursor: String) {
      webhookSubscriptions(first: 100, after: $cursor) {
        pageInfo { hasNextPage endCursor }
        edges { node { id topic endpoint { __typename ... on WebhookHttpEndpoint { callbackUrl } } } }
      }
    }
    GQL;

    public const WEBHOOK_CREATE = <<<'GQL'
    mutation WebhookCreate($topic: WebhookSubscriptionTopic!, $subscription: WebhookSubscriptionInput!) {
      webhookSubscriptionCreate(topic: $topic, webhookSubscription: $subscription) {
        webhookSubscription { id topic }
        userErrors { field message }
      }
    }
    GQL;

    public const WEBHOOK_DELETE = <<<'GQL'
    mutation WebhookDelete($id: ID!) {
      webhookSubscriptionDelete(id: $id) {
        deletedWebhookSubscriptionId
        userErrors { field message }
      }
    }
    GQL;

    public const LOCATIONS = <<<'GQL'
    query Locations($cursor: String) {
      locations(first: 50, after: $cursor) {
        pageInfo { hasNextPage endCursor }
        edges { node { id name isActive address { formatted } } }
      }
    }
    GQL;

    public const BULK_RUN = <<<'GQL'
    mutation BulkRun($query: String!) {
      bulkOperationRunQuery(query: $query) {
        bulkOperation { id status }
        userErrors { field message }
      }
    }
    GQL;

    public const BULK_POLL = <<<'GQL'
    query BulkPoll {
      currentBulkOperation {
        id
        status
        errorCode
        objectCount
        url
        partialDataUrl
        completedAt
      }
    }
    GQL;

    /**
     * Build a Shopify search-syntax delta filter.
     *
     * Shopify expects UTC in this filter; passing a local-offset timestamp
     * quietly shifts the window and drops records.
     */
    public static function updatedSince(\DateTimeInterface $since): string
    {
        return 'updated_at:>='.Carbon::instance(
            \DateTimeImmutable::createFromInterface($since)
        )->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
