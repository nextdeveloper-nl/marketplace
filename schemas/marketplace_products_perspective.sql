-- PostgreSQL
-- VIEW (read-only; re-run this file with CREATE OR REPLACE VIEW whenever the SELECT needs to change)

CREATE OR REPLACE VIEW marketplace_products_perspective AS
SELECT id,
    uuid,
    name,
    description,
    content,
    highlights,
    subscription_type,
    slug,
    version,
    sales_pitch,
    is_service,
    is_in_maintenance,
    is_public,
    is_invisible,
    is_active,
    is_approved,
    ( SELECT n_cc.name
           FROM common_categories n_cc
          WHERE n_cc.id = mp.common_category_id) AS category,
    common_category_id,
    ( SELECT n_mm.name
           FROM marketplace_markets n_mm
          WHERE n_mm.id = mp.marketplace_market_id) AS marketplace,
    marketplace_market_id,
    ( SELECT c_ia.name
           FROM iam_accounts c_ia
          WHERE c_ia.id = mp.iam_account_id) AS maintainer,
    ( SELECT c_ia2.description
           FROM iam_accounts c_ia2
          WHERE c_ia2.id = mp.iam_account_id) AS about_maintainer,
    ( SELECT n_ia.fullname
           FROM iam_users n_ia
          WHERE n_ia.id = mp.iam_user_id) AS responsible,
    ( SELECT count(n_mpc.id) AS count
           FROM marketplace_product_catalogs n_mpc
          WHERE n_mpc.marketplace_product_id = mp.id) AS product_catalog_count,
    ( SELECT
                CASE
                    WHEN count(n_mpc2.id) > 0 THEN true
                    ELSE false
                END AS "case"
           FROM marketplace_product_catalogs n_mpc2
          WHERE n_mpc2.marketplace_product_id = mp.id AND n_mpc2.trial_date > 0) AS has_free_trial,
    ( SELECT n_mpc2.price
           FROM marketplace_product_catalogs n_mpc2
          WHERE n_mpc2.marketplace_product_id = mp.id
          ORDER BY n_mpc2.price
         LIMIT 1) AS starting_from,
    ( SELECT n_cc.code
           FROM common_currencies n_cc
          WHERE n_cc.id = (( SELECT n_mpc3.common_currency_id
                   FROM marketplace_product_catalogs n_mpc3
                  WHERE n_mpc3.marketplace_product_id = mp.id
                  ORDER BY n_mpc3.price
                 LIMIT 1))) AS currency_code,
    ( SELECT n_pa.meeting_link
           FROM partnership_accounts n_pa
          WHERE n_pa.iam_account_id = mp.iam_account_id) AS partner_meeting_link,
    refund_policy,
    after_sales_introduction,
    support_content,
    eula,
    tags,
    iam_account_id,
    iam_user_id,
    created_at,
    updated_at,
    deleted_at
   FROM marketplace_products mp;
