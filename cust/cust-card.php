<?php

/*
  HISTORY:
    2014-09-23 Split off from base.cust.php (to be renamed cust.php)
    2015-01-04 Rewriting how salt and packing work.
    2015-01-10 No longer using salt; it was a mistake.
    2016-05-22 Moved vbzCipher here from base.cust.php
*/

define('KC_SEP_PACK',"\t");	// textified data might include any printable character, so use non-printable

/*%%%%
  PURPOSE: cipher class that works with vbz internals
    Nobody seems to be using this anymore. Cipher_pubkey is used only by Card classes.
    TODO: Should get Data via global App object... or just delete it.
*/
class vbzCipher extends Cipher_pubkey {
    public function encrypt($input) {
	global $vgoDB;

	if (!$this->PubKey_isSet()) {
	    $fn = $vgoDB->VarsGlobal()->Val('public_key.fspec');
	    $fs =  KFP_KEYS.'/'.$fn;
	    $sKey = file_get_contents($fs);
	    $this->PubKey($sKey);
	}
	return parent::encrypt($input);
    }
}
/* =======
 CREDIT CARD UTILITY CLASS
*/
class clsCustCards extends vcBasicTable {

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'cust_cards';
    }
    protected function SingularName() {
	return 'clsCustCard';
    }

    // -- CEMENTING -- //
    // ++ STATIC ++ //

   public static function CardTypeChar($iNum) {
	throw new exception('2016-07-31 The Table version of this function is deprecated; call Records::CalcCardTypeChar() instead.');
	$chDigit = substr($iNum,0,1);
	$arDigits = array(
	  '3' => 'A',
	  '4' => 'V',
	  '5' => 'M',
	  '6' => 'D'
	  );
	if (isset($arDigits[$chDigit])) {
	    $chOut = $arDigits[$chDigit];
	} else {
	    $chOut = '?'.$chDigit;
	}
	return $chOut;
    }
    public static function CardTypeName($iNum) {
	throw new exception('2016-07-31 The Table version of this function is deprecated; call Records::CalcCardTypeName() instead.');
	$chDigit = substr($iNum,0,1);
	$arDigits = array(
	  '3' => 'Amex',
	  '4' => 'Visa',
	  '5' => 'MasterCard',
	  '6' => 'Discover'
	  );
	if (isset($arDigits[$chDigit])) {
	    $out = $arDigits[$chDigit];
	} else {
	    $out = '?'.$chDigit;
	}
	return $out;
    }
    public static function SafeDescr_Short($iNum,$iExp) {
	throw new exception('2016-07-31 The Table version of this function is deprecated; call Records::CalcSafeString_toStore() instead.');
	//$dtExp = strtotime($this->CardExp);
	$dtExp = self::ExpiryText_toDate($iExp);
	if (is_null($dtExp)) {
	    $strDate = '?/?';
	} else {
	    $strDate = $dtExp->format('n/y');
	}
	$out = self::CardTypeChar($iNum).'-'.substr($iNum,-4).'x'.$strDate;
	return $out;
    }
    /* 2016-07-31 No longer needed in Table class; moving to Records class as CalcSafeDescr_long().
    public static function SafeDescr_Long($iNum,$iExp) {
	$dtExp = self::ExpiryText_toDate($iExp);
	if (is_null($dtExp)) {
	    $strDate = '?/?';
	} else {
	    $strDate = $dtExp->format('F').' of '.$dtExp->format('Y').' ('.$dtExp->format('n/y').')';
	}
	$out = self::CardTypeName($iNum).' -'.substr($iNum,-4).' expires '.$strDate;
	return $out;
    } */
    public static function Searchable($iRaw) {
	$xts = new xtString(strtolower($iRaw),TRUE);
	$xts->KeepOnly('0-9');	// keep only numerics
	return $xts->Value;
    }
    /*-----
      INPUT:
	sExpiryRaw: card expiration date as entered into form -- may be month/year, day/month/year, or year/month/day
	  possibly with '-' or '.' instead of '/'.
	nMaxFuture: if year is given as 2 digits, then this is the furthest in the future the year
	  is allowed to be (# of years from now). NOTE: Should be tested with current dates after 2050
	  (or between 1950 and 1999) to make sure it doesn't allow a year too far in the past.
      OUTPUT: EXP as a DateTime object
    */ /* 2016-07-31 This appears to be no longer necessary in the Table class; moving to the Records class.
    public static function ExpiryText_toDate($sExpiryRaw,$nMaxFuture=50) {
	// -- split into month/year or month/day/year
	$arExp = preg_split('/[\/.\- ]/',$sExpiryRaw);
	$intParts = count($arExp);
	switch ($intParts) {
	  case 1:	// for now, we're going to assume MMYY[YY]
	    // TO DO: if people start typing in M with no leading zero, will have to check for even/odd # of chars
	    $intMo = substr($sExpiryRaw,0,2);
	    $intYr = substr($sExpiryRaw,2);
	    break;
	  case 2:	// month/year
	    $intMo = $arExp[0];
	    $intYr = $arExp[1];
	    break;
	  case 3:	// month/day/year or year/month/day
	    if (strlen($arExp[0]) > 3) {
	      // if first number has more than 3 digits, assume it's the year - y/m/d
	      $intYr = $arExp[0];
	      $intMo = $arExp[1];
	      $intDy = $arExp[2];
	    } else {
	      // assume first number is the month - m/d/y
	      $intMo = $arExp[0];
	      $intDy = $arExp[1];
	      $intYr = $arExp[2];
	    }
	    break;
	  default:
	    // unknown format, can't do anything
	}
	// check for validity:
	$ok = FALSE;
	if (isset($intYr)) {
	    if ($intYr > 0) {
		if (($intMo > 0) && ($intMo < 13)) {
		    $ok = TRUE;
		}
	    }
	}
	if ($ok) {
	    if ($intYr < 100) {	// if year has no century, give it one
		$intYrNowPart = strftime('%y');
		$intCent = (int)substr(strftime('%Y'),0,2);
		if ($intYr < $intYrNowPart) {
		    $intCent++;
		}
		$intYr += ($intCent*100);
		$intYrNowFull = (int)strftime('%Y');
		if ($intYr - $intYrNowFull > $nMaxFuture) {
		    $intYr -= 100;
		}
	    }
	    if (!isset($intDy)) {
		$intDy = cal_days_in_month(CAL_GREGORIAN, $intMo, $intYr);	// set to last day of month
	    }
	    $dtOut = $datetime = new DateTime();
	    $dtOut->setDate($intYr, $intMo, $intDy);
	    return $dtOut;
	} else {
	    return NULL;	// if no year, then could not parse format
	}
    } */
