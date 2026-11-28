-- PostgreSQL
-- VIEW (read-only; re-run this file with CREATE OR REPLACE VIEW whenever the SELECT needs to change)
-- New columns go at the END: CREATE OR REPLACE VIEW cannot insert or reorder columns.

CREATE OR REPLACE VIEW marketplace_product_catalogs_perspective AS
SELECT id,
    uuid,
    name,
    price,
    args,
    tags,
    quantity_in_inventory,
    trial_date,
    sku,
    is_public,
    features,
    marketplace_product_id,
    ( SELECT n_mp.name
           FROM marketplace_products n_mp
          WHERE n_mp.id = c.marketplace_product_id) AS product,
    iam_account_id,
    iam_user_id,
    created_at,
    updated_at,
    deleted_at,
    stock_status,
    expected_arrival_at,
    offering_type,
    billing_period,
    min_rental_duration,
    max_rental_duration
   FROM marketplace_product_catalogs c;
