#!/usr/bin/php

<?php
/**
 * mysql with AD accounts syncer
 *
 * @link https://github.com/drlight17/???
 * @author Samoilov Yuri
 * @version 0.3
*/

include("connect.inc"); // connector conf
error_reporting(1);

//***********set config variables************
// mysql settings
$db_src=$mysql_connect['tbl_src'];      			// addressbook view from mail
$db_mail=$mysql_connect['db_mail'];					// exim db name
$host_db=$mysql_connect['host_db'];     			// host with exim db
$user=$mysql_connect['user'];						// mysql user
$passwd=$mysql_connect['pass'];						// mysql password
// output files settings
$log="/var/log/ldap_sync.log";						// log file path
$mysql_to_ldap_array = "mysql_to_ldap_array.txt"; 	// temp debug file
$ldif_output = "test.ldif";							// temp debug filey
// ldap settings
$srv = $ldap_connect['ldap_srv'];					// ldap server address
$uname = $ldap_connect['ldap_user'];				// ldap bind user
$upasswd = $ldap_connect['ldap_password'];			// ldap bind user password
$dn = $ldap_connect['ldap_base_dn'];				// ldap search base dn
$ldap_domain = $ldap_connect['ldap_domain'];		// ldap domain for AD userPrincipalName attr
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

function HumanToUnixTimeUTC($Time) {
  return strtotime($Time."-3 hours"); // convert to UTC
}

function isRussian($text) {
    return preg_match('/[А-Яа-яЁё]/u', $text);
}

