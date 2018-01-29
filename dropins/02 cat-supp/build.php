<?php
/*
  HISTORY:
    2016-01-31 started writing this as a more interactive replacement for maint/build-cat.php
    2017-05-29 I was going to make vctCatalogBuilder a fcTable_keyed_single because that's what it seems to be,
      and the Form object needs GetKeyName(), but fcTable_keyed_single needs a table name, and that's not how
      this works -- so instead, I'm just adding the ftSingleKeyedTable trait.
*/
define('KSF_BTN_SELECT_ALL','btnSelectAll');
define('KSF_BTN_INVERT_SELECTION','btnInvert');

class vctCatalogBuilder extends fcTable_wSource_wRecords implements fiEventAware, fiLinkableTable {
//class vctCatalogBuilder extends fcTable_keyed_single implements fiEventAware, fiLinkableTable {
    use ftSingleKeyedTable;
    use ftLinkableTable;
    use ftExecutableTwig;

    // ++ SETUP ++ //
    
    // CEMENT
    public function GetKeyName() {
	return 'ID_SCItem';
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrCatalogBuilder_row';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_SUPPCAT_BUILD;
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //
  
    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	$oPage = fcApp::Me()->GetPageObject();
	$oPage->SetPageTitle('Catalog Builder');
	//$oPage->SetBrowserTitle('Suppliers (browser)');
	//$oPage->SetContentTitle('Suppliers (content)');
    }
    public function Render() {
	return $this->AdminPage();
    }
    /*
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }*/
    
    // -- EVENTS -- //
    // ++ TABLES ++ //

