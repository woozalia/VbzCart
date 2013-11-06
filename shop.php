<?php
/*
  PURPOSE: vbz library for handling dynamic data related to shopping (cart, mainly)
  HISTORY:
    2010-10-28 kluged the blank-order-email problem
    2010-12-24 Fixed calls to Update() so they always pass arrays
    2011-03-31 created AddMoney() and IncMoney()
  KLUGES:
    RenderReceipt() and TemplateVars() both have to reload the current record, which shouldn't be necessary.
*/

// FILE NAMES:
define('KWP_ICON_ALERT'		,'/tools/img/icons/button-red-X.20px.png');
define('KWP_ICON_OKAY'		,'/tools/img/icons/chkbox.gif');

define('KS_VBZCART_SESSION_KEY','vbzcart_key');

// http query argument names
define('KSQ_ARG_PAGE_DATA','page');
define('KSQ_ARG_PAGE_DEST','goto');

// form sequencing - order in which form pages appear - for navigation code
define('KI_SEQ_CART', 0);
define('KI_SEQ_SHIP', 1);
define('KI_SEQ_PAY', 2);
define('KI_SEQ_CONF', 3);
define('KI_SEQ_RCPT', 4);

/*
 database class with creators for shop classes
*/
class clsVbzData_Shop extends clsVbzData {
    public function Sessions($id=NULL) {
	return $this->Make('clsSessions_StoreUI',$id);
    }
    public function Clients($id=NULL) {
	return $this->Make('clsShopClients',$id);
    }
    public function Users($id=NULL) {
	return $this->Make('clsVbzUserRecs',$id);
    }
    public function Carts($id=NULL) {
	return $this->Make('clsShopCarts',$id);
    }
    public function CartLines($id=NULL) {
	return $this->Make('clsShopCartLines',$id);
    }
    public function CartLog() {
	return $this->Make('clsShopCartLog');
    }
    public function Orders($id=NULL) {
	return $this->Make('clsOrders',$id);
    }
    public function OrdLines($id=NULL) {
	return $this->Make('clsOrderLines',$id);
    }
/*
    public function OrderLog() {
	return $this->Make('clsOrderLog');
    }
*/
    public function OrdMsgs($id=NULL) {
	return $this->Make('clsOrderMsgs',$id);
    }
    public function Custs() {
	return $this->Make('clsCusts');
    }
    public function CustNames() {
	return $this->Make('clsCustNames');
    }
    public function CustAddrs() {
	return $this->Make('clsCustAddrs');
    }
/* 2013-10-19 not sure why these were commented out
    public function CustEmails() {
	return $this->Make('clsCustEmails');
    }
    public function CustPhones() {
	return $this->Make('clsCustPhones');
    }
    public function CustCCards() {
	return $this->Make('clsCustCards');
    }
*/
}

/*==================
  CLASS: clsShipZone
  PURPOSE: shipping zone functions
  USAGE: Customize the isDomestic() function if you're shipping from somewhere other than the US
  RULES:
    * If a country's code isn't found in arDesc, it defaults to International
      ...there's got to be a better way to do this...
*/
class clsShipZone {
    static private $arDesc = array(
      'CA' => 'Canada',
      'US' => 'United States',
      'INT' => 'International',
      );
    // per-item adjustment factors
    static private $arItmFactors = array(
	'US' => 1.0,
	'CA' => 2.0,
	'INT' => 4.0,
      );
    // per-package adjustment factors
    static private $arPkgFactors = array(	// there's got to be a better way to do this...
	'US' => 1.0,
	'CA' => 2.0,
	'INT' => 4.0,
      );
    static private $arCountryCodes = array(
	'united states'	=> 'US',
	'canada'	=> 'CA',
	'australia'	=> 'AU',
      );

    private $strAbbr;

    public function Abbr($iAbbr=NULL) {
	if (!is_null($iAbbr)) {
	    $this->strAbbr = $iAbbr;
	}
	if (empty($this->strAbbr)) {
	    $this->strAbbr = KS_SHIP_ZONE_DEFAULT;
	}
	return $this->strAbbr;
    }

