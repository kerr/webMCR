<?php
if (!defined('MCR')) exit;

/* User class | User group class */

Class User {
private $id;

private $tmp;
private $permissions;

private $ip;				
private $name;
private $email;	

private $lvl;
private $group;	

private $gender;	
private $female;

	public function User($input,$method) {
	global $bd_users,$bd_names;	
	
		$sql = "SELECT `{$bd_users['login']}`,
		               `{$bd_users['id']}`,
					   `{$bd_users['tmp']}`,
					   `{$bd_users['ip']}`,
					   `{$bd_users['email']}`,
					   `{$bd_users['female']}`,
					   `{$bd_users['group']}` FROM {$bd_names['users']} WHERE `".TextBase::SQLSafe($method)."`='".TextBase::SQLSafe($input)."'";
						   
		$result = BD($sql);			
		if ( !$result or mysql_num_rows( $result ) != 1 ) { $this->id = false; return false; }
			
		$this->permissions = null;	
					 
		$line = mysql_fetch_array($result, MYSQL_ASSOC);
			
		$this->id     = (int)$line[$bd_users['id']];			
		$this->name   = $line[$bd_users['login']];
        $this->group  = (int)$line[$bd_users['group']];
		$this->lvl    = $this->getPermission('lvl');
			
		$this->tmp    = $line[$bd_users['tmp']];
		$this->ip     = $line[$bd_users['ip']];
            
		$this->email  = $line[$bd_users['email']];
			
		/* Пол персонажа */
		$gender = $line[$bd_users['female']]; 
		
		$this->gender = (is_numeric($gender))? (int)$gender : (($gender == 'female' or $gender == 'male')? (($gender == 'female')? 1 : 0) : 10);
		$this->female = ($this->gender == 1)? true : false;
			
	return true;			
	}
	
	public function login($tmp, $ip, $save = false) {
	global $bd_users, $bd_names, $config;

		if (!$this->id) return false;
		
		$save = ($save)? true : false;
		
		if ($config['p_logic'] != 'usual' and $config['p_sync'])
			MCMSAuth::login($this->id());	
			
		BD("UPDATE `{$bd_names['users']}` SET `{$bd_users['tmp']}`='".TextBase::SQLSafe($tmp)."' WHERE `{$bd_users['id']}`='".$this->id."'");
		BD("UPDATE `{$bd_names['users']}` SET `{$bd_users['ip']}`='".TextBase::SQLSafe($ip)."' WHERE `{$bd_users['id']}`='".$this->id."'"); 
	
		$this->tmp = $tmp;
		
		if (!isset($_SESSION)) session_start();
		  
		$_SESSION['user_id']   = $this->id();
		$_SESSION['user_name'] = $this->name();
		$_SESSION['ip']        = $this->ip();
		// $_SESSION['ip_check']  = (!strncmp($this->tmp,'ipcheck_',8))? true : false;
		
		if ($save) setcookie( "PRTCookie1", $tmp, time() + 60 * 60 * 24 * 30 * 12, '/');
  		
	return true;
	}
	
	public function logout() {
	global $bd_users, $bd_names, $config;
		
		if ($config['p_logic'] != 'usual' and $config['p_sync'])
			MCMSAuth::logout();	
			
		if (!isset($_SESSION)) session_start();
		if (isset($_SESSION)) session_destroy();
				  
		if (isset($_COOKIE['PRTCookie1'])) { 
				  
			BD("UPDATE `{$bd_names['users']}` SET `{$bd_users['tmp']}`='0' WHERE `{$bd_users['tmp']}`='".TextBase::SQLSafe($_COOKIE['PRTCookie1'])."'");					  
			setcookie("PRTCookie1","",time()-3600);					
			$this->tmp = 0;		  
		}	
	}
	
	public function canPostComment() {
	global $bd_names;
	
	   if (!$this->getPermission('add_comm')) return false;
	    
	/* Интервал по времени 1 минута */
	
		$result = BD("SELECT id FROM `{$bd_names['comments']}` WHERE user_id='".$this->id."' AND time>NOW()-INTERVAL 1 MINUTE");
		if ( mysql_num_rows( $result ) ) return false;	
		
		return true;
	
	}
	
	public function gameLoginConfirm() {
	global $bd_users,$bd_names;	
	
		if (!$this->id) return false;
	
		BD("UPDATE `{$bd_names['users']}` SET gameplay_last=NOW(),play_times=play_times+1 WHERE `{$bd_users['id']}`='".$this->id."'"); 
		
		return true;
	}
	
	public function gameLogoutConfirm() {
	global $bd_users,$bd_names;
	
		if (!$this->id) return false;
	
		$result = BD("SELECT `{$bd_users['id']}` FROM `{$bd_names['users']}` WHERE `{$bd_users['server']}` IS NOT NULL and `{$bd_users['id']}`='".$this->id."'");
		
		if (mysql_num_rows( $result ) == 1)
				BD("UPDATE `{$bd_names['users']}` SET `{$bd_users['server']}`=NULL WHERE `{$bd_users['id']}`='".$this->id."'"); 
		
		return true;
	}
	
	public function gameLoginLast() {
	global $bd_users,$bd_names;
	
		if (!$this->id) return false;
	
		$result = BD("SELECT gameplay_last FROM `{$bd_names['users']}` WHERE gameplay_last<>'0000-00-00 00:00:00' and `{$bd_users['id']}`='".$this->id."'");
		
		if (mysql_num_rows( $result ) == 1) {
		
			$line = mysql_fetch_array($result);
			
			return $line['gameplay_last'];
			
		} else return false;		

	}
	
	public function getStatisticTime($param) {
	global $bd_users,$bd_names;	
	
		if (!$this->id) return false;	
		
		$param = TextBase::SQLSafe($param);
		
		switch ($param) {
		case 'create_time': $param = $bd_users['ctime']; break;
		case 'gameplay_last': 		
		case 'active_last': break;
		default: return false; break;
		}
		
 		$result = BD("SELECT $param FROM {$bd_names['users']} WHERE $param<>'0000-00-00 00:00:00' and {$bd_users['id']}='".$this->id."'");
		
		if (mysql_num_rows( $result ) == 1) {
		
			$line = mysql_fetch_array($result);

			return $line[$param];
			
		} else return false;		

	}
	
	public function getStatistic() {
	global $bd_users,$bd_names;	
	
		if (!$this->id) return false;
	
		$result = BD("SELECT comments_num,play_times,undress_times FROM `{$bd_names['users']}` WHERE `{$bd_users['id']}`='".$this->id."'");
		
		if (mysql_num_rows( $result ) == 1)
				
				return mysql_fetch_array($result);
				
		else 	return false;		
	}
	
	/* Ввести валюту на сайте. Обмен данными с сервером по RCON */	
	
    public function getMoney() {
    global $bd_names, $bd_money;
	
    if (!$this->id) return 0;
	
		if ($bd_names['iconomy']) {
		
			$res  = BD("SELECT {$bd_money['money']} FROM {$bd_names['iconomy']} WHERE {$bd_money['login']}='".TextBase::SQLSafe($this->name())."'");
			$line = mysql_fetch_array($res, MYSQL_NUM);
			
		return $line[0];			
		} 
       
    return 0;
    }	

	public function getSkinFName() {
	global $site_ways;
		return MCRAFT.$site_ways['skins'].$this->name.'.png';
	}
	
	public function getCloakFName() {
	global $site_ways;
		return MCRAFT.$site_ways['cloaks'].$this->name.'.png';
	}

    public function getGroupName() {
	global $bd_names;
	  if (!$this->id) return false; 
	  
	  $result = BD("SELECT `name` FROM `{$bd_names['groups']}` WHERE `id`='{$this->group}'");

      if (!mysql_num_rows( $result )) return 'unnamed';
	  $line   = mysql_fetch_array($result, MYSQL_NUM);

	  return $line[0];
    }
	
	public function deleteSkin() {
	   if (file_exists($this->getSkinFName())) {	   
	    unlink($this->getSkinFName());
	    $this->deleteBuffer();
       }	   
	}
	
	public function deleteCloak() {
	   if (file_exists($this->getCloakFName())) { 
	     unlink($this->getCloakFName());
		 $this->deleteBuffer();
	   }
	}
	
	public function defaultSkinMD5() {

		if (!$this->id) return false;
		
		$def_dir = MCRAFT.'tmp/default_skins/';
		
		if ( $this->isFemale() ) $default_skin_md5 = $def_dir.'md5_female.md5';
		else                     $default_skin_md5 = $def_dir.'md5.md5';

		if ( file_exists($default_skin_md5) ) {

			   $md5 = @file($default_skin_md5); 
		  if ( $md5[0] ) return $md5[0];
		  else { vtxtlog( '[action.php] error while READING md5 cache file. '.$default_skin_md5 ); return false; }		 
		}

		if ( $this->isFemale() ) $default_skin = $def_dir.'Char_female.png';
		else                  	 $default_skin = $def_dir.'Char.png';
		 
		if ( file_exists($default_skin) ) {

				$md5 = md5_file($default_skin);  
		  if ( !$md5 ) { vtxtlog( '[action.php] md5 generate error. '.$default_skin ); return false; }
		  
		  if ( $fp = fopen($default_skin_md5, 'w') ) {    
			if ( !fwrite($fp, $md5) ) vtxtlog( '[action.php] error while SAVE cache file. '.$default_skin_md5 );
			fclose($fp);
		  } else  vtxtlog( '[action.php] error while CREATE cache file. '.$default_skin_md5 );
		  
		  return $md5;  
		  
		} else { vtxtlog( '[action.php] default skin file missing. '.$default_skin ); return false; }
	 
	}	
	
	public function defaultSkinTrigger($new_value = -1) { /* is player use unique skin */
	global $bd_users,$bd_names;
	
	  if (!$this->id) return false;
	
	  if ( $new_value < 0 ) {
	  
	    $result = BD("SELECT default_skin FROM `{$bd_names['users']}` WHERE `{$bd_users['id']}`='{$this->id()}'");
	    $line   = mysql_fetch_array($result, MYSQL_NUM);
		
		$trigger = (int)$line[0];
		
		if ($trigger == 2) {
			
				if ( !file_exists($this->getSkinFName())) $trigger = 1;
			elseif ( !strcmp($this->defaultSkinMD5(),md5_file($this->getSkinFName())) ) $trigger = 1;
			else $trigger = 0;
			
			BD("UPDATE `{$bd_names['users']}` SET default_skin='$trigger' WHERE `{$bd_users['id']}`='{$this->id()}'");
		}
		return ($trigger)? true : false;		
	  }
	  
	  $new_value = ($new_value)? 1 : 0;
      
	  BD("UPDATE `{$bd_names['users']}` SET default_skin='$new_value' WHERE `{$bd_users['id']}`='{$this->id()}'");
	  
	  return ($new_value)? true : false;
	  
	}
	
	public function deleteBuffer() {

	$mini = MCRAFT.'tmp/skin_buffer/'.$this->name.'_Mini.png';
	$skin = MCRAFT.'tmp/skin_buffer/'.$this->name.'.png';

	 if (file_exists($mini)) unlink($mini); 
	 if (file_exists($skin)) unlink($skin); 
	}

    public function setDefaultSkin() {
	
	if (!$this->id) return 0;
	
    $this->deleteSkin(); 
    
	$default_skin = MCRAFT.'tmp/default_skins/Char'.(($this->isFemale())? '_female':'').'.png';

	if ( !copy ( $default_skin , $this->getSkinFName()) ) vtxtlog('[SetDefaultSkin] error while COPY default skin for new user.');
    else $this->defaultSkinTrigger(true);
	
	return 1;
    }
	
	public function changeName($newname) {
	global $bd_names,$bd_users,$site_ways;
	
		if (!$this->id) return 0;
	
		$newname = trim($newname);
		
		if (!preg_match("/^[a-zA-Z0-9_-]+$/", $newname)) return 1401;
		
		$result = BD("SELECT `{$bd_users['login']}` FROM `{$bd_names['users']}` WHERE `{$bd_users['login']}`='".TextBase::SQLSafe($newname)."'");
		
		if (mysql_num_rows($result)) return 1402;
		
		if ((strlen($newname) < 4) or (strlen($newname) > 15)) return 1403;
		
		BD("UPDATE `{$bd_names['users']}` SET `{$bd_users['login']}`='".TextBase::SQLSafe($newname)."' WHERE `{$bd_users['login']}`='".TextBase::SQLSafe($this->name)."'");
		
		if (!empty($_SESSION['user_name']) and $_SESSION['user_name'] == $this->name) $_SESSION['user_name'] = $newname;
			
		/* Переименование файла скина и плаща */
		
		$way_tmp_old = $this->getSkinFName();
		$way_tmp_new = MCRAFT.$site_ways['skins'].$newname.'.png';
		
		if (file_exists($way_tmp_old) and !file_exists($way_tmp_new)) rename($way_tmp_old, $way_tmp_new);

		$way_tmp_old = $this->getCloakFName();
		$way_tmp_new = MCRAFT.$site_ways['cloaks'].$newname.'.png';
		
		if (file_exists($way_tmp_old) and !file_exists($way_tmp_new)) rename($way_tmp_old, $way_tmp_new);
				
		$buff_mini     = MCRAFT.'tmp/skin_buffer/'.$this->name.'_Mini.png';
		$buff_mini_new = MCRAFT.'tmp/skin_buffer/'.$newname.'.png';
	    $buff_skin     = MCRAFT.'tmp/skin_buffer/'.$this->name.'.png';
        $buff_skin_new = MCRAFT.'tmp/skin_buffer/'.$newname.'.png';
		
	    if (file_exists($buff_mini)) rename($buff_mini, $buff_mini_new);
	    if (file_exists($buff_skin)) rename($buff_skin, $buff_skin_new);
		
		$this->name = $newname;
		
		return 1;
	
	}
	
	public function changePassword($newpass, $repass = false, $pass = false) {
	global $bd_names, $bd_users;
	
		if (!$this->id) return 0;
		
		if (!is_bool($repass)) {

            if (strcmp($repass,$newpass)) return 1504;
		
			$regular = "/^[a-zA-Z0-9_-]+$/";
			
			if (!preg_match($regular, $pass) or !preg_match($regular, $newpass)) return 1501;
			
			$result = BD("SELECT `{$bd_users['password']}` FROM `{$bd_names['users']}` WHERE `{$bd_users['login']}`='".TextBase::SQLSafe($this->name)."'"); 
			$line   = mysql_fetch_array( $result, MYSQL_NUM );
			 
			if ($line == NULL or !MCRAuth::checkPass(array('pass_db' => $line[0], 'pass' => $pass, 'user_id' => $this->id, 'user_name' => $this->name)) ) return 1502;
		}
			
		$minlen = 4; $maxlen = 15; $len = strlen($newpass);
		
		if (($len < $minlen) or ($len > $maxlen)) return 1503;
			 
		BD("UPDATE `{$bd_names['users']}` SET `{$bd_users['password']}`='".MCRAuth::createPass($newpass)."' WHERE `{$bd_users['login']}`='".TextBase::SQLSafe($this->name)."'"); 
		
		return 1;
	}
	
	public function changeGroup($newgroup) {
	global $bd_users,$bd_names;	
	
		    $newgroup = (int) $newgroup;		
		if ($newgroup < 0) return false;
		
		$result = BD("SELECT `id` FROM {$bd_names['groups']} WHERE `id`='".TextBase::SQLSafe($newgroup)."'");
		
		if ( !mysql_num_rows( $result ) ) return false;
		
		BD("UPDATE {$bd_names['users']} SET `{$bd_users['group']}`='".TextBase::SQLSafe($newgroup)."' WHERE `{$bd_users['id']}`='".$this->id."'"); 
		
		$this->group = $newgroup;
		$this->permissions['lvl'] = null;
		$this->lvl   = $this->getPermission('lvl');
		
		return true;
	}
	
	public function changeGender($female) {
	global $bd_users,$bd_names,$config;	
	
		$female = (int) $female;
	
		if ($config['p_logic'] == 'xenforo')
			$isFemale = ($female == 1)? 'female' : 'male';
		else
			$isFemale = ($female == 1)? 1 : 0;	
	
		if ((int)$this->isFemale() == $female) return false;
	
		BD("UPDATE {$bd_names['users']} SET `{$bd_users['female']}`='$isFemale' WHERE `{$bd_users['id']}`='".$this->id."'"); 
		
		$this->gender = $female;
		$this->female = ($female)? true : false;
		
		$this->setDefaultSkin();
		return true;
	}
	
	public function changeEmail($email) {
	global $bd_users,$bd_names;	
	
		     $email = filter_var($email, FILTER_VALIDATE_EMAIL); 	
		if (!$email) return 1901;
		
		$result = BD("SELECT `id` FROM {$bd_names['users']} WHERE `{$bd_users['email']}`='".TextBase::SQLSafe($email)."'");		
		if ( mysql_num_rows( $result ) ) return 1902;
				
		BD("UPDATE {$bd_names['users']} SET `{$bd_users['email']}`='".TextBase::SQLSafe($email)."' WHERE `{$bd_users['id']}`='".$this->id."'"); 
		
		$this->email = $email;
		return 1;
	}
	
	public function Delete() {
	global $bd_users,$bd_names;	
	
	    if (!$this->id) return false;
		
		if (!class_exists('Comments_Item')) require_once(MCR_ROOT.'instruments/catalog.class.php');
		
	    $this->deleteCloak();
		$this->deleteSkin();
		$this->deleteBuffer();			
					
		$result = BD("SELECT `id` FROM `{$bd_names['comments']}` WHERE `user_id`='".$this->id."'"); 
		if ( mysql_num_rows( $result ) != 0 ) {
	  
		  while ( $line = mysql_fetch_array( $result, MYSQL_NUM ) ) {
		  		
				$comment_del = new Comments_Item($line[0]);
				$comment_del->Delete(); 
				unset($comment_del);
		  }
		}
		
		BD("DELETE FROM `{$bd_names['users']}` WHERE `{$bd_users['id']}`= '".$this->id()."'");

        $this->id = false;		
		return true;		
	}
	
	public function getVerificationStr() {	
	if (!$this->id) return false;
	
    $salt = sqlConfigGet('email-verification-salt');
	
	if (!$salt) {		
		$salt = randString();
		sqlConfigSet('email-verification-salt',$salt); 	
		}
	
	return md5($this->id().$salt);	
	}
	
	public function getPermission($param) {
	global $bd_names;	
	
	if (isset($this->permissions[$param])) return $this->permissions[$param];
	if (!$this->id) return false;
	
	$group = new Group($this->group);
	$value = $group->GetPermission($param);
	
	unset($group);
	
	if ((int)$value == -1) return false;

	$this->permissions[$param] = $value;
	
	return $value;		
	}

    public function isFemale() {
        return $this->female;
    }	
	
	public function gender() {
		return $this->gender;
	}
	
	public function id() {
		return $this->id;
	}
	
	public function lvl() {
		return $this->lvl;
	}	
	
	public function tmp() {
		return $this->tmp;
	}
	
	public function ip() {
		return $this->ip;
	}	
	
	public function email() {
		return $this->email;
	}
	
	public function group() {
		return $this->group;
	}
	
	public function name() {
		return $this->name;
	}
}

