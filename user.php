<?php
/*
  FILE: user.php
  HISTORY:
    2013-09-15 created for handling log-ins during checkout
    2013-09-25 split most of clsPageUser into clsPageLogin
    2013-10-10 split clsPageUser off to page-user.php to reduce unnecessary lib loading
*/
/*%%%%
  ROLE: this doesn't talk to the database; it's a CMS-User class.
    That dichotomoy needs to be clarified. :-P
*/

// DEPRECATED until we can integrate it better
//class clsVbzUser extends clsUser {

    // STATIC section //
/*
    private static $oApp;

    public static function AppObj(clsVbzApp $iApp=NULL) {
	if (!is_null($iApp)) {
	    self::$oApp = $iApp;
	}
	return self::$oApp;
    }
*/
    /*----
      ACTION: Attempts to log the user in with the given credentials.
      RETURNS: user object if successful, NULL otherwise.
    */
/*
    public static function Login($iUser,$iPass) {
	$tbl = self::AppObj()->Data()->Users();
	$rc = $tbl->FindUser($iUser);
	if (is_null($rc)) {
	    // username not found
	    $oUser = NULL;
	} elseif ($rc->AuthValid($iPass)) {
	    $oUser = new clsVbzUser();
	    $oUser->RecObj($rc);
	} else {
	    // username found, password wrong
	    $oUser = NULL;
	}
	return $oUser;
    }
*/
    // DYNAMIC section //

//    private $oRec;

    /*----
      NOTE: Yes, other classes need to read-access this.
    */
/*
    public function RecObj(clsVbzUserRec $iRec=NULL) {
	if (!is_null($iRec)) {
	    $this->oRec = $iRec;
	}
	return $this->oRec;
    }
    public function CanDo($iAction) {
	return FALSE;	// TODO - no security authorizations implemented yet
    }
    public function PageLink($iText=NULL) {
	// TODO
    }
*/
//}
/*%%%%
  ROLE: this talks to the database (all rows in table)
*/
class clsVbzUserTable extends clsUserAccts {

    // STATIC ++
/*
    public static function HashPass($iPass,$iSalt) {
	$sToHash = $iSalt.$iPass;
	$sHashed = hash('whirlpool',$sToHash,TRUE);
	return $sHashed;
    }
    protected static function UserName_SQL_filt($iName) {
	return 'LOWER(UserName)='.SQLValue(strtolower($iName));
    }
*/
    // /STATIC --
    // DYNAMIC ++

    public function __construct($iDB) {
	parent::__construct($iDB);
	  //$this->Name('user');
	  //$this->KeyName('ID');
	  $this->ClassSng('clsVbzUserRec');
    }
    /*----
      RETURNS: clsVbzUserRec if login successful, NULL otherwise
    */
/*
    public function Login($iUser,$iPass) {
	$rc = $this->FindUser($iUser);
	if (is_null($rc)) {
	    // username not found
	    $oUser = NULL;
	} elseif ($rc->AuthValid($iPass)) {
	    $oUser = $rc;
	} else {
	    // username found, password wrong
	    $oUser = NULL;
	}
	return $oUser;
    }
    public function FindUser($iName) {
	$sqlFilt = self::UserName_SQL_filt($iName);
	$rc = $this->GetData($sqlFilt);
	$nRows = $rc->RowCount();
	if ($nRows == 0) {
	    $rc = NULL;
	} elseif ($nRows > 1) {
	    $nCount = $rc->RowCount();
	    $sDescr = 'Username "'.$iName.'" appears '.$nCount.' times in the user database.';
	    $this->Engine()->LogEvent(__FILE__.' line '.__LINE__,'name='.$iName,$sDescr,'UDUP',TRUE,TRUE);
	    $rc = NULL;
	} else {
	    $rc->NextRow();	// load the first (only) row
	}
	return $rc;
    }
*/
    /*----
      RULES: Usernames are stored with case-sensitivity, but are checked case-insensitively
    */
/*
    public function UserExists($iLogin) {
	$sqlFilt = self::UserName_SQL_filt($iLogin);
	$rc = $this->GetData($sqlFilt);
	return $rc->HasRows();
    }
*/
    /*----
      ACTION: add a user to the database
    */
/*
    public function AddUser($iLogin,$iPass) {
	$sSalt = openssl_random_pseudo_bytes(128);
	$sHashed = clsVbzUserTable::HashPass($sSalt,$iPass);
	$ar = array(
	  'UserName'	=> SQLValue($iLogin),
	  'PassHash'	=> SQLValue($sHashed),
	  'PassSalt'	=> SQLValue($sSalt),
	  'WhenCreated'	=> 'NOW()'
	);
	$rc = $this->Insert_andGet($ar);
	return $rc;
    }
*/
}
/*%%%%
  ROLE: this talks to the database (single row in table)
*/
class clsVbzUserRec extends clsUserAcct {
    public function UserName() {
	return $this->Value('UserName');
    }
    public function FullName() {
	$sFullName = $this->Value('FullName');
	if (is_null($sFullName)) {
	    return $this->Value('UserName');
	} else {
	    return $this->Value('FullName');
	}
    }
    public function AuthValid($iPass) {
	// get salt for this user
	$sSalt = $this->Value('PassSalt');

	// hash salt+pass
	$sHashed = clsVbzUserTable::HashPass($sSalt,$iPass);
	// see if it matches
	return ($sHashed == $this->Value('PassHash'));
    }
    /*----
      RETURNS: recordset of customers for this user (NULL if none)
    */
    public function CustRecs() {
	throw new exception('CustRecs() is deprecated; call ContactRecords().');
    }
    public function ContactRecords() {
	$tCusts = $this->Engine()->Custs();
	$rs = $tCusts->Recs_forUser($this->KeyValue());
	return $rs;
    }
}

