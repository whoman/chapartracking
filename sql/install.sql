CREATE TABLE IF NOT EXISTS `PREFIX_chapar_tracking` (
    `id_tracking` INT(11) NOT NULL AUTO_INCREMENT,
    `id_order` INT(11) NOT NULL,
    `chapar_tracking` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id_tracking`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;
