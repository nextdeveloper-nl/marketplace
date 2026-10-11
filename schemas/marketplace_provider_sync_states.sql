-- PostgreSQL

CREATE TABLE marketplace_provider_sync_states (
    id                       bigint NOT NULL DEFAULT nextval('marketplace_provider_sync_states_id_seq'::regclass),
    uuid                     uuid NOT NULL DEFAULT gen_random_uuid(),
    marketplace_provider_id  bigint,
    entity_type              text,
    cursor_updated_at        timestamp with time zone,
    last_run_at              timestamp with time zone,
    last_success_at          timestamp with time zone,
    consecutive_failures     integer DEFAULT 0,
    last_error               text,
    records_processed        integer DEFAULT 0,
    iam_account_id           bigint,
    iam_user_id              bigint,
    created_at                timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at                timestamp with time zone,
    CONSTRAINT marketplace_provider_sync_states_pkey PRIMARY KEY (id)
);
