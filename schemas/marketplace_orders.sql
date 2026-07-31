-- PostgreSQL
-- Table to manage orders from marketplace providers

CREATE TABLE marketplace_orders (
    id                       bigint NOT NULL DEFAULT nextval('marketplace_orders_id_seq'::regclass),
    uuid                     uuid NOT NULL DEFAULT gen_random_uuid(),
    marketplace_market_id    bigint, -- The account that owns the order
    marketplace_provider_id  bigint, -- The provider that manages the order
    marketplace_product_id   bigint NOT NULL,
    external_order_id        text, -- [skip]
    external_order_number    text, -- Order number as provided by the marketplace provider
    status                   text DEFAULT 'Accepted'::text, -- Current status of the order (e.g. pending, accepted, prepared, dispatched, delivered, cancelled)
    ordered_at               timestamp with time zone DEFAULT CURRENT_TIMESTAMP, -- Timestamp when the order was placed
    accepted_at              timestamp with time zone, -- Timestamp when the order was accepted by the provider
    prepared_at              timestamp with time zone, -- Timestamp when the order was prepared
    dispatched_at            timestamp with time zone, -- Timestamp when the order was dispatched for delivery
    delivered_at             timestamp with time zone, -- Timestamp when the order was delivered to the customer
    cancelled_at             timestamp with time zone, -- Timestamp when the order was cancelled
    customer_data            json, -- Customer information such as name, email, phone number
    delivery_address         json, -- Delivery address details if applicable
    marketplace_metadata     json, -- Metadata related to the marketplace order
    subtotal_amount          numeric(10,2) DEFAULT 0, -- Subtotal amount for the order before any fees or discounts
    delivery_fee             numeric(10,2) DEFAULT 0, -- Delivery fee for the order
    service_fee              numeric(10,2) DEFAULT 0, -- Service fee for the order
    tax_amount               numeric(10,2) DEFAULT 0, -- Tax amount applied to the order
    discount_amount          numeric(10,2) DEFAULT 0, -- Discount amount applied to the order
    total_amount             numeric(10,2) DEFAULT 0, -- Total amount for the order after fees, taxes, and discounts
    order_type               text DEFAULT 'external'::text, -- Type of order (e.g. delivery, pickup, dine-in)
    delivery_method          text, -- Method of delivery (e.g. platform_delivery, self_pickup, restaurant_delivery)
    estimated_delivery_time  timestamp with time zone, -- Estimated time for delivery or pickup
    raw_order_data           json, -- Raw data from the marketplace provider
    last_synced_at           timestamp with time zone DEFAULT CURRENT_TIMESTAMP, -- Timestamp when the order was last synced with the marketplace provider
    sync_error_message       text, -- Error message if syncing fails
    iam_account_id           bigint,
    iam_user_id              bigint,
    created_at               timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at               timestamp with time zone,
    customer_note            text,
    tags                     text[],
    external_line_id         text, -- [skip]
    provider                 text, -- Name of the marketplace provider managing the order
    order_no                 text DEFAULT ((COALESCE(provider, 'KipHok'::text) || ' - '::text) || (id)::text), -- Generated order number combining provider name and order ID
    CONSTRAINT marketplace_orders_pkey PRIMARY KEY (id)
);
