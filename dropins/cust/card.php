<?php
/*
  HISTORY:
    2014-02-13 split card classes off from cust.php
*/
class VCT_CustCards extends clsCustCards_dyn {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VCR_CustCard');	// override parent
	  $this->ActionKey('card');
    }
    /*----
      ACTION: Find a matching card from the given number
      RETURNS: DataSet of matching cards (should be maximum of one row)
      HISTORY:
	2011-03-23 Apparently this method existed in the past but got deleted somehow.
	  Rewriting it from scratch.
	2011-12-18 commenting out -- this function already exists in clsCustCards_dyn
    */
/*
    public function Find($iNum) {
	//$num = str_replace (array(' ','-','.'),'',$iNum);
	$num = self::Searchable($iNum);
	$rs = $this->GetData('CardNum="'.$iNum.'"');
	return $rs;
    }
*/
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ ADMIN WEB UI ++ //

    public function ListPage() {
    // PURPOSE: interface for encrypting credit card data
	global $wgOut;

	$objRow = $this->GetData(NULL,NULL,'ID');
	if ($objRow->hasRows()) {
	    $out = "{| class=sortable \n|-\n! ID || Number || Expiry || CVV || encrypted";
	    $isOdd = TRUE;
	    while ($objRow->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $objRow->Row;

		$id	= $row['ID'];
		$strNum	= $row['CardNum'];
		$strExp = $row['CardExp'];
		$strCVV = $row['CardCVV'];
		$strEncr = htmlspecialchars($row['Encrypted']);

		$out .= "\n|- style=\"$wtStyle\"";
		$out .= "\n| $id || $strNum || $strExp || $strCVV || $strEncr";
	    }
	    $out .= "\n|}";
	} else {
	    $out = 'No credit cards currently in database.';
	}
	$wgOut->addWikiText($out,TRUE);
    }
    public function DropDown_forCust($iCust,$iCard=NULL) {
	$idCust = $iCust;
	$idCard = $iCard;
	if (empty($idCust)) {
	    $out = '<i>no customer ID</i>';
	} else {
	    $objRows = $this->objDB->CustCards()->GetData('ID_Cust='.$idCust);
	    $out = $objRows->DropDown('ccard',$idCard);
	}
	return $out;
    }
    public function AdminEncrypt() {
    // PURPOSE: interface to encrypt/decrypt sensitive data
	global $wgOut,$wgRequest;

	$canDoBulk = MWX_User::Current()->CanDo('crypt.bulk');
	$canDoKeys = MWX_User::Current()->CanDo('crypt.keys');
	if ($canDoBulk || $canDoKeys) {
	    if (IsWebSecure()) {

		if ($canDoBulk) {

		    $doEncry = $wgRequest->getVal('doEncrypt');
		    $doCheck = $wgRequest->getVal('doCheck');
		    $doClear = $wgRequest->getVal('doClear');
		    $doDecry = $wgRequest->getVal('doDecrypt');

		    if ($doCheck || $doDecry) {	// do we need the private key?
			$sKeyPrv = $wgRequest->getVal('cryptKey');	// private key
			$htKeyPrv = htmlspecialchars($sKeyPrv);
		    } else {
			$htKeyPrv = NULL;
		    }

	// do the selected actions
		    //$this->CryptKey($strKey);
		    if ($doEncry) {
			$this->DoAdminEncrypt();
		    }
		    if ($doCheck) {
			$this->DoAdminCheckCrypt($sKeyPrv);
		    }
		    if ($doClear) {
			$this->DoAdminPlainClear();
		    }
		    if ($doDecry) {
			$this->DoAdminDecrypt($sKeyPrv);
		    }

		    $out = 'Select which tasks to perform. If server times out, select fewer tasks.';
		    $out .= '<form method=post>';
		    $out .= '<input type=checkbox name="doEncrypt">Encrypt sensitive data, and save results';
		    $out .= '<br><input type=checkbox name="doCheck">Verify that encrypted data matches existing plaintext - <b>need private key</b>';

		    $out .= '<br><br>If possible, you should make a local backup of your (unencrypted) data before the next step.';
		    $out .= '<br>At this point, sensitive data is available in the clear, so do <i>not</i> copy exported data over an unsecure connection .';
		    $out .= '<br>(Yes, securely exporting the affected table to a file should be a feature of this tool. Eventually.)';
		    $out .= '<br><input type=checkbox name="doClear">Clear unencrypted fields';

		    $out .= '<br><br>Do this part after exporting/migrating data:';
		    $out .= '<br><input type=checkbox name="doDecrypt">Decrypt and save as plaintext - <b>need private key</b>';

		    $out .= "<br><br>Private key (needed for decryption only): <textarea name=cryptKey cols=64 rows=5>$htKeyPrv</textarea>";
		    $out .= '<br><input type=submit name="btnGo" value="Go">';
		    $out .= '</form>';
		}

		if ($canDoKeys) {
		    $out .= '<h2>Key Generation</h2>';
		    $doKeyGen = $wgRequest->getBool('btnKeyGen');
		    if ($doKeyGen) {
			// see http://us.php.net/manual/en/book.openssl.php
			// Create the keypair
			$res = openssl_pkey_new();

			// Get private key
			openssl_pkey_export($res, $privatekey);

			// Get public key
			$publickey = openssl_pkey_get_details($res);
			$publickey = $publickey["key"];

			// generate filename for storing public key:
			$fn = date('Y-m-d His').' '.MWX_User::Current()->ShortText().'.public.key';
			// Username is included so we know at a glance who generated the public key
			  // and was therefore responsible for saving the private key.

			$fs = KFP_KEYS.'/'.$fn;
			// save the public key
			$nRes = file_put_contents($fs,$publickey);
			if ($nRes > 0) {
			    $this->Engine()->VarsGlobal()->Val('public_key.fspec',$fn);

			    $out .= '<h3>Private key</h3><b>Save this</b> in a secure location:<br><pre>'.$privatekey.'</pre>';
			    $out .= '<h3>Public key</h3>This has been saved in '.$fs.':<br><pre>'.$publickey.'</pre>';
			} else {
			    $out .= '<h3>Key Generation Error</h3>The generated public key could not be saved to the file '.$fs.'. Please check folder permissions.';
			}
		    } else {
			$out .= '<form method=post>';

			$out .= '<br><input type=submit name="btnKeyGen" value="Generate New Keys">';
			$out .= '</form>';
		    }
		}
	    } else {
		$out = '<br><span class=warning>You have permission to use this page, but you need to <a href="'.SecureURL().'">switch to https</a>.</span>';
	    }
	} else {
	    $out .= "You don't have permission to use any of the encryption utilities.";
	}

	$wgOut->AddHTML($out);
    }
    // -- ADMIN WEB UI -- //
    // ++ ADMIN WEB ACTIONS ++ //

    /*----
      ACTION: Encrypt data in all rows and save to Encrypted field
    */
    public function DoAdminEncrypt() {
	global $wgOut;

	$objLogger = $this->Engine()->Events();
	$objLogger->LogEvent(__METHOD__,NULL,'encrypting sensitive data in ccard records',NULL,FALSE,FALSE);

	$objRow = $this->GetData();
	if ($objRow->hasRows()) {
	    $intChecked = 0;
	    $intChanged = 0;
	    $out = 'Encrypting credit card data in card records:';
	    $out .= "\n* ".$objRow->RowCount().' records to process';
	    $wgOut->addWikiText($out,TRUE); $out=NULL;
	    while ($objRow->NextRow()) {
		$intChecked++;
		$row = $objRow->Row;

		$strNumEncrOld = $row['Encrypted'];
		$objRow->Encrypt(TRUE,FALSE);
		$strNumEncrNew = $objRow->Encrypted;
		if ($strNumEncrOld != $strNumEncrNew) {
		    $intChanged++;
		}
	    }
	    $strStats = $intChecked.' row'.Pluralize($intChecked).' processed, ';
	    $strStats .= $intChanged.' row'.Pluralize($intChanged).' altered';
	    $objLogger->LogEvent(__METHOD__,NULL,$strStats,NULL,FALSE,FALSE);

	    $out .= "\n* $intChecked row".Pluralize($intChecked).' processed';
	    $out .= "\n* $intChanged row".Pluralize($intChanged).' altered';
	    $wgOut->addWikiText($out,TRUE); $out=NULL;

	} else {
	    $objLogger->LogEvent(__METHOD__,NULL,'CustCards: No records found to process',NULL,FALSE,FALSE);
	    $out = 'No credit cards currently in database.';
	}
	$wgOut->addWikiText($out,TRUE); $out = NULL;
    }
    public function DoAdminCheckCrypt($iPvtKey) {
    // PURPOSE: Verify that encrypted data matches unencrypted data
    //	This was part of AdminCrypt, but it took too long to execute
	global $wgOut;
	global $vgPage;

	$vgPage->UseWiki();	// apparently this isn't set elsewhere

	$objLogger = $this->Engine()->Events();
	$objLogger->LogEvent(__METHOD__,NULL,'checking encrypted data',NULL,FALSE,FALSE);

	$out = NULL;
	$objRow = $this->GetData();
	if ($objRow->hasRows()) {
	    $objRow = $this->GetData();
	    $intMatched = 0;
	    $intBlank = 0;
	    $intFound = 0;
	    $objRow->Reset();
	    $sBad = NULL;
	    while ($objRow->NextRow()) {
		$intFound++;
		$strPlainOld = $objRow->SingleString();
		$strEncrypted = $objRow->Encrypted;
		if (empty($strEncrypted)) {
		    $intBlank++;
		} else {
		    $objRow->Decrypt(FALSE,$iPvtKey);	// don't overwrite unencrypted data
		    $strNumEncrNew = $objRow->Encrypted;
		    $strPlainNew = $objRow->_strPlain;
		    if ($strPlainOld == $strPlainNew) {
			$intMatched++;
		    } else {
			$sBad .= "\n* ".$objRow->AdminLink().": plain=[$strPlainOld] encrypted=[$strPlainNew]";
		    }
		}
	    }
	    $out .= "* $intMatched card".Pluralize($intMatched).' match';
	    $intRows = $objRow->RowCount();
	    $intBad = $intRows - $intMatched - $intBlank;
	    if ($intBad) {
		$strStat = $intBad.' card'.Pluralize($intBad);
		$out .= "\n\n'''ERROR''' - $strStat did NOT match!$sBad\n";
		$objLogger->LogEvent(__METHOD__,NULL,$strStat.' did not match',NULL,FALSE,FALSE);
		// TO DO: If this ever happens, give list of failed cards and some sort of way to figure out what went wrong
	    } else {
		if ($intRows == $intFound) {
		    $strStat = $intRows.' row'.Pluralize($intRows);
		    $out .= "\n\n'''OK''' - $strStat matched";
		    $objLogger->LogEvent(__METHOD__,NULL,$strStat.' matched',NULL,FALSE,FALSE);
		} else {
		    $strStat = $intRows.' row'.Pluralize($intRows).' detected, but only '.$intFound.' row'.Pluralize($intFound).' checked';
		    $out .= "\n\n'''ERROR''' - $strStat!";
		    $objLogger->LogEvent(__METHOD__,NULL,$strStat,NULL,FALSE,FALSE);
		}
	    }
	    if ($intBlank) {
		$strStat = $intBlank.' row'.Pluralize($intBlank);
		$out .= "\n<br>'''NOTE''': $strStat have not been encrypted!";
		$objLogger->LogEvent(__METHOD__,NULL,$strStat.' are not yet encrypted',NULL,FALSE,FALSE);
	    }
	} else {
	    $out = 'No credit cards currently in database.';
	    $objLogger->LogEvent(__METHOD__,NULL,'no data to encrypt',NULL,FALSE,FALSE);
	}
	$wgOut->addWikiText($out,TRUE);
    }
    public function DoAdminPlainClear() {
	global $wgOut;

    // ACTION: Clear plaintext data for all rows that have encrypted data
	$objLogger = $this->Engine()->Events();
	$objLogger->LogEvent(__METHOD__,NULL,'clearing unencrypted card data',NULL,FALSE,FALSE);

	$arUpd = array(
	  'CardNum' => 'NULL',
	  'CardExp' => 'NULL',
	  'CardCVV' => 'NULL'
	  );
	$this->Update($arUpd,'Encrypted IS NOT NULL');
	$intRows = $this->objDB->RowsAffected();
	$strStat = $intRows.' row'.Pluralize($intRows).' modified';
	$out = "\n\n'''OK''': $strStat";
	$wgOut->addWikiText($out,TRUE); $out=NULL;


	$objLogger->LogEvent(__METHOD__,NULL,'plaintext data cleared from card records, '.$strStat,NULL,FALSE,FALSE);
    }
    public function DoAdminDecrypt($iPvtKey) {
	global $wgOut;

	$objLogger = $this->Engine()->Events();
	$objLogger->LogEvent(__METHOD__,NULL,'decrypting data',NULL,FALSE,FALSE);

	$objRow = $this->GetData();
	if ($objRow->hasRows()) {
	    $out = "\n\nDecrypting cards: ";
	    $intFound = 0;
	    while ($objRow->NextRow()) {
		$intFound++;
		$objRow->Decrypt(TRUE,$iPvtKey);	// decrypt and save
		$strNumEncrNew = $objRow->Encrypted;
	    }
	    $intRows = $objRow->RowCount();
	    $intMissing = $intRows - $intFound;
	    if ($intMissing) {
		$strStat = $intFound.' row'.Pluralize($intFound).' out of '.$intRows.' not decrypted!';
		$out .= "'''ERROR''' - $strStat!";
		$objLogger->LogEvent(__METHOD__,NULL,$strStat,NULL,FALSE,FALSE);
	    } else {
		$strStat = $intRows.' row'.Pluralize($intRows);
		$out .= "'''OK''' - $strStat decrypted successfully";
		$objLogger->LogEvent(__METHOD__,NULL,$strStat.' decrypted successfully',NULL,FALSE,FALSE);
	    }
	    $wgOut->addWikiText($out,TRUE);
	} else {
	    $wgOut->addWikiText('No credit cards to decrypt!',TRUE);
	}
    }

    // -- ADMIN WEB ACTIONS -- //
}
class VCR_CustCard extends clsCustCard {
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
//    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
//	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
//    }
    /*----
      HISTORY:
	2011-02-16 Replaced existing code with boilerplate/helper code
    */ /*
    protected function Log() {
	if (!is_object($this->logger)) {
	    $this->logger = new clsLogger_DataSet($this,$this->objDB->Events());
	}
	return $this->logger;
    }
    public function StartEvent(array $iArgs) {
	return $this->Log()->StartEvent($iArgs);
    }
    public function FinishEvent(array $iArgs=NULL) {
	return $this->Log()->FinishEvent($iArgs);
    }
    public function EventListing() {
	return $this->Log()->LogObj()->EventListing();
    }
    */
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ DATA FIELD ACCESS ++ //