    public function Set_fromName($iName) {
	$sName = empty($iName)?KS_SHIP_ZONE_DEFAULT:$iName;
	$strLC = strtolower($sName);
	if (array_key_exists($strLC,self::$arCountryCodes)) {
	    $this->strAbbr = self::$arCountryCodes[$strLC];
	} else {
	    $this->strAbbr = 'INT';	// assume international if not recognized
	}
    }

/*
    public function Set_fromCode($iCode) {
	$sCode = empty($iCode)?KS_SHIP_ZONE_DEFAULT:$iCode;
	$strLC = strtolower($sCode);
	if (array_key_exists($strLC,self::$arDesc)) {
	    $this->strAbbr = self::$arDesc[$strLC];
	} else {
	    echo 'Zone code ['.$sName.'] not found.';
	    throw new exception('Internal error: unknown zone requested.');
	}
    }
*/
    public function Text() {	// should be Name()
	return self::$arDesc[$this->Abbr()];
    }
    
    public function hasState() {
	switch ($this->Abbr()) {
	  case 'AU':	return TRUE;	break;
	  case 'CA':	return TRUE;	break;
	  case 'US':	return TRUE;	break;
	  default:	return FALSE;	break;
	}
    }
    public function StateLabel() {
	switch ($this->Abbr()) {
	  case 'AU':	return 'State/Territory'; break;
	  case 'CA':	return 'Province';	break;
	  case 'US':	return 'State';		break;
	  default:	return 'County/Province'; break;
	}
    }
    public function PostalCodeName() {
	switch ($this->Abbr()) {
	  case 'US':	return 'Zip Code&trade;';	break;
	  default:	return 'Postal Code'; 		break;
	}
    }
    public function Country() {
	switch ($this->strAbbr) {
	  case 'US':	return 'United States';		break;
	  case 'CA':	return 'Canada';		break;
	  default:	return NULL; break;
	}
    }
    public function isDomestic() {
	return ($this->Abbr() == 'US');
    }
    /*----
      VERSION: Total is not being displayed, so no need to prompt to update it
    */
    public function ComboBox() {
	$strZoneCode = $this->Abbr();
	$out = '<select name="ship-zone">';
	foreach (self::$arDesc as $key => $descr) {
	    $strZoneDesc = $descr;
	    if ($key == $strZoneCode) {
		    $htSelect = " selected";
	    } else {
// to prompt user to recalculate the total when zone changes, uncomment this line:
//		$strZoneDesc .= " - recalculate";
		$htSelect = "";
	    }
	    $out .= '<option'.$htSelect.' value="'.$key.'">'.$strZoneDesc.'</option>';
	}
	$out .= '</select>';
	return $out;
    }
    /*----
      RETURNS: per-item price factor for the current shipping zone
    */
/* 2013-10-13 I'm not seeing this debug code anywhere, so I'm wondering if this is ever called.
    protected function PerItemFactor() {
echo 'CODE=['.$this->Abbr().'] ITEM FACTOR=['.self::$arItmFactors[$this->Abbr()].']<br>';
	return self::$arItmFactors[$this->Abbr()];
    }
*/
    /*----
      RETURNS: per-package price factor for the current shipping zone
    */
    protected function PerPkgFactor() {
	return self::$arPkgFactors[$this->Abbr()];
    }
    /*----
      INPUT: base per-item shipping price
      RETURNS: calculated price for the current shipping zone
    */
    public function CalcPerItem($iBase) {
	return $iBase * $this->PerItemFactor();
    }
    /*----
      INPUT: base per-package shipping price
      RETURNS: calculated price for the current shipping zone
    */
    public function CalcPerPkg($iBase) {
	return $iBase * $this->PerPkgFactor();
    }
}

// ShopCart Log
class clsShopCartLog extends clsTable {
    const TableName='shop_cart_event';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
    }
    public function Add($iCart,$iCode,$iDescr,$iUser=NULL) {
	global $vgUserName;

	$strUser = is_null($iUser)?$vgUserName:$iUser;
	if ($iCart->hasField('ID_Sess')) {
	    $idSess = $iCart->ID_Sess;
	} else {
	// this shouldn't happen, but we still need to log the event, and ID_Sess is NOT NULL:
	    $idSess = 0;
	}

	$edit['ID_Cart'] = $iCart->ID;
	$edit['WhenDone'] = 'NOW()';
	$edit['WhatCode'] = SQLValue($iCode);
	$edit['WhatDescr'] = SQLValue($iDescr);
	$edit['ID_Sess'] = $idSess;
	$edit['VbzUser'] = SQLValue($strUser);
	$edit['Machine'] = SQLValue($_SERVER["REMOTE_ADDR"]);
	$this->Insert($edit);
    }
}

