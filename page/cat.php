<?php
/*
  FILE: page-cat.php
  HISTORY:
    2012-05-13 extracting clsVbzPage_Cat and clsPageCat from pages.php
    2013-11-15 finally merging clsPageCat into clsVbzPage_Cat
      Renamed page-cat.php to vbz-page-cat.php
    2016-11-22 massive rewrite of page generation system
    2018-02-25 moved vcAppShop_catalog and vcMenuKiosk_catalog here
*/
class vcAppShop_catalog extends vcAppShop {
    protected function GetPageClass() {
	return 'vcCatalogPage';
    }
    protected function GetKioskClass() {
	return 'vcMenuKiosk_catalog';
    }
}

class vcMenuKiosk_catalog extends fcMenuKiosk_autonomous {
    public function GetBasePath() {
	return vcGlobals::Me()->GetWebPath_forCatalogPages();
    }
}

/*::::
  PURPOSE: Handles display of catalog page types
*/
class vcCatalogPage extends vcPage_shop {

    // ++ SETUP ++ //

    // CEMENT
    protected function Class_forTagHTML() : string {
	return 'vcTag_html_catalog';
    }

    // -- SETUP -- //
    // ++ OVERRIDES ++ //
    
    protected function BaseURL() {
	return KWP_CAT_REL;
    }
    protected function MenuPainter_new() {
	// maybe this shouldn't be here -- does the catalog really need menu functions?
    }
    protected function PreSkinBuild() {
	// this may not be needed
    }
    protected function PostSkinBuild() {
	// this may not be needed
    }
    protected function MenuHome_new() {
	// this suggests a class reorganization is needed...
    }

    // -- CEMENTING -- //
    
// SIDEBAR INFO for different page subtypes

    // NOTE (2018-02-18): This is not currently being used, but we will probably want to fix it up and use it agian.
    private function DoCatIndicia() {
	$this->GetSkinObject()->AddNavItem('<b>Section</b>: ','by supplier',KWP_CAT_REL);
    }
    // NOTE (2018-02-18): This is not currently being used, but we will probably want to fix it up and use it agian.
    private function DoSuppIndicia(vcrSupplier $rcSupp,$isFinal=true) {
	$this->DoCatIndicia();
	$sLabel = '<b>Supplier</b>: ';
	if ($isFinal) {
	    $sName = $rcSupp->NameString();
	    $this->GetSkinObject()->AddNavItem($sLabel,$sName,KURL_WIKI_PUBLIC.$sName);
	} else {
	    $this->GetSkinObject()->AddNavItem($sLabel,$iSupp->Link(),NULL);
	}
    }
    // NOTE (2018-02-18): This is not currently being used, but we will probably want to fix it up and use it agian.
    private function DoDeptIndicia(vcrDept $rcDept,$isFinal=true) {
	$this->DoSuppIndicia($rcDept->SupplierRecord(),false);
	if ($isFinal) {
	    $sVal = $rcDept->NameString();
	} else {
	    $sVal = $rcDept->LinkName();
	}
	$this->GetSkinObject()->AddNavItem('<b>Dept.</b>: ',$sVal);
    }
    // NOTE (2018-02-18): This is not currently being used, but we will probably want to fix it up and use it agian.
    private function DoTitleIndicia(vcrTitle $rcTitle) {
	throw new exception('2018-02-18 Does anything still call this?');
	$this->DoDeptIndicia($rcTitle->DepartmentRecord(),false);

	$this->GetSkinObject()->AddNavItem('<b>Title</b>: ',$rcTitle->Value('Name'));
	$this->GetSkinObject()->AddNavItem('... <b>catalog #</b>: ',$rcTitle->CatNum());
    }

// DIFFERENT TYPES OF PAGES
/*
    protected function DoNotFound() {
	$this->Skin()->SetTitleContextString('Tomb of the...');
	$this->Skin()->PageTitle('Unknown Page');
	//$this->NameStr('unknown title in catalog');
	//$this->strSideXtra	= '<dt><b>Cat #</b>: '.$this->strReq;
    } */
    private function DoCatHome() {
	throw new exception('2018-02-18 Pretty sure we do not need this anymore.');
	$this->DoCatIndicia();
	$oSkin = $this->GetSkinObject();
	$oSkin->SetTitleContextString('hello and welcome to the...');
	$oSkin->SetPageTitle('Catalog Home');
	$oSkin->Content('main',$this->SupplierTable()->DoHomePage());
    }
}
class vcTag_html_catalog extends vcTag_html_shop {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forTag_body() {
	return 'vcTag_body_catalog';
    }

    // -- SETUP -- //

}
class vcTag_body_catalog extends vcTag_body_shop {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forPageContent() {
	return 'vcPageContent_catalog';
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //

    // CreateElements: parent creates header, navbar, content
    // CEMENT
    protected function OnRunCalculations(){}
    
    // -- EVENTS -- //

}

class vcPageContent_catalog extends vcPageContent_shop {
    use vtTableAccess_Supplier;

    // ++ FRAMEWORK ++ //
    
    protected function GetPageObject() {
	return fcApp::Me()->GetPageObject();
    }
    
    // -- FRAMEWORK -- //
    // ++ EVENTS ++ //

    protected function OnRunCalculations() {
	$this->FigureExhibitPage_fromInput();
    }
    
    // -- EVENTS -- //
    // ++ INPUT CALCULATIONS ++ //

    protected function FigureExhibitPage_fromInput() {
	//$wp = $this->GetPathFragument();
	$wp = fcApp::Me()->GetKioskObject()->GetInputString();
	
	$oPage = $this->GetPageObject();
	$tSupp = $this->SupplierTable();
	if (strlen($wp) > 1) {
	    // normalize fragument - remove outside '/'s, then add one at the beginning:
	    $wp = '/'.trim($wp,'/');
	    // split by first character
	    $arPath = fcString::Xplode($wp);
	    // we want to work from left to right, but array_pop() goes from right to left -- so...
	    $arThap = array_reverse($arPath);
	    // hand off the rest of the lookup to the Suppliers table object:
	    $rcPage = $tSupp->LookupExhibitRecord($arThap);
	
	    if (is_null($rcPage)) {
		$isFnd = FALSE;
	    } else {
		$isFnd = !$rcPage->IsNew();
	    }
	    if ($isFnd) {
		$oPage->SetContentTitleContext($rcPage->ExhibitSuperTitle());
		$oPage->SetPageTitle($rcPage->ExhibitMainTitle());
		$this->SetValue($rcPage->ExhibitContent());
	    } else {
		$oPage->SetContentTitleContext('Tomb of the...');
		$oPage->SetPageTitle('Unknown Page');
		$this->SetValue('This URL does not refer to anything currently. Sorry!');
		
		// 2016-11-25 disable when generating a lot of known errors; enable when they're fixed
		//fcApp::Me()->ReportSimpleError("VbzCart input error: request string [$wp] does not map to any content.");
	    }
	} else {
	// 2016-12-01 This should probably just be rethought.
	//    $this->GetParent()->GetElement_PageNavigation()->AddNavItem('<b>Section</b>: ','by supplier',KWP_CAT_REL);
	    $oPage->SetContentTitleContext('hello and welcome to the...');
	    $oPage->SetPageTitle('Supplier Index');
	    $this->SetValue($tSupp->DoHomePage());
	}
    }
    
    // -- INPUT CALCULATIONS -- //
    // ++ CLASSES ++ //

    protected function SuppliersClass() {
	return KS_CLASS_SHOP_SUPPLIERS;
    }
    
    // -- CLASSES -- //

}