/* 2016-07-31 this isn't needed in the Table class anymore; moving to Recordset class
    public static function CalcExpirySQL($iRaw) {
	$dt = self::ExpiryText_toDate($iRaw);
	if (is_object($dt)) {
	    return '"'.$dt->format('Y-m-d').'"';
	} else {
	    return 'NULL';
	}
    }
*/
    // -- STATIC -- //
    // ++ CLASS NAMES ++ //

    protected function CustomersClass() {
	return 'clsCusts';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ++ //

    protected function CustomerTable($id=NULL) {
	return $this->Engine()->Make($this->CustomersClass(),$id);
    }

    // -- DATA TABLES -- //
}
// CUSTOMER CREDIT CARD
class clsCustCards_dyn extends clsCustCards {

    private $objCrypt;
    private $strCryptKey;

    // ++ SEARCHING ++ //

    /*----
      ACTION: Find a matching card from the given number
	Filters by customer ID if it is given, otherwise checks all records.
      RETURNS: DataSet of matching cards (should be maximum of one row)
      HISTORY:
	2011-03-23 Apparently this method existed in the past but got deleted somehow.
	  Rewriting it from scratch.
	2011-12-18
	  removed unnecessary strtolower()
	  added optional $idCust parameter
	  calls MakeFilt() to generate the SQL filter
    */
    public function Find($iNum,$idCust=NULL) {
      throw new exception('2016-07-31 Does anything still use this? MakeFilt_val() needs updating.');
	$sqlFilt = $this->MakeFilt_val_strip($iNum,$idCust);
	$rc = $this->SelectRecords($sqlFilt);
	return $rc;
    }

    // -- SEARCHING -- //
    // ++ CALCULATIONS ++ //

