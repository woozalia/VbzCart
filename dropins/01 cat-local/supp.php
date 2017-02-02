<?php
/*
  PART OF: VbzCart admin interface
  PURPOSE: classes for handling Suppliers
  HISTORY:
    2013-11-06 split off from SpecialVbzAdmin.main.php
    2017-01-18 vtTableAccess_Supplier_admin
*/
trait vtTableAccess_Supplier_admin {
    use vtTableAccess_Supplier;
    protected function SuppliersClass() {
	return 'vctAdminSuppliers';
    }
}
class vctAdminSuppliers extends vctSuppliers {
    use ftLinkableTable;
    use ftLoggableTable;
    use vtLoggableAdminObject;

    // ++ SETUP ++ //

    protected function SingularName() {
	return 'VC_Supplier';
    }
    public function GetActionKey() {
	return KS_ACTION_CATALOG_SUPPLIER;
    }

    // -- SETUP -- //
    // ++ CALLBACKS ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- CALLBACKS -- //
    // ++ TABLES ++ //
    
    // REQUIRED BY ftLoggableTable
    /*
    protected function SystemEventsClass() {
	return KS_CLASS_EVENT_LOG;
    }  */

    // -- TABLES -- //
    
    protected function EventTable() {
	return $this->GetConnection()->MakeTableWrapper($this->SystemEventsClass());
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    protected function GetAdminRecords() {
	$sqlFilt = NULL;	// get all records
	$sqlSort = 'Name';
	$rs = $this->SelectRecords($sqlFilt,$sqlSort);
	return $rs;
    }
    public function DropDown_Records() {
	return $this->SelectRecords(NULL,'Name');	// sort by Name
    }
    public function GetData_forDropDown() {
	throw new exception('call ActiveRecords() or AllRecords() instead.');
    /*
	$sqlName = $this->NameSQL();
	$sql = "SELECT ID, CONCAT_WS(' ',CatKey,Name) AS Text, Name FROM $sqlName WHERE isActive ORDER BY Name";
	$rs = $this->DataSQL($sql);
	return $rs; */
    }
    public function SupplierRecords() {
	throw new exception('SupplierRecords() has been removed; call ActiveRecords() or AllRecords().');
    }
    // RETURNS: Recordset of all active Suppliers. For ALL Suppliers, call AllRecords().
    public function ActiveRecords() {
	return $this->SelectRecords('isActive');
    }
    
    // -- RECORDS -- //
    // ++ ADMIN WEB UI ++ //

    // ++widget++ //
    
    /*----
      INPUT:
	$arSupps: array of Supplier-related data
	  [$key]['vals'] = SupplierRecord->Values()
	  [$key]['text'] = text to show after $key
	  $key = text to use for Supplier's self-link
	$oLinker: calling object's LinkBuilder() object
	$idSuppCurr: ID of Supplier currently selected (NULL = show all)
      HISTORY:
	2016-01-10 Adapted from VCM_RstksNeeded::RenderSupplierMenu()
    */
    public function RenderLineMenu(array $arSupps,fcLinkBuilder $oLinker,$idSuppCurr,$sSep=':') {
	$htLink = $oLinker->LinkHTML('*all*','show items for all suppliers');
	$htCtrl = "[$htLink]";

	$out = clsHTML::FlagToFormat($htCtrl,empty($idSuppCurr));
	$rcSupp = $this->SpawnItem();
	foreach ($arSupps as $key => $arSupp) {	// key = supplier catkey
	    $rcSupp->Values($arSupp['vals']);
	    $idSupp = $rcSupp->GetKeyValue();
	    $sPopup = 'show only '.$rcSupp->NameString().' items';
	    $htLink = $oLinker->LinkHTML($key,$sPopup,array('supp'=>$idSupp));
	    $sText = $arSupp['text'];
	    $htCtrl = "[$htLink$sSep$sText]";

	    $out .= ' '.clsHTML::FlagToFormat($htCtrl,($idSuppCurr == $idSupp));
	}
	return $out;
    }
    
    //--widget--//
    //++listing++//
    
    protected function AdminPage() {
	$out = NULL;
	
	$rs = $this->GetAdminRecords();	// get all applicable Supplier table records
	if ($rs->HasRows()) {
	    $out .= "\n<table class=listing>"
	      .$this->AdminRows_head()
	      ;
	    $isOdd = TRUE;
	    while ($rs->NextRow()) {
		$isActive = $rs->isActive();
		$cssClass = $isOdd?'odd':'even';
		if (!$isActive) {
		    $cssClass .= ' inactive';
		}

		$out .=
		  "\n  <tr class=\"$cssClass\">"
		  .$rs->AdminLine()
		  ."\n  </tr>";
		$isOdd = !$isOdd;
	    }
	    $out .= "\n</table>";
	} else {
	    $out .= 'No suppliers have been created yet.';
	}
	$out .= $this->AdminDependent();
	return $out;
    }
    protected function AdminRows_head() {
	return <<<__END__
  <tr>
    <th>ID</th>
    <th>A?</th>
    <th>Code</th>
    <th>Actions</th>
    <th>Name</th>
  </tr>
__END__;
    }
    
    //--listing--//
    //++dependent++//
    
    protected function AdminDependent() {
	return $this->EventListing();
    }
    
    //--dependent--//

    // -- ADMIN WEB UI -- //

}
class VC_Supplier extends vcrSupplier {
    use ftLinkableRecord;
    use ftLoggableRecord;
    use vtLoggableAdminObject;
    use vtrSupplierShop;
    