/* ===================
  CLASS: clsShopSessions
  PURPOSE: Handles shopping sessions
*/
class clsShopSessions extends clsTable {
    protected $SessKey;
    private $oSess;

    const TableName='shop_session';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsShopSession');
	$this->oSess = NULL;
    }
    private function Create() {
	//$objSess = new clsShopSession($this->objDB);
	$objSess = $this->SpawnItem();
	$objSess->InitNew();
	$objSess->Create();
	return $objSess;
    }
    public function SetCookie($iSessKey=NULL) {
	if (!is_null($iSessKey)) {
	    $this->SessKey = $iSessKey;
	}
	setcookie(KS_VBZCART_SESSION_KEY,$this->SessKey,0,'/','.'.KS_STORE_DOMAIN);
    }
    /*----
      ACTION:
	* gets session key and auth from cookie
	* if session object exists, tries to reuse it
	* if 
      HISTORY:
	2012-10-13 Added caching of the session object to avoid creating multiple copies.
    */
    public function GetCurrent() {
	$okSession = FALSE;
	$objClient = NULL;
	$strSessKey = NULL;
	if (isset($_COOKIE[KS_VBZCART_SESSION_KEY])) {
	    $strSessKey = $_COOKIE[KS_VBZCART_SESSION_KEY];
	}
	$doNew = TRUE;
	if (!is_null($strSessKey)) {
	    list($ID,$strSessRand) = explode('-',$strSessKey);
	    if (!is_null($this->oSess)) {
		if ($this->oSess->KeyValue() == $ID) {
		    $doNew = FALSE;
		}
	    }
	    if ($doNew) {
		$this->oSess = $this->GetItem($ID);
	    }
		
	    $okSession = $this->oSess->IsValidNow($strSessRand);	// do session's creds match browser's creds?
	}
	if (!$okSession) {
	  // no current/valid session, so make a new one:
	    // add new record...
	    $this->oSess = $this->Create();
	    // generate new session key
	    $strSessKey = $this->oSess->SessKey();
	    //setcookie(KS_VBZCART_SESSION_KEY,$strSessKey);
	    $this->SetCookie($strSessKey);
	}
	return $this->oSess;
    }
}
/* ===================
  CLASS: clsShopSession
  PURPOSE: Represents a single shopping session
*/
class clsShopSession extends clsDataSet {
    private $objClient;
    protected $objCart;
    protected $objUser;

