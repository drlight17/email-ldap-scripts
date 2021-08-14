#!/usr/bin/php

<?php
/**
 * ldap accounts with aliases syncer KSC
 *
 * @link https://github.com/drlight17/???
 * @author Samoilov Yuri
 * @version 0.3
*/

include("connect.inc"); // connector conf
error_reporting(1);

//***********set config variables************
// output files settings
$log="/var/log/ldap_aliases_sync.log";				// log file path
$mysql_to_ldap_array = "mysql_to_ldap_array.txt"; 	// temp debug file
$ldif_output = "test.ldif";							// temp debug filey
// ldap settings
$srv = $ldap_connect['ldap_srv'];					// ldap server address
$uname = $ldap_connect['ldap_user'];				// ldap bind user
$upasswd = $ldap_connect['ldap_password'];			// ldap bind user password
$dn = $ldap_connect['ldap_base_dn'];				// ldap search base dn
//******************************************

function console_log( $data ){
  echo json_encode( $data );
}

function ldapTimeToUnixTime($ldapTime) {
  $secsAfterADEpoch = $ldapTime / 10000000;
  $ADToUnixConverter = ((1970 - 1601) * 365 - 3 + round((1970 - 1601) / 4)) * 86400;
  return intval($secsAfterADEpoch - $ADToUnixConverter);
}

function unixTimeToLdapTime($unixTime) {
  $ADToUnixConverter = ((1970 - 1601) * 365 - 3 + round((1970 - 1601) / 4)) * 86400;
  $secsAfterADEpoch = intval($ADToUnixConverter + $unixTime);
  return $secsAfterADEpoch * 10000000;
}

function HumanToUnixTime($Time) {
  return strtotime($Time);
}

function isRussian($text) {
    return preg_match('/[А-Яа-яЁё]/u', $text);
}

function get_ldap_attributes($ldap_attributes,$email) {
    $search = "(&(mail=".$email."))";
    global $srv, $uname, $upasswd, $dn;
    $ds=ldap_connect($srv);
    if (!$ds) die("error connect to LDAP server $srv");
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    $r=ldap_bind($ds, $uname, $upasswd);
    if (!$r) die("error bind!");
    $sr=ldap_search($ds, $dn, iconv("utf-8", "cp1251" ,$search), $ldap_attributes);
    if (!$sr) die("search error!");
    $info = ldap_get_entries($ds, $sr);
    return $info;
}

function get_all_ldap_accounts($ldap_attributes) {
    $search = "(&(mail=*))";
    global $srv, $uname, $upasswd, $dn;
    $ds=ldap_connect($srv);
    if (!$ds) die("error connect to LDAP server $srv");
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    $r=ldap_bind($ds, $uname, $upasswd);
    if (!$r) die("error bind!");
    $sr=ldap_search($ds, $dn, iconv("utf-8", "cp1251" ,$search), $ldap_attributes);
    if (!$sr) die("search error!");
    $info = ldap_get_entries($ds, $sr);
    return $info;
}

function print_ldap_elements($ldap_data) {
	foreach($ldap_data as $ldap_array_element){
		echo "uid: ".$ldap_array_element["uid"][0]."\n";
		echo "sn: ".$ldap_array_element["sn"][0]."\n";
		echo "givenName: ".$ldap_array_element["givenName"][0]."\n";
		echo "cn: ".$ldap_array_element["cn"][0]."\n";
		echo "displayName: ".$ldap_array_element["displayName"][0]."\n";
		echo "userPassword: ".$ldap_array_element["userPassword"][0]."\n";
		echo "description: ".$ldap_array_element["description"][0]."\n";
		echo "o: ".$ldap_array_element["o"][0]."\n";
		echo "departmentNumber: ".$ldap_array_element["departmentNumber"][0]."\n";
		echo "l: ".$ldap_array_element["l"][0]."\n";
		echo "roomNumber: ".$ldap_array_element["roomNumber"][0]."\n";
		echo "mail: ".$ldap_array_element["mail"][0]."\n";
		echo "employeeType: ".$ldap_array_element["employeeType"][0]."\n";
		echo "employeeNumber: ".$ldap_array_element["employeeNumber"][0]."\n";
		echo "carLicense: ".$ldap_array_element["carLicense"][0]."\n";
		echo "st: ".$ldap_array_element["st"][0]."\n";
		echo "jpegPhoto: ".$ldap_array_element["jpegPhoto"][0]."\n";
		echo "telephoneNumber: ".$ldap_array_element["telephoneNumber"][0]."\n";
		echo "modifyTimestamp: ".$ldap_array_element["modifyTimestamp"][0]."\n";
		echo "objectClass: inetOrgPerson"."\n";
		echo "objectClass: organizationalPerson"."\n";
		echo "objectClass: person"."\n";
	}
}