/*%%%%
  PURPOSE: manages emailed authorization tokens
*/
class clsEmailTokens extends clsTable {

    // STATIC

    private static function MakeHash($sVal,$sSalt) {
	$sToHash = $sSalt.$sVal;
	$sHash = hash('whirlpool',$sToHash,TRUE);
	return $sHash;
    }

    // / STATIC
    // DYNAMIC

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('user_tokens');
	  $this->KeyName('ID_Email');
	  $this->ClassSng('clsEmailToken');
    }
    public function MakeToken($idEmail) {
	$isStrong = NULL;
	$nHashLen = 32;
	$sToken = openssl_random_pseudo_bytes($nHashLen,$isStrong);
	if (!$isStrong) {
	    $this->Engine()->LogEvent(__FILE__.' line '.__LINE__,NULL,'Could not use strong encryption for token.','WENC',TRUE,FALSE);
	}
	$sSalt = openssl_random_pseudo_bytes($nHashLen);
	// save the salt and hashed token
/*
	$sToHash = $sSalt.$sToken;
	$sHash = hash('whirlpool',$sToHash,TRUE);
*/
	$sHash = self::MakeHash($sToken,$sSalt);

	$db = $this->Engine();
	$ar = array(
	  'TokenHash'	=> $db->engine_db_safe_param($sHash),
	  'TokenSalt'	=> $db->engine_db_safe_param($sSalt),
	  'WhenExp'	=> 'NOW() + INTERVAL 1 HOUR'	// expires in 1 hour
	  );

	// -- check to see if there's already a hash for this email address
	$sqlFilt = 'ID_Email='.$idEmail;
	$rc = $this->GetData($sqlFilt);
	if ($rc->HasRows()) {
	    $rc->NextRow();	// load the record
	    $rc->Update($ar);
	} else {
	    $ar['ID_Email'] = $idEmail;
	    $rc = $this->Insert($ar);
	    if ($rc === FALSE) {
		echo 'SQL='.$this->sqlExec.'<br>';
		throw new exception('<b>Internal error</b>: could not create token record.');
	    }
	}
	$rc->Token($sToken);	// caller may need this, but it shouldn't be stored
	return $rc;
    }
    /*----
      NOTE: Even if the token has expired, we want to return it so that we can
	tell the user it has expired. This should minimize frustration, and
	doesn't really pose a security risk as far as I can tell.
      RETURNS: token object if a matching token was found; NULL otherwise
    */
    public function FindToken($idEmail,$sToken) {
	//$sqlFilt = '(ID_Email='.$idEmail.') AND (WhenExp > NOW())';
	$sqlFilt = 'ID_Email='.$idEmail;
	$rc = $this->GetData($sqlFilt);
	if ($rc->HasRows()) {
	    $rc->NextRow();	// assume there's only 1 row, and load it
	    $sSalt = $rc->Value('TokenSalt');
	    $sHash = self::MakeHash($sToken,$sSalt);
	    if ($sHash == $rc->Value('TokenHash')) {
		return $rc;
	    }
	} else {
	    // no tokens for that email - fail
	    return NULL;
	}
    }
    /*----
      ACTION: Delete all expired tokens
      TODO: Later, we might want to log unused tokens. Or maybe not.
	Right now, nothing is calling this -- because we don't want
	to delete tokens right after they expire. Not yet sure how
	long to leave them active... but that can be decided later.
	Maybe they should only be deleted once the user has successfully
	reset their password?
    */
    protected function CleanTokens() {
	$sql = 'DELETE FROM '.$this->Name().' WHERE WhenExp < NOW()';
	$this->Engine()->Exec($sql);
    }
}
class clsEmailToken extends clsDataSet {
    private $sToken;

