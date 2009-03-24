# Licensed under The MIT License
# Redistributions of files must retain the above copyright notice.
# http://www.opensource.org/licenses/mit-license.php The MIT License

DROP TABLE IF EXISTS `assets`;
CREATE TABLE IF NOT EXISTS `assets` (
  `id` int(10) NOT NULL auto_increment,
  `created` datetime default NULL,
  `modified` datetime default NULL,
  `parent_id` int(10) default '0',
  `model` varchar(50) NOT NULL default '',
  `foreign_key` int(10) NOT NULL default '0',
  `filename` varchar(255) NOT NULL default '',
  `field` varchar(255) NOT NULL default '',
  `version` varchar(255) NOT NULL default '',
  `mime` varchar(255) NOT NULL,
  `size` varchar(255) NOT NULL default '',
  `width` int(10) default NULL,
  `height` int(10) default NULL,
  PRIMARY KEY  (`id`),
  KEY `model` (`model`(25),`foreign_key`),
  KEY `parent_id` (`parent_id`),
  KEY `field` (`field`(25),`version`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;