function make_html_output ($mysql_data) {
	$w=fopen("mysql_prepared_accounts.html",'a');
	//$info = ldap_get_entries($ds, $sr);
	for ($i=0; $i<$mysql_data["count_records"]; $i++) {
	//$filter='(samaccountname=$info[$i]["samaccountname"])';
	//$res = ldap_search($ds, $dn, $filter);
	//$first = ldap_first_entry($ds, $res);
	//$user_DN = ldap_get_dn($ds, $first);
	fwrite($w,"<b>uid:</b> " . $mysql_data[$i]["uid"] . "<br />");  // 7
	//fwrite($w,"<b>ФИО:</b> " . $mysql_data[$i]["name"][0] . "<br />");  // 7
	//echo "<b>ФИО:</b> " . iconv("cp1251", "utf-8", $mysql_data[$i]["name"][0]) . "<br />";
	fwrite($w,"<b>description:</b> " . $mysql_data[$i]["description"] . "<br />");  // 7
	//echo "<b>должность, телефон:</b> " . iconv("cp1251", "utf-8", $mysql_data[$i]["title"][0]) . "<br />";
	//fwrite($w,"<b>фирма:</b> " . $mysql_data[$i]["company"] . "<br />");  // 7
	//echo "<b>фирма:</b> " . iconv("cp1251", "utf-8", $mysql_data[$i]["company"][0]) . "<br />";
	fwrite($w,"<b>email:</b> <a href=\"mailto:" . $mysql_data[$i]["mail"] . "\">" . $mysql_data[$i]["mail"] . "</a><br />");  // 7
	//fwrite($w,"<b>Последнее изменение пароля:</b> " . date(DateTime::RFC822, ldapTimeToUnixTime($mysql_data[$i]["pwdlastset"][0])) . "<br />");  // 7
	//echo "<b>email:</b> <a href=\"mailto:" . $mysql_data[$i]["mail"][0] . "\">" . $mysql_data[$i]["mail"][0] . "</a><br />";
	fwrite($w,"<b>фото: </b>");  // 7
	$photo = base64_encode($mysql_data[$i]['jpegPhoto']);
	$photo = explode('ENCODING=b;TYPE=png:', $mysql_data[$i]['jpegPhoto']);
	fwrite($w,"<img src=\"data:image/png;base64,".$photo[1]."\" />");
	//fwrite($w,"<img src=\"data:image/jpeg;base64,".$photo[1]."\" />");
	//echo "<b>фото: </b>";
	//if ($photo != "")   // 7//echo "<img src=\"data:image/jpeg;base64,".$photo."\" />";
	//else fwrite($w,"фотографии нет");  // 7//echo "фотографии нет";
	fwrite($w,"<hr />");  // 7
	//echo "<hr />";
	}
	fclose($w);  // 8
}