    public function __construct(clsDatabase $iDB=NULL, $iRes=NULL, array $iRow=NULL) {
	parent::__construct($iDB,$iRes,$iRow);
/*
	if (is_null($this->objDB)) {
	    echo '<pre>';
	    throw new exception('Database not set in clsShopSession.');
	}
*/
	//$this->Table = $this->Engine()->Sessions();
    }
    public function InitNew() {
	$this->Token = RandomString(31);
	$this->ID_Client = NULL;
	$this->ID_Cart = NULL;
	$this->WhenCreated = NULL;	// hasn't been created until written to db
	$this->Client();
    }
    public function Create() {
	$sql =
	  'INSERT INTO `'.clsShopSessions::TableName.'` (ID_Client,ID_Cart,Token,WhenCreated)'.
	  'VALUES('.SQLValue($this->ID_Client).', '.SQLValue($this->ID_Cart).', "'.$this->Token.'", NOW());';
	$this->objDB->Exec($sql);
	$this->ID = $this->objDB->NewID('session.create');
	if (!$this->Client()->isNew) {
	    $this->Client()->Stamp();
	}
    }
    /*-----
      RETURNS: TRUE if the stored session credentials match current reality (browser's credentials)
    */
    public function IsValidNow($iKey) {
	$ok = ($this->Token == $iKey);
	if ($ok) {
	    $idClientWas = $this->ID_Client;
	    $objClient = $this->Client();
	    if ($idClientWas != $this->ID_Client) {
		// not an error, but could indicate a hacking attempt -- so log it, flagged as severe:
		$this->objDB->LogEvent(
		  'session.valid',
		  'KEY='.$iKey,' OLD-CLIENT='.$idClientWas.' NEW-CLIENT='.$this->ID_Client,
		  'stored session client mismatch','XCRED',FALSE,TRUE);
		$ok = FALSE;
	    }
	}
	return $ok;
    }
    public function SetCart($iID) {
	$this->ID_Cart = $iID;
	$this->Update(array('ID_Cart'=>$iID));
    }
    /*----
      RETURNS: TRUE iff the current session has a cart
    */
    public function HasCart() {
	return !is_null($this->Value('ID_Cart'));
    }
    /*----
      RETURNS: TRUE iff the current session has a cart with something in it
    */
    public function HasCartContents() {
	if ($this->HasCart()) {
	    if ($this->CartObj_forID()->HasLines()) {
		return TRUE;
	    }
	}
	return FALSE;
    }
    /*----
      ACTION: Drop the current cart, so that added items will create a new one
      HOW: Tell the cart to lock itself, but don't forget it.
	CartObj() checks the cart to see if it is locked, and gets a new one if so.
      USED BY: "delete cart" user button
    */
    public function DropCart() {
	//$this->ID_Cart = NULL;
	$this->CartObj()->Update(array('WhenVoided'=>'NOW()'));
    }
/*
    private function IsCartUsable($iCart) {
	return !$iCart->IsLocked();
    }
*/
    /*----
      ACTION: return an object for the current cart ID, or NULL if ID is NULL.
      ASSUMES: If there is a cart object already, it is the correct one for this session.
    */
    public function CartObj_forID() {
	$oCart = $this->objCart;
	if (is_null($oCart)) {
	    $tCarts = $this->Engine()->Carts();
	    if (!is_null($this->Value('ID_Cart'))) {
		$oCart = $tCarts->GetItem($this->ID_Cart);
	    }
	}
	$this->objCart = $oCart;
	return $oCart;
    }
    /*----
      ACTION: Return a cart object. If there isn't an associated cart yet, or if
	the current one isn't usable, create a new one.
      NOTE: This is actually a store UI function, even though it returns an object.
	If any non-store-UI code needs to get a cart object, they should get it more
	  directly (via Order, Session, etc.)
      NOTE 2: The above note does not make any sense. I suspect that it represents
	bad conceptual design. If the two methods serve different purposes, they should
	be named differently. TODO: Probably should be renamed CartObj_forShopping. 2013-10-13
      ASSUMES: If there is a cart object already, it is the correct one for this session
	-- unless the order has been locked, in which case we'll get a new one.
    */
    public function CartObj_forShopping() {
// if there's a cart for this session, load it; otherwise create a new one but don't save it:

	$oCart = $this->CartObj_forID();
	if (is_null($oCart) || $oCart->IsLocked()) {
	    // if no cart, or cart is locked, get a new one:
	    $oCart = $this->objDB->Carts()->SpawnItem();
	    $oCart->InitNew($this->ID);
	    $idCart = $oCart->KeyValue();
	    $this->SetCart($idCart);
	    $this->objCart = $oCart;
	}
	return $oCart;

/* OLD CODE 2013-10-14
	if (!isset($this->objCart)) {
	    $objCarts = $this->Engine()->Carts();
	    if (!is_null($this->ID_Cart)) {
		$objCart = $objCarts->GetItem($this->ID_Cart);
		if (!$this->IsCartUsable($objCart)) {
		    // if the order is locked, clear the cart so we get a new one:
		    $this->ID_Cart = NULL;
		}
	    }
	    if (is_null($this->ID_Cart)) {
		$objCart = $this->objDB->Carts()->SpawnItem();
		$objCart->InitNew($this->ID);
	    }
	}
	assert('is_object($objCart);');	// we should always have a cart at this point
	$this->objCart = $objCart;
	return $objCart;
*/
    }
    public function SessKey() {
	return $this->ID.'-'.$this->Token;
    }
    /*----
      ACTION: Loads the cart object.
	* If ID_Cart is set, looks up that cart.
	* If that cart has been locked (which currently happens when the cart
	  is converted to an order but might mean other things in the future),
	  discard it and get a new one.
    */
/*
    public function Cart() {	// DEPRECATED FORM
	return $this->CartObj();
    }
*/
    public function Client() {
// if the session's client record matches, then load the client record; otherwise create a new one:
	if (!isset($this->objClient)) {
	    $this->objClient = NULL;
	    $objClients = $this->objDB->Clients();
	    if (!is_null($this->ID_Client)) {
		$this->objClient = $objClients->GetItem($this->ID_Client);
		if (!$this->objClient->IsValidNow()) {
		    $this->objClient = NULL;	// doesn't match current client; need a new one
// TO DO: this should invalidate the session and be logged somewhere.
// It means that a session has jumped to a new browser, which shouldn't happen and might indicate a hacking attempt.
		}
	    }
	    if (is_null($this->objClient)) {
		$this->objClient = $objClients->SpawnItem();
		$this->objClient->InitNew();
		$this->objClient->Build();
		$this->ID_Client = $this->objClient->ID;
	    }
	}
	return $this->objClient;
    }
    /*----
      NOTE: I can't think of any circumstances under which $this->HasValue('ID_User') would be false.
    */
    public function HasUser() {
	//return !empty($this->objUser);
	return !is_null($this->Value('ID_User'));
    }
    /*----
      ACTION: Attempts to log the user in with the given credentials.
      RETURNS: user object if successful, NULL otherwise.
    */
    public function UserLogin($iUser,$iPass) {
	$oUser = clsVbzUser::Login($iUser,$iPass);
	$this->UserObj($oUser);		// set user for this session
    }
    /*----
      ACTION: Logs the current user out. (Clears ID_User in session record.)
    */
    public function UserLogout() {
	$this->ClearValue('ID_User');
	$arUpd = array('ID_User'=>'NULL');
	$this->Update($arUpd);
    }
    /*----
      RETURNS: User record object, if the session has a user; NULL otherwise
      ASSUMES:
	If $this->objUser is set, it matches ID_User
    */
    public function UserObj(clsVbzUserRec $oUser=NULL) {
	if (!is_null($oUser)) {
	    // we are SETTING the user
	    $doChg = FALSE;
	    if (is_null($this->objUser)) {
		$doChg = TRUE;
	    } else {
		$idOld = $this->Value('ID_User');
		$idNew = $oUser->KeyValue();
		if ($idOld != $idNew) {
		    $doChg = TRUE;
		}
	    }
	    if ($doChg) {
		$this->objUser = $oUser;
		// UPDTE local & saved ID_User
		$this->SetUser($rcUserNew->KeyValue());
	    }
	} else {
	    // we are trying to RETRIEVE the user
	    if (empty($this->objUser)) {
		$tUsers = $this->Engine()->Users();
		if ($this->HasUser()) {
		    $this->objUser = $tUsers->GetItem($this->Value('ID_User'));
		} else {
		    $this->objUser = NULL;
		}
	    }
	}
	return $this->objUser;
    }
    /*----
      ACTION: return or set a CMS-User object
	If oUser is NULL and user is not logged in, returns NULL.
      ASSUMES:
	If $this->objUser is set, it matches ID_User
    */
/* THIS CAN'T BE RIGHT, and I need it elsewhere anyway. Refigure if needed.
    public function UserObj(clsVbzUser $oUser=NULL) {
	if (!is_null($oUser)) {
	    // we are SETTING the user
	    $doChg = FALSE;
	    $rcUserNew = $oUser->RecObj();
	    if (is_null($this->objUser)) {
		$doChg = TRUE;
	    } else {
		$rcUserOld = $this->objUser->RecObj();
		if ($rcUserNew->KeyValue() != $rcUserOld->KeyValue()) {
		    $doChg = TRUE;
		}
	    }
	    if ($doChg) {
		$this->objUser = $oUser;
		$this->SetUser($rcUserNew->KeyValue());
	    }
	} else {
	    // we are trying to RETRIEVE the user
	    if (empty($this->objUser)) {
		$tUsers = $this->Engine()->Users();
		if ($this->HasUser()) {
		    $this->objUser = $tUsers->GetItem($this->Value('ID_User'));
		} else {
		    $this->objUser = NULL;
		}
	    }
	}
	return $this->objUser;
    }
*/
    public function SetUser($idUser) {
	$ar = array('ID_User'=>$idUser);
	$this->Update($ar);			// save user ID to database
	$this->Value('ID_User',$idUser);	// update it in RAM as well
    }
}
class clsShopClients extends clsTable {
    const TableName='shop_client';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsShopClient');
    }
}
class clsShopClient extends clsDataSet {
    public function __construct(clsDatabase $iDB=NULL, $iRes=NULL, array $iRow=NULL) {
	parent::__construct($iDB,$iRes,$iRow);
	//$this->Table = $this->objDB->Clients();
    }
    public function InitNew() {
	$this->ID = NULL;
	$this->Address = $_SERVER["REMOTE_ADDR"];
	$this->Browser = $_SERVER["HTTP_USER_AGENT"];
	$this->Domain = gethostbyaddr($this->Address);
	$this->CRC = crc32($this->Address.' '.$this->Browser);
	$this->isNew = TRUE;
    }
    public function IsValidNow() {
	return (($this->Address == $_SERVER["REMOTE_ADDR"]) && ($this->Browser == $_SERVER["HTTP_USER_AGENT"]));
    }
    public function Stamp() {
	$this->Update(array('WhenFinal'=>'NOW()'));
    }
    public function Build() {
    // update existing record, if any, or create new one
	$sql = 'SELECT * FROM '.clsShopClients::TableName.' WHERE CRC="'.$this->CRC.'";';
	$this->Query($sql);
	if ($this->hasRows()) {
	    $this->NextRow();	// get data
	    $this->isNew = FALSE;
	} else {
	    $strDomain = $this->objDB->SafeParam($this->Domain);
	    $strBrowser = $this->objDB->SafeParam($this->Browser);
	    $sql = 'INSERT INTO `'.clsShopClients::TableName.'` (CRC, Address, Domain, Browser, WhenFirst)'
	    .' VALUES("'.$this->CRC.'", "'.$this->Address.'", "'.$strDomain.'", "'.$strBrowser.'", NOW());';
	    $this->objDB->Exec($sql);
	    $this->ID = $this->objDB->NewID('client.make');
	}
    }
}