    public function Token($iVal=NULL) {
	if (!is_null($iVal)) {
	    $this->sToken = $iVal;
	}
	return $this->sToken;
    }
    /*----
      RETURNS: TRUE iff expiration date has not passed
    */
    public function IsActive() {
	$sExp = $this->Value('WhenExp');
	$dtExp = strtotime($sExp);	// there's got to be a better function for this
	return ($dtExp > time());
    }
    /*----
      ACTION: update the token's expiration date
    */
    public function Renew() {
	$ar = array('WhenExp'	=> 'NOW() + INTERVAL 1 HOUR');	// expires in 1 hour
	$this->Update($ar);
    }
}
class clsEmailAuth extends clsCustEmails {
    /*----
      ACTION: See if we can send a password reset request for the given address.
	If there are no records with that address, don't send it.
      RETURNS: HTML to display showing status of request
    */
    public function SendPassReset_forAddr($sEmail) {
	$rcEmail = $this->Find($sEmail);
	$nEmails = $rcEmail->RowCount();
	if ($nEmails > 0) {
	    $rcEmail->NextRow();

	    if ($nEmails > 1) {
		// log error if more than one row found
		$this->Engine()->LogEvent(__FILE__.' line '.__LINE__,NULL,'Found '.$nEmails.' records for email address "'.$sEmail.'".','EMD',TRUE,TRUE);
	    }
	    if ($this->IsLoggedIn()) {
		//$sAction = 'allow you to change your password';
		$sAction = 'attach customer profiles associated with this email address';
	    } else {
		$sAction = 'allow you to set your username and password';
	    }

	    // generate and store the auth token
	    $rcToken = $this->EmailTokens()->MakeToken($rcEmail->KeyValue());
	    // send the email
	    $url = self::AuthURL($rcEmail->KeyValue(),$rcToken->Token());

	    // these vars are used for both the email template and the message shown on the web
	    $arVars = array(
	      'addr'	=> $sEmail,
	      'action'	=> $sAction,
	      'url'	=> $url
	      );
	    $tplt = new clsStringTemplate_array(KS_TPLT_OPEN,KS_TPLT_SHUT,$arVars);

	    $sMsg = $tplt->Replace(KS_TPLT_AUTH_EMAIL_TEXT);	// "Someone (hopefully you) has made a request..."
	    $sSubj = KS_TEXT_AUTH_EMAIL_SUBJ;
	    //mail($sEmail,$sSubj,$sMsg);	// TODO: include customer name and FROM header
	    $this->Engine()->App()->Page()->DoEmail_fromAdmin_Auto($sEmail,'',$sSubj,$sMsg);

	    // display status message
	    $sMsg = $tplt->Replace(KS_TPLT_AUTH_EMAIL_WEB);	// "A link has been emailed to you..."
	    echo $this->Skin()->RenderSuccess($sMsg);
	    echo $this->Skin()->RenderTableHLine();
	} else {
	    echo $this->Skin()->RenderError('The email address <b>'.$sEmail.'</b> was not found in our records.');
	}
    }
    private function IsLoggedIn() {
	return $this->App()->Session()->HasUser();
    }
    protected function Skin() {
	return $this->App()->Skin();
    }
}