function make_ldif($mysql_data) {
	global $ldif_output;
	$w=fopen($ldif_output,'a');
	for ($i=0; $i<$mysql_data["count_records"]; $i++) {
		fwrite($w,"dn: uid=".$mysql_data[$i]["uid"].",".$mysql_data[$i]["ou"]."\n");
		fwrite($w,"objectclass: inetOrgPerson\n");
		fwrite($w,"objectclass: organizationalPerson\n");
		fwrite($w,"objectclass: person\n");
		fwrite($w,"uid:".$mysql_data[$i]["uid"]."\n");
		fwrite($w,"sn:".$mysql_data[$i]["sn"]."\n");
		fwrite($w,"givenName:".$mysql_data[$i]["givenName"]."\n");
		fwrite($w,"cn:".$mysql_data[$i]["cn"]."\n");
		fwrite($w,"displayName:".$mysql_data[$i]["displayName"]."\n");
		fwrite($w,"userPassword:".$mysql_data[$i]["userPassword"]."\n");
		fwrite($w,"description:".$mysql_data[$i]["description"]."\n");
		fwrite($w,"o:".$mysql_data[$i]["o"]."\n");
		if ($mysql_data[$i]["departmentNumber"] != " ") {
			fwrite($w,"departmentNumber:".$mysql_data[$i]["departmentNumber"]."\n");
		}
		if ($mysql_data[$i]["l"] != " ") {
			fwrite($w,"l:".$mysql_data[$i]["l"]."\n");
		}
		if ($mysql_data[$i]["roomNumber"] != " ") {
			fwrite($w,"roomNumber:".$mysql_data[$i]["roomNumber"]."\n");
		}
		fwrite($w,"mail:".$mysql_data[$i]["mail"]."\n");
		fwrite($w,"employeeType:".$mysql_data[$i]["employeeType"]."\n");
		fwrite($w,"employeeNumber:".$mysql_data[$i]["employeeNumber"]."\n");
		fwrite($w,"carLicense:".$mysql_data[$i]["carLicense"]."\n");
		fwrite($w,"st:".$mysql_data[$i]["st"]."\n");
		$photo = explode('ENCODING=b;TYPE=png:', $mysql_data[$i]['jpegPhoto']);
		if ($photo[1] != "nothing") {
			fwrite($w,"jpegPhoto:: ".$photo[1]."\n");
		}
		if ($mysql_data[$i]["telephoneNumber"] != " ") {
			fwrite($w,"telephoneNumber:".$mysql_data[$i]["telephoneNumber"]."\n");
		}
		fwrite($w,"\n");
	}
	fclose($w);
}

function addRecord($adddn, $record){
	// remove ou as it is not useful anymore
	//unset($record["ou"]);
	// remove modifytimestamp as it is not useful anymore
	unset($record["modifyTimestamp"]);
	global $srv, $uname, $upasswd, $dn;
	$ds=ldap_connect($srv);
	if (!$ds) die("error connect to LDAP server $srv");
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    $r=ldap_bind($ds, $uname, $upasswd);
    if (!$r) die("error bind!");
	$addProcess = ldap_add($ds, $adddn, $record);
	$error = ldap_error($ds);
	//print_r($record)."\n";
	//echo $error."\n";
	echo $addProcess."\n";
	if (!($addProcess)) die("add error!");
}

function modifyRecord($modifydn, $record){
	// remove ou as it is not useful anymore
	//unset($record["ou"]);
	// remove modifytimestamp as it is not useful anymore
	unset($record["modifyTimestamp"]);
	global $srv, $uname, $upasswd, $dn;
	$ds=ldap_connect($srv);
	if (!$ds) die("error connect to LDAP server $srv");
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    $r=ldap_bind($ds, $uname, $upasswd);
    if (!$r) die("error bind!");
	$modifyProcess = ldap_modify($ds, $modifydn, $record);
	$error = ldap_error($ds);
	//print_r($record)."\n";
	//echo $error."\n";
	echo $modifyProcess."\n";
	if (!($modifyProcess)) die("modify error!");
}

function addRecord_to_alias($adddn, $record){
	// remove ou as it is not useful anymore
	//unset($record["ou"]);
	// remove modifytimestamp as it is not useful anymore
	unset($record["modifyTimestamp"]);
	global $srv, $uname, $upasswd, $dn;
	$ds=ldap_connect($srv);
	if (!$ds) die("error connect to LDAP server $srv");
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    $r=ldap_bind($ds, $uname, $upasswd);
    if (!$r) die("error bind!");
	$addProcess = ldap_mod_add($ds, $adddn, $record);
	$error = ldap_error($ds);
	if (!($addProcess)) {
		//print_r($record)."\n";
		echo "Error adding account ".$record["proxyaddresses"]." to alias ". $adddn.": ".$error.".\n";
	}
	//print_r($record)."\n";
	/*echo $error."\n";
	echo $addProcess."\n";*/
	//if (!($addProcess)) die("add error!");
}