Class Group extends TextBase {
private $id;
private $pavailable;

    public function Group($id = false) {
		
		$this->id = (int)$id;
		$this->pavailable = array("change_skin", 
		                          "change_pass",
								  "lvl",
								  "change_cloak",
								  "change_login",
								  "max_fsize",
								  "max_ratio",
								  "add_news",
								  "adm_comm",
								  "add_comm");								  
	}
	
	public function GetPermission($param) {
	global $bd_names;	

		if (!$this->id) return -1;		
		if (!in_array($param, $this->pavailable)) return -1;

		$result = BD("SELECT `$param` FROM `{$bd_names['groups']}` WHERE `id`='".$this->id."'");
		
		if (mysql_num_rows( $result ) == 1) {
		
			$line  = mysql_fetch_array($result, MYSQL_NUM );
			$value = (int)$line[0];
			
			if ($param != 'max_fsize' and
                $param != 'max_ratio' and
				$param != 'lvl' )
				 
			$value = ($line[0])? true : false;
			return $value;
			
		} else return -1;		
	}
	
	public function GetAllPermissions() {
    global $bd_names;
	
	$sql_names = null; 
	
	for ($i=0;$i < sizeof($this->pavailable);$i++) 
		if ($sql_names) $sql_names .= ",`{$this->pavailable[$i]}`"; 
		else            $sql_names .= "`{$this->pavailable[$i]}`"; 	
   	
	$result = BD("SELECT $sql_names FROM {$bd_names['groups']} WHERE `id`='".$this->id."'");  
	return mysql_fetch_array( $result, MYSQL_ASSOC );	
	}
	
	public function Exist() {
	global $bd_names;	
		
		if (!$this->id) return false;
		
		$result = BD("SELECT `id` FROM {$bd_names['groups']} WHERE `id`='".$this->id."'"); 

		if ( mysql_num_rows( $result ) == 1 ) return true;
		else return false;
		
	}
	
	public function Create($name, &$permissions) {
	global $bd_names,$user;	
		
		if ($this->id or empty($user) or $user->lvl() < 15) return false; 
		
		if (!$name or !TextBase::StringLen($name)) return false;
		
		$result = BD("SELECT COUNT(*) FROM `{$bd_names['groups']}` WHERE `name`='".TextBase::SQLSafe($name)."'");
		$num   = mysql_fetch_array($result, MYSQL_NUM);
		if ($num[0]) return false;	
		
		$sql_names = null; $sql_vars = null;
		
		  foreach ($permissions as $key=>$value) {
		  
		    if (!in_array($key, $this->pavailable)) continue;
				
			if ($key != 'max_fsize' and
                $key != 'max_ratio' and
                $key != 'lvl')	$value = ($value)? 1 : 0;				
            else                $value = TextBase::SQLSafe((int) $value);
			
			if ($sql_names) $sql_names .= ",`$key`"; else $sql_names .= "`$key`"; 
			if ($sql_vars)  $sql_vars  .= ",'$value'"; else $sql_vars .= "'$value'"; 
		  }

		$result = BD("INSERT INTO `{$bd_names['groups']}` (`name`,$sql_names) values ('".TextBase::SQLSafe($name)."',$sql_vars)");	
		if ($result and mysql_affected_rows()) $this->id = mysql_insert_id();
		else return false;
	 
		return true; 
	}
	
	public function GetName() {
    global $bd_names;

		$result = BD("SELECT `name` FROM {$bd_names['groups']} WHERE id='".$this->id."'"); 

		if ( mysql_num_rows( $result ) != 1 ) return false;
        $line = mysql_fetch_array( $result, MYSQL_NUM );
		  
		return $line[0];		
    }
	
	public function IsSystem() {
    global $bd_names;

		$result = BD("SELECT `system` FROM {$bd_names['groups']} WHERE id='".$this->id."'"); 

		if ( mysql_num_rows( $result ) != 1 ) return false;
        $line = mysql_fetch_array( $result, MYSQL_NUM );
		  
		return ($line[0])? true : false;		
    }	
	
	public function Edit($name, &$permissions) {
	global $bd_names,$user;
		
		if (!$this->id or empty($user) or $user->lvl() < 15) return false; 
	
		if (!$name or !TextBase::StringLen($name)) return false;
		
		$result = BD("SELECT COUNT(*) FROM `{$bd_names['groups']}` WHERE `name`='".TextBase::SQLSafe($name)."' and `id`!='".$this->id."'");
		$num   = mysql_fetch_array($result, MYSQL_NUM);
		if ($num[0]) return false;	
		
		$sql = null;
		
		for ($i=0;$i < sizeof($this->pavailable);$i++) {
		
		$key = $this->pavailable[$i];
		
			if (isset($permissions[$key])){	
		
				if ($key != 'max_fsize' and
					$key != 'max_ratio' and
					$key != 'lvl')	$value = ($permissions[$key])? 1 : 0;				
				else				$value = TextBase::SQLSafe((int) $permissions[$key]);				
				
			} else $value = 0;
			
		$sql .= ",`$key`='$value'";
		}
		
		if (!$sql) $sql = '';
		
		    $result = BD("UPDATE {$bd_names['groups']} SET `name`='".TextBase::SQLSafe($name)."'$sql WHERE `id`='".$this->id."'"); 
		if ($result and mysql_affected_rows()) return true;
		
		return true; 
	}	
	
	public function Delete() {
	global $bd_names, $bd_users, $user;	

		if (!$this->id or empty($user) or $user->lvl() < 15) return false; 
		if ($this->IsSystem()) return false;
		
		$result = BD("SELECT `id` FROM `{$bd_names['users']}` WHERE `group`='".$this->id."'"); 
		if ( mysql_num_rows( $result ) != 0 ) {
	  
		  while ( $line = mysql_fetch_array( $result, MYSQL_NUM ) ) {
		  		
				$user_del = new User($line[0], $bd_users['id']);
				$user_del->Delete(); 
				unset($user_del);
		  }
		}
		
		$result = BD("DELETE FROM `{$bd_names['groups']}` WHERE `id` = '".$this->id."' and `system` = '0'");
		
		$this->id = false;		
		if ($result and mysql_affected_rows()) return true;
		
		return false; 
	}	
}

Class GroupMenager {

	public static function GetList($selected) {
	global $bd_names;
		
		$result = BD("SELECT id,name FROM {$bd_names['groups']} ORDER BY name DESC LIMIT 0,90");  
		$group_list = '';
							
		while ( $line = mysql_fetch_array( $result, MYSQL_ASSOC ) ) 
		 $group_list .= '<option value="'.$line['id'].'" '.(($selected == $line['id'])?'selected':'').'>'.$line['name'].'</option>';
		 
		return $group_list;
	}
	
	public static function GetNameByID($id) {

        if (!$id or $id < 0) return 'Удаленный';
		
		$grp_item = new Group($id);
		$grp_name = $grp_item->GetName();
		
		unset($grp_item);
		
		if (!$grp_name) return 'Удаленный';
		           else return $grp_name;
	}
}
?>