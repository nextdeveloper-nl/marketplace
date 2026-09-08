-- PostgreSQL

CREATE TABLE marketplace_providers (
    id                     bigint NOT NULL DEFAULT nextval('marketplace_providers_id_seq'::regclass),
    uuid                   uuid NOT NULL DEFAULT gen_random_uuid(),
    name                   text,
    description            text,
    action                 text,
    url                    text,
    marketplace_market_id  bigint,
    iam_account_id         bigint,
    iam_user_id            bigint,
    created_at             timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at             timestamp with time zone,
    api_config             json,
    is_active              boolean DEFAULT true,
    adapter                text, -- The adapter used for the provider, e.g. "trendyol_go" for Trendyol Go, "yemeksepeti" for Yemeksepeti, etc.
    CONSTRAINT marketplace_providers_pkey PRIMARY KEY (id)
);
