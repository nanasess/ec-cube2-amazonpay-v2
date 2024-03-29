ALTER TABLE dtb_payment ADD module_code text;
INSERT INTO dtb_payment
(payment_id, payment_method, charge, rule_max, rank, note, fix, status, del_flg, creator_id, create_date, update_date, payment_image, upper_rule, charge_flg, rule_min, upper_rule_max, module_id, module_path, memo01, memo02, memo03, memo04, memo05, memo06, memo07, memo08, memo09, memo10, module_code)
VALUES(999998, 'Amazon Pay', 0, 0, 3, NULL, 2, 1, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'amazonpay_v2');
INSERT INTO dtb_payment_options (deliv_id, payment_id, rank) VALUES (1, 999998, 5);
INSERT INTO dtb_payment_options (deliv_id, payment_id, rank) VALUES (2, 999998, 6);
ALTER TABLE dtb_customer ADD amazonpay_buyer_id text;
CREATE INDEX dtb_customer_amazonpay_buyer_id_idx ON dtb_customer (amazonpay_buyer_id);
ALTER TABLE dtb_other_deliv ADD amazonpay_shipping_address_flg smallint;