function deleteRecord_from_alias($deletedn, $record){
	// remove ou as it is not useful anymore
	//unset($record["ou"]);
	// remove modifytimestamp as it is not useful anymore
	unset($record["modifyTimestamp"]);
	global $srv, $uname, $upasswd, $dn;
	$ds=ldap_connect($srv);
	if (!$ds) die("error connect to LDAP server $srv");
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    $r=ldap_bind($ds, $uname, $upasswd);
    if (!$r) die("error bind!");
	$deleteProcess = ldap_mod_del($ds, $deletedn, $record);
	$error = ldap_error($ds);
	if (!($addProcess)) {
		//print_r($record)."\n";
		echo "Error deleting account ".$record["proxyaddresses"]." from alias ". $deletedn.": ".$error.".\n";
	}
	//print_r($record)."\n";
	/*echo $error."\n";
	echo $deleteProcess."\n";
	if (!($deleteProcess)) die("add error!");*/
}

function get_auto_aliases () {
	$search = "(objectClass=group)";
	$dn = "ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
    global $srv, $uname, $upasswd;
    $ds=ldap_connect($srv);
    if (!$ds) die("error connect to LDAP server $srv");
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    $r=ldap_bind($ds, $uname, $upasswd);
    if (!$r) die("error bind!");
    $sr=ldap_search($ds, $dn, iconv("utf-8", "cp1251", $search), array(dn));
	$error = ldap_error($ds);
	if (!($sr)) die("search error!");
    $info = ldap_get_entries($ds, $sr);
    return $info;
}

function get_manual_aliases () {
	$search = "(objectClass=group)";
	$dn = "ou=manual,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
    global $srv, $uname, $upasswd;
    $ds=ldap_connect($srv);
    if (!$ds) die("error connect to LDAP server $srv");
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    $r=ldap_bind($ds, $uname, $upasswd);
    if (!$r) die("error bind!");
    $sr=ldap_search($ds, $dn, iconv("utf-8", "cp1251", $search), array(dn));
	$error = ldap_error($ds);
	if (!($sr)) die("search error!");
    $info = ldap_get_entries($ds, $sr);
    return $info;
}

function get_alias_members ($alias) {
    //$search = "(cn=".$alias.")";
	$search = "(".$alias.")";
	$dn = "ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
    global $srv, $uname, $upasswd;
    $ds=ldap_connect($srv);
    if (!$ds) die("error connect to LDAP server $srv");
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    $r=ldap_bind($ds, $uname, $upasswd);
    if (!$r) die("error bind!");
    $sr=ldap_search($ds, $dn, iconv("utf-8", "cp1251", $search), array('proxyAddresses'));
	$error = ldap_error($ds);
	if (!($sr)) die("search error!");
    $info = ldap_get_entries($ds, $sr);
    return $info;
}

