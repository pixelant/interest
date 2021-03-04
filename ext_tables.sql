#
# Table structure for table 'tx_interest_api_token'
#
#
CREATE TABLE tx_interest_api_token (
     uid int(11) DEFAULT '0' NOT NULL auto_increment,
     token varchar(255) DEFAULT '' NOT NULL,
     expires_in bigint(20) DEFAULT '0' NOT NULL,

     PRIMARY KEY (uid)
);

CREATE TABLE tx_interest_test_table (
     title varchar(255) DEFAULT '' NOT NULL,
     relation_field1 varchar(255) DEFAULT '' NOT NULL,
     relation_field2 varchar(255) DEFAULT '' NOT NULL,
);

CREATE TABLE tx_interest_test_table2 (
     title varchar(255) DEFAULT '' NOT NULL,
     field1 varchar(255) DEFAULT '' NOT NULL,
     field2 varchar(255) DEFAULT '' NOT NULL,
);

CREATE TABLE tx_interest_remote_id_mapping (
    uid int(11) DEFAULT '0' NOT NULL auto_increment,
    remote_id varchar(255) DEFAULT '' NOT NULL,
    table varchar(255) DEFAULT '0' NOT NULL,
    uid_local int(11) DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid)
);

CREATE TABLE tx_interest_pending_relations (
    uid int(11) DEFAULT '0' NOT NULL auto_increment,
    remote_id varchar(255) DEFAULT '' NOT NULL,
    table varchar(255) DEFAULT '0' NOT NULL,
    field varchar(255) DEFAULT '0' NOT NULL,
    record_uid int(11) DEFAULT '0' NOT NULL,
    all_field_relations varchar(255) DEFAULT '' NOT NULL,
    timestamp bigint(20) DEFAULT current_timestamp NOT NULL,

    PRIMARY KEY (uid)
);

