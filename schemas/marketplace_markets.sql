-- PostgreSQL

CREATE TABLE marketplace_markets (
    id                  bigint NOT NULL DEFAULT nextval('marketplace_markets_id_seq'::regclass),
    uuid                uuid DEFAULT gen_random_uuid(),
    name                text NOT NULL, -- [label:"This will be the name of the marketplace. If you make this market public, the market will be listed with this name."]
    description         text, -- [ui:markdown][label:"Please give us a brief information about your marketplace"]
    common_domain_id    bigint NOT NULL,
    is_public           boolean NOT NULL DEFAULT false, -- [label:"If you make your market public, then everybody will see your products."]
    is_active           boolean NOT NULL DEFAULT true,
    common_currency_id  bigint NOT NULL,
    common_language_id  bigint NOT NULL,
    common_country_id   bigint NOT NULL,
    iam_account_id      bigint NOT NULL,
    iam_user_id         bigint NOT NULL,
    created_at          timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at          timestamp with time zone,
    CONSTRAINT marketplace_markets_pkey PRIMARY KEY (id)
);