function add_new_accounts () {
	global $log;
    $w=fopen($log,'a');
	//echo "Running checking new entries...\n";
	$counter = 0;
	$attributes=array('sAMAccountName','distinguishedName','givenName','sn','cn','displayName','description','o','departNumber','l','roomNumber','mail','employeeType','employeeNumber','carLicense','st','jpegPhoto','telephoneNumber','whenChanged','userAccountControl');
	$ldap_data = get_all_ldap_accounts($attributes);
	$ldap_found = ($ldap_data && $ldap_data['count'] === 1);
	$auto_aliases = get_auto_aliases();
	// start of ldap data processing
	if (!($ldap_found)) {
		for ($i=0; $i<$auto_aliases[count]; $i++ ) {
			$alias = explode(',', $auto_aliases[$i][dn]);
			echo "Checking alias: ".$alias[0]."\n";
			for ($l=0; $l<$ldap_data[count]; $l++ ) {
				echo "Checking email: ".$ldap_data[$l]['mail'][0]."\n";
				echo "UAC flag: ".$ldap_data[$l]['useraccountcontrol'][0]."\n";
				// if account is disabled/locked - don't add it to alias
				if (($ldap_data[$l]['useraccountcontrol'][0] == 514)||($ldap_data[$l]['useraccountcontrol'][0] == 546)||($ldap_data[$l]['useraccountcontrol'][0] == 66050)||($ldap_data[$l]['useraccountcontrol'][0] == 66082)||($ldap_data[$l]['useraccountcontrol'][0] == 2)||($ldap_data[$l]['useraccountcontrol'][0] == 16)||($ldap_data[$l]['useraccountcontrol'][0] == 8388608)) {
					$to_add = false;
					echo "Account with email ".$ldap_data[$l]['mail'][0]." is disabled/locked. Don't add it to aliases!\n";
				} else {
					$to_add = true;
				}
				$alias_members = get_alias_members ($alias[0]);
				//print_r($alias_members);
					for ($k=0; $k<$alias_members[count]; $k++ ) {
						//print_r ($alias_members[$k]['proxyaddresses']);
						for ($j=0; $j<$alias_members[$k]['proxyaddresses'][count]; $j++ ) {
							if ($to_add) {
								echo "Comparing account email ".$ldap_data[$l]['mail'][0]." with ".$alias_members[$k]['proxyaddresses'][$j]."\n";
								if ($ldap_data[$l]['mail'][0] == $alias_members[$k]['proxyaddresses'][$j]) {
									echo "Email was found in alias. Don't add or update!\n";
									$to_add=false;
								};
							};
						};
					};
				// if email wasn't found and not disabled/locked- add it to alias
				
				if ($to_add) {
					$alias_array["proxyaddresses"] = $ldap_data[$l]['mail'][0];
					// check of user OP affiliation
					//echo "Flag!\n";
					//echo $alias[0]."\n";
					if ($alias[0] == "CN=all") {
						$addDN = "CN=all,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
						echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
						fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.ru\n");
						addRecord_to_alias ($addDN,$alias_array); // uncomment in production
						$counter++;
					} else {
						if ($alias[0] == "CN=all-adm") {
							// TODO check this ADM alias exception "" or null
							if (($ldap_data[$l]['o'][0] == "") || ($ldap_data[$l]['o'][0] == null)) {
								$addDN = "CN=all-adm,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
								echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
								fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.ru\n");
								addRecord_to_alias ($addDN,$alias_array); // uncomment in production
								$counter++;
							}
						}
						elseif ($alias[0] == "CN=all-chemy") {
							if ($ldap_data[$l]['o'][0] == "ИХТРЭМС") {
								$addDN = "CN=all-chemy,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
								echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
								fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.run");
								addRecord_to_alias ($addDN,$alias_array); // uncomment in production
								$counter++;
							}
						}
						elseif ($alias[0] == "CN=all-geo") {
							if ($ldap_data[$l]['o'][0] == "ГИ") {
								$addDN = "CN=all-geo,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
								echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
								fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.ru\n");
								addRecord_to_alias ($addDN,$alias_array); // uncomment in production
								$counter++;
							}
						}
						elseif ($alias[0] == "CN=all-goi") {
							if ($ldap_data[$l]['o'][0] == "ГоИ") {
								$addDN = "CN=all-goi,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
								echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
								fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.ru\n");
								addRecord_to_alias ($addDN,$alias_array); // uncomment in production
								$counter++;
							}
						}
						elseif ($alias[0] == "CN=all-ien") {
							if ($ldap_data[$l]['o'][0] == "ЦЭС") {
								$addDN = "CN=all-ien,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
								echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
								fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.ru\n");
								addRecord_to_alias ($addDN,$alias_array); // uncomment in production
								$counter++;
							}
						}
						elseif ($alias[0] == "CN=all-iep") {
							if ($ldap_data[$l]['o'][0] == "ИЭП") {
								$addDN = "CN=all-iep,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
								echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
								fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.ru\n");
								addRecord_to_alias ($addDN,$alias_array); // uncomment in production
								$counter++;
							}
						}
						elseif ($alias[0] == "CN=all-iimm") {
							if ($ldap_data[$l]['o'][0] == "ИИММ") {
								$addDN = "CN=all-iimm,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
								echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
								fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.ru\n");
								addRecord_to_alias ($addDN,$alias_array); // uncomment in production
								$counter++;
							}
						}
						elseif ($alias[0] == "CN=all-inep") {
							if ($ldap_data[$l]['o'][0] == "ИППЭС") {
								$addDN = "CN=all-inep,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
								echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
								fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.ru\n");
								addRecord_to_alias ($addDN,$alias_array); // uncomment in production
								$counter++;
							}
						}
						elseif ($alias[0] == "CN=all-isc") {
							if ($ldap_data[$l]['o'][0] == "ЦГП") {
								$addDN = "CN=all-isc,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
								echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
								fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.ru\n");
								addRecord_to_alias ($addDN,$alias_array); // uncomment in production
								$counter++;
							}
						}
						elseif ($alias[0] == "CN=all-vita") {
							if ($ldap_data[$l]['o'][0] == "НИЦ МБП") {
								$addDN = "CN=all-vita,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
								echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
								fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.ru\n");
								addRecord_to_alias ($addDN,$alias_array); // uncomment in production
								$counter++;
							}
						}
						elseif ($alias[0] == "CN=all-pubgi") {
							if ($ldap_data[$l]['o'][0] == "НИЦ МБП") {
								$addDN = "CN=all-vita,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
								echo "Added ".$ldap_data[$l]['mail'][0]." to alias ".$alias[0]."\n";
								fwrite($w,"Аккаунт ".$ldap_data[$l]['mail'][0]." добавлен в рассылку ".explode('=', $alias[0])[1]."@ksc.ru\n");
								addRecord_to_alias ($addDN,$alias_array); // uncomment in production
								$counter++;
							}
						}
					}
				}
			}
		}
	}
    fclose($w);
    return $counter;
}

