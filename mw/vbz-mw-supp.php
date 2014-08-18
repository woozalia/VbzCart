<?php
/*
  PART OF: VbzAdmin
  PURPOSE: classes for handling Suppliers
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
*/
class VbzAdminSuppliers extends clsSuppliers {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('VbzAdminSupplier');
    }
    public function AdminPage() {
	global $wgOut;
	global $vgPage;

	$vgPage->UseWiki();

	$out = '==Suppliers==';
	$wgOut->addWikiText($out,TRUE);	$out = '';
	$objRecs = $this->GetData();
	if ($objRecs->HasRows()) {
	    $out = "{| class=sortable\n|-\n! ID || A? || Code || Actions || Name";
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$strCatKey = $objRecs->CatKey;
		$id = $objRecs->ID;
		$wtActions =
		  '['.KWP_CAT.strtolower($strCatKey)." shop] "
		  .$objRecs->AdminLink('manage');
		$wtCatNum = $strCatKey;
		$wtStyle = $isOdd?'background:#ffffff;':'background:#cccccc;';
		$isActive = $objRecs->isActive;
		if ($isActive) {
		    $wtName = '[['.$objRecs->Name.']]';
		} else {
		    $wtName = $objRecs->Name;
		    $wtStyle .= ' color: #888888;';
		}
		$out .= "\n|- style=\"$wtStyle\"\n| ".$id
		  .' || '.($isActive?'&radic;':'')
		  .' || '.$wtCatNum
		  .' || '.$wtActions
		  .' || '.$wtName;
		$isOdd = !$isOdd;
	    }
	    $out .= "\n|}";
	} else {
	    $out = 'No suppliers have been created yet.';
	}
	$wgOut->addWikiText($out,TRUE);	$out = '';
    }
}
class VbzAdminSupplier extends clsSupplier {
    /*====
      HISTORY:
	2010-12-05 boilerplate event logging added to VbzAdminSupplier
    */
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
	return $this->Log()->EventListing();
    }
    //====
    /*----
      HISTORY:
	2010-10-11 Replaced existing code with call to static function
    */
    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsAdminData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }
    public function AdminLink_name() {
	return $this->AdminLink($this->Name);
    }
    /*----
      HISTORY:
	2010-11-04 Created so AdminPage() can use HTML
	2011-02-16 Disabled until I figure out why it isn't redundant
    */
