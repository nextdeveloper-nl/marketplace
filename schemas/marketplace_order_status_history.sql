-- PostgreSQL
-- Table to track status changes for marketplace orders

CREATE TABLE marketplace_order_status_history (
    id                    bigint NOT NULL DEFAULT nextval('marketplace_order_status_history_id_seq'::regclass),
    uuid                  uuid NOT NULL DEFAULT gen_random_uuid(),
    marketplace_order_id  bigint NOT NULL, -- The order this status history belongs to
    old_status            text NOT NULL, -- Previous status of the order
    new_status            text NOT NULL, -- New status of the order
    changed_at            timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP, -- When the status was changed
    notes                 text, -- Optional notes about the status change
    iam_account_id        bigint,
    iam_user_id           bigint,
    created_at            timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at            timestamp with time zone,
    CONSTRAINT marketplace_order_status_history_pkey PRIMARY KEY (id)
);
