-- PostgreSQL
-- VIEW (read-only; re-run this file with CREATE OR REPLACE VIEW whenever the SELECT needs to change)

CREATE OR REPLACE VIEW marketplace_markets_perspective AS
SELECT id,
    uuid,
    name,
    description,
    ( SELECT n_cd.name
           FROM common_domains n_cd
          WHERE n_cd.id = mm.common_domain_id) AS domain,
    common_domain_id,
    ( SELECT n_cc.name
           FROM common_currencies n_cc
          WHERE n_cc.id = mm.common_currency_id) AS currency,
    common_currency_id,
    ( SELECT n_cl.name
           FROM common_languages n_cl
          WHERE n_cl.id = mm.common_language_id) AS language,
    common_language_id,
    ( SELECT n_c.name
           FROM common_countries n_c
          WHERE n_c.id = mm.common_country_id) AS country,
    common_country_id,
    ( SELECT count(n_mp.id) AS count
           FROM marketplace_products n_mp
          WHERE n_mp.marketplace_market_id = mm.id AND n_mp.is_active = true AND n_mp.is_public = true) AS product_count,
    is_public,
    is_active,
    ( SELECT c_ia.name
           FROM iam_accounts c_ia
          WHERE c_ia.id = mm.iam_account_id) AS maintainer,
    ( SELECT n_ia.fullname
           FROM iam_users n_ia
          WHERE n_ia.id = mm.iam_user_id) AS responsible,
    iam_account_id,
    iam_user_id,
    created_at,
    updated_at,
    deleted_at
   FROM marketplace_markets mm;
