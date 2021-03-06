<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Departments -- shopping UI
  HISTORY:
    2016-01-24 split off from vbz-cat-dept.php
*/
//$t = new vcqtTitlesInfo_forDept();	// DEBUG

class vctDepts_shop extends vctDepts {
    use ftQueryableTable;

    // ++ OVERRIDES ++ //
    
    protected function SingularName() {
	return 'vcrDept_shop';
    }

    // -- OVERRIDES -- //
}

class vcrDept_shop extends vcrDept {
    use vtFrameworkAccess;
    use vtTableAccess_ItemType;
    use vtTableAccess_ImagesInfo;

    // ++ CLASSES ++ //
    
    protected function SuppliersClass() {	// override
	return 'vctSuppliers_shop';
    }
    protected function TitlesClass() {
	return 'vctShopTitles';
    }
    protected function ItemsClass() {
	return 'vctItems';
    }
    /*
    protected function ImagesInfoClass() {
	return 'vcqtImagesInfo';
    }*/
    protected function TitlesInfoClass() {
	return 'vcqtTitlesInfo_forDept_shop';
    }
    
    // -- CLASSES -- //
    // ++ TABLES ++ //
    
    protected function ItemTable($id=NULL) {
	throw new exception('2018-05-13 Is anything still using this?');
	return $this->Engine()->Make($this->ItemsClass(),$id);
    }
    /*
    protected function ImageInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper($this->ImagesInfoClass());
    }*/
    protected function TitleInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper($this->TitlesInfoClass());
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      PURPOSE: loads data needed to display catalog views for this department
      HISTORY
	2010-11-12 disabled automatic cache update
	2010-11-16 changed sorting field from cntInPrint to cntForSale
	2011-02-02 using _dept_ittyps now instead of qryItTypsDepts_ItTyps
	  Also added "AND (cntForSale)" to WHERE clause -- not listing titles with nothing to sell
	2013-11-18 rewriting
	2016-02-?? Now using TitleInfoTable() (later renamed TitleInfoQuery()) class to generate records.
    */
    protected function Data_forStore() {	// was GetDeptData()
	return $this->TitleInfoQuery()->GetRecords_forDeptExhibit($this->GetKeyValue());
    }
    protected function GetTitleRecord_byCatKey($sKey) {
	return $this->TitleTable()->GetRecord_byDepartment_andCatKey($this->GetKeyValue(),$sKey);
    }

    // -- RECORDS -- //
    // ++ FIELD CALCULATIONS ++ //
    