    protected function CustomerID() {
	return $this->Value('ID_Cust');
    }
    protected function AddressID() {
	return $this->Value('ID_Addr');
    }
    protected function NameID() {
	return $this->Value('ID_Name');
    }
    protected function CardName() {
	return $this->Value('Name');
    }
    protected function CardNumber() {
	return $this->Value('CardNum');
    }
    protected function CardExpiration_string() {
	return $this->Value('CardExp');
    }
    protected function CardExpiration_short() {
	$sCardExp = $this->CardExpiration_string();
	if ($vCardExp == '') {
	    $ftExpVal = '';
	} else {
	    $utExpVal = strtotime($sCardExp);
	    $ftExpVal = date('n/y',$utExpVal);
	}
	return $ftExpVal;
    }
    protected function CardVerification_string() {
	return $this->Value('CardCVV');
    }
    protected function OwnerName_string() {
	return $this->Value('OwnerName');
    }
    protected function OwnerAddress_string() {
	return $this->Value('Address');
    }
    protected function Notes() {
	return $this->Value('Notes');
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA TABLE ACCESS ++ //

    protected function CustomerTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_ADMIN_CUSTOMERS,$id);
    }
    protected function AddressTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_MAIL_ADDRS,$id);
    }
    protected function NameTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_CUST_NAMES,$id);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORDS ++ //

    public function Cust() {
	throw new exception('Cust() has been renamed CustomerRecord().');
    }
    protected function CustomerRecord() {
	$doLoad = TRUE;
	if (isset($this->rcCust)) {
	    if ($this->rcCust->KeyValue() == $this->CustomerID()) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->rcCust = $this->CustomerTable($this->CustomerID());
	}
	return $this->rcCust;
    }
    public function Addr() {
	throw new exception ('Addr() has been renamed AddressRecord().');
    }
    protected function AddressRecord() {
	$doLoad = TRUE;
	if (isset($this->rcAddr)) {
	    if ($this->rcAddr->KeyValue() == $this->AddressID()) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->rcAddr = $this->AddressTable($this->AddressID());
	}
	return $this->rcAddr;
    }
    public function Name() {
	throw new exception ('Name() has been renamed NameRecord().');
    }
    protected function NameRecord() {
	$doLoad = TRUE;
	if (isset($this->rcName)) {
	    if ($this->rcName->KeyValue() == $this->NameID()) {
		$doLoad = FALSE;
	    }
	}
	if ($doLoad) {
	    $this->rcName = $this->NameTable($this->NameID());
	}
	return $this->rcName;
    }

    // -- DATA RECORDS -- //
    // ++ WEB UI WIDGETS ++ //

    public function DropDown($iName,$iWhich=NULL) {
	$out = '<select name="'.$iName.'">';
	while ($this->NextRow()) {
	    if ($this->ID == $iWhich) {
		$htSelect = " selected";
	    } else {
		$htSelect = '';
	    }
	    $out .= '<option'.$htSelect.' value="'.$this->ID.'">'.$this->SafeString().'</option>';
	}
	$out .= '</option>';
	return $out;
    }

    // -- WEB UI WIDGETS -- //
    // ++ ADMIN WEB UI ++ //

    public function AdminPage() {

	if (clsHTTP::Request()->getBool('btnSave')) {
	    $this->AdminSave();	// save edit
	    $this->Reload();	// we want to see the new values, not the ones already loaded
	}

	$oPage = $this->Engine()->App()->Page();

	$doEdit = $oPage->PathArg('edit');
	//$htPath = $vgPage->SelfURL(array('edit'=>!$doEdit));
	$id = $this->KeyValue();
/*
	$objPage = new clsWikiFormatter($vgPage);
	//$objSection = new clsWikiAdminSection($strName);
	$objSection = new clsWikiSection($objPage,'Credit Card ID '.$id);
	//$out = $objSection->HeaderHtml_Edit();
	$objSection->ToggleAdd('edit');
	//$objSection->ActionAdd('view');
	$out = $objSection->Generate();
*/
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	  new clsActionLink_option(array(),'edit')
	  );
	$sTitle = 'Credit Card ID '.$id;
	$out = $oPage->ActionHeader($sTitle,$arActs);

	$rcCust = $this->CustomerRecord();
	$rcAddr = $this->AddressRecord();
	$rcName = $this->NameRecord();

	$id = $this->KeyValue();

	$ftTagVal = htmlspecialchars($this->CardName());
	$ftNumVal = htmlspecialchars($this->CardNumber());
	$ftCardExp = $this->CardExpiration_short();
	$ftCVVVal = htmlspecialchars($this->CardVerification_string());
	$ftOwnVal = htmlspecialchars($this->OwnerName_string());
	$ftAddrVal = htmlspecialchars($this->OwnerAddress_string());
	$ftNotes = htmlspecialchars($this->Notes());

	if ($doEdit) {
	    $out .= '<form method=post action="'.$htPath.'">';
	    $ftTag = '<input name="tag" size=5 value="'.$ftTagVal.'">';
	    $ftCust = '<input name="cust" size=5 value="'.$this->CustID().'">';	// LATER: drop-down
	    $ftAddr = '<input name="addr" size=5 value="'.$this->AddrID().'">';	// LATER: drop-down
	    $ftName = '<input name="name" size=5 value="'.$this->NameID().'">';	// LATER: drop-down
	    $ftNum = '<input name="num" size=19 value="'.$ftNumVal.'">';
	    $ftExp = '<input name="exp" size=5 value="'.$ftExpVal.'">';
	    $ftCVV = ' CVV <input name="cvv" size=3 value="'.$ftCVVVal.'">';
	    $ftOwnName = '<input name="owner" size=30 value="'.$ftOwnVal.'">';
	    $ftAddrTxt = '<textarea name="addrtxt" height=3 width=20>'.$ftAddrVal.'</textarea>';
	    $ftNotes = '<textarea name="notes" height=3 width=40>'.$ftNotes.'</textarea>';
	} else {
	    $ftTag = $ftTagVal;
	    $ftCust = $rcCust->AdminLink_name();
	    $ftAddr = $rcAddr->AdminLink($rcAddr->AsSingleLine(' / '));
	    $ftName = $rcName->AdminLink_name();
	    $ftNum = $ftNumVal;
	    $ftExp = $ftExpVal;
	    $ftCVV = empty($ftCVVVal)?'':' CVV '.$ftCVVVal;
	    $ftOwnName = $ftOwnVal;
	    $ftAddrTxt = '<pre>'.$ftAddrVal.'</pre>';
	    $ftNotes = $ftNotes;
	}
	$out .= <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>$id</td></tr>
  <tr><td align=right><b>Tag</b>:</td><td>$ftTag</td></tr>
  <tr><td align=right><b>Customer</b>:</td><td>$ftCust</td></tr>
  <tr><td align=right><b>Address</b>:</td><td>$ftAddr</td></tr>
  <tr><td align=right><b>Name</b>:</td><td>$ftName</td></tr>
  <tr><td align=right><b>Number</b>:</td><td>$ftNum x $ftExp$ftCVV</td></tr>
  <tr><td align=right><b>Owner Name</b>:</td><td>$ftOwnName</td></tr>
  <tr><td align=right><b>Address Text</b>:</td><td>$ftAddrTxt</td></tr>
  <tr><td colspan=2><b>Notes</b>:<br>$ftNotes</td></tr>
