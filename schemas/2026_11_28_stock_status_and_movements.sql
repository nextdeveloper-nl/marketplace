-- PostgreSQL
-- plusclouds/leo4#1396: stock status per plan (marketplace_product_catalogs) and a stock movement history.
--
-- NOT APPLIED. Run by hand, once, on the shared DB BEFORE deploying the package version that ships this file
-- (the models list the new columns as fillable, so a plan save that carries stock_status fails until they exist).
-- Afterwards re-run schemas/marketplace_product_catalogs_perspective.sql so the plan list returns the new columns.
-- Safe to re-run: every statement is IF NOT EXISTS / DROP ... IF EXISTS first.

BEGIN;

-- 1. Stock status per plan. NULL = the seller does not track a status (all existing rows; nothing is backfilled,
--    because 4 000+ plans hold quantity 0 only because the panel sent 0 for a blank "unlimited" field).
ALTER TABLE marketplace_product_catalogs
    ADD COLUMN IF NOT EXISTS stock_status        text,
    ADD COLUMN IF NOT EXISTS expected_arrival_at timestamp with time zone;

ALTER TABLE marketplace_product_catalogs DROP CONSTRAINT IF EXISTS chk_marketplace_product_catalogs_stock_status;
ALTER TABLE marketplace_product_catalogs
    ADD CONSTRAINT chk_marketplace_product_catalogs_stock_status
        CHECK (stock_status IS NULL OR stock_status IN ('in_stock', 'out_of_stock', 'in_transit', 'at_customs', 'on_order'));

COMMENT ON COLUMN marketplace_product_catalogs.stock_status IS '[label:"Stock status of this item: in_stock, out_of_stock, in_transit, at_customs or on_order. Empty when not tracked."]';
COMMENT ON COLUMN marketplace_product_catalogs.expected_arrival_at IS '[label:"When the stock is expected to arrive. Used with in_transit, at_customs and on_order."]';

-- 2. Stock movements: one row per change of the plan's stock. quantity_in_inventory on the plan stays the running
--    total; every movement written through the API updates it in the same transaction and stores the result here.
CREATE TABLE IF NOT EXISTS marketplace_stock_movements (
    id                              bigserial PRIMARY KEY,
    uuid                            uuid NOT NULL DEFAULT gen_random_uuid(),
    marketplace_product_catalog_id  bigint NOT NULL REFERENCES marketplace_product_catalogs (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    movement_type                   text NOT NULL,
    quantity                        integer NOT NULL,
    quantity_after                  integer NOT NULL,
    moved_at                        timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes                           text,
    crm_opportunity_id              bigint REFERENCES crm_opportunities (id) ON UPDATE RESTRICT ON DELETE SET NULL,
    crm_quote_id                    bigint REFERENCES crm_quotes (id) ON UPDATE RESTRICT ON DELETE SET NULL,
    iam_account_id                  bigint NOT NULL REFERENCES iam_accounts (id),
    iam_user_id                     bigint REFERENCES iam_users (id),
    created_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at                      timestamp with time zone,
    CONSTRAINT chk_marketplace_stock_movements_type
        CHECK (movement_type IN ('incoming', 'sale', 'rental_out', 'rental_return', 'adjustment')),
    CONSTRAINT chk_marketplace_stock_movements_quantity
        CHECK (quantity <> 0)
);

CREATE UNIQUE INDEX IF NOT EXISTS marketplace_stock_movements_uuid_key
    ON marketplace_stock_movements (uuid);
CREATE INDEX IF NOT EXISTS marketplace_stock_movements_catalog_moved_at_idx
    ON marketplace_stock_movements (marketplace_product_catalog_id, moved_at DESC) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS marketplace_stock_movements_account_idx
    ON marketplace_stock_movements (iam_account_id) WHERE deleted_at IS NULL;

COMMENT ON TABLE marketplace_stock_movements IS 'Stock history of a plan: incoming shipments, sales, rentals out and back, adjustments.';
COMMENT ON COLUMN marketplace_stock_movements.movement_type IS '[label:"incoming, sale, rental_out, rental_return or adjustment"]';
COMMENT ON COLUMN marketplace_stock_movements.quantity IS '[label:"Signed change of the stock: positive adds, negative removes. Never 0."]';
COMMENT ON COLUMN marketplace_stock_movements.quantity_after IS '[ro] [label:"Stock of the plan right after this movement."]';
COMMENT ON COLUMN marketplace_stock_movements.moved_at IS '[label:"When the stock moved."]';
COMMENT ON COLUMN marketplace_stock_movements.crm_opportunity_id IS '[label:"Optional opportunity this movement belongs to."]';
COMMENT ON COLUMN marketplace_stock_movements.crm_quote_id IS '[label:"Optional quote this movement belongs to."]';

COMMIT;

-- Rollback (by hand):
-- BEGIN;
-- DROP VIEW IF EXISTS marketplace_product_catalogs_perspective;  -- then re-create it from the previous version of its file
-- DROP TABLE IF EXISTS marketplace_stock_movements;
-- ALTER TABLE marketplace_product_catalogs DROP CONSTRAINT IF EXISTS chk_marketplace_product_catalogs_stock_status;
-- ALTER TABLE marketplace_product_catalogs DROP COLUMN IF EXISTS expected_arrival_at;
-- ALTER TABLE marketplace_product_catalogs DROP COLUMN IF EXISTS stock_status;
-- COMMIT;
