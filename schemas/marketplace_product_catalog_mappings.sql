-- PostgreSQL
-- Table to manage product catalogs from different marketplace providers

CREATE TABLE marketplace_product_catalog_mappings (
    id                              bigint NOT NULL DEFAULT nextval('marketplace_product_catalog_mappings_id_seq'::regclass),
    uuid                            uuid NOT NULL DEFAULT gen_random_uuid(),
    marketplace_product_catalog_id  bigint NOT NULL, -- The product catalog this entry belongs to
    marketplace_provider_id         bigint NOT NULL, -- The provider this catalog belongs to
    external_catalog_id             text NOT NULL, -- Unique identifier for the catalog in the marketplace provider system
    created_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at                      timestamp with time zone,
    CONSTRAINT marketplace_product_catalog_mappings_pkey PRIMARY KEY (id)
);
