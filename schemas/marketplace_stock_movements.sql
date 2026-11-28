-- PostgreSQL
-- Stock history of a plan (plusclouds/leo4#1396). Created by 2026_11_28_stock_status_and_movements.sql.

CREATE TABLE marketplace_stock_movements (
    id                              bigint NOT NULL DEFAULT nextval('marketplace_stock_movements_id_seq'::regclass),
    uuid                            uuid NOT NULL DEFAULT gen_random_uuid(),
    marketplace_product_catalog_id  bigint NOT NULL, -- The plan whose stock moved
    movement_type                   text NOT NULL, -- [label:"incoming, sale, rental_out, rental_return or adjustment"]
    quantity                        integer NOT NULL, -- [label:"Signed change of the stock: positive adds, negative removes. Never 0."]
    quantity_after                  integer NOT NULL, -- [ro] [label:"Stock of the plan right after this movement."]
    moved_at                        timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP, -- [label:"When the stock moved."]
    notes                           text,
    crm_opportunity_id              bigint, -- [label:"Optional opportunity this movement belongs to."]
    crm_quote_id                    bigint, -- [label:"Optional quote this movement belongs to."]
    iam_account_id                  bigint NOT NULL,
    iam_user_id                     bigint,
    created_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at                      timestamp with time zone,
    CONSTRAINT marketplace_stock_movements_pkey PRIMARY KEY (id),
    CONSTRAINT chk_marketplace_stock_movements_type CHECK (movement_type IN ('incoming', 'sale', 'rental_out', 'rental_return', 'adjustment')),
    CONSTRAINT chk_marketplace_stock_movements_quantity CHECK (quantity <> 0)
);
