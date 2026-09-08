-- PostgreSQL
-- Table to manage individual items within a marketplace order

CREATE TABLE marketplace_order_items (
    id                              bigint NOT NULL DEFAULT nextval('marketplace_order_items_id_seq'::regclass),
    uuid                            uuid NOT NULL DEFAULT gen_random_uuid(),
    marketplace_order_id            bigint NOT NULL, -- The order this item belongs to
    marketplace_product_catalog_id  bigint NOT NULL, -- The product being ordered from the marketplace catalog
    quantity                        integer NOT NULL DEFAULT 1, -- Quantity of the product ordered
    price_per_item                  numeric(10,2) NOT NULL, -- Price per item at the time of order
    total_price                     numeric(10,2) NOT NULL, -- Total price for this item (quantity * price_per_item)
    modifiers                       json, -- Any modifiers or customizations for the product
    special_instructions            text, -- Special instructions from the customer
    item_data                       json, -- Original product data from the marketplace provider
    created_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at                      timestamp with time zone,
    iam_account_id                  bigint,
    iam_user_id                     bigint,
    CONSTRAINT marketplace_order_items_pkey PRIMARY KEY (id)
);
