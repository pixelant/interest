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
