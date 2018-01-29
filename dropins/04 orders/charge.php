<?php
/*
  FILE: dropins/orders/charge.php -- customer order charges administration dropin for VbzCart
  HISTORY:
    2014-02-22 split off OrderCharge classes from order.php
*/
// order charges
class vctAdminOrderCharges extends vcAdminTable {
    use ftLinkableTable;
    
    // ++ SETUP ++ //
    
    // CEMENT
    protected function TableName() {
	return 'cust_charges';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrAdminOrderCharge';
    }
    // CEMENT
    public function GetActionKey() {
	return 'chg';
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    public function DoEvent($nEvent) {}	// no action needed
    public function Render() {
	return $this->AdminPage();
    }
    
    // -- EVENTS -- //
    // ++ WEB UI ++ //

    /*----
      HISTORY:
	2011-03-24 adapted from SpecialVbzAdmin::doCharges()
	2017-01-06 This is obviously going to need some updating.
    */
    public function AdminPage() {

	$doAll = $vgPage->Arg('all');
	$strMnuAll = $vgPage->SelfLink_WT(array('all' => TRUE),'all');
	$strMnuUnp = $vgPage->SelfLink_WT(array('all' => FALSE),'to process');

//	$objTbl = new VbzAdminOrderChgs($this->DB());
	$objTbl = $this;
	if ($doAll) {
	    $strMenu = "$strMnuUnp .. <b>$strMnuAll</b>";
	    $strDescr = ' in database';
	    $sqlFilt = NULL;
	} else {
	    $strMenu = "<b>$strMnuUnp</b> .. $strMnuAll";
	    $strDescr = ' to be processed';
	    $sqlFilt = '(WhenDecided IS NULL) AND ((WhenXmitted IS NULL) OR isSuccess) AND (WhenVoided IS NULL) AND (WhenHeldUp IS NULL)';
	}
	$objRows = $objTbl->GetData($sqlFilt,NULL,'ID DESC');
	$wgOut->AddWikiText("<b>Show Charges</b>: $strMenu", TRUE);
	$arArgs['descr'] = $strDescr;
	$wgOut->AddWikiText($objRows->AdminTable($arArgs),TRUE);
    }

    // -- WEB UI -- //
}
class vcrAdminOrderCharge extends vcAdminRecordset {

    // ++ FIELD VALUES ++ //

    protected function GetOrderID() {
	return $this->GetFieldValue('ID_Order');
    }
    protected function CardID() {
	return $this->GetFieldValue('ID_Card');
    }

    // -- FIELD VALUES -- //
    // ++ CLASSES ++ //

    protected function OrdersClass() {
	return KS_CLASS_ORDERS;
    }
    protected function CardsClass() {
	return KS_CLASS_CUST_CARDS_ADMIN;
    }

    // -- CLASSES -- //
    // ++ TABLES ++ //

