-- PostgreSQL

CREATE TABLE marketplace_products (
    id                        bigint NOT NULL DEFAULT nextval('marketplace_products_id_seq'::regclass),
    uuid                      uuid DEFAULT gen_random_uuid(),
    name                      text NOT NULL, -- [label:"This will be the name of your product, like: Acme CRM Software"]
    description               text, -- [ui:markdown][return:html][label:"Please give us a brief information about your product."]
    content                   text, -- [ui:markdown][label:"This will be the content which your product will be explained detailed."]
    highlights                text[], -- [label:"Tell us about your products highlighted features."]
    after_sales_introduction  text, -- [ui:markdown]
    support_content           text, -- [ui:markdown][label:"Tell us about how you provide support for your product"]
    refund_policy             text, -- [ui:markdown][label:"What happens if the customer would like to make charge back."]
    eula                      text, -- [label:"Please enter the end users licence agreement here."][ui:markdown]
    subscription_type         subscription_type NOT NULL DEFAULT 'hourly'::subscription_type,
    slug                      text, -- [ro]
    version                   text,
    product_type              product_type DEFAULT 'onetime'::product_type,
    is_service                boolean DEFAULT false,
    is_in_maintenance         boolean DEFAULT false,
    is_public                 boolean DEFAULT false,
    is_invisible              boolean DEFAULT false,
    is_active                 boolean DEFAULT true,
    common_category_id        bigint,
    iam_account_id            bigint NOT NULL,
    iam_user_id               bigint NOT NULL,
    marketplace_market_id     bigint NOT NULL,
    tags                      text[] DEFAULT '{}'::text[],
    created_at                timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at                timestamp with time zone,
    sales_pitch               text, -- [ui:markdown]
    is_approved               boolean NOT NULL DEFAULT false, -- [ro]
    marketplace_provider_id   bigint,
    payment_gateway_mappings  json,
    metadata                  jsonb,
    CONSTRAINT marketplace_products_pkey PRIMARY KEY (id)
);