</table>
__END__;
	if ($doEdit) {
	    $out .= <<<__END__
<input type=submit name=btnSave value="Save">
<input type=reset value="Revert">
<input type=submit name=btnCancel value="Cancel">
</form>
__END__;
	}

	$out .= $oPage->SectionHeader('Events')
	  .$this->EventListing()
	  .'<hr><small>generated by '.__FILE__.':'.__LINE__.'</small>'
	  ;
	return $out;
    }
/* 2010-10-25 wrote this accidentally. Try it sometime.
    public function AdminPage() {
	global $vgPage,$vgOut;

	if (is_null($this->WhenXmitted)) {
	    $strActDescr = 'Process ';
	} else {
	    if (is_null($this->WhenDecided)) {
		$strActDescr = 'Confirm ';
	    } else {
		$strActDescr = '';
	    }
	}
	$vgPage->UseHTML();
	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,'Credit Card Charge',NULL,3);
	//$objSection->ToggleAdd('edit','edit image records','edit.img');
	$out .= $objSection->Generate();

	$strCard = $this->CardTypeName().' '.$this->CardNum.$vgOut->Italic(' exp ').$this->ShortExp();
	$out .= $vgOut->TableOpen();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Customer ID</b>:','align=right');
	  $out .= $vgOut->TblCell($this->CustObj()->AdminLink());
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Name on card</b>:','align=right');
	  $out .= $vgOut->TblCell($this->OwnerName);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('record:','align=right');
	  $out .= $vgOut->TblCell($this->NameObj()->AdminLink());
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Number</b>:','align=right');
	  $out .= $vgOut->TblCell($strCard);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('<b>Address</b>:','align=right');
	  $out .= $vgOut->TblCell($this->Address);
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TblRowOpen();
	  $out .= $vgOut->TblCell('record:','align=right');
	  $out .= $vgOut->TblCell($this->AddrObj()->AdminLink());
	$out .= $vgOut->TblRowShut();
	$out .= $vgOut->TableShut();

	$vgOut->AddText($out);
    }
*/
    public function AdminSave() {
	global $wgRequest;

      // capture form input
	$txtTag = $wgRequest->GetText('tag');
	$idCust = $wgRequest->GetIntOrNull('cust');
	$idAddr = $wgRequest->GetIntOrNull('addr');
	$idName = $wgRequest->GetIntOrNull('name');
	$txtNum = $wgRequest->GetText('num');
	$txtExp = $wgRequest->GetText('exp');
	$dtExp = clsCustCards::ExpDate($txtExp);
	$sqlExp = is_object($dtExp)?($dtExp->Format('Y-m-d')):'';
	$txtCVV = $wgRequest->GetText('cvv');
	$txtOwnName = $wgRequest->GetText('owner');
	$txtAddrTxt = $wgRequest->GetText('addrtxt');
	$txtNotes = $wgRequest->GetText('notes');

      // build update request
	$arUpd = array(
	  'Name'	=> SQLValue($txtTag),
	  'ID_Cust'	=> SQLValue($idCust),
	  'ID_Addr'	=> SQLValue($idAddr),
	  'ID_Name'	=> SQLValue($idName),
	  'CardNum'	=> SQLValue($txtNum),	// LATER: strip out punctuation
	  'CardExp'	=> SQLValue($sqlExp),
	  'CardCVV'	=> SQLValue($txtCVV),
	  'OwnerName'	=> SQLValue($txtOwnName),
	  'Address'	=> SQLValue($txtAddrTxt),
	  'Notes'	=> SQLValue($txtNotes)
	  );
	$strDescr = 'admin edit';
	if (!empty($txtNotes)) {
	    $strDescr .= ': '.$txtNotes;
	}
	$arEv = array(
	  'descr'	=> $strDescr,
	  'where'	=> __METHOD__,
	  'code'	=> 'UPD'
	  );
	$this->StartEvent($arEv);	// log that we're attempting a change
	$this->Update($arUpd);		// attempt the edit
	$this->FinishEvent();		// log completion
    }

    // -- ADMIN WEB UI -- //
}