/*
    public function ShopLink($iText=NULL) {
	return '<a href="'.KWP_CAT.strtolower($this->CatKey).'">'.$iText.'</a>';
    }
*/
    public function PageTitle() {
	global $vgPage;

	$doShow = $vgPage->Arg('show');
	return $this->Value('CatKey').':'.$doShow;
    }
    /*----
      RETURNS: object for Supplier's Topic
      HISTORY:
	2011-10-01 written -- replacing Departments with Topics
    */
    public function TopicObj() {
	$id = $this->Value('ID_Topic');
	if (is_null($id)) {
	    return NULL;
	} else {
	    $row = $this->Engine()->Topics($id);
	    return $row;
	}
    }
    /*----
      RETURNS: nicely-formatted link to Supplier's Topic
      HISTORY:
	2011-10-01 written -- replacing Departments with Topics
    */
    public function TopicLink($iNone='<i>n/a</i>') {
	$row = $this->TopicObj();
	if (is_object($row)) {
	    return $row->AdminLink_name();
	} else {
	    return $iNone;
	}
    }
    /*----
    */
    public function AdminPage() {
	global $wgOut,$wgRequest;
	global $vgPage,$vgOut;

	$vgPage->UseHTML();
	$out = NULL;
	$doEdit = $vgPage->Arg('edit');
	$doSave = $wgRequest->GetBool('btnSave');

	// save edits before showing events
	$ftSaveStatus = NULL;
	if ($doEdit || $doSave) {
	    $this->BuildEditForm();
	    if ($doSave) {
		$ftSaveStatus = $this->AdminSave();
	    }
	}

	$strCatKey = $this->CatKey;
	$strName = $this->Name;

	$doDeptAdd = $vgPage->Arg('add.dept');

	$objPage = new clsWikiFormatter($vgPage);
	$objSection = new clsWikiSection($objPage,$strName.' ('.$strCatKey.')');
	$out = $objSection->Generate();
	$wgOut->AddHTML($out); $out = '';

	$sHdr = 'Current Record (ID '.$this->ID.')';
//	$objSection = new clsWikiSection($objPage,$sHdr,NULL,3);
//	$objSection->ToggleAdd('edit');
//	$out = $objSection->Generate();

	$objSection = new clsWikiSection_std_page($objPage,$sHdr,3);
	$objSection->AddLink_local(new clsWikiSectionLink_keyed(array('edit'=>TRUE),'edit'));
	$out = $objSection->Render();

	if ($doEdit) {
	    $out .= $objSection->FormOpen();

	    $objForm = $this->objForm;
	    $ftName	= $objForm->Render('Name');
	    $ftCatKey	= $objForm->Render('CatKey');
	    $ftTopic	= $objForm->Render('ID_Topic');
	    $ftActive	= $objForm->Render('isActive');
	} else {
	    $ftName	= $objPage->Page()->SelfLink(array(),$strName,'reload this page').' ('.$vgOut->WikiLink($strName,'info').')';
	    $ftCatKey	= $this->ShopLink($strCatKey);
	    $ftTopic	= $this->TopicLink();
	    $ftActive	= NoYes($this->isActive);
	}

	$ftID = $this->AdminLink();

	$out .= $vgOut->TableOpen();

	$out .= $vgOut->TblRowOpen();
	$out .= $vgOut->TblCell('<b>ID</b>:','align=right');
	$out .= $vgOut->TblCell($ftID);
	$out .= $vgOut->TblRowShut();

	$out .= $vgOut->TblRowOpen();
	$out .= $vgOut->TblCell('<b>Name</b>:','align=right');
	$out .= $vgOut->TblCell($ftName);
	$out .= $vgOut->TblRowShut();

	$out .= $vgOut->TblRowOpen();
	$out .= $vgOut->TblCell('<b>CatKey</b>:','align=right');
	$out .= $vgOut->TblCell($ftCatKey);
	$out .= $vgOut->TblRowShut();

	$out .= $vgOut->TblRowOpen();
	$out .= $vgOut->TblCell('<b>Topic</b>:','align=right');
	$out .= $vgOut->TblCell($ftTopic);
	$out .= $vgOut->TblRowShut();

	$out .= $vgOut->TblRowOpen();
	$out .= $vgOut->TblCell('<b>Active</b>:','align=right');
	$out .= $vgOut->TblCell($ftActive);
	$out .= $vgOut->TblRowShut();

	$out .= $vgOut->TableShut();

	if ($doEdit) {
	    $out .= '<b>Edit notes</b>: <input type=text name="EvNotes" size=40><br>';
	    $out .= '<input type=submit name="btnSave" value="Save">';
	    $out .= '<input type=submit name="btnCancel" value="Cancel">';
	    $out .= '<input type=reset value="Reset">';
	    $out .= '</form>';
	}

	$vgOut->addText($out,TRUE);	$out = '';

	// show submenu
	$arMnu = array(
	  'dept'	=> '.departments.'.$strName.' departments',
	  'cat'		=> '.catalogs.'.$strName.' wholesale catalogs',
	  'rreq'	=> '.restocks.'.$strName.' restock requests',
	  'ctg'		=> '.catalog groups.groups for organizing '.$strName.' catalog items'
	  );
	$out = '<b>Manage</b>: '.$vgPage->SelfLinkMenu('show',$arMnu);
	$vgOut->AddText($out); $out = '';

	$doShow = $vgPage->Arg('show');

	//$vgPage->UseWiki();
	switch ($doShow) {
/* Some remediation needed here. Some of these output in wikitext, others in HTML.
 All of them seem to output directly, rather than returning rendered text.
*/
	  case 'dept':
	    $objSection = new clsWikiSection($objPage,'Departments',NULL,3);
	    $objSection->ToggleAdd('add','add a department to '.$strName,'add.dept');
	    $out = $objSection->Generate();
	    $wgOut->addHTML($out,TRUE); $out = '';
	    $out .= $this->DeptsListing();
	    break;
	  case 'cat':
	    $sHdr = 'Catalogs';
	    $arLink = array(
	      'page'	=> $this->Engine()->CtgSrcs()->ActionKey(),
	      'id'	=> 'new',
	      'supp'	=> $this->ID
	      );

//	    $objSection = new clsWikiSection($objPage,'Catalogs',NULL,3);
//	    $objSection->ActionAdd('add','add a catalog to '.$strName,FALSE,$arLink);
//	    $out = $objSection->Generate();

	    $objSection = new clsWikiSection_std_page($objPage,$sHdr,3);
	    $objSection->AddLink_local(new clsWikiSectionLink_keyed($arLink,'add'));
	    $out = $objSection->Render();

	    $wgOut->addHTML($out,TRUE); $out = '';
	    $out .= $this->CatalogAdmin();
	    break;
	  case 'rreq':
	    $objSection = new clsWikiSection($objPage,'Restock Requests',NULL,3);
	    $out = $objSection->Generate();
	    $wgOut->addHTML($out,TRUE); $out = '';
	    $out .= $this->RstkReqAdmin();
	    break;
	  case 'ctg':
	    //$objSection = new clsWikiSection($objPage,'Catalog Groups','catalog management groups',3);
	    $objSection = new clsWikiSection_std_page($objPage,'Catalog Groups',3);
	    //$objSection->ArgsToKeep(array('show','page','id'));
	    $objSection->PageKeys(array('show','page','id'));
	    //$objSection->ToggleAdd('edit','edit the list of groups','edit.ctg');
	    $objLink = $objSection->AddLink_local(new clsWikiSectionLink_keyed(array('edit.ctg'=>TRUE),'edit','edit.ctg'));
	      $objLink->Popup('edit the list of groups');
	    $out = $objSection->Render();

	    $wgOut->addHTML($out,TRUE); $out = '';
	    $doEdit = $vgPage->Arg('edit.ctg');
	    $out .= $this->CtgGrpAdmin($doEdit);
	    break;
	  default:
	    $out = '';
	}
	$vgOut->addText($out);
    }
    /*----
      ACTION: Save user changes to the record
      HISTORY:
	2010-11-06 copied from VbzStockBin to VbzAdminItem
	2011-01-26 copied from VbzAdminItem to clsAdminTopic
	2011-10-02 copied from clsAdminTopic to VbzAdminSupplier
    */
    public function AdminSave() {
	global $vgOut;

	$out = $this->objForm->Save();
	$vgOut->AddText($out);
    }
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
	2011-10-01 adapted from VbzAdminTitle for VbzAdminSupplier
    */
    private function BuildEditForm() {
	global $vgOut;

	if (is_null($this->objForm)) {
	    // create fields & controls
	    $objForm = new clsForm_DataSet($this,$vgOut);
	    //$objCtrls = new clsCtrls($objForm->Fields());
	    //$objCtrls = $objForm;

	    $objForm->AddField(new clsField('Name'),		new clsCtrlHTML(array('size'=>25)));
	    $objForm->AddField(new clsFieldNum('ID_Topic'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('CatKey'),		new clsCtrlHTML(array('size'=>8)));
	    $objForm->AddField(new clsFieldBool('isActive'),	new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsField('Notes'),	new clsCtrlHTML_TextArea(array('height'=>3,'width'=>50)));

	    $this->objForm = $objForm;
	    //$this->objCtrls = $objCtrls;
	}
    }
    /*
      DEPRECATED - switching to topics
    */
    public function DeptsListing() {
	$out = $this->objDB->Depts()->Listing_forSupp($this->ID,$this);
	return $out;
    }
    /*----
      ACTION: Add a list of titles to this department
      INPUT:
	iTitles: array
	  iTitles[catkey] = name
	iEvent: array to be passed to event log
      HISTORY:
	2012-02-02 adapted from VbzAdminDept to VbzAdminSupplier
    */
    public function AddTitles(array $iTitles,array $iEvent=NULL) {
	$cntTitles = count($iTitles);
	if ($cntTitles > 0) {
	    $strDescr = 'adding '.$cntTitles.' title'.Pluralize($cntTitles);

	    $iEvent['descr'] = StrCat($iEvent['descr'],$strDescr,' ');
	    $iEvent['where'] = nz($iEvent['where'],__METHOD__);
	    $iEvent['code'] = 'ADM';	// add multiple
	    $this->StartEvent($iEvent);

	    $id = $this->KeyValue();
	    $cntAdded = 0;
	    $cntError = 0;
	    $txtAdded = '';
	    $txtError = '';
	    $tblTitles = $this->objDB->Titles();
	    foreach ($iTitles as $catnum => $name) {
		$arIns = array(
		  'Name'	=> SQLValue($name),
		  'CatKey'	=> SQLValue($catnum),
		  'ID_Supp'	=> $id,
		  'DateAdded'	=> 'NOW()'
		  );
		$ok = $tblTitles->Insert($arIns);
		if ($ok) {
		    $idNew = $tblTitles->LastID();
		    $cntAdded++;
		    $txtAdded .= '['.$catnum.' ID='.$idNew.']';
		} else {
		    $cntError++;
		    $txtError .= '['.$catnum.' Error: '.$this->objDB->getError().']';
		}
	    }
	    if ($cntError > 0) {
		$txtDescr = $cntError.' error'.Pluralize($cntError).': '.$txtError;
		$txtDescr .= ' and ';
	    } else {
		$txtDescr = 'Success:';
	    }
	    $txtDescr .= $cntAdded.' title'.Pluralize($cntAdded).' added '.$txtAdded;
	    $arEv = array(
	      'descrfin' => SQLValue($txtDescr),
	      'error' => SQLValue($cntError > 0)
	      );
	    $this->FinishEvent($arEv);
	}
    }
  /*%%%%
    SECTION: Data Entry Management
    PROCESS:
      * User enters a list of Titles or Items in a textarea box, one per line.
      * Each line is checked against Supplier catalog #s
      * if found, shows details for the title/item and provides option to approve it in the final resultset
      * if not found, gives user the option to give more information identifying the Title/Item, such as
	our catalog # (or what our catalog # would be if the Title/Item existed in the database)
  */
    /*----
      PURPOSE: renders form for reconciliation of a user-entered list of Titles from a Supplier source document
      INPUT:
	$iTitles[line #] = array of title information, format to be determined
	  ['id'] = ID of Title record to be approved as matching the input
	  ['scat'] = supplier's catalog number
	  ['ocat'] = our catalog number (may be hypothetical)
	  ['$buy'] = cost to us
	  ['$sell'] = our selling price (to customer)
	  ['name'] = descriptive name for the Title
      RETURNS: HTML code for reconciliation form. Does not include <form> tags or buttons.
    */
/*
    public function AdminTitles_form_entry(array $iTitles) {
	die('This function is not ready yet!');

	if (count($iTitles) > 0) {
	    $isOdd = TRUE;
	    $isReady = TRUE;	// ready to enter - all items have been identified
	    $out .= '<table><tr><th>ID</th><th>Cat#</th><th>Title</th><th>Qty</th><th>Price</th></tr>';
	    foreach ($iTitles as $cnt => $data) {
		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$htAttr = ' style="'.$ftStyle.'"';
		$isOdd = !$isOdd;

		$strScat = nz($data['scat']);
		$strInvTitle = nz($data['title']);
		$strPrBuy = nz($data['$buy']);
		$strPrSell = nz($data['$sell']);
		$strQty = nz($arOut['qty']);
		$out .= "<tr$htAttr><td></td><td>$strScat</td><td>$strInvTitle</td><td align=center>$strQty</td><td align=right>$strPrice</td></tr>";

	    }
	} else {
	    $out  = 'No titles entered.';
	}
    }
*/
    /*----
      PURPOSE: renders form for reconciliation of a user-entered list of Items from a Supplier source document
	(same as AdminTitles_form_entry() but for Items instead of Titles)
      ACTION: Renders an Item-reconciliation form
      INPUT: output from AdminItems_data_check()
      OUTPUT: returned data
      RETURNS: HTML of form containing data AdminItems_form_receive() is expecting to see
    */
    public function AdminItems_form_entry(array $iData) {
	$cntItems = count($iData);

	if ($cntItems > 0) {
	    $strPfx = $this->Value('CatKey');
	    $isOdd = TRUE;
	    $out = '<table><tr>'
	      .'<th></th>'
	      .'<th>status</th>'
	      .'<th>ID</th>'
	      .'<th>Our Cat#</th>'
	      .'<th>SCat#</th>'
	      .'<th>What</th>'
	      .'<th>Qty</th>'
	      .'<th>$buy</th>'
	      .'<th>$sell</th>'
	      .'</tr>';
	    foreach ($iData as $idx => $row) {
		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$htAttr = ' style="'.$ftStyle.'"';
		$isOdd = !$isOdd;

		$idItem = nz($row['id']);
		$strOCat = nz($row['ocat']);
		$strSCat = nz($row['scat']);
		$strInvTitle = nz($row['name']);
		$strPrBuy = nz($row['$buy']);
		$strPrSell = nz($row['$sell']);
		$strQty = nz($row['qty']);
		$objItem = $row['@obj'];

		$htOCat = $strOCat;
		$htItem = NULL;

		$cnOCat = "ocat[$idx]";

		$canUpdSCat = FALSE;
		$canUpdDescr = FALSE;

		if (is_null($objItem)) {
		    //$cntNoItem++;
		    if (!empty($strOCat)) {
			$arOkAdd[$idx] = $data;
		    }
		    // let user enter our catalog #
		    $htOCat = $strPfx.'-<input name="'.$cnOCat.'" size=15 value="'.htmlspecialchars($strOCat).'">';
		} else {
		    $data['obj'] = $objItem;
		    $arOkFnd[$idx] = $data;
		    $strOCat = $objItem->Value('CatNum');
		    $idItem = $objItem->KeyValue();

		    $htOCat = $strOCat.'<input type=hidden name="'.$cnOCat.'" value="'.htmlspecialchars($strOCat).'">';
		    $htItem = $objItem->AdminLink().'<input type=hidden name="id['.$idx.']" value="'.$idItem.'">';
		    $htOCat = $strOCat;

		    // compare entered values with recorded values
		    // -- supplier catalog #
		    $strSCatEnt = $strSCat;			// entered
		    $strSCatRec = $objItem->Supp_CatNum;	// recorded
		    if ($strSCatEnt != $strSCatRec) {
			$canUpdSCat = TRUE;
		    }
		    // -- title
		    $strDescrEnt = $strInvTitle;		// entered
		    $strDescrRec = $objItem->Descr;		// recorded
		    if ($strDescrEnt != $strDescrRec) {
			$canUpdDescr = TRUE;
		    }
		}

		$htSCat = $strSCat.'<input type=hidden name="scat['.$idx.']" value="'.htmlspecialchars($strSCat).'">';
		if ($canUpdSCat) {
		    if (empty($strSCatRec)) {
			$strAct = 'save this';
		    } else {
			$strAct = 'replace <b>'.$strSCatRec.'</b>';
		    }
		    $htSCat .= '<br><small><input type=checkbox name="do-upd-scat['.$idx.']">'.$strAct.'</small>';
		}
		$htName = $strInvTitle.'<input type=hidden name="name['.$idx.']" value="'.htmlspecialchars($strInvTitle).'">';
		if ($canUpdDescr) {
		    if (empty($strDescrRec)) {
			$strAct = 'save this';
		    } else {
			$strAct = 'replace <b>'.$strDescrRec.'</b>';
		    }
		    $htName .= '<br><small><input type=checkbox name="do-upd-desc['.$idx.']">'.$strAct.'</small>';
		}

		$htQty = $strQty.'<input type=hidden name="qty['.$idx.']" value='.$strQty.'>';

		$htPrBuy = $strPrBuy.'<input type=hidden name="$buy['.$idx.']" value="'.$strPrBuy.'">';
		$htPrSell = $strPrSell.'<input type=hidden name="$sell['.$idx.']" value="'.$strPrSell.'">';

		switch ($row['@state']) {
		  case 'use':
		    $htStatus = '<span style="color: #008800"><b>ready</b></span>';
		    break;
		  case 'add':
		    $htStatus = '<span style="color: #000088" title="there is enough info to add this item">addable</span>';
		    break;
		  default:
		    $htStatus = '<span color=red title="need more information">?</span>';
		}

		$out .= "\n<tr$htAttr>"
		  ."\n\t<td>$idx.</td>"
		  ."\n\t<td>$htStatus</td>"
		  ."\n\t<td>$htItem</td>"
		  ."\n\t<td>$htOCat</td>"
		  ."\n\t<td>$htSCat</td>"
		  ."\n\t<td>$htName</td>"
		  ."\n\t<td align=center>$htQty</td>"
		  ."\n\t<td align=right>$htPrBuy</td>"
		  ."\n\t<td align=right>$htPrSell</td>"
		  ."\n\t</tr>";
	    }
	    $out .= '</table>';
	} else {
	    $out  = 'No titles entered.';
	}
	return $out;
    }
    /*----
      ACTION: Receives user data from form rendered by AdminItems_form_entry()
      INPUT: http POST data from Item reconciliation form
	id[line #] = array of Item IDs, where known
	name[line #] = array of item descriptions
	qty[line #] = array of item quantities
	scat[line #] = array of supplier catalog numbers for each line, entered by user
	ocat[line #] = array of our catalog numbers for each line, entered by user
	$buy[line #] = array of price-to-us for each line, entered by user
	$sell[line #] = array of price-to-customer for each line, entered by user
      NOTE: sell[] is not currently used in any known scenario. Possibly it should be removed.
      RETURNS: array of all received data, but indexed by line number first
	includes the following fields:
	  ['qty'] = item quantity
	  ['ocat'] = catalog number entered by the user
	    to be used either for looking up the item or creating it
      FUTURE: This should be generalized somehow
   */
    public function AdminItems_form_receive() {
	global $wgRequest;

	$arCols = array('id','name','qty','ocat','scat','$buy','$sell','do-upd-scat','do-upd-desc');
	foreach ($arCols as $col) {
	    $arOut[$col] = $wgRequest->GetArray($col);
	}
	$arRtn = ArrayPivot($arOut);
//echo '<pre>'.print_r($arRtn,TRUE).'</pre>';
	return $arRtn;
    }
    /*----
      ACTION: Check item data against database and return status information
      INPUT:
	$iItems[line #]: array in format returned by AdminItems_form_receive()
      RETURNS:
	['#add'] = number of rows which need to be added to the catalog
	['#use'] = number of rows which are ready to be used (item exists in catalog)
	['rows'] = input data with additional fields:
	    ['@state']: status of line as indicated by one of the following strings:
	      'use' = item has been found, so this line is ready to use
	      'add' = item not found, but there is enough information to create it
	    ['@obj'] is the Item object (only included if @state = 'use')
    */
    public function AdminItems_data_check(array $iItems) {
	if (count($iItems) > 0) {
	    $cntUse = 0;
	    $cntAdd = 0;
	    $cntUpd = 0;	// count of updatable fields
	    $arRows = array();
	    $strCatPfx = $this->Value('CatKey');
	    foreach ($iItems as $idx => $data) {
		$idItem = nz($data['id']);

		$strOCatRaw = nz($data['ocat']);
		$strOCatFull = $strCatPfx.'-'.$strOCatRaw;
		$gotOCat = !empty($strOCatRaw);

		$strSCat = nz($data['scat']);

		$data['@state'] = NULL;
		$data['@obj'] = NULL;
		if (empty($idItem)) {
		    if ($gotOCat) {
			// look up item using our catalog #
			$objItem = $this->objDB->Items()->Get_byCatNum($strOCatFull);
		    } else {
			// look up item using supplier catalog #
			$objItem = $this->GetItem_bySCatNum($strSCat);
		    }
		    if (is_null($objItem)) {
			if ($gotOCat) {
			    $data['@state'] = 'add';
			    $cntAdd++;
			}
		    }
		} else {
		    $objItem = $this->objDB->Items($idItem);
		}
		if (is_object($objItem)) {
		    $data['@obj'] = $objItem;
		    $data['@state'] = 'use';
		    $cntUse++;

		    // compare entered values with recorded values
		    // -- supplier catalog #
		    $strSCatEnt = $strSCat;			// entered
		    $strSCatRec = $objItem->Supp_CatNum;	// recorded
		    if ($strSCatEnt != $strSCatRec) {
			$cntUpd++;
			$data['@can-upd-scat'] = TRUE;
		    } else {
			$data['@can-upd-scat'] = FALSE;
		    }
		    // -- title
		    $strDescrEnt = nz($data['name']);		// entered
		    $strDescrRec = $objItem->Descr;		// recorded
		    if ($strDescrEnt != $strDescrRec) {
			$cntUpd++;
			$data['@can-upd-desc'] = TRUE;
		    } else {
			$data['@can-upd-desc'] = FALSE;
		    }
		}
		$arRows[$idx] = $data;
	    }	// foreach ($iItems...)
	} else {
	    $arRtn = NULL;
	}
	$arRtn = array(
	  'rows' => $arRows,
	  '#use' => $cntUse,
	  '#upd' => $cntUpd,
	  '#add' => $cntAdd);
	return $arRtn;
    }
    /*----
      ACTION: Creates listed catalog items
      INPUT: Array of items as returned by AdminItems_data_check()
      RETURNS: HTML to display (messages)
    */
    public function AdminItems_data_add(array $iItems) {
	$out = '';

	$tblItems = $this->objDB->Items();

	$cntItems = count($iItems);
	$txtOCats = '';
	foreach ($iItems as $idx => $row) {
	    $txtOCats .= ' '.$row['ocat'];
	}
	$strEv = 'Adding '.$cntItems.' item'.Pluralize($cntItems).':'.$txtOCats;

	$arEv = array(
	  'descr'	=> SQLValue($strEv),
	  'where'	=> SQLValue(__METHOD__),
	  'code'	=> SQLValue('RI+')	// Reconcile Items: add
	  );
	$this->StartEvent($arEv);

	$strCatPfx = $this->Value('CatKey');
	foreach ($iItems as $idx => $row) {
	    $strOCat = $strCatPfx.'-'.strtoupper($row['ocat']);
	    $arAdd = array(
	      'CatNum'		=> SQLValue($strOCat),
	      'isCurrent'	=> 'FALSE',	// we don't actually know anything about availability yet
	      'ID_Title'	=> 0,		// needs to be assigned to a title
	      'Descr'		=> SQLValue($row['name']),
	      'Supp_CatNum'	=> SQLValue($row['scat'])
	      );
	    if (!empty($row['$buy'])) {
		$arAdd['PriceBuy'] = SQLValue($row['$buy']);
	    }
	    if (!empty($row['$sell'])) {
		$arAdd['PriceSell'] = SQLValue($row['$sell']);
	    }
	    //$out .= '<pre>'.print_r($arAdd,TRUE).'</pre>';
	    $tblItems->Insert($arAdd);
	}
	$out = $strEv;
	$this->FinishEvent();
	return $out;
    }
    /*----
      ACTION: Renders drop-down box of all Suppliers, with the current one as default
      USED BY: Restock edit screen
      HISTORY:
	2011-03-02 What happened? Apparently this method used to exist, but got deleted without a trace.
	  Rewriting from scratch.
    */
    public function DropDown($iName=NULL,$iNone=NULL) {
	$strName = is_null($iName)?($this->Table->ActionKey()):$iName;	// control name defaults to action key

	$rs = $this->Table->GetData();

	if ($rs->HasRows()) {
	    $out = "\n<SELECT NAME=$strName>";
	    if (!is_null($iNone)) {
		$out .= DropDown_row(NULL,$iNone,$iDefault);
	    }
	    while ($rs->NextRow()) {
		$id = $rs->Value('ID');
		$txtAbbr = $rs->Value('CatKey');
		$htAbbr = is_null($txtAbbr)?'':($txtAbbr.' ');
		$htShow = $htAbbr.$rs->Value('Name');
		$out .= DropDown_row($id,$htShow,$this->Value('ID'));
	    }
	    $out .= "\n</select>";
	    return $out;
	} else {
	    return NULL;
	}
    }
    /*----
      ACTION: Renders drop-down box of active departments for this supplier
      RETURNS: HTML code
    */
    public function Depts_DropDown($iName=NULL,$iDefault=NULL,$iNone=NULL) {
	$objRecs = $this->objDB->Depts()->Data_forSupp($this->ID,'isActive');
	$out = $objRecs->DropDown($iName,$iDefault,$iNone);
	return $out;
    }
    public function CatalogAdmin() {
	$objTbl = $this->objDB->Catalogs();
	$objRows = $objTbl->GetData('ID_Supplier='.$this->ID,NULL,'ID DESC');
	$out = $objRows->AdminList();
	return $out;
    }
    public function RstkReqAdmin() {
	$objTbl = $this->objDB->RstkReqs();
	$objRows = $objTbl->GetData('ID_Supplier='.$this->ID,NULL,'IFNULL(WhenOrdered,WhenCreated) DESC');
	$out = $objRows->AdminList();
	return $out;
    }
    public function CtgGrpAdmin($iEdit) {
	$objTbl = $this->objDB->CtgGrps();
	$id = $this->KeyValue();
	$objRows = $objTbl->GetData('ID_Supplier='.$id,NULL,'Sort');
	$out = $objRows->AdminList($iEdit,array('ID_Supplier'=>$id));
	return $out;
    }
    /*-----
      ACTION: Finds the last restock request for the given supplier
      RETURNS: the last request by date and the last request sorted by (our) PO #
    */
    public function LastReq() {
	//$sqlBase = 'SELECT * FROM `rstk_req` WHERE ID_Supplier='.$this->ID;
	$sqlBase = 'WHERE ID_Supplier='.$this->ID;
	$sql = $sqlBase.' ORDER BY PurchOrdNum DESC LIMIT 1;';
	//$objRow = $this->objDB->DataSet($sql);
	$objRow = $this->objDB->RstkReqs()->DataSet($sql);
	$objRow->NextRow();
	$arOut['by purch ord'] = $objRow->RowCopy();

	$sql = $sqlBase.' ORDER BY WhenOrdered DESC LIMIT 1;';
	//$objRow = $this->objDB->DataSet($sql);
	$objRow = $this->objDB->RstkReqs()->DataSet($sql);
	$objRow->NextRow();
	$arOut['by ord date'] = $objRow->RowCopy();

	return $arOut;
    }
    /*----
      ACTION: Checks each item in the list to see if it corresponds to a given item for the current supplier
      INPUT: Array of supplier catalog numbers
      OUTPUT: Array in this format:
	array[cat#] = item object (if found) or NULL (if not found)
    */
/*
    public function FindItems(array $iList) {
	$objTblItems = $this->objDB->Items();
	foreach ($iList as $catnum) {
	    $strCat = rtrim($catnum,';#!');	// remove comments
	    $strCat = trim($strCat);		// remove leading & trailing whitespace
	    if (!empty($strCat)) {
		$sqlFind = 'Supp_CatNum="'.$strCat.'"';
		$objItem = $objTblItems->GetData($sqlFind);
		if (is_null($objItem)) {
		    $arOut[$strCat] = NULL;
		} else {
		    $arOut[$strCat] = $objItem->RowCopy();
		}
	    }
	}
	return $arOut;
    }
*/
    // DEPRECATED - use GetItem_bySCatNum()
    public function FindItem($iCatNum) { return $this->GetItem_bySCatNum($iCatNum); }
}