    protected function LCItemTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_ADMIN_CLASS_LC_ITEMS,$id);
    }

    // -- TABLES -- //
    // ++ SQL CALCULATIONS ++ //
    
    /*----
      RETURNS: SQL
      DATA: catalog items (including discontinueds)
      HISTORY:
	2017-06-11 Added "AND (i.isActive)", because SC Item needs to be active too, right?
	2017-06-21
	  * Added "AND (IFNULL(s.DateExpires, NOW()) => NOW())" - don't include expired sources
	  * Changed WhenAvail to WhenActive
	  * Including DateActive and DateExpires in output fields
	  * I *think* this means that if the same Item is activated by multiple Sources, it will appear once for each Source.
	    This offers kind of a crude solution to the problem of dealing with multiple active Sources for the same Item,
	    in that we can manually select Items from only the most recent Source in order to ensure that the final Item record
	    refers to the most recent update.
	2017-07-01 Adding more fields as needed to create new fcItem records. Existing fields were just for display.
    */
    protected function CatalogItems_SQL() {
    
	return <<<__END__
SELECT 
    i.ID AS ID_SCItem,
    i.ID_ItTyp,
    i.ID_ItOpt,
    i.Descr AS ItemDescr,
    i.PriceBuy,
    i.PriceSell,
    i.PriceList,
    i.ID_ShipCost,
    lcio.CatKey AS OptionCatKey,
    lcio.Descr AS OptionName,
    lcio.Sort AS OptionSort,
    t.ID AS ID_SCTitle,
    t.ID_Title AS ID_LCTitle,
    t.ID_Group,
    t.ID_Source,
    t.Code AS TitleCode,
    t.Descr AS TitleDescr,
    t.Supp_CatNum,
    g.ID_Supplier,
    g.Name AS GroupName,
    g.Code AS GroupCode,
    g.Descr AS GroupDescr,
    g.Sort AS GroupSort,
    s.DateActive,
    s.LastUpdate,
    s.DateExpires,
    s.LastUpdate,
    s.isCloseOut
FROM
    ctg_titles AS t
        LEFT JOIN
    ctg_sources AS s ON t.ID_Source = s.ID
        LEFT JOIN
    ctg_groups AS g ON t.ID_Group = g.ID
        LEFT JOIN
    ctg_items AS i ON i.ID_Group = g.ID
        LEFT JOIN
    cat_ioptns AS lcio ON i.ID_ItOpt = lcio.ID
WHERE
    (s.ID_Supercede IS NULL)
        AND (IFNULL(s.DateActive, NOW()) <= NOW())
        AND (IFNULL(s.DateExpires, NOW()) >= NOW())
        AND (IFNULL(t.WhenDiscont, NOW()) >= NOW())
        AND (s.ID IS NOT NULL)
        AND (g.isActive)
        AND (i.isActive)
ORDER BY ID_LCTitle , g.Sort , i.Sort , lcio.Sort
__END__;
    
    }

    // -- SQL CALCULATIONS -- //
    // ++ RECORDS ++ //
    
    protected function CatalogItems_Recordset() {
	$rs = $this->FetchRecords($this->CatalogItems_SQL());
	return $rs;
    }

    // -- RECORDS -- //
    // ++ INPUT ++ //

    protected function MakeChanges() {
	//$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	$arSelected = $oFormIn->GetArray('chkSCI');
	$arData = $oFormIn->GetArray('datSCI');
	
	//echo 'SELECTED:'.fcArray::Render($arSelected);
	//echo 'DATA:'.fcArray::Render($arData);
	//die();
	$rc = $this->SpawnRecordset();
	$db = $this->GetConnection();
	$out = "\n<div class=content>";
	
	// STAGE 1/2: deactivate all items (isAvail = FALSE)
	
	$arChg = array(
	  'isAvail' => 'FALSE',
	  'isInPrint' => 'FALSE',
	  );
	$tItems = $this->LCItemTable();
	$tItems->Update($arChg,'isAvail = TRUE');
	$nChg = $db->CountOfAffectedRows();
	$sPlr = fcString::Pluralize($nChg);
	$out .= "\n$nChg available item$sPlr changed to unavailable.<br>";
	
	// STAGE 2/2: activate all submitted items
	
	$nNew = 0;
	$nOld = 0;
	foreach($arSelected as $key => $sOn) {
	    $sRow = $arData[$key];
	    $arRow = unserialize($sRow);
	    $rc->SetFieldValues($arRow);
	    $idItem = $arRow['!ID_Item'];
	    $arChg = array(
	      'PriceBuy'	=> $db->SanitizeValue($arRow['PriceBuy']),
	      'PriceSell'	=> $db->SanitizeValue($arRow['PriceSell']),
	      'PriceList'	=> $db->SanitizeValue($arRow['PriceList']),
	      'ID_ShipCost'	=> $db->SanitizeValue($arRow['ID_ShipCost']),
	      'Supp_CatNum'	=> $db->SanitizeValue($arRow['Supp_CatNum']),
	      'SC_DateActive'	=> $db->SanitizeValue($arRow['DateActive']),
	      'SC_LastUpdate'	=> $db->SanitizeValue($arRow['LastUpdate']),
	      'SC_DateExpires'	=> $db->SanitizeValue($arRow['DateExpires']),
	      'isAvail'		=> 'TRUE',
	      'isInPrint'	=> $arRow['isCloseOut']?'FALSE':'TRUE',
	      );
	    if ($idItem == KS_NEW_REC) {
		$nNew++;
		$sCatNum = $db->SanitizeString($arRow['!CatNum']);
		$out .= "\nCreating $sCatNum...";
		$arChg['CatNum']	= '"'.$sCatNum.'"';
		$arChg['ID_Supp']	= $arRow['ID_Supplier'];
		$arChg['ID_Title']	= $arRow['ID_LCTitle'];
		$arChg['ID_ItTyp']	= $arRow['ID_ItTyp'];
		$arChg['ID_ItOpt']	= $arRow['ID_ItOpt'];
		$arChg['ItOpt_Descr']	= $db->SanitizeValue($arRow['OptionName']);
		$arChg['ItOpt_Sort']	= $db->SanitizeValue($arRow['OptionSort']);
		$arChg['GrpCode']	= $db->SanitizeValue($arRow['GroupCode']);
		$arChg['GrpDescr']	= $db->SanitizeValue($arRow['GroupDescr']);
		$arChg['GrpSort']	= $db->SanitizeValue($arRow['GroupSort']);
		$arChg['WhenCreated']	= 'NOW()';
//		$htItem = '(debug)';
		$idItem = $tItems->Insert($arChg);
		if (empty($idItem)) {
		    echo '<b>SQL</b>: '.$tItems->sql.'<br>';
		    echo '<b>Error</b>: '.$db->ErrorString();
		    throw new exception('Could not create LC Item "'.$sCatNum.'".');
		}
		$rcItem = $this->LCItemTable($idItem);
		$htItem = $rcItem->SelfLink();
		$out .= " ID=$htItem<br>";
	    } else {
		$nOld++;
		$rcItem = $this->LCItemTable($idItem);
		$htItem = $rcItem->SelfLink();
		$out .= "\nUpdating $sCatNum (item #$htItem)<br>";
		$arChg['WhenUpdated'] = 'NOW()';
		$rc->SetKeyValue($idItem);
		$rc->PublicUpdate($arChg);
	    }
	    //echo "[<b>$key</b> input]:".fcArray::Render($arRow);
	    //echo "[<b>$key</b> SQL]:".fcArray::Render($arChg);
	}
	$nTot = $nOld+$nNew;
	$out .= $nNew.' new item'.fcString::Pluralize($nNew).' added and '
	  .$nOld.' existing item'.fcString::Pluralize($nOld).' activated.<br>'
	  .$nTot.' item'.fcString::Pluralize($nTot).' now available from suppliers.'
	  ."\n</div>"
	  ;

	return $out;	// list the changes made
    }

    // ++ WEB UI ++ //
    
    protected function AdminPage() {
	$oFormIn = fcHTTP::Request();
	if ($oFormIn->GetBool(KSF_BTN_MAKE_CHANGES)) {
	    // make the changes specified by form input
	    $out = $this->MakeChanges();
	} else {
	    $rs = $this->CatalogItems_Recordset();
	    if ($rs->HasRows()) {
		$out = $rs->AdminRows();
	    } else {
		$out = 'Nothing to build';
	    }
	}
	return $out;
    }
    
    // -- WEB UI -- //
}

