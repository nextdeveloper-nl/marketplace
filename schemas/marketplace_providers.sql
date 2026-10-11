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
    adapter                text, -- Registered in MarketplaceAdapterFactory, e.g. "shopify", "ideasoft".
    access_token_enc       text, -- Encrypted OAuth/API access token. Never returned by the API; write-only from clients.
    webhook_secret_enc     text, -- Encrypted webhook signing secret. Never returned by the API; write-only from clients.
    client_secret_enc      text, -- Encrypted OAuth client secret (Ideasoft). Never returned by the API; write-only from clients.
    CONSTRAINT marketplace_providers_pkey PRIMARY KEY (id)
);
