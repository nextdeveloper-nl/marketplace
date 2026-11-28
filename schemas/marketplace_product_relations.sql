-- PostgreSQL
-- Product families (plusclouds/leo4#1397). Created by 2026_11_28_product_families_and_offering_types.sql.

CREATE TABLE marketplace_product_relations (
    id                              bigint NOT NULL DEFAULT nextval('marketplace_product_relations_id_seq'::regclass),
    uuid                            uuid NOT NULL DEFAULT gen_random_uuid(),
    marketplace_product_id          bigint NOT NULL, -- [label:"The main product (the family), e.g. the robot."]
    related_marketplace_product_id  bigint NOT NULL, -- [label:"The accessory, spare part or bundled item that belongs to the family."]
    relation_type                   text NOT NULL, -- [label:"accessory, spare_part or bundle"]
    iam_account_id                  bigint NOT NULL,
    iam_user_id                     bigint,
    created_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at                      timestamp with time zone,
    CONSTRAINT marketplace_product_relations_pkey PRIMARY KEY (id),
    CONSTRAINT chk_marketplace_product_relations_type CHECK (relation_type IN ('accessory', 'spare_part', 'bundle')),
    CONSTRAINT chk_marketplace_product_relations_not_self CHECK (marketplace_product_id <> related_marketplace_product_id)
);