/* ===============
 UTILITY FUNCTIONS
*/
function RandomString($iLen) {
    $out = '';
    for ($i = 0; $i<$iLen; $i++) {
	$n = mt_rand(0,61);
	$out .= CharHash($n);
    }
    return $out;
}
function CharHash($iIndex) {
    if ($iIndex<10) {
	return $iIndex;
    } elseif ($iIndex<36) {
	return chr($iIndex-10+ord('A'));
    } else {
	return chr($iIndex-36+ord('a'));
    }
}
// this can later be adapted to be currency-neutral
// for now, it just does dollars
function FormatMoney($iAmount,$iPrefix='',$iPlus='') {
    if ($iAmount < 0) {
	$str = '-'.$iPrefix.sprintf( '%0.2f',-$iAmount);
    } else {
	$str = $iPlus.$iPrefix.sprintf( '%0.2f',$iAmount);
    }
    return $str;
}
/*
  HISTORY:
    2011-08-03 added round() function to prevent round-down error
*/
function AddMoney($iMoney1,$iMoney2) {
    $intMoney1 = (int)round($iMoney1 * 100);
    $intMoney2 = (int)round($iMoney2 * 100);
    $intSum = $intMoney1 + $intMoney2;
    return $intSum/100;
}
/*
  HISTORY:
    2011-08-03 added round() function to prevent round-down error
*/
function IncMoney(&$iMoney,$iMoneyAdd) {
    $intBase = (int)round(($iMoney * 100));
    $intAdd = (int)round(($iMoneyAdd * 100));
    $intSum = $intBase + $intAdd;
    $iMoney = $intSum/100;
}
