-- PostgreSQL
-- Table to manage mappings between marketplace products and their external identifiers in different providers

CREATE TABLE marketplace_product_mappings (
    id                       bigint NOT NULL DEFAULT nextval('marketplace_product_mappings_id_seq'::regclass),
    uuid                     uuid NOT NULL DEFAULT gen_random_uuid(),
    marketplace_product_id   bigint NOT NULL, -- The product this mapping belongs to
    marketplace_provider_id  bigint NOT NULL, -- The provider this mapping is for
    external_product_id      text NOT NULL, -- Unique identifier for the product in the marketplace provider system
    created_at               timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at               timestamp with time zone,
    CONSTRAINT marketplace_product_mappings_pkey PRIMARY KEY (id)
);
