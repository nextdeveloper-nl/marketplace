-- PostgreSQL
-- plusclouds/leo4#1397: product families (accessories, spare parts, bundles) and plan offering types.
--
-- NOT APPLIED. Run by hand, once, on the shared DB BEFORE deploying the package version that ships this file
-- (the plan model lists the new columns as fillable, so a plan save that carries offering_type fails until they exist).
-- Requires 2026_11_28_stock_status_and_movements.sql first (the perspective view files below include its columns).
-- Afterwards re-run schemas/marketplace_product_catalogs_perspective.sql and schemas/marketplace_products_perspective.sql
-- so the lists return the new columns.
-- Safe to re-run: every statement is IF NOT EXISTS / DROP ... IF EXISTS first.

BEGIN;

-- 1. Kind of product. Reuses the existing product_type enum: 'product' = main product, 'service' = service.
--    Two values are added for the family members. ADD VALUE is allowed inside a transaction on PostgreSQL 12+;
--    the new values cannot be used until this transaction commits (nothing below uses them).
ALTER TYPE product_type ADD VALUE IF NOT EXISTS 'accessory';
ALTER TYPE product_type ADD VALUE IF NOT EXISTS 'spare_part';

-- 2. What a plan sells and how often it is billed. NULL = not set (every existing row; nothing is backfilled:
--    the product's subscription_type is 'hourly' on 11 000+ physical products, so it cannot be trusted as a period).
ALTER TABLE marketplace_product_catalogs
    ADD COLUMN IF NOT EXISTS offering_type        text,
    ADD COLUMN IF NOT EXISTS billing_period       text,
    ADD COLUMN IF NOT EXISTS min_rental_duration  integer,
    ADD COLUMN IF NOT EXISTS max_rental_duration  integer;

ALTER TABLE marketplace_product_catalogs DROP CONSTRAINT IF EXISTS chk_marketplace_product_catalogs_offering_type;
ALTER TABLE marketplace_product_catalogs
    ADD CONSTRAINT chk_marketplace_product_catalogs_offering_type
        CHECK (offering_type IS NULL OR offering_type IN ('sale', 'short_term_rental', 'long_term_rental', 'service'));

ALTER TABLE marketplace_product_catalogs DROP CONSTRAINT IF EXISTS chk_marketplace_product_catalogs_billing_period;
ALTER TABLE marketplace_product_catalogs
    ADD CONSTRAINT chk_marketplace_product_catalogs_billing_period
        CHECK (billing_period IS NULL OR billing_period IN ('one_time', 'day', 'week', 'month', 'year'));

ALTER TABLE marketplace_product_catalogs DROP CONSTRAINT IF EXISTS chk_marketplace_product_catalogs_rental_duration;
ALTER TABLE marketplace_product_catalogs
    ADD CONSTRAINT chk_marketplace_product_catalogs_rental_duration
        CHECK (
            (min_rental_duration IS NULL OR min_rental_duration > 0)
            AND (max_rental_duration IS NULL OR max_rental_duration > 0)
            AND (min_rental_duration IS NULL OR max_rental_duration IS NULL OR min_rental_duration <= max_rental_duration)
        );

COMMENT ON COLUMN marketplace_product_catalogs.offering_type IS '[label:"What this plan sells: sale, short_term_rental, long_term_rental (Robot-as-a-Service) or service. Empty when not set."]';
COMMENT ON COLUMN marketplace_product_catalogs.billing_period IS '[label:"How often the price is charged: one_time, day, week, month or year. Empty when not set."]';
COMMENT ON COLUMN marketplace_product_catalogs.min_rental_duration IS '[label:"Shortest rental, in billing periods (e.g. 3 with month = at least 3 months)."]';
COMMENT ON COLUMN marketplace_product_catalogs.max_rental_duration IS '[label:"Longest rental, in billing periods."]';

-- 3. Product families: a main product (robot) and its accessories, spare parts and bundled items.
--    Many-to-many: the same accessory can be linked to several main products.
CREATE TABLE IF NOT EXISTS marketplace_product_relations (
    id                              bigserial PRIMARY KEY,
    uuid                            uuid NOT NULL DEFAULT gen_random_uuid(),
    marketplace_product_id          bigint NOT NULL REFERENCES marketplace_products (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    related_marketplace_product_id  bigint NOT NULL REFERENCES marketplace_products (id) ON UPDATE RESTRICT ON DELETE CASCADE,
    relation_type                   text NOT NULL,
    iam_account_id                  bigint NOT NULL REFERENCES iam_accounts (id),
    iam_user_id                     bigint REFERENCES iam_users (id),
    created_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at                      timestamp with time zone,
    CONSTRAINT chk_marketplace_product_relations_type
        CHECK (relation_type IN ('accessory', 'spare_part', 'bundle')),
    CONSTRAINT chk_marketplace_product_relations_not_self
        CHECK (marketplace_product_id <> related_marketplace_product_id)
);

CREATE UNIQUE INDEX IF NOT EXISTS marketplace_product_relations_uuid_key
    ON marketplace_product_relations (uuid);
-- One live link per (main product, item, type). Soft-deleted rows do not block a re-link.
CREATE UNIQUE INDEX IF NOT EXISTS marketplace_product_relations_unique_live
    ON marketplace_product_relations (marketplace_product_id, related_marketplace_product_id, relation_type)
    WHERE deleted_at IS NULL;
-- "Which families is this accessory in?"
CREATE INDEX IF NOT EXISTS marketplace_product_relations_related_idx
    ON marketplace_product_relations (related_marketplace_product_id) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS marketplace_product_relations_account_idx
    ON marketplace_product_relations (iam_account_id) WHERE deleted_at IS NULL;

COMMENT ON TABLE marketplace_product_relations IS 'Product families: the accessories, spare parts and bundled items of a main product. Many-to-many.';
COMMENT ON COLUMN marketplace_product_relations.marketplace_product_id IS '[label:"The main product (the family), e.g. the robot."]';
COMMENT ON COLUMN marketplace_product_relations.related_marketplace_product_id IS '[label:"The accessory, spare part or bundled item that belongs to the family."]';
COMMENT ON COLUMN marketplace_product_relations.relation_type IS '[label:"accessory, spare_part or bundle"]';

COMMIT;

-- Rollback (by hand). Enum values cannot be dropped; set the rows back first and leave the two values in place:
-- BEGIN;
-- UPDATE marketplace_products SET product_type = 'product' WHERE product_type IN ('accessory', 'spare_part');
-- DROP VIEW IF EXISTS marketplace_product_catalogs_perspective;  -- then re-create both views from the previous
-- DROP VIEW IF EXISTS marketplace_products_perspective;          -- version of their files
-- DROP TABLE IF EXISTS marketplace_product_relations;
-- ALTER TABLE marketplace_product_catalogs DROP CONSTRAINT IF EXISTS chk_marketplace_product_catalogs_rental_duration;
-- ALTER TABLE marketplace_product_catalogs DROP CONSTRAINT IF EXISTS chk_marketplace_product_catalogs_billing_period;
-- ALTER TABLE marketplace_product_catalogs DROP CONSTRAINT IF EXISTS chk_marketplace_product_catalogs_offering_type;
-- ALTER TABLE marketplace_product_catalogs DROP COLUMN IF EXISTS max_rental_duration;
-- ALTER TABLE marketplace_product_catalogs DROP COLUMN IF EXISTS min_rental_duration;
-- ALTER TABLE marketplace_product_catalogs DROP COLUMN IF EXISTS billing_period;
-- ALTER TABLE marketplace_product_catalogs DROP COLUMN IF EXISTS offering_type;
-- COMMIT;
