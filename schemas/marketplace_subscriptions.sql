-- PostgreSQL

CREATE TABLE marketplace_subscriptions (
    id                              bigint NOT NULL DEFAULT nextval('marketplace_subscriptions_id_seq'::regclass),
    uuid                            uuid DEFAULT gen_random_uuid(),
    marketplace_product_catalog_id  bigint NOT NULL,
    iam_account_id                  bigint NOT NULL,
    iam_user_id                     bigint,
    subscription_data               json,
    subscription_starts_at          timestamp with time zone,
    subscription_ends_at            timestamp with time zone,
    is_valid                        boolean NOT NULL DEFAULT true,
    tags                            text[] NOT NULL DEFAULT '{}'::text[],
    created_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at                      timestamp with time zone,
    CONSTRAINT marketplace_subscriptions_pkey PRIMARY KEY (id)
);