define('KSF_BTN_MAKE_CHANGES','btnMakeChanges');

class vcrCatalogBuilder_row extends vcAdminRecordset {

    // ++ TABLES ++ //

    protected function LCItemTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_ADMIN_CLASS_LC_ITEMS,$id);
    }
    protected function LCTitleTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_CATALOG_TITLES,$id);
    }
    protected function SCTitleTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_TITLES,$id);
    }
    protected function SCItemTable() {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_ITEMS);
    }
    protected function SCGroupTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_GROUPS,$id);
    }
    protected function SCSourceTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_SUPPCAT_SOURCES,$id);
    }
    protected function ShipCostTable($id=NULL) {
	return $this->Engine()->Make(KS_ADMIN_CLASS_SHIP_COSTS,$id);
    }
    protected function LCSupplierTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_CLASS_CATALOG_SUPPLIERS,$id);
    }
    protected function ItemTypeTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_ADMIN_CLASS_LC_ITEM_TYPES,$id);
    }
    protected function ItemOptionTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper(KS_ADMIN_CLASS_LC_ITEM_OPTIONS,$id);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    protected function LCTitleRecord() {
	return $this->LCTitleTable($this->LCTitleID());
    }
    protected function SCTitleRecord() {
	return $this->SCTitleTable($this->SCTitleID());
    }
    protected function LCSupplierRecord() {
	return $this->LCSupplierTable($this->LCSupplierID());
    }
    protected function SCSourceRecord() {
	return $this->SCSourceTable($this->SCSourceID());
    }
    // RETURNS: Recordset object with just enough information to do a SelfLink()
    protected function SCGroupRecord_forLink() {
	$rc = $this->SCGroupTable()->SpawnRecordset();
	$rc->SetKeyValue($this->SCGroupID());
	return $rc;
    }
    // RETURNS: Recordset object with just enough information to do a SelfLink()
    protected function SCItemRecord_forLink() {
	$rc = $this->SCItemTable()->SpawnRecordset();
	$rc->SetKeyValue($this->SCItemID());
	return $rc;
    }
    
    // -- RECORDS -- //
    // ++ FIELD VALUES ++ //
    
    protected function SCItemID() {
	return $this->GetFieldValue('ID_SCItem');
    }
    protected function LCTitleID() {
	return $this->GetFieldValue('ID_LCTitle');
    }
    protected function SCTitleID() {
	return $this->GetFieldValue('ID_SCTitle');
    }
    protected function LCSupplierID() {
	return $this->GetFieldValue('ID_Supplier');
    }
    protected function SCSourceID() {
	return $this->GetFieldValue('ID_Source');
    }
    protected function SCGroupID() {
	return $this->GetFieldValue('ID_Group');
    }
    protected function SCGroupName() {
	return $this->GetFieldValue('GroupName');
    }
    protected function SCGroupCode() {
	return $this->GetFieldValue('GroupCode');
    }
    protected function SCGroupDescr() {
	return $this->GetFieldValue('GroupDescr');
    }
    protected function LCOptionCatKey() {
	return $this->GetFieldValue('OptionCatKey');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD LOOKUPS ++ //
    
    protected function LCTitleCatNum() {
	return $this->LCTitleRecord()->CatNum();
    }
    
    // -- FIELD LOOKUPS -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function HasLCTitle() {
	return !is_null($this->LCTitleID());
    }
    protected function SCItemLink() {
	$rc = $this->SCItemRecord_forLink();
	$out = $rc->SelfLink();
    /*
	$id = $this->SCItemID();
	$arLink = array(
	  'page'	=> KS_ACTION_SUPPCAT_ITEM,
	  'id'		=> $id
	  );
	$url = $this->Engine()->App()->Page()->SelfURL($arLink);
	$out = "<a href='$url'>$id</a>";
    */
	return $out;
    }
    protected function SCGroupLink() {
	$sText = $this->SCGroupText();
	$htAbout = fcString::EncodeForHTML($this->SCGroupDescr());

	$rc = $this->SCGroupRecord_forLink();
	$out = $rc->SelfLink($sText,$htAbout);
    /*
	$id = $this->SCGroupID();
	$arLink = array(
	  'page'	=> KS_ACTION_SUPPCAT_GROUP,
	  'id'		=> $id
	  );
	$url = $this->Engine()->App()->Page()->SelfURL($arLink);
	$sText = $this->SCGroupText();
	$htDesc = fcString::EncodeForHTML($this->SCGroupDescr());
	$out = "<a href='$url' title='$htDesc'>$sText</a>";
	*/
	return $out;
    }
    protected function SCGroupHasCode() {
	return !is_null($this->SCGroupCode());
    }
    protected function SCGroupText() {
	$sName = $this->SCGroupName();
	$sCode = $this->SCGroupCode();
	$out = $this->SCGroupHasCode()?"<b>$sCode</b> $sName":$sName;
	return $out;
    }
    protected function ExpectedCatNum() {
	return fcString::ConcatArray('-',
	  array(
	    $this->LCTitleCatNum(),
	    $this->LCOptionCatKey()
	    )
	  );
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ ARRAY CALCULATIONS ++ //
    
    private $arCat;
    protected function CatNums_clear() {
	$this->arCat = NULL;
    }
    protected function CatNums_enter($sCatNum) {
	fcArray::NzSum($this->arCat[$sCatNum],'cnt');	// increment count
    }
    // ASSUMES: sCatNum has been entered at least once
    protected function CatNums_status($sCatNum) {
	$nFnd = $this->arCat[$sCatNum]['cnt'];
	$nDup = $nFnd - 1;
	$out = ($nDup > 0)
	  ?($nDup.' duplicate'.fcString::Pluralize($nDup))
	  :NULL
	  ;
	return $out;
    }
    private $arItems;
    protected function ItemArray(vcrItem $rs=NULL) {
	if (!is_null($rs)) {
	    while ($rs->NextRow()) {
		$sCatNum = $rs->CatNum();
		// FUTURE: we might want to check for duplicate CatNums here)
		// we probably don't need more than the ID, but for now...
		$this->arItems[$sCatNum] = $rs->GetFieldValues();
	    }
	}
	return $this->arItems;
    }
    
    // -- ARRAY CALCULATIONS -- //
    // ++ INPUT ++ //
    
    protected function GetOption_SelectAll() {
	return fcHTTP::Request()->GetBool(KSF_BTN_SELECT_ALL);
    }
    protected function GetOption_InvertSelection() {
	return fcHTTP::Request()->GetBool(KSF_BTN_INVERT_SELECTION);
    }
    private $arChk=NULL;
    protected function GetInput_IsChecked($sIndex) {
	if (is_null($this->arChk)) {
	    $this->arChk = fcHTTP::Request()->GetArray('chkSCI');
	}
	return array_key_exists($sIndex,$this->arChk);
    }
    
    // -- INPUT -- //
    // ++ WEB UI ++ //
  
    /*----
      ASSUMES: There is at least one row of data.
      RULES: see http://htyp.org/VbzCart/pieces/catalog/building/2017 for data logic
    */
    public function AdminRows(array $arFields = NULL, array $arOptions = NULL) {
	if ($this->HasRows()) {

	    $out = "\n<form method=post>"
	      ."\n<table class=listing>"
	      .self::LineHeader()
	      ;
	    $idSuppLast = NULL;
	    $idSourceLast = NULL;
	    $idTitleLast = NULL;
	    $this->CatNums_clear();
	    while ($this->NextRow()) {
		$idTitle = $this->LCTitleID();
		if ($idTitle != $idTitleLast) {
		    // CHANGE OF TITLE
		
		    $idTitleLast = $idTitle;
		    $idGroupLast = NULL;
		    
		    // if the Title has changed, the Source might have changed too:
		    $idSource = $this->SCSourceID();
		    if ($idSource != $idSourceLast) {
			// CHANGE OF SOURCE
			$idSourceLast = $idSource;
			
			// if the Source has changed, the Supplier might have changed too:
			$idSupp = $this->LCSupplierID();
			if ($idSupp != $idSuppLast) {
			    $idSuppLast = $idSupp;
			    $rcSupp = $this->LCSupplierRecord();
			    $sHdr = $rcSupp->SelfLink_name();
			    $out .= "\n  <tr class=table-section-header><td colspan=5>$sHdr</td></tr>";
			} // - Supplier
			
			$rcSource = $this->SCSourceRecord();
			$sHdr = $rcSource->SelfLink_name();
			$out .= <<<__END__

  <tr class=table-section-header>
    <td class=table-cell-indent></td>
    <td colspan=5>$sHdr</td>
  </tr>
__END__;
		    } // - Source
		    
		    // show header for new Title:
		    
		    // - Local Catalog info
		    $rc = $this->LCTitleRecord();
		    $sLC = 
		      $rc->SelfLink($rc->CatNum())
		      .' '.$rc->NameString()
		      ;
		    $ri = $rc->ItemRecords();
		    $qi = $ri->RowCount();
		    $sItemStatus = $qi.' item'.fcString::Pluralize($qi);
		    $hasItems = ($qi>0);
		    $htItemStatus = $hasItems?$sItemStatus:"<i>($sItemStatus)</i>";
		    $this->ItemArray($ri);	// convert recordset to CatNum-keyed array
		      
		    // - Supplier Catalog info
		    $rc = $this->SCTitleRecord();
		    $sSC = $rc->SelfLink_name();
		      
		    $out .= <<<__END__

  <tr class=table-section-header>
    <td colspan=2 align=right class=table-cell-indent>title:</td>
    <td colspan=4>$sLC</td>
    <td colspan=2>$sSC</td>
    <td>$htItemStatus</td>
  </tr>
__END__;
		
		} // - Title
		
		$idGroup = $this->SCGroupID();
		if ($idGroupLast != $idGroup) {
		    $idGroupLast = $idGroup;
		    
		    $sHdr = $this->SCGroupLink();
		    $out .= <<<__END__

  <tr class=table-section-header>
    <td colspan=3 align=right class=table-cell-indent>group:</td>
    <td colspan=4>$sHdr</td>
  </tr>
__END__;
		    }
		
		$out .= $this->AdminRow();
	    }
	    $out .= 
	      "\n<tr><td colspan=4>"
	      ."\n<input type=submit name='".KSF_BTN_SELECT_ALL."' value='Select All'>"
	      ."\n<input type=submit name='".KSF_BTN_INVERT_SELECTION."' value='Invert Selection'>"
	      ."\n</td><td colspan=5 align=right>"
	      ."\n<input type=submit name='".KSF_BTN_MAKE_CHANGES."' value='Update Catalog'>"
	      ."\n</td></tr>"
	      ."\n</table>"
	      ."\n</form>"
	      ;
	    $out .= "\n<form>";
	} else {
	    $out = '<div class=content>No proposed updates or additions.</div>';
	}
	return $out;
    }
    
    protected function AdminRow() {
	$frm = $this->RecordForm();
	
	// I hope this is sufficient to uniquely identify each proposed change:
	$sBuildKey = $this->LCTitleID().'.'.$this->SCItemID();
	
	// preliminary calculations
	$sCatNum = $this->ExpectedCatNum();
	//$this->SetFieldValue('!CatNum',$sCatNum);	// add figured cat# to posted data
	$this->CatNums_enter($sCatNum);			// keep track of catnums found
	$sCatNumMsg = $this->CatNums_status($sCatNum);	// show if this is a duplicate
	$ctCatNum = $sCatNum
	  .(is_null($sCatNumMsg)
	    ? NULL
	    : " <span class=error>$sCatNumMsg</span>"
	    )
	    ;
	$arItems = $this->ItemArray();
	$ok = TRUE;
	if (fcArray::Exists($arItems,$sCatNum)) {
	    $rcItem = $this->LCItemTable()->SpawnRecordset();
	    $rcItem->SetFieldValues($arItems[$sCatNum]);
	    $sAction = 'update '.$rcItem->SelfLink();
	    $this->SetFieldValue('!ID_Item',$rcItem->GetKeyValue());	// for batch updates
	} else {
	    $rsItems = $this->LCItemTable()->Get_byCatNum($sCatNum,TRUE);	// search entire LC for matches
	    if (is_null($rsItems)) {
		$sAction = 'new: add';
	    } else {
		$sAction = 'resolve:';
		$ok = FALSE;	// can't update this yet
		while ($rsItems->NextRow()) {
		    $sAction .= ' '.$rsItems->SelfLink();
		}
		// If this happens, the option to proceed is not shown. Resolve ambiguous items first.
	    }
	    $this->SetFieldValue('!ID_Item',KS_NEW_REC);
	    $this->SetFieldValue('!CatNum',$sCatNum);	// include calculated catalog #
	}
	$ctAction = $sAction;	// TODO: approval checkbox

	$out = NULL;
	
	$frm->LoadRecord();
	$oTplt = $this->LineTemplate();
	$arCtrls = $frm->RenderControls(FALSE);	// line-view is read-only
	$arCtrls['!cssClass'] = $this->AdminRow_CSSclass();
	$arCtrls['ID_SCItem'] = $this->SCItemLink();
	$arCtrls['!CatNum'] = $ctCatNum;
	$arCtrls['!action'] = $ctAction;

	if ($ok) {
	    $htName = "chkSCI[$sBuildKey]";
	    if ($this->GetOption_SelectAll()) {
		$doSel = TRUE;
	    } elseif ($this->GetOption_InvertSelection()) {
		//$bWas = fcHTTP::Request()->GetArray('chkSCI')->KeyExists($htName);
		$bWas = $this->GetInput_IsChecked($sBuildKey);
		//echo "VALUE of [$htName] IS [$bWas]<br>";
		$doSel = !$bWas;
	    } else {
		$doSel = FALSE;	// default state is leave unchecked
	    }
	    $htMark = $doSel?' checked':'';
	    $htChk = "<input type=checkbox title='approve' name='$htName'$htMark>";
	} else {
	    $htChk = NULL;	// can't operate yet
	}
	$sData = serialize($this->GetFieldValues());
	$htData = fcHTML::FormatString_forTag($sData);
	$htHid = "<input type=hidden name=datSCI[$sBuildKey] \nvalue='$htData'\n>";
	$arCtrls['checkbox'] = $htChk.$htHid;
	
	// render the template
	$oTplt->SetVariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	return $out;
    }
    // NOTE: Stolen from ftShowableRecord; it looks like this thing is too complicated to try shoehorning it into that.
    protected function AdminRow_CSSclass() {
	static $isOdd = FALSE;
	
	$isOdd = !$isOdd;
	return $isOdd?'odd':'even';
    }
    static protected function LineHeader() {
	return <<<__END__
<table class=listing>
  <tr>
    <th>ID</th>
    <th>Description</th>
    <th>Type</th>
    <th>Option</th>
    <th>$ Buy</th>
    <th>$ Sell</th>
    <th>$ List</th>
    <th><small>calculated<br>cat. #</small></th>
    <th>action</th>
  </tr>
__END__;
    }
    private $tpLine;
    protected function LineTemplate() {
	if (empty($this->tpLine)) {
	    $sTplt = <<<__END__
  <tr class={{!cssClass}}>

    <td>{{ID_SCItem}}</td>
    <td>{{ItemDescr}}</td>
    <td>{{ID_ItTyp}}</td>
    <td>{{ID_ItOpt}}</td>
    <td align=right>{{PriceBuy}}</td>
    <td align=right>{{PriceSell}}</td>
    <td align=right>{{PriceList}}</td>
    
    <td><b>{{!CatNum}}</b></td>
    <td align=right><b>{{!action}}{{checkbox}}</b></td>
    
  </tr>
__END__;
	    $this->tpLine = new fcTemplate_array('{{','}}',$sTplt);
	}
	return $this->tpLine;
    }
    private $oForm;
    private function RecordForm() {

	if (empty($this->oForm)) {
	
	    $oForm = new fcForm_DB($this);
	    
	      $oField = new fcFormField_Num($oForm,'ID_SCItem');
	      $oField = new fcFormField_Text($oForm,'ItemDescr');
	      $oField = new fcFormField_Num($oForm,'PriceBuy');
	      $oField = new fcFormField_Num($oForm,'PriceSell');
	      $oField = new fcFormField_Num($oForm,'PriceList');
	      /*
	      $oField = new fcFormField_Num($oForm,'ID_ShipCost');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->ShipCostTable()->ActiveRecords()); */
		
	      $oField = new fcFormField_Num($oForm,'ID_ItTyp');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->SetRecords($this->ItemTypeTable()->ActiveRecords());
	      $oField = new fcFormField_Num($oForm,'ID_ItOpt');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->SetRecords($this->ItemOptionTable()->ActiveRecords());
	      
	    /*
	      $oField = new fcFormField_Num($oForm,'ID_SCTitle');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->SCTitleTable()->ActiveRecords());
	      $oField = new fcFormField_Text($oForm,'TitleCode');
	      $oField = new fcFormField_Text($oForm,'TitleDescr');
	      $oField = new fcFormField_Text($oForm,'Supp_CatNum');

	      $oField = new fcFormField_Num($oForm,'ID_Group');	// SC Group
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->SCGroupTable()->ActiveRecords());
	      $oField = new fcFormField_Text($oForm,'GroupName');
	      $oField = new fcFormField_Text($oForm,'GroupCode');
	      $oField = new fcFormField_Text($oForm,'GroupDescr');
	      */

	    $this->oForm = $oForm;
	}
	return $this->oForm;
    }
    
    // -- WEB UI -- //

}