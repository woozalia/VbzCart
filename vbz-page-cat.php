<?php
/*
  FILE: page-cat.php
  HISTORY:
    2012-05-13 extracting clsVbzPage_Cat and clsPageCat from pages.php
    2013-11-15 finally merging clsPageCat into clsVbzPage_Cat
      Renamed page-cat.php to vbz-page-cat.php
*/
/* ===================
  CLASS: clsVbzPage_Cat
  PURPOSE: Handles display of catalog page types
*/
class clsVbzPage_Cat extends clsVbzPage_Browse {
// helper objects
    protected $db;	// database - CHANGE TO PRIVATE
// query
    protected $strReq;	// requested page
// page definition
    //protected $arNav;		// array of navigation links
    protected $strWikiPg;	// name of wiki page to embed, if any (blank = suppress embedding)
    private $sContent;		// content to display
// flags set by wiki contents
    protected $hideImgs;
// object cache
    private $objCatPage;	// object for identifying page to display

    // ++ ACCESS METHODS ++ //

    protected function CatPageObj() {
	return $this->objCatPage;
    }

    // -- ACCESS METHODS -- //
    // ++ CEMENTING ++ //

    /*-----
      IMPLEMENTATION: Retrieves request from URL and parses it
	URL data identifies page, keyed to cat_pages data
    */
    protected function ParseInput() {
	$strReq = static::GetPathInfo();
	$this->strReq = $strReq;
	/* 2015-04-16 not sure what this was for, so commenting it out for now:
	if (strrpos($strReq,'/')+1 < strlen($strReq)) {
	    $strRedir = KWP_CAT_REL.substr($strReq,1).'/';
	    header('Location: '.$strRedir);
	    exit;	// retry with new URL
	} */
    }
    protected function HandleInput() {

	if ($this->strReq) {
	    $strReq = $this->strReq;
	    $this->objCatPage = $this->Data()->Pages()->GetItem_byKey($strReq);
	    $rcPage = $this->objCatPage;
	    if ($rcPage->HasRows()) {
		switch ($rcPage->TypeKey()) {
		case 'S':
		  $this->DoCatSupp();
		  break;
		case 'D':
		  $this->DoCatDept();
		  break;
		case 'T':
		  $this->DoCatTitle();
		  break;
		case 'I':
		  $this->DoCatImage();
		  break;
		}
	    } else {
		$this->DoNotFound();
	    }
	} else {
	    $this->DoCatHome();
	}
    }
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

    // -- CEMENTING -- //
    // ++ OBJECT FACTORY ++ //

    private function Suppliers($id=NULL) {
	$tbl = $this->Data()->Suppliers();
	$tbl->Page($this);
	if (is_null($id)) {
	    return $tbl;
	} else {
	    $rc = $tbl->GetItem($id);
	    return $rc;
	}
    }
    private function Titles($id=NULL) {
	$tbl = $this->Data()->Titles();
	$tbl->Page($this);
	if (is_null($id)) {
	    return $tbl;
	} else {
	    $rc = $tbl->GetItem($id);
	    return $rc;
	}
    }

// UTILITY
    /*----
      NOTE: For now, this just outputs everything immediately.
	I'm keeping it around in case we want to implement
	some kind of content-cache later on.
    */
    protected function AddText($iText) {
	//$this->strContText .= $iText;
//	$this->Doc()->AddText($iText);
	echo $iText;
    }
    /*----
      ACTION: Sets the text to be displayed by DoContent().
	This is not incremental.
	This is needed so that we can get all the page information
	  in a single method.
    */
/*
    protected function PageText($iText=NULL) {
	if (!is_null($iText)) {
	    $this->sContent = $iText;
	}
	return $this->sContent;
    }
*/

// SIDEBAR INFO for different page subtypes

    private function DoCatIndicia() {
	$this->Skin()->AddNavItem('<b>Section</b>: ','by supplier',KWP_CAT_REL);
    }
    private function DoSuppIndicia($iSupp,$isFinal=true) {
	$this->DoCatIndicia();
	$sLabel = '<b>Supplier</b>: ';
	if ($isFinal) {
	    $sName = $iSupp->Value('Name');
	    $this->Skin()->AddNavItem($sLabel,$sName,KWP_WIKI_PUBLIC.$sName);
	} else {
	    $this->Skin()->AddNavItem($sLabel,$iSupp->Link(),NULL);
	}
    }
    private function DoDeptIndicia($iDept,$isFinal=true) {
	$this->DoSuppIndicia($iDept->Supplier(),false);
	if ($isFinal) {
	    $sVal = $iDept->NameStr();
	} else {
	    $sVal = $iDept->LinkName();
	}
	$this->Skin()->AddNavItem('<b>Dept.</b>: ',$sVal);
    }
    private function DoTitleIndicia($iTitle) {
	$this->DoDeptIndicia($iTitle->DepartmentRecord(),false);

	$this->Skin()->AddNavItem('<b>Title</b>: ',$iTitle->Value('Name'));
	$this->Skin()->AddNavItem('... <b>catalog #</b>: ',$iTitle->CatNum());
//	$oNav = $this->NavBarObj();
//	  $oi = new clsNavText($oNav,'Title',$iTitle->Value('Name'));
//	  $oi = new clsNavText($oNav,' - catalog #',$iTitle->CatNum());
    }

// DIFFERENT TYPES OF PAGES

