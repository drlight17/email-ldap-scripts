<?php
  $mysql_connect = array
  (
        "host_db"               => "mail.example.com",
        "user"                  => "mail_user",
        "pass"                  => "mail_password",
        "db_mail"               => "mail",
        "tbl_src"               => "ldap_export"
  );
  $ldap_connect = array
  (
	"ldap_srv" 		=> "dc.example.com",
	"ldap_user"		=> "CN=Administrator,CN=Users,DC=example,DC=com",
	"ldap_password"		=> "domain_admin_password",
	"ldap_base_dn" 		=> "ou=accounts,dc=example,dc=com",
	"ldap_domain" 		=> "example.com"
  );
?>