    public function ShopLink($sText=NULL) {
	$url = $this->ShopURL();
	if (is_null($sText)) {
	    $sText = $this->NameString();
	}
	return "<a href='$url'>$sText</a>";
    }
    // TODO: rename this.
    public function TitleStr() {
	$out = $this->NameStr().' department of '.$this->SupplierRecord()->NameStr();
	return;
    }
    /*----
      TODO:
	* figure out how to have a consistent naming system so that the same object can return both shopping and admin URLs
	* For now, use Shop* for shopping-related URLs/links, and deprecate this.
    */
    public function ShopURL() {
	$url = $this->SupplierRecord()->ShopURL();
	$sKey = $this->PageKey_toUse();
	if ($sKey) {
	    $url .= strtolower($sKey).'/';
	}
	return $url;
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ EXHIBIT API ++ //
    
    public function ExhibitSuperTitle() {
	$fpCat = vcGlobals::Me()->GetWebPath_forCatalogPages();
	return
	  "items <a href='$fpCat' title='supplier index page'>supplied</a> by "
	  .$this->SupplierRecord()->ShopLink()."'s "
	  .$this->ShopLink().' department:'
	  ;
    }
    public function ExhibitMainTitle() {
	return $this->NameString();
    }
    public function ExhibitContent() {
	return $this->RenderPage();
    }
    // PUBLIC so Supplier object can call it
    public function LookupExhibitRecord(array $arThap) {
	$sNext = array_pop($arThap);	// will be a Title
	$sFork = NULL;
	$anyMore = count($arThap) > 0;

	$rcTitle = $this->GetTitleRecord_byCatKey($sNext);
	if ($rcTitle->HasRows()) {
	    if ($anyMore) {
		$rcExh = $rcTitle->LookupExhibitRecord($arThap);
		//$sFork = 'requesting lookup from Title';
	    } else {
		// we've found the target exhibit
		$rcExh = $rcTitle;
		//$sFork = 'using Title as exhibit';
	    }
	} else {
	    $htSupp = $this->SelfLink($this->CatKey());
	    $sMsg = "Not sure what page you're looking for; supplier $htSupp has no department or title abbreviated \"$sNext\".";
	    $this->PageObject()->AddErrorMessage($sMsg);
	    $rcExh = NULL;
	    //$sFork = 'looking up Department';
	}

	return $rcExh;
    }
    
    // -- EXHIBIT API -- //
    // ++ UI PAGES ++ //

    /*----
      PURPOSE: Render page for current department
      ACTION:
	* Iterates through item types available for this department.
	* For each item type, prints header and then a list of titles.
      HISTORY:
	2010-11-?? Started using cached table _title_ittyps instead of qryTitles_ItTyps_Titles
	2010-11-16 $cntAvail is now cntForSale, not cntInPrint+qtyInStock
	2011-02-02 $qtyInStock now set to Row['qtyInStock'], not Row['qtyForSale'] which didn't make sense anyway
	2016-01-24 moved from clsDept to vcrDept_shop (formerly vcrDept_UI)
    */
    public function RenderPage() {
	$ht = NULL;
	$idDept = $this->GetKeyValue();
	
	$tInfo = $this->TitleInfoQuery();
	//$rs = $tInfo->SQL_forDeptPage_wTitleInfo($idDept,TRUE);
	$ht = $tInfo->RenderImages_forDept($idDept);

	return $ht;
	
	// 2018-02-17 old version
	
	$arData = $tInfo->StatsArray_forDept($idDept);
	$rsImg = $this->ImageInfoQuery()->GetRecords_forThumbs_forDept($this->GetKeyValue());
	$arData = $rsImg->Collate_byTitle($arData);
	$rcTitle = $tInfo->SpawnRecordset();
	$rcTitle->sql = $rsImg->sql;	// for debugging
	$arRes = $rcTitle->RenderTitleResults($arData);

	//$cntAct = count($arRes['act']['text']);
	$cntAct = count($arData['active']);
	$cntRet = count($arData['retired']);
	
	if (($cntAct + $cntRet) == 0) {
	    $ht = '<span class=main>This department appears to be disused. (How did you get here?)</span>';
	} else {
	
	    $ht = '';
	    $oGlob = vcGlobalsApp::Me();
	    if ($cntAct > 0) {
		$sHdr = $cntAct.' Available Title'.fcString::Pluralize($cntAct);
		$sContent = 
		  '<table class="catalog-summary"><tr><td>'
		  .$arRes['act']['text']
		  .'</td></tr></table>'
		  .$arRes['act']['imgs']
		  ;
		$oSection = new vcHideableSection('hide-available',$sHdr,$sContent);
		$ht .= $oSection->Render();
	    
	    /* 2018-02-14 the old way
		$wsArrow = $oGlob->GetWebSpec_DownPointer();
		$htArrow = "<img src='$wsArrow' alt='&darr; (down arrow)'>";
		$sTitle = $cntAct.' Available Title'.fcString::Pluralize($cntAct);
		$oHdr = new fcSectionHeader($htArrow.' '.$sTitle);
		$ht .= $oHdr->Render()
		  .'<table class="catalog-summary"><tr><td>'
		  .$arRes['act']['text']
		  .'</td></tr></table>'
		  .$arRes['act']['imgs']
		  ;
	      */
	    }
	    if ($cntRet > 0) {
		$sHdr = $cntRet.' Unavailable Title'.fcString::Pluralize($cntRet);
		$oSection = new vcHideableSection('show-retired',$sHdr,$arRes['ret']);
		$oSection->SetDefaultHide(TRUE);
		$ht .= $oSection->Render();
	    
	    /* 2018-02-14 should be redundant now
		$oFormIn = fcHTTP::Request();
		$sHdr = $cntRet.' Unavailable Title'.fcString::Pluralize($cntRet);
		
		$doRet = $oFormIn->KeyExists('ret');
		if ($doRet) {
		    $url = './';
		    $sPopup = 'hide unavailable titles';
		    $wsArrow = $oGlob->GetWebSpec_DownPointer();
		    $htAlt = '&darr; (down arrow)';
		    $htRet = $arRes['ret'];
		} else {
		    $url = '?ret';
		    $sPopup = 'show unavailable titles';
		    $wsArrow = $oGlob->GetWebSpec_RightPointer();
		    $htAlt = '&rarr; (right arrow)';
		    $htRet = '';
		}
		$sTitle = $cntRet.' Unavailable Title'.fcString::Pluralize($cntRet);
		$htArrow = "<img src='$wsArrow' alt='$htAlt' title='$sPopup'>";
		
		$oHdr = new fcSectionHeader("<a href='$url'>$htArrow</a> $sTitle");
		$ht .= $oHdr->Render().$htRet;
	    */
	    }
	}
	return $ht;
    }
    
    // -- UI PAGES -- //
}
