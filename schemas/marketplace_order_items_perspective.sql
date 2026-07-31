-- PostgreSQL
-- VIEW (read-only; re-run this file with CREATE OR REPLACE VIEW whenever the SELECT needs to change)

CREATE OR REPLACE VIEW marketplace_order_items_perspective AS
SELECT moi.id,
    moi.uuid,
    mpc.name,
    moi.marketplace_order_id,
    moi.marketplace_product_catalog_id,
    moi.quantity,
    mpc.quantity_in_inventory,
    moi.price_per_item,
    moi.total_price,
    moi.modifiers,
    moi.special_instructions,
    moi.item_data,
    mpc.sku,
    moi.created_at,
    moi.updated_at,
    moi.deleted_at,
    moi.iam_account_id,
    moi.iam_user_id,
    mo.delivery_method,
    mp.name AS product_name,
    mpv.name AS provider_name,
    mo.external_order_number AS order_number,
    mo.status,
    mo.ordered_at,
    mo.accepted_at,
    mo.customer_note
   FROM marketplace_order_items moi
     JOIN marketplace_product_catalogs mpc ON moi.marketplace_product_catalog_id = mpc.id
     JOIN marketplace_orders mo ON moi.marketplace_order_id = mo.id
     JOIN marketplace_products mp ON mo.marketplace_product_id = mp.id
     LEFT JOIN marketplace_providers mpv ON mo.marketplace_provider_id = mpv.id
  WHERE moi.deleted_at IS NULL AND mo.deleted_at IS NULL;
