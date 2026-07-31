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
    CONSTRAINT marketplace_product_catalogs_pkey PRIMARY KEY (id)
);
