<?php
/*
  HISTORY:
    2016-01-31 started writing this as a more interactive replacement for maint/build-cat.php
*/

class vcCatalogBuilder extends vcAdminTable {

    // ++ SETUP ++ //
    
    public function __construct($db) {
	parent::__construct($db);
	  $this->ClassSng('vcrCatalogBuilder_row');
    }
    
    // -- SETUP -- //
    // ++ CALLBACKS ++ //

    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }
    
    // -- CALLBACKS -- //
    // ++ SQL CALCULATIONS ++ //
    
    /*----
      RETURNS: SQL
      DATA: catalog items (including discontinueds)
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
    lcio.CatKey AS OptionCatKey,
    lcio.Descr AS OptionName,
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
    g.Descr AS GroupDescr
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
        AND (IFNULL(s.DateAvail, NOW()) <= NOW())
        AND (IFNULL(t.WhenDiscont, NOW()) >= NOW())
        AND (s.ID IS NOT NULL)
        AND (g.isActive)
ORDER BY ID_LCTitle , g.Sort , i.Sort , lcio.Sort
__END__;
    
    }

    // -- SQL CALCULATIONS -- //
    // ++ RECORDS ++ //
    
    protected function CatalogItems_Recordset() {
	$rs = $this->DataSQL($this->CatalogItems_SQL());
	return $rs;
    }

    // -- RECORDS -- //
    // ++ WEB UI ++ //
    
    protected function AdminPage() {
	$rs = $this->CatalogItems_Recordset();
	if ($rs->HasRows()) {
	    $out = $rs->AdminRows();
	} else {
	    $out = 'Nothing to build';
	}
	return $out;
    }
    
    // -- WEB UI -- //
}

class vcrCatalogBuilder_row extends vcAdminRecordset {

    // ++ TABLES ++ //

    protected function LCItemTable($id=NULL) {
	return $this->Engine()->Make(KS_ADMIN_CLASS_LC_ITEMS,$id);
    }
    protected function LCTitleTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_CATALOG_TITLES,$id);
    }
    protected function SCTitleTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_SUPPCAT_TITLES,$id);
    }
    protected function SCGroupTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_SUPPCAT_GROUPS,$id);
    }
    protected function SCSourceTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_SUPPCAT_SOURCES,$id);
    }
    protected function ShipCostTable($id=NULL) {
	return $this->Engine()->Make(KS_ADMIN_CLASS_SHIP_COSTS,$id);
    }
    protected function LCSupplierTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_CATALOG_SUPPLIERS,$id);
    }
    protected function ItemTypeTable($id=NULL) {
	return $this->Engine()->Make(KS_ADMIN_CLASS_LC_ITEM_TYPES,$id);
    }
    protected function ItemOptionTable($id=NULL) {
	return $this->Engine()->Make(KS_ADMIN_CLASS_LC_ITEM_OPTIONS,$id);
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
    
    // -- RECORDS -- //
    // ++ FIELD VALUES ++ //
    
    protected function SCItemID() {
	return $this->Value('ID_SCItem');
    }
    protected function LCTitleID() {
	return $this->Value('ID_LCTitle');
    }
    protected function SCTitleID() {
	return $this->Value('ID_SCTitle');
    }
    protected function LCSupplierID() {
	return $this->Value('ID_Supplier');
    }
    protected function SCSourceID() {
	return $this->Value('ID_Source');
    }
    protected function SCGroupID() {
	return $this->Value('ID_Group');
    }
    protected function SCGroupName() {
	return $this->Value('GroupName');
    }
    protected function SCGroupCode() {
	return $this->Value('GroupCode');
    }
    protected function SCGroupDescr() {
	return $this->Value('GroupDescr');
    }
    protected function LCOptionCatKey() {
	return $this->Value('OptionCatKey');
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
	$id = $this->SCItemID();
	$arLink = array(
	  'page'	=> KS_ACTION_SUPPCAT_ITEM,
	  'id'		=> $id
	  );
	$url = $this->Engine()->App()->Page()->SelfURL($arLink);
	$out = "<a href='$url'>$id</a>";
	return $out;
    }
    protected function SCGroupLink() {
	$id = $this->SCGroupID();
	$arLink = array(
	  'page'	=> KS_ACTION_SUPPCAT_GROUP,
	  'id'		=> $id
	  );
	$url = $this->Engine()->App()->Page()->SelfURL($arLink);
	$sText = $this->SCGroupText();
	$htDesc = fcString::EncodeForHTML($this->SCGroupDescr());
	$out = "<a href='$url' title='$htDesc'>$sText</a>";
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
	clsArray::NzSum($this->arCat[$sCatNum],'cnt');	// increment count
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
    protected function ItemArray(clsItem $rs=NULL) {
	if (!is_null($rs)) {
	    while ($rs->NextRow()) {
		$sCatNum = $rs->CatNum();
		// FUTURE: we might want to check for duplicate CatNums here)
		// we probably don't need more than the ID, but for now...
		$this->arItems[$sCatNum] = $rs->Values();
	    }
	}
	return $this->arItems;
    }
    
    // -- ARRAY CALCULATIONS -- //
    // ++ WEB UI ++ //
  
    /*----
      ASSUMES: There is at least one row of data.
      RULES: see http://htyp.org/VbzCart/pieces/catalog/building/2016/rules
    */
    public function AdminRows(array $arFields = NULL, array $arOptions = NULL) {
	if ($this->HasRows()) {
	    $out = "\n<form method=post>"
	      .self::LineHeader()
	      ;
	    $idSuppLast = NULL;
	    $idSourceLast = NULL;
	    $idTitleLast = NULL;
	    $this->CatNums_clear();
	    while ($this->NextRow()) {
		$idTitle = $this->LCTitleID();
		if ($idTitle != $idTitleLast) {
		    $idTitleLast = $idTitle;
		    $idGroupLast = NULL;
		    
		    // if the Title has changed, the Source might have changed too:
		    $idSource = $this->SCSourceID();
		    if ($idSource != $idSourceLast) {
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
	    $out .= "\n</table>"
	      ."\n<center><input type=submit name=btnChange value='Make Changes'></center>"
	      ."\n</form>"
	      ;
	} else {
	    $out = 'No proposed updates or additions.';
	}
	return $out;
    }
    
    protected function AdminRow() {
	$frm = $this->RecordForm();
	
	// I hope this is sufficient to uniquely identify each proposed change:
	$sBuildKey = $this->LCTitleID().'.'.$this->SCItemID();
	
	// preliminary calculations
	$sCatNum = $this->ExpectedCatNum();
	$this->CatNums_enter($sCatNum);
	$sCatNumMsg = $this->CatNums_status($sCatNum);
	$ctCatNum = $sCatNum
	  .(is_null($sCatNumMsg)
	    ? NULL
	    : " <span class=error>$sCatNumMsg</span>"
	    )
	    ;
	$arItems = $this->ItemArray();
	$ok = TRUE;
	if (clsArray::Exists($arItems,$sCatNum)) {
	    $rcItem = $this->LCItemTable()->SpawnItem();
	    $rcItem->Values($arItems[$sCatNum]);
	    $sAction = 'update '.$rcItem->SelfLink();
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
	    }
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
	    $htChk = '<input type=checkbox title="approve" name="sci['.$sBuildKey.']">';
	} else {
	    $htChk = NULL;	// can't operate yet
	}
	$arCtrls['checkbox'] = $htChk;
	
	// render the template
	$oTplt->VariableValues($arCtrls);
	$out .= $oTplt->RenderRecursive();

	return $out;
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
		$oCtrl->Records($this->ItemTypeTable()->ActiveRecords());
	      $oField = new fcFormField_Num($oForm,'ID_ItOpt');
		$oCtrl = new fcFormControl_HTML_DropDown($oField,array());
		$oCtrl->Records($this->ItemOptionTable()->ActiveRecords());
	      
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