<?php
/**
* 2002-2015 TemplateMonster
*
* TM Category Products
*
* NOTICE OF LICENSE
*
* This source file is subject to the General Public License (GPL 2.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/GPL-2.0
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade the module to newer
* versions in the future.
*
*  @author    TemplateMonster (Alexander Grosul)
*  @copyright 2002-2015 TemplateMonster
*  @license   http://opensource.org/licenses/GPL-2.0 General Public License (GPL 2.0)
*/

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'tmcategoryproducts` (
    `id_tab` int(11) NOT NULL AUTO_INCREMENT,
	`category` int(11) NOT NULL,
	`num` int(11) NOT NULL,
	`mode` int(11) NOT NULL,
	`status` int(11) NOT NULL,
	`use_carousel` int(11) NOT NULL,
    PRIMARY KEY  (`id_tab`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'tmcategoryproducts_shop` (
    `id_tab` int(11) NOT NULL,
	`id_shop` int(11) NOT NULL,
    PRIMARY KEY  (`id_tab`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