    protected function OrderTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->OrdersClass(),$id);
    }
    protected function CardTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->CardsClass(),$id);
    }

    // -- TABLES -- //
    // ++ RECORDS ++ //

    protected function CardRecord() {
	return $this->CardTable($this->CardID());
    }
    public function OrderRecord() {
	return $this->OrderTable($this->GetOrderID());
    }

    // -- RECORDS -- //
    // ++ ADMIN PIECES ++ //

    /*----
      HISTORY:
	2013-11-07 The code seems to be expecting a field called "CardNumExp", but there ain't no such.
	  I'm guessing that this table has been revised since then, for improved security, and it looks
	  like what we want is just the contents of the "CardSafe" field.
    */
    protected function SafeCardData() {
	$row = $this->GetFieldValues();

	if (array_key_exists('CardSafe',$row)) {
	    $out = $row['CardSafe'];
	} else {
	    $out = "<i>no card data</i>";
	}
/*
	$idCard = $row['ID_Card'];
	$strCard = $row['CardExp'];

	if (empty($idCard)) {
	    if (empty($strCard)) {
		$out = "''no card data''";
	    } else {
		$out = '...'.substr($strCard,-13);
	    }
	} else {
	    $objCard = $this->objDB->CustCards()->GetItem($idCard);
	    $out = $objCard->SafeString();
	}
*/
	return $out;
    }

    // -- ADMIN PIECES -- //
    // ++ ADMIN UI ++ //

    /*-----
      ACTION: Renders table for this dataset
      INPUT: iArgs[]
	descr: appended to "no charges" when there are no records in the dataset
      TODO: Rename Render_AdminRows, OSLT
    */
    public function AdminTable(array $iArgs=NULL) {
	if ($this->hasRows()) {
	    $out = <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Card</th>
    <th>Order</th>
    <th>Trx</th>
    <th>$ total</th>
    <th>$ sold</th>
    <th>$ s/h</th>
    <th>When Ent.</th>
    <th>When Xmt</th>
    <th>OK</th>
    <th>Notes</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    while ($this->NextRow()) {
		$css = $isOdd?'odd':'even';
		$isOdd = !$isOdd;

		// TODO: replace row[] accesses with class methods
		$row = $this->GetFieldValues();
		$id = $row['ID'];
		$strCard = $this->SafeCardData();
		$ftCard = $this->CardRecord()->SelfLink($strCard);
		$rcOrd = $this->OrderRecord();
		$ftOrd = $rcOrd->SelfLink($rcOrd->NumberString());
		$idTrx = $row['ID_Trxact'];
		$strAmtTotal = $row['AmtTrx'];
		$strAmtSold = $row['AmtSold'];
		$strAmtShip = $row['AmtShip'];
		$strAmtTax = $row['AmtTax'];
		$dtWhenEnt = $row['WhenEntered'];
		$dtWhenXmt = $row['WhenXmitted'];
		$isVoid = !is_null($row['WhenVoided']);
		$isSuccess = $row['isSuccess'];
		$strNotes = $row['Notes'];


		$htID = '<b>'.$this->SelfLink().'</b>';

		if ($isVoid) {
		    $css .= ',void';
		    $htOk = 'VOID';
		} else {
		    $htOk = $isSuccess?'&radic;':'';
		}


		$out .= <<<__END__
  <tr class=$css>
    <td>$htID</td>
    <td>$ftCard</td>
    <td>$ftOrd</td>
    <td>$idTrx</td>
    <td align=right>$strAmtTotal</td>
    <td align=right>$strAmtSold</td>
    <td align=right>$strAmtShip</td>
    <td>$dtWhenEnt</td>
    <td>$dtWhenXmt</td>
    <td align=center>$htOk</td>
    <td>$strNotes</td>
  </tr>
__END__;
	    }
	    $out .= "\n</table>";
	} else {
	    $strDescr = fcArray::Nz($iArgs,'descr');
	    $out = "\n<div class=container>No charges$strDescr.</div>";
	}
	return $out;
    }
    public function AdminPage() {
	throw new exception('2017-06-05 This will need updating.');
	$oPage = $this->Engine()->App()->Page();

	$doEdit = $oPage->PathArg('edit');
	$strAct = $oPage->PAthArg('do');

	$doVoid = ($strAct == 'void');

	if ($doVoid) {
	    $arEv = array(
	      'code'	=> 'VOID',
	      'descr'	=> 'voiding the charge',
	      'where'	=> __METHOD__
	      );
	    $this->StartEvent($arEv);
	    $arUpd = array(
	      'WhenVoided'	=> 'NOW()'
	      );
	    $this->Update($arUpd);
	    $this->FinishEvent();
	    $this->AdminRedirect();
	}

	if ($oPage->ReqArgBool('btnSave')) {
	    // handle standard edit
	    $this->BuildEditForm(FALSE);
	    $this->AdminSave();		// save edit to existing package
	} elseif ($wgRequest->getBool('btnUpd')) {
	    // 1: calculate updates to be done
	    $strDescr = 'charge ';

	    // handle charge status update
	    $isAuthOk = TRUE;
	    $isVeriOk = TRUE;
	    $arUpdAuth = array();
	    if ($oPage->ReqArgBool('doAccept')) {

		$intAccept = $oPage->ReqArgInt('doAccept');
		$isAuthOk = ($intAccept > 0);
		$strAuth = $oPage->ReqArg('Confirmation');
		$strDescr .= '[processed: '.($isAuthOk?'ok':'FAIL').']';
		$arUpdAuth = array(
		  'Confirmation'	=> SQLValue($strAuth),
		  'WhenXmitted'		=> 'NOW()',
		  'isSuccess'		=> SQLValue($isAuthOk)
		  );
	    }
	    $arUpdVeri = array();
	    if ($oPage->ReqArgBool('doVerify')) {
		$intVerify = $oPage->ReqArgInt('doVerify');
		$chVerify = $oPage->ReqArg('AVSRespCode');
		if ($intVerify != 0) {
		    $isVeriOk = ($intVerify > 0);
		    $strDescr .= '[verified: '.($isVeriOk?'ok':'FAIL').' (code '.$chVerify.')]';
		    $arUpdVeri = array(
		      'AVSRespCode'	=> SQLValue($chVerify),
		      'WhenDecided'	=> 'NOW()',
		      'isAccepted'	=> SQLValue($isVeriOk)
		      );
		}
	    }
	    // 2: log the update attempt
	    $arEv = array(
	      'descr'	=> $strDescr,
	      'where'	=> __METHOD__,
	      'code'	=> 'ED'
	      );
	    $this->StartEvent($arEv);

	    // 3: do the update
	    $arUpd = array_merge($arUpdAuth,$arUpdVeri);
	    $isOk = $isAuthOk && $isVeriOk;
	    if (!$isOk) {
		$arUpd['WhenHeldUp'] = 'NOW()';
	    }
	    $this->Update($arUpd);
	    global $sql;
	    $out .= 'SQL: '.$sql;
	    $this->Reload();
		// TO DO: PULL ORDER IF WhenHeldUp NOT NULL

	    // 4: log update completion
	    $this->FinishEvent();
	}

	$doProcess = FALSE;
	$doVerify = FALSE;
	if (is_null($this->WhenXmitted)) {
	    $strActDescr = 'Process ';
	    $doProcess = TRUE;
	    $doVerify = TRUE;	// allow processing & verification in one step
	} else {
	    if (is_null($this->WhenDecided)) {
		$strActDescr = 'Verify ';
		$doVerify = TRUE;
	    } else {
		$strActDescr = '';
	    }
	}

	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strActDescr.'Card Charge',NULL);
	$objSection->ToggleAdd('edit','edit charge record');
	$out = $objSection->Generate();

	// later, work out some way to make this editable via wiki
	$out .= 'Useful link: [<a href="http://paypal.com">PayPal</a>]<br>';

	$doForm = $doEdit || $doProcess || $doVerify;
	if ($doForm) {
	    $arLink = $vgPage->Args(array('page','id'));
	    $htPath = $vgPage->SelfURL($arLink);
	    $out .= "\n<form method=post action=\"$htPath\">";
	    $this->BuildEditForm(FALSE);
	}

	if ($doProcess) {
	    $ctrlConf = $this->objForm->Render('Confirmation');
	} else {
	    $ctrlConf = $this->Confirmation;
	}
	$chCode = $this->AVSRespCode;
	if ($doVerify) {
	    $ctrlVerif = $this->AddrVerifDropDown('AVSRespCode',$chCode);
	} else {
	    $arCodes = $this->AddrVerifCodes();
	    $ctrlVerif = $chCode.' ('.$arCodes[$chCode].')';
	}

	if ($doEdit) {
	    $objForm = $this->objForm;
	    $ctrlCard		= $this->objForm->Render('CardNumExp');
	    $ctrlAddr		= $this->objForm->Render('CardBillAddr');
	    $ctrlAmtTrx		= $this->objForm->Render('AmtTrx');
	    $ctrlAmtSold	= $this->objForm->Render('AmtSold');
	    $ctrlAmtShip	= $this->objForm->Render('AmtShip');
	    $ctrlAmtTax		= $this->objForm->Render('AmtTax');
	    $ctrlWhenEnt	= $this->objForm->Render('WhenEntered');
	    $ctrlWhenXmt	= $this->objForm->Render('WhenXmitted');
	    $ctrlWhenHld	= $this->objForm->Render('WhenHeldUp');
	    $ctrlWhenDcd	= $this->objForm->Render('WhenDecided');
	    $ctrlWhenVoid	= $this->objForm->Render('WhenVoided');
	} else {
	    $ctrlAmtTrx =  $this->AmtTrx;
	    $ctrlAmtSold = $this->AmtSold;
	    $ctrlAmtShip = $this->AmtShip;
	    $ctrlAmtTax = $this->AmtTax;

	    $ctrlCard = $this->CardNumExp;
	    $ctrlAddr = '<pre>'.$this->CardBillAddr.'</pre>';
	    if ($doProcess) {
		// show accepted/declined choice
		$ctrlWhenXmt =
		  '<input type=radio name=doAccept value=1>accept '
		  .'<input type=radio name=doAccept value=-1>decline';
	    } else {
		$ctrlWhenXmt = $this->WhenXmitted;
	    }
	    if ($doVerify) {
		// show allow/reject choice
		$ctrlWhenDcd =
		  '<input type=radio name=doVerify value=1>allow '
		  .'<input type=radio name=doVerify value=-1>reject'
		  .'<input type=radio name=doVerify value=0>unknown';
	    } else {
		$ctrlWhenDcd = $this->WhenDecided;
	    }
	    $ctrlWhenEnt = $this->WhenEntered;
	    $ctrlWhenHld = $this->WhenHeldUp;
	    $dtWhenVoid = $this->Value('WhenVoided');
	    if (is_null($dtWhenVoid)) {
		$vgPage->ArgsToKeep(array('page','id'));
		$ftLink = $vgPage->SelfLink(array('do'=>'void'),'void now','void this charge without processing');
		$ctrlWhenVoid = " [ $ftLink ]";
	    } else {
		$ctrlWhenVoid = $dtWhenVoid;
	    }
	}

	//$strCard = $this->CardTypeName().' '.$this->CardNum.$vgOut->Italic(' exp ').$this->ShortExp();
	$strStatus = (($this->isSuccess)?' SUCCESS':'').(($this->isTest)?' TEST':'');
	$rcOrd = $this->OrderRecord();
	$rcCard = $this->CardRecord();
	$htOrd = $rcOrd->SelfLink_name();
	$htCard = $rcCard->SelfLink($rcCard->ShortNumExpName());
	$out .= <<<__END__
