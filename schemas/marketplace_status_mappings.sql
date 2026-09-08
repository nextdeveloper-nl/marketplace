-- PostgreSQL
-- Table to manage status mappings between external marketplace provider statuses and normalized internal statuses

CREATE TABLE marketplace_status_mappings (
    id                       bigint NOT NULL DEFAULT nextval('marketplace_status_mappings_id_seq'::regclass),
    marketplace_provider_id  bigint, -- The provider this status mapping belongs to
    external_status          text NOT NULL, -- Status as provided by the marketplace provider
    normalized_status        text NOT NULL, -- Normalized status used internally in the system
    description              text,
    created_at               timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT marketplace_status_mappings_pkey PRIMARY KEY (id)
);
