<?php

/*
  HISTORY:
    2014-09-23 Split off from base.cust.php (to be renamed cust.php)
    2015-01-04 Rewriting how salt and packing work.
    2015-01-10 No longer using salt; it was a mistake.
*/

define('KC_SEP_PACK',"\t");	// textified data might include any printable character, so use non-printable

/* =======
 CREDIT CARD UTILITY CLASS
*/
class clsCustCards extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cust_cards');
	  $this->KeyName('ID');
	  $this->ClassSng('clsCustCard');
    }

    // ++ STATIC ++ //

   public static function CardTypeChar($iNum) {
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
	//$dtExp = strtotime($this->CardExp);
	$dtExp = self::ExpDate($iExp);
	if (is_null($dtExp)) {
	    $strDate = '?/?';
	} else {
	    $strDate = $dtExp->format('n/y');
	}
	$out = self::CardTypeChar($iNum).'-'.substr($iNum,-4).'x'.$strDate;
	return $out;
    }
    public static function SafeDescr_Long($iNum,$iExp) {
	$dtExp = self::ExpDate($iExp);
	if (is_null($dtExp)) {
	    $strDate = '?/?';
	} else {
	    $strDate = $dtExp->format('F').' of '.$dtExp->format('Y').' ('.$dtExp->format('n/y').')';
	}
	$out = self::CardTypeName($iNum).' -'.substr($iNum,-4).' expires '.$strDate;
	return $out;
    }
    public static function Searchable($iRaw) {
	$xts = new xtString(strtolower($iRaw),TRUE);
	$xts->KeepOnly('0-9');	// keep only numerics
	return $xts->Value;
    }
    /*-----
      INPUT:
	iMaxFuture: if year is given as 2 digits, then this is the furthest in the future the year
	  is allowed to be (# of years from now). NOTE: Should be tested with current dates after 2050
	  (or between 1950 and 1999) to make sure it doesn't allow a year too far in the past.
      OUTPUT: EXP as a DateTime object
    */
    public static function ExpDate($iRaw,$iMaxFuture=50) {
	$strExp = $iRaw;
	// -- split into month/year or month/day/year
	$arExp = preg_split('/[\/.\- ]/',$strExp);
	$intParts = count($arExp);
	switch ($intParts) {
	  case 1:	// for now, we're going to assume MMYY[YY]
	    // TO DO: if people start typing in M with no leading zero, will have to check for even/odd # of chars
	    $intMo = substr($strExp,0,2);
	    $intYr = substr($strExp,2);
	    break;
	  case 2:	// month/year
	    $intMo = $arExp[0];
	    $intYr = $arExp[1];
	    break;
	  case 3:	// month/day/year or year/month/day
	    if (strlen($arExp[0]) > 3) {
	      $intYr = $arExp[0];
	      $intMo = $arExp[1];
	      $intDy = $arExp[2];
	    } else {
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
		if ($intYr - $intYrNowFull > $iMaxFuture) {
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
    public static function ExpDateSQL($iRaw) {
	$dt = self::ExpDate($iRaw);
	if (is_object($dt)) {
	    return '"'.$dt->format('Y-m-d').'"';
	} else {
	    return 'NULL';
	}
    }

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

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
//	  $this->Name('cust_cards');
//	  $this->KeyName('ID');
	  $this->ClassSng('clsCustCard');
    }

    // -- SETUP -- //
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
	$sqlFilt = $this->MakeFilt_val_strip($iNum,$idCust);
	$rc = $this->GetData($sqlFilt);
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
	$strVal = (string)self::Searchable($iNum);	// strip out extraneous characters
	$sqlFilt = $this->MakeFilt_val($strVal,$idCust);
	return $sqlFilt;
    }
    /*----
      PURPOSE: Same as MakeFilt_val_strip, but assumes $iNum is already stripped of punctuation
    */
    protected function MakeFilt_val($iNum,$idCust=NULL) {
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
    */
    public function CreateRecord($idBuyer, clsPayment $oPay) {
	$sNum = $oPay->CardNumValue();
	$sExp = $oPay->CardExpValue();
	$sSrch = (string)self::Searchable($sNum);
	$sqlNum = SQLValue($sSrch);	// strip out extraneous characters
	$sqlExp = $this->ExpDateSQL($sExp);
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
    }
}
class clsCustCard extends clsVbzRecs {

    // ++ AUXILIARY FIELDS ++ //
/*
    private $sPlain;	// unencrypted data
    public function PlainData($sData=NULL) {
	if (!is_null($sData)) {
	    $this->sPlain = $sData;
	}
	return $this->sPlain;
    }*/
    /*
    private $sCrypt;	// encrypted data
    public function CryptData($sData=NULL) {
	if (!is_null($sData)) {
	    $this->sCrypt = $sData;
	}
	return $this->sCrypt;
    }*/
    /*
    private $sSalt;	// encryption salt
    public function CryptSalt($sData=NULL) {
	if (!is_null($sData)) {
	    $this->sSalt = $sData;
	}
	return $this->sSalt;
    }*/

    // -- AUXILIARY FIELDS -- //
    // ++ DATA FIELDS ++ //

    protected function IsActive() {
	return $this->Value('isActive');
    }
    /*----
      PUBLIC so Cart can retrieve it from a user-selected card
    */
    public function CustID() {
	return $this->Value('ID_Cust');
    }
    protected function AddrID() {
	return $this->Value('ID_Addr');
    }
    protected function NameID() {
	return $this->Value('ID_Name');
    }
    /*----
      PUBLIC so clsCartVar can access it
    */
    public function OwnerName() {
	return $this->Value('OwnerName');
    }
    /*----
      PUBLIC so clsCartVar can access it
    */
    public function NumberRaw($sVal=NULL) {
	return $this->Value('CardNum',$sVal);
    }
    /*----
      PUBLIC so clsCartVar can access it
    */
    public function ExpiryRaw($sVal=NULL) {
	return $this->Value('CardExp',$sVal);
    }
    /*----
      PUBLIC just because I'm sure something will eventually need to access it from outside
    */
    public function SCodeRaw($sVal=NULL) {
	return $this->Value('CardCVV',$sVal);
    }
    public function ShortDescr() {
	return $this->SafeString();
    }
    public function SafeDescr_Long() {
	return $this->Table()->SafeDescr_Long($this->NumberRaw(),$this->ExpiryRaw());
    }
    public function ShortExp() {
	return date('n/y',$this->ExpiryRaw());
    }
    public function AsSingleLine() {	// alias
	return $this->SafeString();
    }
    // ACTION: Return plain card data as a single parseable string
    public function SingleString() {
	return self::PackCardData($this->NumberRaw(),$this->SCodeRaw(),$this->ExpiryRaw());
    }
    /*----
      RETURNS: card number in a friendly format
    */
    public function FriendlyNum() {
	$out = $this->CardNum;
	$arChunks = str_split($out, 4);	// split number into chunks of 4 chars
	$out = implode('-',$arChunks);
	return $out;
    }
    public function ShortNumExpName() {
	return $this->SafeString().' '.$this->OwnerName();
    }
    public function CardTypeChar() {
	return clsCustCards::CardTypeChar($this->CardNumber());
    }
    public function CardTypeName() {
	return clsCustCards::CardTypeName($this->CardNumber());
    }
    public function SafeString() {
	return $this->Value('CardSafe');
	return clsCustCards::SafeDescr_Short($this->CardNumber(),$this->CardExpiry());
    }
    protected static function PackCardData($sNum,$sCVV,$sExp) {
	$sData =
	  KC_SEP_PACK.$sNum
	  .KC_SEP_PACK.$sCVV
	  .KC_SEP_PACK.$sExp;
	return $sData;
    }
    public function Encrypted() {
	return $this->Value('Encrypted');
    }
/*
    public function CryptSalt($sVal=NULL) {
	return $this->Value('CardSalt',$sVal);
    }
*/
/* 2015-01-06 deprecating this -- salt is always in textified form
    public function CryptSalt_text() {
	//return convert_uuencode($this->CryptSalt());
	return base64_encode($this->CryptSalt());
    } */

    // -- DATA FIELDS -- //
    // ++ DATA RECORDSETS ++ //

    public function CustObj() {
	$idCust = $this->CustID();
	return $this->CustTable($idCust);
    }
    public function AddrObj() {
	throw new exception('AddrObj() is deprecated; call AddressRecord().');
    }
    /*----
      PUBLIC so clsPerson can call it.
    */
    public function AddressRecord() {
	$idAddr = $this->AddrID();
	return $this->AddrTable($idAddr);
    }
/*    public function NameObj() {
	$idName = $this->NameID();
	return $this->NameTable($idName);
    } */

    // -- DATA RECORDSETS -- //
    // ++ DATA TABLES ++ //

    protected function CustTable($id=NULL) {
	return $this->Engine()->Make($this->CustsClass(),$id);
    }
    protected function AddrTable($id=NULL) {
	return $this->Engine()->Make($this->AddrsClass(),$id);
    }
    protected function NameTable($id=NULL) {
    	return $this->Engine()->Make($this->NamesTable(),$id);
    }

    // -- DATA TABLES -- //
    // ++ CLASS NAMES ++ //

    protected function CustsClass() {
	return 'clsCusts';
    }
    protected function AddrsClass() {
	return 'clsCustAddrs';
    }
    protected function NamesClass() {
	return 'clsCustNames';
    }

    // -- CLASS NAMES -- //
    // ++ OTHER OBJECTS ++ //

    private $oCrypt;
    public function CryptObj() {
	if (!isset($this->oCrypt)) {
	    //$this->oCrypt = new vbzCipher();
	    $this->oCrypt = new Cipher_pubkey();
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
    public function Load_fromPayment(clsPayment $oPay) {
	$this->NumberRaw($oPay->CardNumValue());
	$this->ExpiryRaw($oPay->CardExpValue());
	$this->SCodeRaw($oPay->CardCVVValue());	// TODO: this is not yet supported
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
	if (is_null($this->NumberRaw())) {
	    // this might happen if card data isn't completely decrypted after a migration or backup
	    // This may turn out to be unnecessary, depending on what (if anything) causes it:
	    throw new exception('Attempting to encrypt empty card data.');
	} else {
	    $ok = TRUE;

	    $sPacked = $this->SingleString();
	    $sEncrypted = $this->CryptObj()->encrypt($sPacked);

	    $nLenCrypt = strlen($sEncrypted);
	    if ($nLenCrypt > 256) {
		throw new exception("Encrypted data length ($nLenCrypt) exceeds storage field length.");
	    }
	    $this->Encrypted($sEncrypted);

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