    /*----
      PURPOSE: Make the SQL filter to find matching records for a given customer and card number
      HISTORY:
	2011-11-29 de-coupled this from pre-processed cart data classes (clsPayment)
	2011-12-18
	  reversed order of params so idCust can be optional
	  renamed from MakeFilt_Cust() to MakeFilt_val_strip()
    */
    public function MakeFilt_val_strip($iNum,$idCust=NULL) {
      throw new exception('2016-07-31 This is only called by Filt()');
	$strVal = (string)self::Searchable($iNum);	// strip out extraneous characters
	$sqlFilt = $this->MakeFilt_val($strVal,$idCust);
	return $sqlFilt;
    }
    /*----
      PURPOSE: Same as MakeFilt_val_strip, but assumes $iNum is already stripped of punctuation
    */
    protected function MakeFilt_val($iNum,$idCust=NULL) {
      throw new exception('2016-07-31 This is only called by MakeFilt_val_strip(), and needs updating.');
	$sqlFilt = '(CardNum='.SQLValue((string)$iNum).')';
	if (!is_null($idCust)) {
	    $sqlFilt .= " AND (ID_Cust=$idCust)";
	}
	return $sqlFilt;
    }

    // -- CALCULATIONS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Creates a payment card record from the given Payment info object.
      RETURNS: ID for the new record
      HISTORY:
	2014-09-16 Rewritten; not yet tested.
	2016-05-17 Needs to be rewritten some more. We're not using clsPayment anymore.
    */
    
    public function CreateRecord($idBuyer, clsCustAddr $rcAddr, vcCartData_Buyer $oBuyer) {
	// prepare record object
	$rcCard = $this->SpawnRecordset();
	$rcCard->Load_fromBuyer($oBuyer);	// load card numbers
	$rcCard->Load_fromAddr($rcAddr);	// load address fields
	
	$rcCard->Save();
	return $rcCard;
/* 2016-06-04 Actually, I think all this is handled in Load_fromBuyer() now.
	// convert to SQL-friendly forms
	$db = $this->Engine();
	$sqlNum = $db->SanitizeAndQuote($sNum);
	$sqlExp = $this->CalcExpirySQL($sExp);
	$sqlAddr = $db->SanitizeAndQuote($sAddr);
	$sqlName = $db->SanitizeAndQuote($sName);
	
	die ('TO BE WRITTEN');
*/
    }
    /* 2016-05-17 old version
    public function CreateRecord($idBuyer, clsPayment $oPay) {
	$sNum = $oPay->CardNumValue();
	$sExp = $oPay->CardExpValue();
	$sSrch = (string)self::Searchable($sNum);
	$sqlNum = SQLValue($sSrch);	// strip out extraneous characters
	$sqlExp = $this->CalcExpirySQL($sExp);
	$sqlAddr = SQLValue($oPay->CardAddrValue());
	$sqlName = SQLValue($oPay->CardNameValue());

	$sNumSafe = self::SafeDescr_Long($sSrch,$sExp);
	$rcCard = $this->SpawnItem();
	$rcCard->Load_fromPayment($oPay);
	$rcCard->Encrypt(FALSE,FALSE);

	// look up stuff in related records
	$rcBuyer = $this->CustomerTable($idBuyer);	// not working!

	$arIns = array(
	  'WhenEnt'	=> 'NOW()',
	  'WhenEnc'	=> 'NOW()',
	  'ID_Cust'	=> $idBuyer,
	  'ID_Name'	=> $rcBuyer->NameID(),
	  'ID_Addr'	=> $rcBuyer->AddrID(),
	  'CardNum'	=> $sqlNum,
	  'CardExp'	=> $sqlExp,
	  'CardSafe'	=> SQLValue($sNumSafe),
	  'Encrypted'	=> SQLValue($rcCard->Encrypted()),
	  'OwnerName'	=> $sqlName,
	  'Address'	=> $sqlAddr,
	  'isActive'	=> 'TRUE',
	  );
	$idNew = $this->Insert($arIns);

	return $idNew;
    }*/
}
class vcrContactCard_trait extends vcBasicRecordset {
    use ftSaveableRecord;
}
class clsCustCard extends vcrContactCard_trait {

    // ++ OVERRIDES ++ //
    
