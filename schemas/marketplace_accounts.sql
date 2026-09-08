-- PostgreSQL

CREATE TABLE marketplace_accounts (
    id                  integer NOT NULL DEFAULT nextval('marketplace_accounts_id_seq'::regclass),
    uuid                uuid DEFAULT gen_random_uuid(),
    iam_account_id      bigint NOT NULL,
    is_service_enabled  boolean NOT NULL DEFAULT false,
    created_at          timestamp with time zone DEFAULT now(),
    updated_at          timestamp with time zone DEFAULT now(),
    deleted_at          timestamp with time zone,
    CONSTRAINT marketplace_accounts_pkey PRIMARY KEY (id)
);
