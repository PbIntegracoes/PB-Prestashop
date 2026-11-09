CREATE TABLE IF NOT EXISTS `PREFIX_pb_transaction` (
    `id_pb_transaction` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order` INT UNSIGNED NOT NULL,
    `id_cart` INT UNSIGNED DEFAULT NULL,
    `pb_order_id` VARCHAR(64) DEFAULT NULL,
    `pb_charge_id` VARCHAR(64) DEFAULT NULL,
    `payment_method` VARCHAR(32) NOT NULL,
    `status` VARCHAR(32) NOT NULL,
    `amount_cents` INT UNSIGNED NOT NULL DEFAULT 0,
    `payload` LONGTEXT,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_pb_transaction`),
    KEY `id_order` (`id_order`),
    KEY `pb_order_id` (`pb_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