function get_ldap_attributes($ldap_attributes,$email) {
    $search = "(&(mail=".$email."))";
	//$search = "(&(mail=*))";
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
	//foreach($ldap_data as $ldap_array_element){
		//echo "uid: ".$ldap_array_element["uid"][0]."\n";
		echo "sAMAccountName: ".$ldap_data["samaccountname"][0]."\n";
		echo "sn: ".$ldap_data["sn"][0]."\n";
		echo "givenName: ".$ldap_data["givenname"][0]."\n";
		echo "cn: ".$ldap_data["cn"][0]."\n";
		echo "displayName: ".$ldap_data["displayname"][0]."\n";
		//echo "userPassword: ".$ldap_data["userPassword"][0]."\n";
		echo "unicodePwd: ".$ldap_data["unicodepwd"][0]."\n";
		echo "description: ".$ldap_data["description"][0]."\n";
		echo "o: ".$ldap_data["o"][0]."\n";
		echo "department: ".$ldap_data["department"][0]."\n";
		echo "l: ".$ldap_data["l"][0]."\n";
		echo "roomNumber: ".$ldap_data["roomnumber"][0]."\n";
		echo "mail: ".$ldap_data["mail"][0]."\n";
		echo "employeeType: ".$ldap_data["employeetype"][0]."\n";
		echo "employeeNumber: ".$ldap_data["employeenumber"][0]."\n";
		echo "carLicense: ".$ldap_data["carlicense"][0]."\n";
		echo "st: ".$ldap_data["st"][0]."\n";
		echo "jpegPhoto: ".$ldap_data["jpegphoto"][0]."\n";
		echo "telephoneNumber: ".$ldap_data["telephonenumber"][0]."\n";
		echo "whenChanged: ".$ldap_data["whenchanged"][0]."\n";
		echo "objectClass: user"."\n";
		echo "objectClass: organizationalperson"."\n";
		echo "objectClass: person"."\n";
	//}
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

function prepare_mail_acc_to_ldif($select_mysql_data) {
	global $ldap_domain;
	$record_number = 0;
	while ($select_array = mysqli_fetch_array($select_mysql_data, MYSQLI_ASSOC))
		{
			$mysql_data[$record_number]['objectclass'][0] = "user";
			$mysql_data[$record_number]['objectclass'][1] = "organizationalPerson";
			$mysql_data[$record_number]['objectclass'][2] = "person";
			$mysql_data[$record_number]["sAMAccountName"] = " ".$select_array["sAMAccountName"];
			$mysql_data[$record_number]["userPrincipalName"] = $mysql_data[$record_number]["sAMAccountName"]."@".$ldap_domain;			
			if (isRussian($select_array["sn"])) {
				$mysql_data[$record_number]["sn"] = ": ".base64_encode($select_array["sn"]);
			} else {
				$mysql_data[$record_number]["sn"] = " ".$select_array["sn"];
			}
			if (isRussian($select_array["givenName"])) {
				$mysql_data[$record_number]["givenName"] = ": ".base64_encode($select_array["givenName"]);
			} else {
				$mysql_data[$record_number]["givenName"] = " ".$select_array["givenName"];
			}
			if (isRussian($select_array["cn"])) {
				$mysql_data[$record_number]["cn"] = ": ".base64_encode($select_array["cn"]);
			} else {
				$mysql_data[$record_number]["cn"] = " ".$select_array["cn"];
			}
			if (isRussian($select_array["displayName"])) {
				$mysql_data[$record_number]["displayName"] = ": ".base64_encode($select_array["displayName"]);
			} else {
				$mysql_data[$record_number]["displayName"] = " ".$select_array["displayName"];
			}
			//$mysql_data[$record_number]["userPassword"] = " ".$select_array["userPassword"];
			if (isRussian($select_array["description"])) {
				$mysql_data[$record_number]["description"] = ": ".base64_encode($select_array["description"]);
			} else {
				$mysql_data[$record_number]["description"] = " ".$select_array["description"];
			}
			if ($select_array["o"] == "ИХТРЭМС") ($mysql_data[$record_number]["ou"] = "ou=chemy,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ГИ") ($mysql_data[$record_number]["ou"] = "ou=geo,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ГоИ") ($mysql_data[$record_number]["ou"] = "ou=goi,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ИИММ") ($mysql_data[$record_number]["ou"] = "ou=iimm,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ИППЭС") ($mysql_data[$record_number]["ou"] = "ou=inep,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ИЭП") ($mysql_data[$record_number]["ou"] = "ou=iep,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "НИЦ МБП") ($mysql_data[$record_number]["ou"] = "ou=vita,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ПАБГИ") ($mysql_data[$record_number]["ou"] = "ou=pabgi,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ЦГП") ($mysql_data[$record_number]["ou"] = "ou=isc,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ЦЭС") ($mysql_data[$record_number]["ou"] = "ou=ien,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ПАБГИ") ($mysql_data[$record_number]["ou"] = "ou=pabgi,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if (isRussian($select_array["o"])) {
				$mysql_data[$record_number]["o"] = ": ".base64_encode($select_array["o"]);
			} else {
				$mysql_data[$record_number]["o"] = " ".$select_array["o"];
			}
			if (isRussian($select_array["department"])) {
				$mysql_data[$record_number]["department"] = ": ".base64_encode($select_array["department"]);
			} else {
				$mysql_data[$record_number]["department"] = " ".$select_array["department"];
			}
			if (isRussian($select_array["l"])) {
				$mysql_data[$record_number]["l"] = ": ".base64_encode($select_array["l"]);
			} else {
				$mysql_data[$record_number]["l"] = " ".$select_array["l"];
			}
			if (isRussian($select_array["roomNumber"])) {
				$mysql_data[$record_number]["roomNumber"] = ": ".base64_encode($select_array["roomNumber"]);
			} else {
				$mysql_data[$record_number]["roomNumber"] = " ".$select_array["roomNumber"];
			}
			$mysql_data[$record_number]["mail"] = " ".$select_array["mail"];
			$mysql_data[$record_number]["employeeType"] = " ".$select_array["employeeType"];
			$mysql_data[$record_number]["employeeNumber"] = " ".$select_array["employeeNumber"];
			$mysql_data[$record_number]["carLicense"] = " ".$select_array["carLicense"];
			$mysql_data[$record_number]["st"] = " ".$select_array["st"];
			/*$photo = explode('ENCODING=b;TYPE=png:', $select_array["jpegPhoto"]);
			if (($photo[1] != "nothing")||($photo[1] !="")) {
				$mysql_data[$record_number]["jpegPhoto"] = " ".$photo[1];
			}	*/		
			$mysql_data[$record_number]["jpegPhoto"] = " ".$select_array["jpegPhoto"];
			if ($select_array["telephoneNumber"] != "") {
				$mysql_data[$record_number]["telephoneNumber"] = " ".$select_array["telephoneNumber"];
			}
			if (isRussian($select_array["cn"])) {
				$mysql_data[$record_number]["distinguishedName"] = ": ".base64_encode("cn=".$select_array["cn"].",".$mysql_data[$record_number]["ou"]);
			} else {
				$mysql_data[$record_number]["distinguishedName"] = " cn=".$select_array["cn"].",".$mysql_data[$record_number]["ou"];
			}
			if ($select_array["active"] == "YES") {
				$mysql_data[$record_number]["userAccountControl"] = 66048;
			} else {
				$mysql_data[$record_number]["userAccountControl"] = 514;
			}
			$record_number+=1;
		}
		$mysql_data["count_records"] = $record_number;
	return $mysql_data;
}

function prepare_mail_acc_to_ldap($select_mysql_data) {
	global $ldap_domain;
	$record_number = 0;
	while ($select_array = mysqli_fetch_array($select_mysql_data, MYSQLI_ASSOC))
		{
			$mysql_data[$record_number]['objectclass'][0] = "user";
			$mysql_data[$record_number]['objectclass'][1] = "organizationalPerson";
			$mysql_data[$record_number]['objectclass'][2] = "person";
			$mysql_data[$record_number]["sAMAccountName"] = $select_array["sAMAccountName"];
			$mysql_data[$record_number]["userPrincipalName"] = $mysql_data[$record_number]["sAMAccountName"]."@".$ldap_domain;
			if ((trim($select_array["sn"]) != "") && ($select_array["sn"] !== NULL)) {
				$mysql_data[$record_number]["sn"] = $select_array["sn"];
			}
			if ((trim($select_array["givenName"]) != "") && ($select_array["givenName"] !== NULL)) {
				$mysql_data[$record_number]["givenName"] = $select_array["givenName"];
			}
			if ((trim($select_array["cn"]) != "") && ($select_array["cn"] !== NULL)) {
				$mysql_data[$record_number]["cn"] = $select_array["cn"];
			} else {
				$mysql_data[$record_number]["cn"] = $select_array["sAMAccountName"];
			}
			if ((trim($select_array["displayName"]) != "") && ($select_array["displayName"] !== NULL)) {
				$mysql_data[$record_number]["displayName"] = $select_array["displayName"];
			} else {
				$mysql_data[$record_number]["displayName"] = $select_array["sAMAccountName"];
			}
			$mysql_data[$record_number]["unicodePwd"] = iconv("UTF-8", "UTF-16LE", '"' . $select_array["clear_password"] . '"');
			//$mysql_data[$record_number]["userPassword"] = $select_array["userPassword"];
			if (($select_array["description"] != "") && ($select_array["description"] !== NULL)) {
				$mysql_data[$record_number]["description"] = $select_array["description"];
			}
			if ($select_array["o"] == "ИХТРЭМС") ($mysql_data[$record_number]["ou"] = "ou=chemy,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ГИ") ($mysql_data[$record_number]["ou"] = "ou=geo,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ГоИ") ($mysql_data[$record_number]["ou"] = "ou=goi,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ИИММ") ($mysql_data[$record_number]["ou"] = "ou=iimm,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ИППЭС") ($mysql_data[$record_number]["ou"] = "ou=inep,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ИЭП") ($mysql_data[$record_number]["ou"] = "ou=iep,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "НИЦ МБП") ($mysql_data[$record_number]["ou"] = "ou=vita,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ПАБГИ") ($mysql_data[$record_number]["ou"] = "ou=pabgi,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ЦГП") ($mysql_data[$record_number]["ou"] = "ou=isc,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ЦЭС") ($mysql_data[$record_number]["ou"] = "ou=ien,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			if ($select_array["o"] == "ПАБГИ") ($mysql_data[$record_number]["ou"] = "ou=pabgi,ou=accounts,ou=KSC_RAS,dc=ksc,dc=test");
			$mysql_data[$record_number]["o"] = $select_array["o"];
			if (($select_array["department"] != "") && ($select_array["department"] !== NULL)) {
				$mysql_data[$record_number]["department"] = $select_array["department"];
			}
			if (($select_array["l"] != "") && ($select_array["l"] !== NULL)) {
				$mysql_data[$record_number]["l"] = $select_array["l"];
			}
			if (($select_array["roomNumber"] != "") && ($select_array["roomNumber"] !== NULL)) {
				$mysql_data[$record_number]["roomNumber"] = $select_array["roomNumber"];
			}
			$mysql_data[$record_number]["mail"] = $select_array["mail"];
			$mysql_data[$record_number]["employeeType"] = $select_array["employeeType"];
			$mysql_data[$record_number]["employeeNumber"] = $select_array["employeeNumber"];
			$mysql_data[$record_number]["carLicense"] = $select_array["carLicense"];
			if (($select_array["st"] != "") && ($select_array["st"] !== NULL)) {
				$mysql_data[$record_number]["st"] = $select_array["st"];
			}
			$photo = explode('ENCODING=b;TYPE=png:', $select_array["jpegPhoto"]);
			//if (($photo[1] != "nothing")||($photo[1] !="")) {
			if ($photo[1] != "nothing") {
				$mysql_data[$record_number]["jpegPhoto"] = base64_decode($photo[1]);
			}
			if (($select_array["telephoneNumber"] != "") && ($select_array["telephoneNumber"] !== NULL)) {
				$mysql_data[$record_number]["telephoneNumber"] = $select_array["telephoneNumber"];
			}
			if (($select_array["whenChanged"] != "") && ($select_array["whenChanged"] !== NULL)) {
				$mysql_data[$record_number]["whenChanged"] = $select_array["whenChanged"];
			}
			$mysql_data[$record_number]["distinguishedName"] = "cn=".$mysql_data[$record_number]["cn"].",".$mysql_data[$record_number]["ou"];
			if ($select_array["active"] == "YES") {
				$mysql_data[$record_number]["userAccountControl"] = 66048;
			} else {
				$mysql_data[$record_number]["userAccountControl"] = 514;
			}				
			$record_number+=1;
		}
		$mysql_data["count_records"] = $record_number;
	return $mysql_data;
}

function make_ldif($mysql_data) {
	global $ldif_output;
	$w=fopen($ldif_output,'a');
	for ($i=0; $i<$mysql_data["count_records"]; $i++) {
		//fwrite($w,"dn: cn=".$mysql_data[$i]["cn"].",".$mysql_data[$i]["ou"]."\n");
		fwrite($w,"dn:".$mysql_data["distinguishedName"]."\n");
		fwrite($w,"objectclass: user\n");
		fwrite($w,"objectclass: organizationalPerson\n");
		fwrite($w,"objectclass: person\n");
		fwrite($w,"sAMAccountName:".$mysql_data[$i]["sAMAccountName"]."\n");
		fwrite($w,"userPrincipalName:".$mysql_data[$i]["userPrincipalName"]."\n");
		fwrite($w,"sn:".$mysql_data[$i]["sn"]."\n");
		fwrite($w,"givenName:".$mysql_data[$i]["givenName"]."\n");
		fwrite($w,"cn:".$mysql_data[$i]["cn"]."\n");
		fwrite($w,"displayName:".$mysql_data[$i]["displayName"]."\n");
		//fwrite($w,"userPassword:".$mysql_data[$i]["userPassword"]."\n");
		fwrite($w,"description:".$mysql_data[$i]["description"]."\n");
		fwrite($w,"o:".$mysql_data[$i]["o"]."\n");
		if ($mysql_data[$i]["department"] != " ") {
			fwrite($w,"department:".$mysql_data[$i]["department"]."\n");
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
		fwrite($w,"distinguishedName:".$mysql_data["distinguishedName"]."\n");
		fwrite($w,"userAccountControl: ".$mysql_data["userAccountControl"]."\n");
		fwrite($w,"\n");
	}
	fclose($w);
}

function make_single_ldif($mysql_data) {
	global $ldif_output;
	$w=fopen($ldif_output,'a');
	//for ($i=0; $i<$mysql_data["count_records"]; $i++) {
		//fwrite($w,"dn: CN=".$mysql_data["cn"].",".$mysql_data["ou"]."\n");
		fwrite($w,"dn:".$mysql_data["distinguishedName"]."\n");
		fwrite($w,"objectclass: user\n");
		fwrite($w,"objectclass: organizationalPerson\n");
		fwrite($w,"objectclass: person\n");
		fwrite($w,"sAMAccountName:".$mysql_data["sAMAccountName"]."\n");
		fwrite($w,"userPrincipalName:".$mysql_data[$i]["userPrincipalName"]."\n");
		fwrite($w,"sn:".$mysql_data["sn"]."\n");
		fwrite($w,"givenName:".$mysql_data["givenName"]."\n");
		fwrite($w,"cn:".$mysql_data["cn"]."\n");
		fwrite($w,"displayName:".$mysql_data["displayName"]."\n");
		//fwrite($w,"userPassword:".$mysql_data["userPassword"]."\n");
		fwrite($w,"description:".$mysql_data["description"]."\n");
		fwrite($w,"o:".$mysql_data["o"]."\n");
		if ($mysql_data["department"] != " ") {
			fwrite($w,"department:".$mysql_data["department"]."\n");
		}
		if ($mysql_data["l"] != " ") {
			fwrite($w,"l:".$mysql_data["l"]."\n");
		}
		if ($mysql_data["roomNumber"] != " ") {
			fwrite($w,"roomNumber:".$mysql_data["roomNumber"]."\n");
		}
		fwrite($w,"mail:".$mysql_data["mail"]."\n");
		fwrite($w,"employeeType:".$mysql_data["employeeType"]."\n");
		fwrite($w,"employeeNumber:".$mysql_data["employeeNumber"]."\n");
		fwrite($w,"carLicense:".$mysql_data["carLicense"]."\n");
		fwrite($w,"st:".$mysql_data["st"]."\n");
		$photo = explode('ENCODING=b;TYPE=png:', $mysql_data['jpegPhoto']);
		//print_r ($photo);
		if ($photo[1] != "nothing") {
			fwrite($w,"jpegPhoto:: ".$photo[1]."\n");
		}
		if ($mysql_data["telephoneNumber"] != " ") {
			fwrite($w,"telephoneNumber:".$mysql_data["telephoneNumber"]."\n");
		}
		fwrite($w,"distinguishedName:".$mysql_data["distinguishedName"]."\n");
		fwrite($w,"userAccountControl: ".$mysql_data["userAccountControl"]."\n");
		fwrite($w,"\n");
	//}
	fclose($w);
}

function addRecord($adddn, $record){
	// remove ou as it is not useful anymore
	unset($record["ou"]);
	// remove whenChanged as it is not useful anymore
	unset($record["whenChanged"]);
	unset($record["unicodePwd"]);
	global $srv, $uname, $upasswd, $dn;
	$ds=ldap_connect($srv);
	if (!$ds) die("error connect to LDAP server $srv");
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    $r=ldap_bind($ds, $uname, $upasswd);
    if (!$r) die("error bind!");
	$addProcess = ldap_add($ds, $adddn, $record);
	$error = ldap_error($ds);
	//print_r($record)."\n";
	//echo $adddn."\n";
	//echo $error."\n";
	//echo $addProcess."\n";
	if (!($addProcess)) {
		print_r($record)."\n";
		echo "Error adding account ".$record["mail"].": ".$error.". This maybe because of such account exists.\n";
		//die("add error!");
	}
}

function modifyRecord($modifydn, $record){
	// remove ou as it is not useful anymore
	unset($record["ou"]);
	// remove whenChanged as it is not useful anymore
	unset($record["whenChanged"]);
	unset($record["unicodePwd"]);
	//unset($record["sAMAccountName"]);
	// shete must be unset to allow modify
	unset($record["distinguishedName"]);
	unset($record["cn"]);
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
	//echo $modifyProcess."\n";
	if (!($modifyProcess)) {
		//print_r($record)."\n";
		echo "Error modifying account ".$record["mail"].": ".$error.".\n";
		//die("modify error!");
	}
}

function addRecord_to_alias($adddn, $record){
	// remove ou as it is not useful anymore
	//unset($record["ou"]);
	// remove whenChanged as it is not useful anymore
	unset($record["whenChanged"]);
	global $srv, $uname, $upasswd, $dn;
	$ds=ldap_connect($srv);
	if (!$ds) die("error connect to LDAP server $srv");
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    $r=ldap_bind($ds, $uname, $upasswd);
    if (!$r) die("error bind!");
	$addProcess = ldap_mod_add($ds, $adddn, $record);
	$error = ldap_error($ds);
	if (!($addProcess)) {
	//	print_r($record)."\n";
		echo "Error adding account ".$record["proxyAddresses"]." to alias ". $adddn.": ".$error.".\n";
	
	/*echo $error."\n";
	echo $addProcess."\n";*/
	//if (!($addProcess)) die("add error!");
	}
}

function setPassword($userdn, $password) {
        //if(!function_exists('ldap_exop_passwd')) {
        if(!function_exists('ldap_modify'))
        {
                // since PHP 7.2 – respondToActions checked this already, this
                // method should not be called. Double check due to public scope.
                // This method can be removed when Nextcloud 16 compat is dropped.
                return false;
        }
        global $srv, $uname, $upasswd, $dn;
        $ds=ldap_connect($srv);
        if (!$ds) die("error connect to LDAP server $srv");
		ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        $r=ldap_bind($ds, $uname, $upasswd);
        if (!$r) die("error bind!");
		$entry["unicodePwd"] = $password;
        if (ldap_modify($ds,$userdn,$entry))
        {
            return true;
        } else {
            echo "Error setting account's ".$userdn." password: ".ldap_error($ds). " error " .ldap_errno($ds)."\n";
            return false;
        }
}

function add_or_update ($attributes, $mysql_data, $user, $passwd, $log) {
    $w=fopen($log,'a');
	$counter = 0;
	// start of mysql_data processing
	for ($i=0; $i<$mysql_data["count_records"]; $i++) { // uncomment in production
	//for ($i=0; $i<2; $i++) { //uncomment in test
		$added_updated = false;
		$ldap_data = get_ldap_attributes($attributes,$mysql_data[$i]["mail"]);
		$ldap_found = ($ldap_data && $ldap_data['count'] === 1);
		//print_r($ldap_data)."\n";
		// check existence of mysql_data element in ldap_data
		if (!($ldap_found)) {
			fwrite($w,(date(DATE_RFC822)));
			fwrite($w," Добавлен новый пользователь с email ".$mysql_data[$i]["mail"]."\n");
			//echo "Add new user with email ".$mysql_data[$i]["mail"]."\n";
			// add user
			$addDN = trim($mysql_data[$i]["distinguishedName"]);
			addRecord($addDN, $mysql_data[$i]);				// uncomment in production
			setPassword($addDN,$mysql_data[$i]["unicodePwd"]); // uncomment in production
			$counter++;
			//echo "UAC flag: ".$mysql_data[$i]['userAccountControl']."\n";
			if (($mysql_data[$i]['userAccountControl'] == 514)||($mysql_data[$i]['userAccountControl'] == 546)||($mysql_data[$i]['userAccountControl'] == 66050)||($mysql_data[$i]['userAccountControl'] == 66082)||($mysql_data[$i]['userAccountControl'] == 2)||($mysql_data[$i]['userAccountControl'] == 16)||($mysql_data[$i]['userAccountControl'] == 8388608)) {
				$added_updated = false;
				fwrite($w,(date(DATE_RFC822)));
				fwrite($w," Аккаунт с email ".$mysql_data[$i]['mail']." отключен или заблокирован, поэтому не добавлен в рассылки!\n");
				//echo "Account with email ".$mysql_data[$i]['mail']." is disabled/locked. Don't add it to aliases!\n";
			} else {
				$added_updated = true;
			}
		} else {
			fwrite($w,(date(DATE_RFC822)));
			fwrite($w," Пользователь с email " .$mysql_data[$i]["mail"]." уже существует!\n");
			//echo "User with email " .$mysql_data[$i]["mail"]." is already exists!\n";
			// check if mysql is newer then ldap
			$mysql_lastmodified = HumanToUnixTimeUTC($mysql_data[$i]["whenChanged"]);
			foreach ($ldap_data as $ldap_data_element) {
				//echo $ldap_data_element['whenchanged'][0];
				$ldap_lastmodified = HumanToUnixTime(explode('.', $ldap_data_element['whenchanged'][0])[0]);
				//$ldap_lastmodified = HumanToUnixTime($ldap_data_element['whenchanged'][0]);
			}
			//echo "Ldap time: ".$ldap_lastmodified."\n";
			//echo "Mysql time: ".$mysql_lastmodified."\n";
			if ($mysql_lastmodified > $ldap_lastmodified) {
				fwrite($w,(date(DATE_RFC822)));
				fwrite($w," Учетная запись устарела. Обновляем ldap!\n");
				//echo "User is outdated. Updating ldap!\n";
				// modify user
				//$modifyDN = "uid=".trim($mysql_data[$i]["uid"]).",".$mysql_data[$i]["ou"];
				$modifyDN = trim($mysql_data[$i]["distinguishedName"]);
				//echo $modifyDN."\n";
				modifyRecord($modifyDN, $mysql_data[$i]);		// uncomment in production
				setPassword($modifyDN,$mysql_data[$i]["unicodePwd"]); // uncomment in production
				$counter++;
				//echo "UAC flag: ".$mysql_data[$i]['userAccountControl']."\n";
				if (($mysql_data[$i]['userAccountControl'] == 514)||($mysql_data[$i]['userAccountControl'] == 546)||($mysql_data[$i]['userAccountControl'] == 66050)||($mysql_data[$i]['userAccountControl'] == 66082)||($mysql_data[$i]['userAccountControl'] == 2)||($mysql_data[$i]['userAccountControl'] == 16)||($mysql_data[$i]['userAccountControl'] == 8388608)) {
					$added_updated = false;
					fwrite($w,(date(DATE_RFC822)));
					fwrite($w," Аккаунт с email ".$mysql_data[$i]['mail']." отключен или заблокирован, поэтому не добавлен в рассылки!\n");
					//echo "Account with email ".$mysql_data[$i]['mail']." is disabled/locked. Don't add it to aliases!\n";
				} else {
					$added_updated = true;
				}
			} else {
				fwrite($w,(date(DATE_RFC822)));
				fwrite($w," Учетная запись актуальна. Нет необходимости обновлять ldap!\n");
				//echo "User is not outdated. No need to update ldap!\n";
			}
		}
		// add user to auto aliases if added or updated
		if ($added_updated) {
			$alias_array["proxyAddresses"] = $mysql_data[$i]["mail"];
			$addDN = "cn=all,ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
			addRecord_to_alias ($addDN,$alias_array);			// uncomment in production
			fwrite($w,(date(DATE_RFC822)));
			fwrite($w," Пользователь с email ".$mysql_data[$i]["mail"]." добавлен в рассылку all@ksc.ru.\n");
			// OP substr
			$OP = explode(',', explode('=', $mysql_data[$i]["ou"])[1])[0];
			$addDN = "cn=all-".$OP.",ou=all,ou=aliases,ou=KSC_RAS,dc=ksc,dc=test";
			addRecord_to_alias ($addDN,$alias_array);			// uncomment in production
			fwrite($w,(date(DATE_RFC822)));
			fwrite($w," Пользователь с email ".$mysql_data[$i]["mail"]." добавлен в рассылку all-".$OP."@ksc.ru.\n");
		}
    }
    fclose($w);
    return $counter;
}

function get_all_contacts_mail ($host_db, $user, $passwd, $db_mail, $db_src) {
    $link_db=connect_to_db ($host_db, $user, $passwd);
    $select="SELECT * FROM ".$db_mail.".".$db_src;";";
    $select_query=mysqli_query($link_db, $select) or die("Query failed");
    mysqli_close($link_db);
    return $select_query;
}

function connect_to_db ($host, $user, $passwd) {
    $link_db = mysqli_connect($host, $user, $passwd)
       or die("Could not connect: " . mysqli_error());
    return $link_db;
}


//***********************************************************************************************
echo "Запущена синхронизация ".(date(DATE_RFC822))."\n";
$w=fopen($log,'a');
fwrite($w,"Запущена синхронизация ".(date(DATE_RFC822))."\n");
fclose($w);

//$attributes=array('uid','dn','givenName','sn','cn','displayName','userPassword','description','o','department','l','roomNumber','mail','employeeType','employeeNumber','carLicense','st','jpegPhoto','telephoneNumber','modifyTimestamp');
$attributes=array('sAMAccountName','userPrincipalName','distinguishedName','givenName','sn','cn','displayName','description','o','department','l','roomNumber','mail','employeeType','employeeNumber','carLicense','st','jpegPhoto','telephoneNumber','whenChanged','userAccountControl');
$select_mysql_data = get_all_contacts_mail($host_db, $user, $passwd, $db_mail, $db_src);
$mysql_data = prepare_mail_acc_to_ldap($select_mysql_data);
//$mysql_data = prepare_mail_acc_to_ldif($select_mysql_data);

/*for ($i=0; $i<$ldap_data["count"]; $i++) {
	print_ldap_elements($ldap_data[$i]);
}*/
$processed = add_or_update ($attributes, $mysql_data, $user, $passwd, $log);

//$unicode_pwd = iconv("cp1251", "UTF-16LE", '"' . "thalopaxaba" . '"');
//$unicode_pwd = mb_convert_encoding("thalopaxaba" , 'UTF-8' , 'UTF-16LE');
//echo $unicode_pwd;
//***********************************************************************************************
echo "Всего обработано ".$processed." записей.\n";
echo "Завершена синхронизация ".(date(DATE_RFC822))."\n";
echo "См. подробный лог в ".$log."\n"."\n";
$w=fopen($log,'a');
fwrite($w,"Всего обработано ".$processed." записей.\n");
fwrite($w,"Завершена синхронизация ".(date(DATE_RFC822))."\n"."\n");
fclose($w);

?>