    // ++ TRAIT HELPERS ++ //
    
    public function SelfLink_name() {
	return $this->SelfLink($this->NameString());
    }
    
    // -- TRAIT HELPERS -- //
    // ++ CALLBACKS ++ //
    
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }
    // CALLBACK for dropdown Control
    public function ListItem_Text() {
	if ($this->HasValue('Text')) {
	    return $this->Value('Text');
	} else {
	    return $this->CatKey().' '.$this->NameString();
	}
    }
    // CALLBACK for dropdown Control
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }

    // -- CALLBACKS -- //
    // ++ CLASSES ++ //

    protected function ItemsClass() {
	return KS_ADMIN_CLASS_LC_ITEMS;
    }
    protected function DepartmentsClass() {
	return KS_CLASS_CATALOG_DEPARTMENTS;
    }
    protected function TopicsClass() {
	return KS_CLASS_CATALOG_TOPICS;
    }
    protected function SCGroupsClass() {
	return KS_CLASS_SUPPCAT_GROUPS;
    }
    protected function SCSourcesClass() {
	return KS_CLASS_SUPPCAT_SOURCES;
    }
    
    // -- CLASSES -- //
    // ++ TABLES ++ //
    
    // DepartmentTable() defined by parent class

    protected function TopicTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->TopicsClass(),$id);
    }
    protected function CatalogSupplierTable() {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_SUPPLIERS);
    }
    // TODO: fail gracefully when Supplier Catalogs dropin is not available
    protected function SupplierCatalogGroupTable($id=NULL) {
	return $this->Engine()->Make($this->SCGroupsClass(),$id);
    }
    protected function SCSourceTable($id=NULL) {
	return $this->Engine()->Make($this->SCSourcesClass(),$id);
    }
    protected function PriceFxTable($id=NULL) {
    	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_PRICES,$id);
    }
    protected function RestockRequestTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_ADMIN_CLASS_RESTOCK_REQUESTS,$id);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      RETURNS: record for Supplier's Topic
      HISTORY:
	2011-10-01 written -- replacing Departments with Topics
    */
    public function TopicObj() {
	throw new exception('TopicObj() is deprecated; call TopicRecord().');
    }
    protected function TopicRecord() {
	$id = $this->Value('ID_Topic');
	if (is_null($id)) {
	    return NULL;
	} else {
	    $row = $this->TopicTable($id);
	    return $row;
	}
    }

    // -- RECORDS -- //
    // ++ FIELD VALUES ++ //
    
    // NameString() is defined by parent
    
    /*----
      PUBLIC because Table's AdminPage() uses it to determine CSS style
    */
    public function IsActive() {
	return $this->GetFieldValue('isActive');
    }
    protected function PriceFxID() {
	return $this->GetFieldValue('ID_PriceFunc');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    /*----
      CALLED BY: vctAdminSuppliers::AdminPage()
      HISTORY:
	2010-11-04 Created so AdminPage() can use HTML
	2011-02-16 Disabled until I figure out why it isn't redundant
	2014-03-22 re-enabled to replace code in table's AdminPage()
    */
    public function ShopLink($sShow=NULL) {
	return '<a href="'.KWP_CAT.strtolower($this->CatKey()).'">'.$sShow.'</a>';
    }
    /*----
      RETURNS: Supplier Catalog Management link for this Supplier
      HISTORY:
	2017-01-14 Noted that this will need updating.
	2017-01-17 Updated. Now spawns a blank SCSupplier record, then stuffs values into it and uses that to make the link.
    */
    protected function SCMLink($sShow,$sPopup) {
	$rcCS = $this->CatalogSupplierTable()->SpawnRecordset();
	$rcCS->SetFieldValues($this->GetFieldValues());
//	$this->Table()->ActionKey(KS_ACTION_SUPPCAT_SUPPLIER);	// masquerade as SCM Supplier
	return $rcCS->SelfLink($sShow,$sPopup);
    }
    // this is a bit of a kluge
    protected function PublicWikiLink($sShow) {
	$sName = $this->NameString();
	$wpWiki = str_replace(' ','_',$sName);
	$url = KURL_WIKI_PUBLIC.$wpWiki;
	return "<a href=\"$url\">$sShow</a>";
    }
    // this is a bit of a kluge
    protected function AdminWikiLink($sShow) {
	$sName = $this->NameString();
	$wpWiki = str_replace(' ','_',$sName);
	$url = KURL_WIKI_PRIVATE.$wpWiki;
	return "<a href=\"$url\">$sShow</a>";
    }
    /*----
      RETURNS: nicely-formatted link to Supplier's Topic
      HISTORY:
	2011-10-01 written -- replacing Departments with Topics
    */
    public function TopicLink($sNone='<i>n/a</i>') {
	$rc = $this->TopicRecord();
	if (is_object($rc)) {
	    return $rc->SelfLink_name();
	} else {
	    return $sNone;
	}
    }
    /*----
      RETURNS: TRUE iff this Supplier uses Catalog Management.
      NOTE: The logic behind this function may change later.
	Right now, we assume YES if it has a pricing function, and NO otherwise.
    */
    protected function HasCatalogs() {
	return !is_null($this->PriceFxID());
    }

    // -- FIELD CALCULATIONS -- //
    // ++ FIGURING ++ //

    /*-----
      ACTION: Finds the last restock request for the given supplier
      RETURNS: the last request by date and the last request sorted by (our) PO #
    */
    public function LastReq() {
	//$sqlBase = 'SELECT * FROM `rstk_req` WHERE ID_Supplier='.$this->ID;
	$sqlBase = 'WHERE ID_Supplier='.$this->ID;
	$sql = $sqlBase.' ORDER BY PurchOrdNum DESC LIMIT 1;';
	//$objRow = $this->objDB->DataSet($sql);
	$objRow = $this->Engine()->RstkReqs()->DataSet($sql);
	$objRow->NextRow();
	$arOut['by purch ord'] = $objRow->RowCopy();

	$sql = $sqlBase.' ORDER BY WhenOrdered DESC LIMIT 1;';
	//$objRow = $this->objDB->DataSet($sql);
	$objRow = $this->Engine()->RstkReqs()->DataSet($sql);
	$objRow->NextRow();
	$arOut['by ord date'] = $objRow->RowCopy();

	return $arOut;
    }
    // DEPRECATED - use GetItem_bySCatNum()
    public function FindItem($iCatNum) { return $this->GetItem_bySCatNum($iCatNum); }

    // -- FIGURING -- //
    // ++ ADMIN WEB UI ++ //

    //++multi++//
    
    public function AdminLine() {
	$sCatKey = $this->CatKey();
	$id = $this->GetKeyValue();
	$htShop = $this->ShopLink('shop');
	$htManage = $this->SelfLink('manage');
	$htWikiPub = $this->PublicWikiLink('public');
	$htWikiPvt = $this->AdminWikiLink('private');
	$wtActions = "<b>[$htManage]</b>[$htShop] info:[$htWikiPub][$htWikiPvt]";

	$wtName = $this->NameString();
	$ftActive = clsHTML::fromBool($this->isActive());

	$out = <<<__END__
    <td>$id</td>
    <td>$ftActive</td>
    <td>$sCatKey</td>
    <td>$wtActions</td>
    <td>$wtName</td>
__END__;
	return $out;
    }
    
    //--multi--//
    //++single++//

    protected function AdminPage() {
	$oApp = fcApp::Me();
	$oPage = $oApp->GetPageObject();
	$oInput = $oApp->GetKioskObject()->GetInputObject();

	$out = NULL;

	$sDo = $oInput->GetString('do');
	$doEdit = ($sDo == 'edit');
	$sShow = $oInput->GetString('show');	// subpage to show

	$doSave = fcHTTP::Request()->GetBool('btnSave');

	// save edits before showing events
	if ($doSave) {
	    $frm = $this->PageForm();
	    $frm->Save();
	    $ftSaveMsg = $frm->MessagesString();
	    $this->SelfRedirect(NULL,$ftSaveMsg);
	}

	$sCatKey = $this->CatKey();
	$sName = $this->NameString();
	$sTitle = $sName." ($sCatKey)";
	$oPage->SetPageTitle($sTitle);
	
	//$sHdr = 'Current Record (ID '.$this->GetKeyValue().')';
	// set up header action-links
	$oMenu = fcApp::Me()->GetHeaderMenu();
	//$oMenu = new fcHeaderMenu();	// for putting menu in a section header
	  // $sLinkKey,$sGroupKey=NULL,$sDispOff=NULL,$sDispOn=NULL,$sPopup=NULL
	  $oMenu->SetNode($ol = new fcMenuOptionLink('edit','do',NULL,'cancel','edit this supplier'));
	    $ol->SetBasePath($this->SelfURL());
	  $oMenu->SetNode($oGrp = new fcHeaderMenuGroup('Manage'));
	    $oGrp->SetNode($ol = new fcMenuOptionLink('dept','show','departments',NULL,$sName.' departments'));
	      $ol->SetBasePath($this->SelfURL());
	    $oGrp->SetNode($ol = new fcMenuOptionLink('rreq','show','restocks',NULL,$sName.' restock requests'));
	      $ol->SetBasePath($this->SelfURL());
	    $oGrp->SetNode($ol = new fcMenuOptionLink('events','show','events',NULL,$sName.' system events'));
	      $ol->SetBasePath($this->SelfURL());

	$doDeptAdd = $oInput->GetBool('add.dept');	// shouldn't this be a possible value of 'do'?
	
	// set up titlebar menu
	
	/* 2017-01-15 old menu system
	$arActs = array(
	  new clsActionLink_option(array(),
	    'edit',			// $iLinkKey
	    'do',			// $iGroupKey
	    NULL,			// $iDispOff
	    'cancel',			// $iDispOn
	    'edit this supplier'	// $iDescr
	    ),
	  new clsAction_section('Manage'),	// menu divider
	  new clsActionLink_option(array(),
	    'dept',
	    'show',
	    'departments',
	    NULL,
	    $strName.' departments'
	    ),
	  new clsActionLink_option(array(),
	    'rreq',
	    'show',
	    'restocks',
	    NULL,
	    $strName.' restock requests'
	  ),
	);
	$oPage->PageHeaderWidgets($arActs);
	$oSkin->SetPageTitle($sTitle);
	*/
	
	$frmEdit = $this->PageForm();
	if ($this->IsNew()) {
	    $frmEdit->ClearValues();
	} else {
	    $frmEdit->LoadRecord();
	}
	$oTplt = $this->PageTemplate();
	$arCtrls = $frmEdit->RenderControls($doEdit);
	if ($this->HasCatalogs()) {
	    $htSCLink = ' ['.$this->SCMLink('SCM','supplier catalog management').']';
	} else {
	    $htSCLink = NULL;
	}
	$arCtrls['!ID'] = $this->SelfLink().$htSCLink;	


	if ($doEdit) {
	    $out .= "\n<form method=post>";
	}
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();
	
	if ($doEdit) {
	    $out .= <<<__END__
<b>Edit notes</b>: <input type=text name="EvNotes" size=40><br>
<input type=submit name="btnSave" value="Save">
<input type=reset value="Reset">
</form>
__END__;
	}

	// 2017-01-17 These will need updating.
	$oMenu = new fcHeaderMenu();
	switch ($sShow) {
	  case 'dept':
	    /* 2017-01-17 old
	    $arActs = array(
	      new clsActionLink_option(array(),
		'add.dept',		// $iLinkKey
		'do',			// $iGroupKey
		'add',			// $iDispOff
		NULL,			// $iDispOn
		"add a department to $strName"	// $iDescr
	      )
	    );
	    $out .= $oPage->ActionHeader($sHdr,$arActs);
	    */
	    $sHdr = 'Departments';
	    $oMenu->SetNode($ol = new fcMenuOptionLink(
	      'add.dept',			// $sLinkKey
	      'do',				// $sGroupKey=NULL
	      'add',				// $sDispOff=NULL
	      'cancel',				// $sDispOn=NULL
	      "add a department to $sName"	// $sPopup=NULL
	      ));
	    $sContent = $this->DeptsListing();
	    break;
	  case 'rreq':
	    $sHdr = 'Restock Requests';
	    //$out .= $oPage->ActionHeader($sHdr);
	    $sContent = $this->RstkReqAdmin();
	    break;
	  case 'events':
	    $out .= $this->EventListing();	// does its own header
	    $sHdr = NULL;
	    $oMenu = NULL;
	    $sContent = NULL;
	    break;
	  default:
	    $sHdr = NULL;
	    $oMenu = NULL;
	    $sContent = NULL;
	}
	$oHdr = new fcSectionHeader($sHdr,$oMenu);
	$out .= 
	  $oHdr->Render()
	  .$sContent
	  ;
	return $out;
    }
    /*----
      HISTORY:
	2010-11-06 adapted from VbzStockBin for VbzAdminTitle
	2011-10-01 adapted from VbzAdminTitle for VbzAdminSupplier
    */
    private $frmPage;
    private function PageForm() {
	if (empty($this->frmPage)) {
	
	    $oForm = new fcForm_DB($this);

	      $oField = new fcFormField_Text($oForm,'Name');
		$oCtrl = new fcFormControl_HTML($oField,array('size'=>25));

	      $oField = new fcFormField_Num($oForm,'ID_Topic');
		$oField->ControlObject($oCtrl = new fcFormControl_HTML_DropDown($oField));
		$oCtrl->Records($this->TopicTable()->GetData_forDropDown());
		$oCtrl->AddChoice(NULL,'none (root)');

	      $oField = new fcFormField_Text($oForm,'CatKey');
		$oField->ControlObject()->TagAttributes(array('size'=>8));

	      $oField = new fcFormField_Num($oForm,'isActive');	// currently stored as BOOL (INT)
		$oField->ControlObject(new fcFormControl_HTML_CheckBox($oField));
		
	      $oField = new fcFormField_Num($oForm,'ID_PriceFunc');
		$oField->ControlObject($oCtrl = new fcFormControl_HTML_DropDown($oField));
		$oCtrl->Records($this->PriceFxTable()->AdminRecords());
		$oCtrl->AddChoice(NULL,'(not set)');

	      $oField = new fcFormField_Text($oForm,'Notes');
		$oField->ControlObject(new fcFormControl_HTML_TextArea($oField,array('rows'=>3,'cols'=>60)));
/*	    
	    $objForm->AddField(new clsField('Name'),		new clsCtrlHTML(array('size'=>25)));
	    $objForm->AddField(new clsFieldNum('ID_Topic'),	new clsCtrlHTML());
	    $objForm->AddField(new clsField('CatKey'),		new clsCtrlHTML(array('size'=>8)));
	    $objForm->AddField(new clsFieldBool('isActive'),	new clsCtrlHTML_CheckBox());
	    $objForm->AddField(new clsField('Notes'),	new clsCtrlHTML_TextArea(array('height'=>3,'width'=>50)));
*/
	    $this->frmPage = $oForm;
	}
	return $this->frmPage;
    }
    private $tpPage;
    protected function PageTemplate() {
	if (empty($this->tpPage)) {
	    $sTplt = <<<__END__
<table>
  <tr>	<td align=right><b>ID</b>:</td>		<td>[[!ID]]</td>	</tr>
  <tr>	<td align=right><b>Name</b>:</td>	<td>[[Name]]</td>	</tr>
  <tr>	<td align=right><b>CatKey</b>:</td>	<td>[[CatKey]]</td>	</tr>
  <tr>	<td align=right><b>Price Code</b>:</td>	<td>[[ID_PriceFunc]]</td></tr>
  <tr>	<td align=right><b>Topic</b>:</td>	<td>[[ID_Topic]]</td>	</tr>
  <tr>	<td align=right><b>Active</b>:</td>	<td>[[isActive]]</td>	</tr>
</table>
__END__;
	    $this->tpPage = new fcTemplate_array('[[',']]',$sTplt);
	}
	return $this->tpPage;
    }
    
    //--single--//
    //++dependent++//
    
    protected function DeptsListing() {
	$out = $this->DepartmentTable()->Listing_forSupp($this);
	return $out;
    }
    protected function RstkReqAdmin() {
	$tbl = $this->RestockRequestTable();
	$rs = $tbl->SelectRecords('ID_Supplier='.$this->GetKeyValue(),'IFNULL(WhenOrdered,WhenCreated) DESC');
	$out = $rs->AdminRows();
	return $out;
    }
    
    //--dependent--//
    //++forms++//
    
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
		    $htOCat = $strPfx
		      .'-<input name="'
		      .$cnOCat
		      .'" size=15 value="'
		      .fcString::EncodeForHTML($strOCat)
		      .'">';
		} else {
		    $data['obj'] = $objItem;
		    $arOkFnd[$idx] = $data;
		    $strOCat = $objItem->Value('CatNum');
		    $idItem = $objItem->GetKeyValue();

		    $htOCat = $strOCat
		      .'<input type=hidden name="'
		      .$cnOCat
		      .'" value="'
		      .fcString::EncodeForHTML($strOCat)
		      .'">';
		    $htItem = $objItem->SelfLink().'<input type=hidden name="id['.$idx.']" value="'.$idItem.'">';
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

		$htSCat = $strSCat
		  ."<input type=hidden name='scat[$idx]' value='"
		  .fcString::EncodeForHTML($strSCat)
		  ."'>";
		if ($canUpdSCat) {
		    if (empty($strSCatRec)) {
			$strAct = 'save this';
		    } else {
			$strAct = 'replace <b>'.$strSCatRec.'</b>';
		    }
		    $htSCat .= '<br><small><input type=checkbox name="do-upd-scat['.$idx.']">'.$strAct.'</small>';
		}
		$htName = $strInvTitle
		  ."<input type=hidden name='name[$idx]' value='"
		  .fcString::EncodeForHTML($strInvTitle)
		  ."'>";
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
	$arRtn = clsArray::Pivot($arOut);
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
      HISTORY:
	2016-01-26 Updated a few function calls, but did not test; may need further revision.
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

		$strSCat = clsArray::Nz($data,'scat');

		$data['@state'] = NULL;
		$data['@obj'] = NULL;
		if (empty($idItem)) {
		    if ($gotOCat) {
			// look up item using our catalog #
			$rcItem = $this->ItemTable()->Get_byCatNum($strOCatFull);
		    } else {
			// look up item using supplier catalog #
			$rcItem = $this->GetItem_bySCatNum($strSCat);
		    }
		    if (is_null($rcItem)) {
			if ($gotOCat) {
			    $data['@state'] = 'add';
			    $cntAdd++;
			}
		    }
		} else {
		    $rcItem = $this->ItemTable($idItem);
		}
		if (is_object($rcItem)) {
		    $data['@obj'] = $rcItem;
		    $data['@state'] = 'use';
		    $cntUse++;

		    // compare entered values with recorded values
		    // -- supplier catalog #
		    $strSCatEnt = $strSCat;			// entered
		    $strSCatRec = $rcItem->Supplier_CatNum();	// recorded
		    if ($strSCatEnt != $strSCatRec) {
			$cntUpd++;
			$data['@can-upd-scat'] = TRUE;
		    } else {
			$data['@can-upd-scat'] = FALSE;
		    }
		    // -- title
		    $strDescrEnt = clsArray::Nz($data['name']);		// entered
		    $strDescrRec = $rcItem->Description();		// recorded
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

	$tblItems = $this->ItemTable();

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
      ACTION: Renders drop-down box of active departments for this supplier
      RETURNS: HTML code
      CALLED BY: Title entry form in Supplier Catalog Sources
    */
    public function Depts_DropDown($iName=NULL,$iDefault=NULL,$iNone=NULL) {
	$rs = $this->DepartmentTable()->Data_forSupp($this->GetKeyValue(),'isActive');
	$out = $rs->DropDown($iName,$iDefault,$iNone);
	return $out;
    }

    // -- ADMIN WEB UI -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Add a list of titles to this department
      INPUT:
	iTitles: array
	  iTitles[catkey] = name
	iEvent: array to be passed to event log
      HISTORY:
	2012-02-02 adapted from VbzAdminDept to VbzAdminSupplier
	2016-01-26 This needs updates before it will work again. (Who calls it?)
	2016-02-05 This is used by the Source forms which allow bulk entry of Titles.
    */
    public function AddTitles(array $iTitles,array $iEvent=NULL) {
	$cntTitles = count($iTitles);
	if ($cntTitles > 0) {
	    $strDescr = 'adding '.$cntTitles.' title'.fcString::Pluralize($cntTitles);

	    $iEvent['descr'] = fcString::StrCat(clsArray::Nz($iEvent,'descr'),$strDescr,' ');
	    $iEvent['where'] = clsArray::Nz($iEvent,'where',__METHOD__);
	    $iEvent['code'] = 'ADM';	// add multiple
	    $rcEv = $this->CreateEvent($iEvent);

	    $id = $this->GetKeyValue();
	    $cntAdded = 0;
	    $cntError = 0;
	    $txtAdded = '';
	    $txtError = '';
	    $tblTitles = $this->TitleTable();
	    $db = $this->Engine();
	    foreach ($iTitles as $catnum => $name) {
		$arIns = array(
		  'Name'	=> $db->SanitizeAndQuote($name),
		  'CatKey'	=> $db->SanitizeAndQuote($catnum),
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
		    $txtError .= '['.$catnum.' Error: '.$this->Engine()->getError().']';
		}
	    }
	    if ($cntError > 0) {
		$txtDescr = $cntError.' error'.fcString::Pluralize($cntError).': '.$txtError;
		$txtDescr .= ' and ';
	    } else {
		$txtDescr = 'Success:';
	    }
	    $txtDescr .= $cntAdded.' title'.fcString::Pluralize($cntAdded).' added '.$txtAdded;
	    $arEv = array(
	      'descrfin' => $txtDescr,
	      'error' => ($cntError > 0)
	      );
	    $rcEv->Finish($arEv);
	}
    }
    
    // -- ADMIN WEB UI -- //

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

    // -- ACTIONS -- //

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
}