function delete_nonexistent_accounts (){
	global $log;
	$w=fopen($log,'a');
	$counter = 0;
	//echo "Running cleanup...\n";
	$attributes=array('sAMAccountName','distinguishedName','givenName','sn','cn','displayName','description','o','departNumber','l','roomNumber','mail','employeeType','employeeNumber','carLicense','st','jpegPhoto','telephoneNumber','whenChanged','userAccountControl');
	// auto aliases cleanup
	$auto_aliases = get_auto_aliases();
	for ($i=0; $i<$auto_aliases[count]; $i++ ) {
		$alias = explode(',', $auto_aliases[$i][dn]);
		//echo "Checking alias: ".$alias[0]."\n";
		$alias_members = get_alias_members ($alias[0]);
		for ($k=0; $k<$alias_members[count]; $k++ ) {
			for ($j=0; $j<$alias_members[$k]['proxyaddresses'][count]; $j++ ) {
				//echo "Checking if ".$alias_members[$k]['proxyaddresses'][$j]." is in ldap...\n";
				$ldap_data = get_ldap_attributes($attributes,$alias_members[$k]['proxyaddresses'][$j]);
				//echo "UAC flag: ".$ldap_data[0]['useraccountcontrol'][0]."\n";
				$ldap_found = ($ldap_data && $ldap_data['count'] === 1);
				// check ldap account state: if disabled/locked - also delete from alias to prevent send email errors (514 || 546 || 66050 || 66082 || 2 || 16 || 8388608)
				if ((!($ldap_found))||($ldap_data[0]['useraccountcontrol'][0] == 514)||($ldap_data[0]['useraccountcontrol'][0] == 546)||($ldap_data[0]['useraccountcontrol'][0] == 66050)||($ldap_data[0]['useraccountcontrol'][0] == 66082)||($ldap_data[0]['useraccountcontrol'][0] == 2)||($ldap_data[0]['useraccountcontrol'][0] == 16)||($ldap_data[0]['useraccountcontrol'][0] == 8388608)) {
					$alias_array["proxyaddresses"] = $alias_members[$k]['proxyaddresses'][$j];
					$deleteDN = $alias[0].",ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
					deleteRecord_from_alias ($deleteDN,$alias_array); // uncomment in production
					$counter++;
					fwrite($w,"Аккаунт ".$alias_members[$k]['proxyaddresses'][$j]." удален из рассылки ".explode('=', $alias[0])[1]."@ksc.ru\n");
				};
			};
		};
	}
	// manual aliases cleanup
	$manual_aliases = get_manual_aliases();
	//print_r($manual_aliases);
	for ($i=0; $i<$manual_aliases[count]; $i++ ) {
		$alias = explode(',', $manual_aliases[$i][dn]);
		$cur_ou = explode(',', $manual_aliases[$i][dn]);
		//echo "Checking alias: ".$alias[0]."\n";
		$alias_members = get_alias_members ($alias[0]);
		for ($k=0; $k<$alias_members[count]; $k++ ) {
			for ($j=0; $j<$alias_members[$k]['proxyaddresses'][count]; $j++ ) {
				//echo "Checking if ".$alias_members[$k]['proxyaddresses'][$j]." is in ldap...\n";
				$ldap_data = get_ldap_attributes($attributes,$alias_members[$k]['proxyaddresses'][$j]);
				//print_R($ldap_data[0]);
				//echo "UAC flag: ".$ldap_data[0]['useraccountcontrol'][0]."\n";
				$ldap_found = ($ldap_data && $ldap_data['count'] === 1);
				// check ldap account state: if disabled/locked - also delete from alias to prevent send email errors (514 || 546 || 66050 || 66082 || 2 || 16 || 8388608)
				if ((!($ldap_found))||($ldap_data[0]['useraccountcontrol'][0] == 514)||($ldap_data[0]['useraccountcontrol'][0] == 546)||($ldap_data[0]['useraccountcontrol'][0] == 66050)||($ldap_data[0]['useraccountcontrol'][0] == 66082)||($ldap_data[0]['useraccountcontrol'][0] == 2)||($ldap_data[0]['useraccountcontrol'][0] == 16)||($ldap_data[0]['useraccountcontrol'][0] == 8388608)) {
					$alias_array["proxyaddresses"] = $alias_members[$k]['proxyaddresses'][$j];
					$deleteDN = $alias[0].",".$cur_ou[1].",ou=manual,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
					deleteRecord_from_alias ($deleteDN,$alias_array); // uncomment in production
					$counter++;
					fwrite($w,"Аккаунт ".$alias_members[$k]['proxyaddresses'][$j]." удален из рассылки ".explode('=', $alias[0])[1]."@ksc.ru\n");
				};
			};
		};
	}

	fclose($w);
    return $counter;
}
//***********************************************************************************************
echo "Запущена синхронизация ".(date(DATE_RFC822))."\n";
$w=fopen($log,'a');
fwrite($w,"Запущена синхронизация ".(date(DATE_RFC822))."\n");
fclose($w);
$added = add_new_accounts ();
$deleted = delete_nonexistent_accounts ();
$processed = $added + $deleted;
//***********************************************************************************************
echo "Добавлено ".$added." записей.\n";
echo "Удалено ".$deleted." записей.\n";
echo "Всего обработано ".$processed." записей.\n";
echo "Завершена синхронизация ".(date(DATE_RFC822))."\n";
echo "См. подробный лог в ".$log."\n"."\n";
$w=fopen($log,'a');
fwrite($w,"Добавлено ".$added." записей.\n");
fwrite($w,"Удалено ".$deleted." записей.\n");
fwrite($w,"Всего обработано ".$processed." записей.\n");
fwrite($w,"Завершена синхронизация ".(date(DATE_RFC822))."\n"."\n");
fclose($w);

?>

