
#
# Table structure for table 'tx_solr_indexqueue_item'
#
CREATE TABLE tx_solr_indexqueue_item (
	uid int(11) NOT NULL auto_increment,

	root int(11) DEFAULT '0' NOT NULL,

	item_type varchar(255) DEFAULT '' NOT NULL,
	item_uid int(11) DEFAULT '0' NOT NULL,
	indexing_configuration varchar(255) DEFAULT '' NOT NULL,
	changed int(11) DEFAULT '0' NOT NULL,
	indexed int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY changed (changed),
	KEY item_id (item_type,item_uid)
);
