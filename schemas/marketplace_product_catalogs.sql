-- PostgreSQL

CREATE TABLE marketplace_product_catalogs (
    id                        bigint NOT NULL DEFAULT nextval('marketplace_product_catalogs_id_seq'::regclass),
    uuid                      uuid DEFAULT gen_random_uuid(),
    name                      text NOT NULL,
    agreement                 text, -- [label:"The currency of the price will be the same with the market you are presenting your product. If the market is USD then its USD, if the market is EUR, then the price is EUR."]
    args                      json, -- [label:"Arguements are the features or options that comes with this catalog item."]
    price                     numeric(20,8) NOT NULL, -- [ui:money]
    marketplace_product_id    bigint NOT NULL,
    tags                      text[] NOT NULL DEFAULT '{}'::text[],
    created_at                timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at                timestamp with time zone,
    sku                       text,
    trial_date                integer NOT NULL DEFAULT 0, -- [label:"If you can offer a free trial, how many days does a customer use this catalog item?"]
    features                  text[], -- [label:"Features of this catalog item when you compare with other items. What is different ?"]
    iam_user_id               bigint,
    iam_account_id            bigint,
    is_public                 boolean DEFAULT true,
    quantity_in_inventory     integer NOT NULL DEFAULT '-1'::integer, -- [label:"The amount of the items you can sell right now."]
    common_currency_id        bigint,
    payment_gateway_mappings  json,
    stock_status              text, -- [label:"Stock status of this item: in_stock, out_of_stock, in_transit, at_customs or on_order. Empty when not tracked."]
    expected_arrival_at       timestamp with time zone, -- [label:"When the stock is expected to arrive. Used with in_transit, at_customs and on_order."]
    offering_type             text, -- [label:"What this plan sells: sale, short_term_rental, long_term_rental (Robot-as-a-Service) or service. Empty when not set."]
    billing_period            text, -- [label:"How often the price is charged: one_time, day, week, month or year. Empty when not set."]
    min_rental_duration       integer, -- [label:"Shortest rental, in billing periods (e.g. 3 with month = at least 3 months)."]
    max_rental_duration       integer, -- [label:"Longest rental, in billing periods."]
    CONSTRAINT chk_marketplace_product_catalogs_stock_status CHECK (stock_status IS NULL OR stock_status IN ('in_stock', 'out_of_stock', 'in_transit', 'at_customs', 'on_order')),
    CONSTRAINT chk_marketplace_product_catalogs_offering_type CHECK (offering_type IS NULL OR offering_type IN ('sale', 'short_term_rental', 'long_term_rental', 'service')),
    CONSTRAINT chk_marketplace_product_catalogs_billing_period CHECK (billing_period IS NULL OR billing_period IN ('one_time', 'day', 'week', 'month', 'year')),
    CONSTRAINT chk_marketplace_product_catalogs_rental_duration CHECK ((min_rental_duration IS NULL OR min_rental_duration > 0) AND (max_rental_duration IS NULL OR max_rental_duration > 0) AND (min_rental_duration IS NULL OR max_rental_duration IS NULL OR min_rental_duration <= max_rental_duration)),
    CONSTRAINT marketplace_product_catalogs_pkey PRIMARY KEY (id)
);
