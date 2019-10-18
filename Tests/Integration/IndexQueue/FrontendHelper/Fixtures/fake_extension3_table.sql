CREATE TABLE tx_fakeextension3_pages_mm (
   uid_local int(11) unsigned DEFAULT '0' NOT NULL,
   uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
   tablenames varchar(90) DEFAULT '' NOT NULL,
   fieldname varchar(90) DEFAULT '' NOT NULL,
   sorting int(11) unsigned DEFAULT '0' NOT NULL,
   sorting_foreign int(11) unsigned DEFAULT '0' NOT NULL,
   KEY uid_local (uid_local),
   KEY uid_foreign (uid_foreign)
);

CREATE TABLE pages (
   page_relations int(11) unsigned DEFAULT '0' NOT NULL,
   relations varchar(90) DEFAULT '' NOT NULL
);