    /*----
      PURPOSE: This is where we fill in the calculated fields.
    */
    protected function ChangeArray($ar=NULL) {
	$this->Encrypt(FALSE,FALSE);	// updates value of local "Encrypted" field
	$this->SetSafeString(self::CalcSafeDescr_toStore($this->GetNumberRaw(),$this->GetExpiryRaw()));
	return fcArray::Merge(
	  parent::ChangeArray($ar),
	  array(
	    'CardNum'	=> $this->NumberSQL(),
	    'CardExp'	=> $this->ExpirySQL(),
	    'Encrypted'	=> $this->EncryptedSQL(),
	    'CardSafe'	=> $this->SafeStringSQL(),
	    )
	  );
    }
    protected function UpdateArray($ar=NULL) {
	$arUpd = parent::UpdateArray($ar);
	$arUpd['WhenUpd'] = 'NOW()';
	return $arUpd;
    }
    protected function InsertArray($ar=NULL) {
	$arIns = parent::InsertArray($ar);
	$arIns['WhenEnt'] = 'NOW()';
	$arIns['isActive'] = 'TRUE';
	return $arIns;
    }

    // -- OVERRIDES -- //
    // ++ FIELD VALUES ++ //

    protected function IsActive() {
	return $this->Value('isActive');
    }
    /*----
      PUBLIC so Cart can retrieve it from a user-selected card
    */
    public function CustID() {
	return $this->Value('ID_Cust');
    }
    protected function SetAddrID($s) {
	return $this->SetFieldValue('ID_Addr',$s);
    }
    protected function GetAddrID() {
	return $this->GetFieldValue('ID_Addr');
    }
    protected function NameID() {
	return $this->Value('ID_Name');
    }
    // one of this pair may need to be PUBLIC so the value can be added to the debit card Charge record
    protected function SetAddrString($s) {
	return $this->SetFieldValue('Address',$s);
    }
    protected function GetAddrString() {
	return $this->GetFieldValue('Address');
    }
    protected function SetOwnerName($s) {
	return $this->SetFieldValue('OwnerName',$s);
    }
    protected function GetOwnerName() {
	return $this->GetFieldValue('OwnerName');
    }
    protected function SetNumberRaw($s) {
	return $this->SetFieldValue('CardNum',$s);
    }
    protected function GetNumberRaw() {
	return $this->GetFieldValue('CardNum');
    }
    protected function SetExpiryRaw($s) {
	return $this->SetFieldValue('CardExp',$s);
    }
    protected function GetExpiryRaw() {
	return $this->GetFieldValue('CardExp');
    }
    /*----
      HISTORY:
	2016-07-31
	  * Was PUBLIC just because it seemed likely this would eventually be needed, but now I'm thinking
	    we should never assume PUBLIC is needed until it is. Outside routines can use field calculations
	    that fetch the value in friendly or useful formats.
	  * Changed from Value() to ValueNz() because sometimes this hasn't been set yet when Encrypt() wants it.
      TODO: Figure out why it hasn't been set yet sometimes. Maybe make sure it gets initialized to NULL.
    */
    protected function GetSCodeRaw() {
	if ($this->FieldIsSet('CardCVV')) {
	    return $this->GetFieldValue('CardCVV');
	} else {
	    return NULL;
	}
    }
    protected function SetSafeString($s) {
	return $this->SetFieldValue('CardSafe',$s);
    }
    protected function GetSafeString() {
	return $this->GetFieldValue('CardSafe');
    }
    protected function SetEncrypted($s) {
	return $this->SetFieldValue('Encrypted',$s);
    }
    protected function GetEncrypted() {
	return $this->GetFieldValue('Encrypted');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected static function PackCardData($sNum,$sCVV,$sExp) {
	$sData =
	  KC_SEP_PACK.$sNum
	  .KC_SEP_PACK.$sCVV
	  .KC_SEP_PACK.$sExp;
	return $sData;
    }
    protected function NumberSQL() {
	return '"'.$this->GetNumberRaw().'"';	// TODO: does this strip out punctuation?
    }
    protected function ExpirySQL() {
	return self::CalcExpirySQL($this->GetExpiryRaw());
    }
    public function ShortDescr() {
	return $this->SafeString();
    }
    public function SafeDescr_long() {
	return self::CalcSafeDescr_long($this->GetNumberRaw(),$this->GetExpiryRaw());
    }
    public function ShortExp() {
	return date('n/y',$this->ExpiryRaw());
    }
    public function AsSingleLine() {	// alias
	return $this->SafeString();
    }
    // ACTION: Return plain card data as a single parseable string
    public function SingleString() {
	return self::PackCardData($this->GetNumberRaw(),$this->GetSCodeRaw(),$this->GetExpiryRaw());
    }
    /*----
      RETURNS: card number in a friendly format
    */ /* 2016-07-31 Apparently no longer used.
    public function FriendlyNum() {
	$out = $this->CardNum;
	$arChunks = str_split($out, 4);	// split number into chunks of 4 chars
	$out = implode('-',$arChunks);
	return $out;
    } */
    public function ShortNumExpName() {
	return $this->SafeString().' '.$this->OwnerName();
    }
    public function CardTypeChar() {
	return clsCustCards::CardTypeChar($this->CardNumber());
    }
    public function CardTypeName() {
	return clsCustCards::CardTypeName($this->CardNumber());
    }
    protected function SafeStringSQL() {
	return $this->GetConnection()->Sanitize_andQuote($this->GetSafeString());
    }
    protected function EncryptedSQL() {
	return $this->GetConnection()->Sanitize_andQuote($this->GetEncrypted());
    }

    // -- FIELD CALCULATIONS -- //
    // ++ VALUE CALCULATIONS ++ //

    protected function CalcExpirySQL($sRaw) {
	$dt = self::ExpiryText_toDate($sRaw);
	if (is_object($dt)) {
	    return '"'.$dt->format(KSQL_FORMAT_DATE).'"';
	} else {
	    return 'NULL';
	}
    }
    protected static function CalcSafeDescr_toStore($sNum,$sExp) {
	$dtExp = self::ExpiryText_toDate($sExp);
	if (is_null($dtExp)) {
	    $sDate = '?/?';
	} else {
	    $sDate = $dtExp->format('n/y');
	}
	$out = self::CalcCardTypeChar($sNum).'-'.substr($sNum,-4).'x'.$sDate;
	return $out;
    }
    protected static function CalcSafeDescr_long($sNum,$sExp) {
	$dtExp = self::ExpiryText_toDate($sExp);
	if (is_null($dtExp)) {
	    $sDate = '?/?';
	} else {
	    $sDate = $dtExp->format('F').' of '.$dtExp->format('Y').' ('.$dtExp->format('n/y').')';
	}
	$out = self::CalcCardTypeName($sNum).' -'.substr($sNum,-4).' expires '.$sDate;
	return $out;
    }
    protected static function CalcCardTypeName($sNum) {
	$chDigit = substr($sNum,0,1);
	$arDigits = array(
	  '3' => 'Amex',
	  '4' => 'Visa',
	  '5' => 'MasterCard',
	  '6' => 'Discover'
	  );
	if (isset($arDigits[$chDigit])) {
	    $out = $arDigits[$chDigit];
	} else {
	    $out = '?'.$chDigit;
	}
	return $out;
    }
    protected static function CalcCardTypeChar($sNum) {
	$chDigit = substr($sNum,0,1);
	$arDigits = array(
	  '3' => 'A',
	  '4' => 'V',
	  '5' => 'M',
	  '6' => 'D'
	  );
	if (isset($arDigits[$chDigit])) {
	    $chOut = $arDigits[$chDigit];
	} else {
	    $chOut = '?'.$chDigit;
	}
	return $chOut;
    }
    /*-----
      INPUT:
	sExpiryRaw: card expiration date as entered into form -- may be month/year, day/month/year, or year/month/day
	  possibly with '-' or '.' instead of '/'.
	nMaxFuture: if year is given as 2 digits, then this is the furthest in the future the year
	  is allowed to be (# of years from now). NOTE: Should be tested with current dates after 2050
	  (or between 1950 and 1999) to make sure it doesn't allow a year too far in the past.
      OUTPUT: EXP as a DateTime object
    */
    public static function ExpiryText_toDate($sExpiryRaw,$nMaxFuture=50) {
	// -- split into month/year or month/day/year
	$arExp = preg_split('/[\/.\- ]/',$sExpiryRaw);
	$intParts = count($arExp);
	switch ($intParts) {
	  case 1:	// for now, we're going to assume MMYY[YY]
	    // TO DO: if people start typing in M with no leading zero, will have to check for even/odd # of chars
	    $intMo = substr($sExpiryRaw,0,2);
	    $intYr = substr($sExpiryRaw,2);
	    break;
	  case 2:	// month/year
	    $intMo = $arExp[0];
	    $intYr = $arExp[1];
	    break;
	  case 3:	// month/day/year or year/month/day
	    if (strlen($arExp[0]) > 3) {
	      // if first number has more than 3 digits, assume it's the year - y/m/d
	      $intYr = $arExp[0];
	      $intMo = $arExp[1];
	      $intDy = $arExp[2];
	    } else {
	      // assume first number is the month - m/d/y
	      $intMo = $arExp[0];
	      $intDy = $arExp[1];
	      $intYr = $arExp[2];
	    }
	    break;
	  default:
	    // unknown format, can't do anything
	}
	// check for validity:
	$ok = FALSE;
	if (isset($intYr)) {
	    if ($intYr > 0) {
		if (($intMo > 0) && ($intMo < 13)) {
		    $ok = TRUE;
		}
	    }
	}
	if ($ok) {
	    if ($intYr < 100) {	// if year has no century, give it one
		$intYrNowPart = strftime('%y');
		$intCent = (int)substr(strftime('%Y'),0,2);
		if ($intYr < $intYrNowPart) {
		    $intCent++;
		}
		$intYr += ($intCent*100);
		$intYrNowFull = (int)strftime('%Y');
		if ($intYr - $intYrNowFull > $nMaxFuture) {
		    $intYr -= 100;
		}
	    }
	    if (!isset($intDy)) {
		$intDy = cal_days_in_month(CAL_GREGORIAN, $intMo, $intYr);	// set to last day of month
	    }
	    $dtOut = $datetime = new DateTime();
	    $dtOut->setDate($intYr, $intMo, $intDy);
	    return $dtOut;
	} else {
	    return NULL;	// if no year, then could not parse format
	}
    }

    // -- VALUE CALCULATIONS -- //
    // ++ RECORDSETS ++ //

    public function CustObj() {
	throw new exception('CustObj() is deprecated; call CustomerRecord().');
    }
    public function CustomerRecord() {
	$idCust = $this->CustID();
	return $this->CustomerTable($idCust);
    }
    public function AddrObj() {
	throw new exception('AddrObj() is deprecated; call AddressRecord().');
    }
    /*----
      PUBLIC so clsPerson can call it.
    */
    public function AddressRecord() {
	$idAddr = $this->AddrID();
	return $this->AddressTable($idAddr);
    }
/*    public function NameObj() {
	$idName = $this->NameID();
	return $this->NameTable($idName);
    } */

    // -- RECORDSETS -- //
    // ++ TABLES ++ //

    protected function CustomerTable($id=NULL) {
	return $this->Engine()->Make($this->CustomersClass(),$id);
    }
    protected function AddressTable($id=NULL) {
	return $this->Engine()->Make($this->AddressesClass(),$id);
    }
    protected function NameTable($id=NULL) {
    	return $this->Engine()->Make($this->NamesTable(),$id);
    }

    // -- TABLES -- //
    // ++ CLASS NAMES ++ //

    protected function CustomersClass() {
	return 'clsCusts';
    }
    protected function AddressesClass() {
	return 'vctCustAddrs';
    }
    protected function NamesClass() {
	return 'clsCustNames';
    }

    // -- CLASS NAMES -- //
    // ++ OTHER OBJECTS ++ //

    private $oCrypt;
    public function CryptObj() {
	if (!isset($this->oCrypt)) {
	    $this->oCrypt = new vcCipher();
	}
	return $this->oCrypt;
    }
    public function CryptReset() {
	unset($this->oCrypt);
    }

    // -- OTHER OBJECTS -- //
    // ++ ACTIONS ++ //

    public function Reset() {
    // PURPOSE: Force object to reload the crypt key
	throw new exception('Reset() is deprecated -- does anything actually use it? Call CryptReset() instead.');
	$this->CryptReset();
    }
    public function Load_fromBuyer(vcCartData_Buyer $oBuyer) {
	// get entered values from form object
	$sNum = $oBuyer->GetValue_forCardNumber();
	$sExp = $oBuyer->GetValue_forCardExpiry();

	$this->SetNumberRaw($sNum);
	//$this->SCodeRaw(NULL); // not yet supported by form
	$this->SetExpiryRaw($sExp);
//	$this->OwnerName($rcAddr->NameString());	// $rcAddr does not have NameString yet
	$this->SetOwnerName($oBuyer->GetNameFieldValue());
    }
    /* 2016-05-19 Obsolete.
    public function Load_fromPayment(clsPayment $oPay) {
	$this->NumberRaw($oPay->CardNumValue());
	$this->ExpiryRaw($oPay->CardExpValue());
	$this->SCodeRaw($oPay->CardCVVValue());	// TODO: this is not yet supported
    }*/
    public function Load_fromAddr(clsCustAddr $rcAddr) {
	$sAddr = $rcAddr->AsString();
	if ($rcAddr->IsNew()) {
	    throw new exception('Internal error: need to save new address record before passing it here.');
	}
	
	$this->SetAddrID($rcAddr->GetKeyValue());
	$this->SetAddrString($rcAddr->AsString());
    }

    // -- ACTIONS -- //
    // ++ ENCRYPTION ++ //

    /*----
      RETURNS: FALSE if update was requested and failed; returns encrypted string otherwise
      HISTORY:
	2014-09-15 Let's change this so it always returns an array, too.
	2015-01-04 Why? Can't remember what I had in mind.
	  Removing 'todo'.
	  UUencoding salt before saving.
	2015-01-05 Changing from UUencoding to Base64
	2015-01-06 Now always uses stored salt, if not NULL.
	  This is so we can tell if anything has changed when re-encrypting. If the salt is always new,
	    then the encryption result will always be different.
	  To regenerate salt, just clear out the CryptSalt field first.
	2015-01-10 No longer using salt; it was a bad idea.
    */
    public function Encrypt($bDoSave,$bDoWipe) {
	if (is_null($this->GetNumberRaw())) {
	    // this might happen if card data isn't completely decrypted after a migration or backup
	    // This may turn out to be unnecessary, depending on what (if anything) causes this condition:
	    throw new exception('Attempting to encrypt empty card data.');
	} else {
	    $ok = TRUE;

	    $sPacked = $this->SingleString();
	    $sEncrypted = $this->CryptObj()->encrypt($sPacked);

	    $nLenCrypt = strlen($sEncrypted);
	    if ($nLenCrypt > 256) {
		throw new exception("Encrypted data length ($nLenCrypt) exceeds storage field length.");
	    }
	    $this->SetEncrypted($sEncrypted);

	    if ($bDoWipe) {
		$this->ClearValue('CardNum');
		$this->ClearValue('CardCVV');
		$this->ClearValue('CardExp');
	    }
	    if ($bDoSave) {
		$arUpd['Encrypted'] = SQLValue($sEncrypted);
		$arUpd['WhenEnc'] = 'NOW()';
		if ($bDoWipe) {
		    $arUpd['CardNum'] = 'NULL';
		    $arUpd['CardCVV'] = 'NULL';
		    $arUpd['CardExp'] = 'NULL';
		}
		$ok = $this->Update($arUpd);
	    }
	    return $sEncrypted;
	}
    }
    /*----
      ASSUMES crypt object already knows the private key
    */
    public function Decrypt($bDoSave) {
	$sCrypt = $this->Encrypted();

	$sMsg = NULL;
	while ($sErr = openssl_error_string()) {
	    $sMsg .= "\n<br>PRIOR ERROR: $sErr";
	}
	$oCrypt = $this->CryptObj();
	$sPlain = $oCrypt->decrypt($sCrypt);

	while ($sErr = openssl_error_string()) {
	    $sMsg .= "\n<br>CURRENT ERROR: $sErr";
	}
	if (!is_null($sMsg)) {
	    echo $sMsg;
	    throw new exception('One or more errors were encountered during decryption.');
	}

	$arPacked = clsString::Xplode($sPlain);
	$sNum = $arPacked[0];
	$sCVV = $arPacked[1];
	$sExp = $arPacked[2];

	if (empty($sNum)) {
	    $arUpd = array(
	      'CardNum' => 'NULL',
	      'CardCVV' => 'NULL',
	      'CardExp' => 'NULL',
	      'CardSafe' => 'NULL'
	      );
	} else {
	    $this->CardNum = ($sNum == '')?NULL:$sNum;
	    $this->CardCVV = ($sCVV == '')?NULL:$sCVV;
	    $this->CardExp = ($sExp == '')?NULL:$sExp;

	    if ($bDoSave) {
		$arUpd = array(
		  'CardNum' => SQLValue($this->CardNum),
		  'CardCVV' => SQLValue($this->CardCVV),
		  'CardExp' => SQLValue($this->CardExp),
		  'CardSafe' => SQLValue($this->SafeString())
		  );
	    }
	}
	if ($bDoSave) {
	    $this->Update($arUpd);
	}
	return $sPlain;
    }

    // -- ENCRYPTION -- //
}