    protected function DoNotFound() {
	$this->Skin()->TitleContext('Tomb of the...');
	$this->Skin()->PageTitle('Unknown Page');
	//$this->NameStr('unknown title in catalog');
	//$this->strSideXtra	= '<dt><b>Cat #</b>: '.$this->strReq;
    }
    private function DoCatHome() {
	$this->DoCatIndicia();
	$this->Skin()->PageTitle('Catalog Home');
	//$this->NameStr('Catalog main page');
	$this->Skin()->TitleContext('hello and welcome to the...');
	$this->Skin()->Content('main',$this->Suppliers()->DoHomePage());
    }
    private function DoCatSupp() {
	$idRow = $this->objCatPage->Value('ID_Row');
	$rcSupp = $this->Suppliers($idRow);
	assert('is_object($rcSupp)');
	$strSuppName = $rcSupp->Value('Name');

	$this->DoSuppIndicia($rcSupp);
	$this->Skin()->PageTitle($strSuppName);
	//$this->NameStr('listing for '.$strSuppName);
	$this->Skin()->TitleContext('<a href="'.KWP_CAT_REL.'">Suppliers</a>: <b>'.$strSuppName.'</b>:');
	$this->Skin()->Content('main',$rcSupp->DoDeptsPage());
    }
    private function DoCatDept() {
	$objDeptTbl = $this->Data()->Depts();
	$objDept = $objDeptTbl->GetItem($this->objCatPage->RowID());
	$objSupp = $objDept->Supplier();
	$strDeptName = $objDept->NameStr();
	$strSuppName = $objSupp->NameStr();
	$strDeptLink = $objDept->LinkName();
	$strSuppLink = $objSupp->Link();

	$this->DoDeptIndicia($objDept);

	//$this->NameStr( $strDeptName.' dept. of '.$strSuppName);
	$this->Skin()->PageTitle($strDeptName);
	$this->Skin()->TitleContext('items <a href="'.KWP_CAT_REL.'">supplied</a> by '.$strSuppLink.'\'s <b>'.$strDeptName.'</b> department:');

	$this->Skin()->Content('main',$objDept->RenderPage());
    }
    private function DoCatTitle() {
	$strPageKey = $this->objCatPage->Value('Path');

	$idRow = $this->objCatPage->Value('ID_Row');
	$objTitle = $this->Titles($idRow);
	$objDept = $objTitle->DepartmentRecord();
	$objSupp = $objDept->Supplier();
	$strTitleName = $objTitle->Value('Name');

	$this->DoTitleIndicia($objTitle);

	//$this->Skin()->NameStr($strPageKey.' "'.$strTitleName.'" from '.$objSupp->Value('Name'));
	$this->Skin()->PageTitle($strTitleName);
	$this->Skin()->TitleContext(
	  'items <a href="'.KWP_CAT_REL.
	  '">supplied</a> by '.$objSupp->Link()."'s ".
	  $objDept->LinkName().' department:'
	  );
	$objTitle->hideImgs = $this->hideImgs;

	$this->Skin()->Content('cat-title',$objTitle->DoPage());
    }
    private function DoCatImage() {
//	$sPageKey = $this->objCatPage->Value('Path');
	$idRow = $this->objCatPage->Value('ID_Row');
	$oImage = $this->Data()->Images($idRow);
	$oTitle = $oImage->TitleObj();
	$sAttr = $oImage->Value('AttrDispl');
	$sSize = $oImage->SizeDescr();
	if (is_null($sAttr)) {
	    $htAttr = NULL;
	} else {
	    $htAttr = " in <b>$sAttr</b>";
	}
	$sTitleName = $oTitle->Value('Name');
	$this->Skin()->PageTitle($sTitleName);
	$this->Skin()->TitleContext(
	  "<b>$sSize</b> image$htAttr for"
	  );

	$rc = $this->objCatPage->ItemObj();
	$this->Skin()->Content('cat-image',$rc->DoPage());
    }
}
