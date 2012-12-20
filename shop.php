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


// TABLE ACTION KEYS
define('KS_URL_PAGE_SESSION',	'sess');
define('KS_URL_PAGE_ORDER',	'ord');	// must be consistent with events already logged
define('KS_URL_PAGE_ORDERS',	'orders');

if (!defined('LIBMGR')) {
    require(KFP_LIB.'/libmgr.php');
}

clsLibMgr::Add('strings',		KFP_LIB.'/strings.php',__FILE__,__LINE__);
clsLibMgr::Add('string.tplt',	KFP_LIB.'/StringTemplate.php',__FILE__,__LINE__);
clsLibMgr::Add('tree',		KFP_LIB.'/tree.php',__FILE__,__LINE__);
clsLibMgr::Add('vbz.store',	KFP_LIB_VBZ.'/store.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsVbzPage', 'vbz.store');
clsLibMgr::Add('vbz.cart',	KFP_LIB_VBZ.'/cart.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsPageCart', 'vbz.cart');
  clsLibMgr::AddClass('clsShopCarts','vbz.cart');
clsLibMgr::Add('vbz.order',	KFP_LIB_VBZ.'/orders.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsOrders', 'vbz.order');
clsLibMgr::Load('strings',__FILE__,__LINE__);
clsLibMgr::Load('string.tplt',__FILE__,__LINE__);
clsLibMgr::Load('tree',__FILE__,__LINE__);
clsLibMgr::Load('vbz.store',__FILE__,__LINE__);
//    clsLibMgr::Load('vbz.cart',__FILE__,__LINE__);

define('KS_VBZCART_SESSION_KEY','vbzcart_key');

// http query argument names
define('KSQ_ARG_PAGE_DATA','page');
define('KSQ_ARG_PAGE_DEST','goto');

// http query values
define('KSQ_PAGE_CART','cart');	// shopping cart
define('KSQ_PAGE_SHIP','ship');	// shipping page
define('KSQ_PAGE_PAY','pay');	// payment page
define('KSQ_PAGE_CONF','conf');	// customer confirmation of order
define('KSQ_PAGE_RCPT','rcpt');	// order receipt
// if no page specified, go to the shipping info page (first page after cart):
define('KSQ_PAGE_DEFAULT',KSQ_PAGE_SHIP);

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
/*
    public function Custs() {
	return $this->Make('clsCusts');
    }
    public function CustNames() {
	return $this->Make('clsCustNames');
    }
    public function CustAddrs() {
	return $this->Make('clsCustAddrs');
    }
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
//echo '<br>RESETTING SHIPZONE; WAS '.$this->ShipZone;
	    $this->strAbbr = 'US';	// TO DO: set from configurable parameter
	}
	return $this->strAbbr;
    }
    public function Set_fromName($iName) {
	$strLC = strtolower($iName);
	if (array_key_exists($strLC,self::$arCountryCodes)) {
	    $this->strAbbr = self::$arCountryCodes[$strLC];
	} else {
	    echo 'Country ['.$iName.'] not found in list.';
	    throw new exception('Internal error: unknown country requested.');
	}
    }
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
    public function ComboBox() {
	$strZoneCode = $this->Abbr();
	$out = '<select name="ship-zone">';
	foreach (self::$arDesc as $key => $descr) {
//$dest (keys(%listShipListDesc)) {
		$strZoneDesc = $descr;
		if ($key == $strZoneCode) {
			$htSelect = " selected";
		} else {
			$strZoneDesc .= " - recalculate";
			$htSelect = "";
		}
		$out .= '<option'.$htSelect.' value="'.$key.'">'.$strZoneDesc.'</option>';
	}
	$out .= '</select>';
	return $out;
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

    const TableName='shop_session';

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name(self::TableName);
	  $this->KeyName('ID');
	  $this->ClassSng('clsShopSession');
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
    public function GetCurrent() {
	$okSession = FALSE;
	$objClient = NULL;
	$strSessKey = NULL;
	if (isset($_COOKIE[KS_VBZCART_SESSION_KEY])) {
	    $strSessKey = $_COOKIE[KS_VBZCART_SESSION_KEY];
	}
	if (!is_null($strSessKey)) {
	    list($ID,$strSessRand) = explode('-',$strSessKey);
	    $objSess = $this->GetItem($ID);
	    $okSession = $objSess->IsValidNow($strSessRand);	// do session's creds match browser's creds?
	}
	if (!$okSession) {
	  // no current/valid session, so make a new one:
	    // add new record...
	    $objSess = $this->Create();
	    // generate new session key
	    $strSessKey = $objSess->SessKey();
	    //setcookie(KS_VBZCART_SESSION_KEY,$strSessKey);
	    $this->SetCookie($strSessKey);
	}
	return $objSess;
    }
}
/* ===================
  CLASS: clsShopSession
  PURPOSE: Represents a single shopping session
*/
class clsShopSession extends clsDataSet {
    private $objCart;
    private $objClient;

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
      ACTION: Drop the current cart, so that added items will create a new one
      HOW: Tell the cart to lock itself, but don't forget it.
	CartObj() checks the cart to see if it is locked, and gets a new one if so.
      USED BY: "delete cart" user button
    */
    public function DropCart() {
	//$this->ID_Cart = NULL;
	$this->CartObj()->Update(array('WhenVoided'=>'NOW()'));
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
      INPUT: $iCaller is for debugging and is discarded; caller should pass __METHOD__ as the argument.
    */
    public function Cart() {	// DEPRECATED FORM
	return $this->CartObj();
    }
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
