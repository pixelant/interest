#
# Table structure for table 'tx_interest_api_token'
#
#
CREATE TABLE tx_interest_api_token (
     uid int(11) DEFAULT '0' NOT NULL auto_increment,
     token varchar(255) DEFAULT '' NOT NULL,
     be_user varchar(255) DEFAULT '' NOT NULL,
     password varchar(255) DEFAULT '' NOT NULL,
     expires_in bigint(20) DEFAULT '0' NOT NULL,
     cached_data mediumblob,

     PRIMARY KEY (uid)
);

CREATE TABLE tx_interest_remote_id_mapping (
		uid int(11) DEFAULT '0' NOT NULL auto_increment,
		pid int(11) DEFAULT '0' NOT NULL,
		tstamp int(11) DEFAULT '0' NOT NULL,
		crdate int(11) DEFAULT '0' NOT NULL,
		cruser_id int(11) DEFAULT '0' NOT NULL,
    remote_id varchar(255) DEFAULT '' NOT NULL,
    table varchar(255) DEFAULT '0' NOT NULL,
    uid_local int(11) DEFAULT '0' NOT NULL,
    record_hash varchar(255) DEFAULT '0' NOT NULL,
    manual smallint (3) DEFAULT '0' NOT NULL,

		PRIMARY KEY (uid),
    KEY remote_id (remote_id),
    KEY local_side (table, uid_local)
);

CREATE TABLE tx_interest_pending_relations (
    remote_id varchar(255) DEFAULT '' NOT NULL,
    table varchar(255) DEFAULT '0' NOT NULL,
    field varchar(255) DEFAULT '0' NOT NULL,
    record_uid int(11) DEFAULT '0' NOT NULL,

    KEY local_record (table, field, record_uid),
    KEY remote_id (remote_id)
);

CREATE TABLE tx_interest_log (
	uid int(11) DEFAULT '0' NOT NULL auto_increment,
	timestamp int(11) DEFAULT '0' NOT NULL,
	execution_time int(11) DEFAULT '0' NOT NULL,
	status_code int(11) DEFAULT '0' NOT NULL,
	method varchar(16) DEFAULT '' NOT NULL,
	uri text DEFAULT '' NOT NULL,
	request_headers text DEFAULT '' NOT NULL,
	request_body mediumtext DEFAULT '' NOT NULL,
	response_headers text DEFAULT '' NOT NULL,
	response_body mediumtext DEFAULT '' NOT NULL,

	PRIMARY KEY (uid)
);

CREATE TABLE tx_interest_deferred_operation (
	uid int(11) DEFAULT '0' NOT NULL auto_increment,
	crdate int(11) DEFAULT '0' NOT NULL,
	dependent_remote_id varchar(255) DEFAULT '' NOT NULL,
	class varchar(255) DEFAULT '' NOT NULL,
	arguments mediumtext DEFAULT '' NOT NULL,

  PRIMARY KEY (uid),
	KEY dependent_remote_id (dependent_remote_id),
	KEY crdate (crdate),
);