<table>
  <tr>
    <td align=right><b>Status</b></td>
    <td>$strStatus</td>
  </tr>
  <tr>
    <td align=right><b>Order</b></td>
    <td>$htOrd</td>
  </tr>
  <tr>
    <td align=right><b>Card</b></td>
    <td>$ctrlCard</td>
  </tr>
  <tr>
    <td align=right>object</td>
    <td>$htCard</td>
  </tr>
  <tr>
    <td align=right valign=top><b>Address</b>:</td>
    <td>$ctrlAddr</td>
  </tr>
  <tr>
    <td align=right><b>Total</b>:</td>
    <td>$$ctrlAmtTrx</td>
  </tr>
  <tr>
    <td align=right><b>Sale</b>:</td>
    <td>$$ctrlAmtSold</td>
  </tr>
  <tr>
    <td align=right><b>Shipping</b>:</td>
    <td>$$ctrlAmtShip</td>
  </tr>
  <tr>
    <td align=right><b>Tax</b>:</td>
    <td>$$ctrlAmtTax</td>
  </tr>
  <tr>
    <td align=right><b>Time stamps</b>:</td>
  </tr>
  <tr>
    <td align=center colspan=2 bgcolor=#8888ff>
      <table>
	<tr>
	  <td align=right><b>Entered</b>:</td>
	  <td>$ctrlWhenEnt</td>
	</tr>
	<tr>
	  <td align=right><b>Processed</b>:</td>
	  <td>$ctrlWhenXmt</td>
	  <td align=right><b>Transaction ID</b>:</td>
	  <td>$ctrlConf</td>
	</tr>
	<tr>
	  <td align=right><b>Verified</b>:</td>
	  <td>$ctrlWhenDcd</td>
	  <td align=right><b>Verification code</b>:</td>
	  <td>$ctrlVerif</td>
	</tr>
	<tr>
	  <td align=right><b>Held up</b>:</td>
	  <td>$ctrlWhenHld</td>
	</tr>
	<tr>
	  <td align=right><b>Voided</b>:</td>
	  <td>$ctrlWhenVoid</td>
	</tr>
      </table>
    </td>
  </tr>
</table>
__END__;
	if ($doForm) {
	    if ($doEdit) {
		$out .= '<input type=submit name=btnSave value="Save">';
	    } else {
		$out .= '<input type=submit name=btnUpd value="Update">';
	    }
	    $out .= '</form>';
	}

	// events
	$out .= $oPage->SectionHeader('Events');
	$out .= $this->EventListing();
	$out .= '<hr><small>generated by '.__FILE__.':'.__LINE__.'</small>';

	return $out;
    }
    private function BuildEditForm($iNew) {
	global $vgOut;
	// create fields & controls

	if (is_null($this->objFlds)) {
	    $objForm = new clsForm_DataSet($this,$vgOut);

	    $objForm->AddField(new clsField('CardNumExp'),	new clsCtrlHTML(array('size'=>30)));
	    $objForm->AddField(new clsField('CardBillAddr'),	new clsCtrlHTML_TextArea(array('height'=>4,'width'=>30)));
	    $objForm->AddField(new clsField('Confirmation'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('AVSRespCode'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('AmtTrx'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('AmtSold'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('AmtShip'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldNum('AmtTax'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenEntered'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenXmitted'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenHeldUp'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenDecided'),	new clsCtrlHTML());
	    $objForm->AddField(new clsFieldTime('WhenVoided'),	new clsCtrlHTML());

	    $this->objForm = $objForm;
	}
    }
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
	2011-03-24 copied from VbzAdminItem to VbzAdminOrderChg
    */
    public function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    /*----
      RETURNS: array of address verification codes
	array[code letter] = description
    */
    protected function AddrVerifCodes() {
	return array(
	  'A' => 'bad zip, ok addr',
	  'B' => 'no zip, ok addr (non-US A)',
	  'C' => 'bad addr+zip (non-US N)',
	  'D' => 'ok addr+zip (non-US X/Y)',
	  'E' => 'F2F only',
	  'F' => 'ok addr+zip (UK X/Y)',
	  'G' => 'unavailable (global)',
	  'I' => 'unavailable (non-US)',
	  'N' => 'bad addr+zip',
	  'P' => 'no zip, ok addr (non-US Z)',
	  'R' => 'retry later',
	  'S' => 'not supported',
	  'U' => 'addr info unavailable',
	  'W' => 'bad addr, ok zip+4',
	  'X' => 'ok addr+zip+4',
	  'Y' => 'ok addr+zip',
	  'Z' => 'bad addr, ok zip'
	  );
    }
    /*----
      RETURNS: HTML for a drop-down box listing all the available codes
    */
    protected function AddrVerifDropDown($iName,$iVal) {
	$out = "\n".'<select name="'.$iName.'">';
	$arCodes = $this->AddrVerifCodes();
	foreach ($arCodes as $code => $descr) {
	    $out .= "\n".'<option value="'.$code.'"';
	    if ($code == $iVal) {
		$out .= ' selected';
	    }
	    $out .= '>'.$code.' - '.$descr.'</option>';
	}
	$out .= '</select>';
	return $out;
    }
}
