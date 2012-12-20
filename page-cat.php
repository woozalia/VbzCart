<?php
/*
  FILE: page-topic.php
  HISTORY:
    2012-05-13 extracting clsVbzPage_Cat and clsPageCat from pages.php
*/
/* ===================
  CLASS: clsVbzPage_Cat
  PURPOSE: Handles display of catalog page types
  TO DO: These classes still need more tidying -- see clsPageCat --
    and still need a bit of work to allow user-choosable skins.
*/

if (!defined('LIBMGR')) {
    require(KFP_LIB.'/libmgr.php');
}

clsLibMgr::Add('vbz.pages',	KFP_LIB_VBZ.'/pages.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsVbzSkin_Standard','vbz.pages');
clsLibMgr::Add('vbz.base.cat',	KFP_LIB_VBZ.'/base.cat.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsSuppliers', 'vbz.base.cat');
clsLibMgr::Add('vbz.page.topic',	KFP_LIB_VBZ.'/page-topic.php',__FILE__,__LINE__);
  clsLibMgr::AddClass('clsTopics_StoreUI','vbz.page.topic');

abstract class clsVbzPage_Cat extends clsVbzSkin_Standard {
// helper objects
    protected $db;	// database - CHANGE TO PRIVATE
// query
    protected $strReq;	// requested page
// page definition
    protected $strName;	// short title: {item name} (goes into html title, prefixed with store name)
    protected $strTitle;	// longer, descriptive title: {"item name" by Supplier} (goes at top of page)
    protected $strWikiPg;	// name of wiki page to embed, if any (blank = suppress embedding)
// calculated fields
//    protected $strCalcTitle;
    protected $strContText;
// flags set by wiki contents
    protected $hideImgs;

    /*-----
      IMPLEMENTATION: Retrieves request from URL and parses it
	URL data identifies page, keyed to cat_pages data
    */
    protected function ParseInput() {
	if (isset($_SERVER['PATH_INFO'])) {
	    $strReq = $_SERVER['PATH_INFO'];
	} else {
	    $strReq = '';
	}
	$this->strReq = $strReq;
	if (strrpos($strReq,'/')+1 < strlen($strReq)) {
	    $strRedir = KWP_CAT_REL.substr($strReq,1).'/';
	    header('Location: '.$strRedir);
	    exit;	// retry with new URL
	}
    }
// DIFFERENT TYPES OF PAGES
    protected function DoNotFound() {
	$this->strWikiPg	= '';
	$this->strTitle	= 'Unknown Page';
	$this->strName	= 'unknown title in catalog';
	$this->strTitleContext	= 'Tomb of the...';
	$this->strHdrXtra	= '';
	$this->strSideXtra	= '<dt><b>Cat #</b>: '.$this->strReq;
    }
// UTILITY
    protected function AddText($iText) {
	$this->strContText .= $iText;
    }
    private function DoWikiContent() {
# WIKI CONTENTS
#	$txtPage = GetEmbedPage('cat');
	if (KF_USE_WIKI) {
	    $txtWiki = GetWikiPage($this->strWikiPg);
	    if ($txtWiki) {
		if (strpos($txtWiki,'__NOIMG__') != -1) {
		    $txtWiki = str_replace('__NOIMG__','',$txtWiki);
		    $this->hideImgs = true;
		}
	    }
	    if ($txtWiki) {
		echo '<table class=main><tr><td>'.$txtWiki.'</td></tr></table>';
	    }
	}
    }
}

/*%%%%
  TODO:
    * figure out why we need to have this as a separate class from clsVbzPage_Cat
    * give it a better name
*/
class clsPageCat extends clsVbzPage_Cat {
    private $objCatPage;	// object for identifying page to display

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

    protected function RenderHdrBlocks() {
	if ($this->useSkin) {
	    parent::RenderHdrBlocks();
	}
    }
    protected function RenderFtrBlocks() {
	if ($this->useSkin) {
	    parent::RenderFtrBlocks();
	}
    }
    protected function HandleInput() {
	parent::HandleInput();
	$strReq = $this->strReq;
	$this->objCatPage = $this->Data()->Pages()->GetItem_byKey($strReq);
	$objPage = $this->objCatPage;
	$this->useSkin = TRUE;

	if ($this->strReq) {
	    if (is_object($objPage)) {
		switch ($objPage->Type) {
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
		  $this->useSkin = FALSE;
		  echo $this->DoCatImage();
		  break;
		}
	    } else {
		$this->DoNotFound();
	    }
	} else {
	    $this->DoCatHome();
	}
    }
// SIDEBAR INFO for different types of pages
  private function DoCatIndicia() {
    $this->lstTop->Add('Section','<a href="'.KWP_CAT_REL.'">by supplier</a>');
  }
  private function DoSuppIndicia($iSupp,$isFinal=true) {
    $this->DoCatIndicia();
    if ($isFinal) {
      $this->lstTop->Add('Supplier',$iSupp->Name);
      $this->lstTop->Add('<a href="'.KWP_WIKI.$iSupp->Name.'">more info</a>');
    } else {
      $this->lstTop->Add('Supplier',$iSupp->Link());
    }
  }
  private function DoDeptIndicia($iDept,$isFinal=true) {
    $this->DoSuppIndicia($iDept->Supplier(),false);
    if ($isFinal) {
      $this->lstTop->Add('Dept.',$iDept->Name);
    } else {
      $this->lstTop->Add('Dept.',$iDept->LinkName());
    }
  }
  private function DoTitleIndicia($iTitle) {
    $this->DoDeptIndicia($iTitle->Dept(),false);
    $this->lstTop->Add('Title',$iTitle->Name);
    $this->lstTop->Add(' - catalog #',$iTitle->CatNum());
  }


    private function DoCatHome() {
	$this->DoCatIndicia();
	$this->strWikiPg	= 'cat';
	$this->strTitle	= 'Catalog Home';
	$this->strName	= 'Catalog main page';
	$this->strTitleContext	= 'hello and welcome to the...';
	$this->Suppliers()->DoHomePage();
	$this->AddText($this->Doc()->Render());
    }
    private function DoCatSupp() {
	$idRow = $this->objCatPage->Value('ID_Row');
	$rcSupp = $this->Suppliers($idRow);
	assert('is_object($rcSupp)');
	$strSuppName = $rcSupp->Value('Name');

	$this->DoSuppIndicia($rcSupp);
	$this->strWikiPg	= 'supp:'.strtoupper($rcSupp->Value('CatKey'));
	$this->strTitle	= $strSuppName;
	$this->strName	= 'listing for '.$strSuppName;
	$this->strTitleContext	= '<a href="'.KWP_CAT_REL.'">Suppliers</a>: <b>'.$strSuppName.'</b>:';
	$rcSupp->DoDeptsPage();
	$this->AddText($this->Doc()->Render());
    }
  private function DoCatDept() {
    CallEnter($this,__LINE__,'clsPage.DoCatDept()');

//    $objDeptTbl = VbzClasses::Depts();
    $objDeptTbl = $this->Depts();
    $objDept = $objDeptTbl->GetItem($this->objCatPage->ID_Row);
    assert('is_object($objDept)');
    $objSupp = $objDept->Supplier();
    assert('is_object($objSupp)');
    $strDeptName = $objDept->Name;
    $strSuppName = $objSupp->Name;
    $strDeptLink = $objDept->LinkName();
    $strSuppLink = $objSupp->Link();

    $this->DoDeptIndicia($objDept);
    $this->strWikiPg	= 'dept:'.strtoupper($objDept->PageKey);

    $this->strTitle	= $strSuppName;
    $this->strName	= $strDeptName.' dept. of '.$strSuppName;
    $this->strTitleContext	= 'items <a href="'.KWP_CAT_REL.'">supplied</a> by '.$strSuppLink.'\'s <b>'.$strDeptName.'</b> department:';
    $this->AddText($objDept->DoPage());
    CallExit('clsPage.DoCatDept()');
  }
    protected function TitleObj($id) {
	$rs = $this->Titles();
	$rc = $rs->GetItem($id);
	return $rc;
    }
  private function DoCatTitle() {
    CallEnter($this,__LINE__,'clsPage.DoCatTitle()');

    $strPageKey = $this->objCatPage->Path;
//    $objTitleTbl = VbzClasses::Titles();
//    $objTitleTbl = $this->Titles();

    $idRow = $this->objCatPage->ID_Row;
    //$objTitle = $objTitleTbl->GetItem($idRow);
    $objTitle = $this->TitleObj($idRow);
    assert('is_object($objTitle)');
    $objDept = $objTitle->Dept();
    assert('is_object($objDept)');
    $objSupp = $objDept->Supplier();
    assert('is_object($objSupp)');
    $strTitleName = $objTitle->Name;

    $this->DoTitleIndicia($objTitle);

//    $this->strAbbr	= 'title:'.strtoupper($strCatNum);
    $this->strWikiPg	= 'title:'.$objTitle->CatNum();
//print 'ABBR='.$this->strAbbr;
    $this->strTitle	= $strTitleName;
    $this->strName	= $strPageKey.' "'.$strTitleName.'" from '.$objSupp->Name;
    $this->strTitleContext 	= 
      'items <a href="'.KWP_CAT_REL.
      '">supplied</a> by '.$objSupp->Link().'\'s '.
      $objDept->LinkName().' department:';
    $objTitle->hideImgs = $this->hideImgs;
    $this->AddText($objTitle->DoPage());
    CallExit('clsPage.DoCatTitle()');
  }
  private function DoCatImage() {
    $rc = $this->objCatPage->ItemObj();
/*
    $objImageTbl = $objTitle->ShopPage_Images();
    $objImage = $objImageTbl->GetItem($this->objCatPage->ID_Row);
    $objImage->DoPage();
*/
    return $rc->DoPage();
  }
}
/*%%%%
  PURPOSE: extends clsSuppliers to handle store UI interactions
*/
class clsSuppliers_StoreUI extends clsSuppliers {
    private $objPage;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsSupplier_StoreUI');
    }
    public function Page(clsVbzSkin $iPage=NULL) {
	if (!is_null($iPage)) {
	    $this->objPage = $iPage;
	}
	if (!is_object($this->objPage)) {
	    throw new exception('Internal error: must set page before trying to read it.');
	}
	return $this->objPage;
    }
    public function DoHomePage() {
	$rc = $this->DataSet_forStore();
	if ($rc->hasRows()) {
	    $objPage = $this->Page();
	    $objPage->NewSection('Suppliers');
	    $objTbl = $objPage->NewTable();
	    $objTbl->ClassName('catalog-summary');
	    $strKeyLast = $outCell = '';
	    while ($rc->NextRow()) {
		$strKey = $rc->Value('CatKey');
		if ($strKey != $strKeyLast) {
		    // supplier has changed
		    $strKeyLast = $strKey;
		    $strKeyLink = strtolower($strKey).'/';
		    if ($outCell) {
			// dump accumulated list in 2nd column
			$objRow->NewCell($outCell);
			$outCell = '';
		    }
		    // start a new row
		    $objRow = $objTbl->NewRow();
		    $objRow->NewCell('<b><a href="'.$strKeyLink.'">'.$rc->Value('Name').'</a></b>');
		    $isFirst = true;
		}
		if ($isFirst) {
		    $isFirst = false;
		} else {
		    $outCell .= ', ';
		}
		$strItType = $rc->Value('ItemType');
		if ($strItType == '') {
		    $strItType = '?id'.$rc->KeyString();
		}
		$outCell .= ' <b>'.$rc->Value('ItemCount').'</b> '.$strItType;
	    }
	    $objRow->NewCell($outCell);
	}
    }
}

class clsSupplier_StoreUI extends clsSupplier {
    /*----
      HISTORY:
	2012-05-10 extracted from clsSuppliers and renamed from DeptsPage_forStore() to DoDeptsPage()
	2012-05-11 no longer returns rendered output, but leaves it in Doc() object
    */
    public function DoDeptsPage() {
	$this->DoPiece_ItTyp_Summary();
	$this->Table()->Page()->NewSection('Departments:');
	$this->DoPiece_Dept_ItTyps();
    }
    /*----
      ACTION: Generates the table of departments and the summary of items available for each
    */
    public function DoPiece_Dept_ItTyps() {
	$arData = $this->DeptsData_forStore();
	$arObjs = $arData['supp'];
	$arDeptCntForSale = $arData['depts'];
	$objPage = $this->Table()->Page();

	$objTbl = $objPage->NewTable();
	$objTbl->ClassName('catalog-summary');
	
	$isOdd = FALSE;
	$fpSupp = KWP_CAT_REL.strtolower($this->Value('CatKey')).'/';
	$arAttrCell = array('valign' => 'top');
	foreach ($arDeptCntForSale as $idDept=>$arCnts) {
	    $isOdd = !$isOdd;

	    $outRow = '';
	    foreach ($arCnts as $id=>$cnt) {
		if ($cnt > 0) {
		    $objTyp = $arObjs[$id];
		    $strType = $objTyp->Name($cnt);
		    if ($outRow != '') {
			$outRow .= ', ';
		    }
		    $outRow .= '<b>'.$cnt.'</b> '.$strType;
		}
	    }
	    if ($outRow != '') {
		$objDept = $this->objDB->Depts()->GetItem($idDept);
		$strPageKey = $objDept->PageKey();
		$strName = $objDept->Name;

		$objRow = $objTbl->NewRow();
		if ($isOdd) {
		    $objRow->ClassName('catalog-stripe');
		} else {
		    $objRow->ClassName('catalog');
		}

		$objCell = $objRow->NewCell('<a href="'.$fpSupp.strtolower($strPageKey).'/">'.$strName.'</a>');
		  $objCell->SetAttrs($arAttrCell);
		$objCell = $objRow->NewCell($outRow);
		  $objCell->SetAttrs($arAttrCell);
	    }
	}
    }
}

/*%%%%
  PURPOSE: extends clsTitles to handle store UI interactions
*/
class clsTitles_StoreUI extends clsVbzTitles {
    private $objPage;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsTitle_StoreUI');
    }
    public function Page(clsVbzSkin $iPage=NULL) {
	if (!is_null($iPage)) {
	    $this->objPage = $iPage;
	}
	if (!is_object($this->objPage)) {
	    throw new exception('Internal error: must set page before trying to read it.');
	}
	return $this->objPage;
    }
}
class clsTitle_StoreUI extends clsVbzTitle {
    public function DoPage() {
	$idTitle = $this->KeyValue();
	assert('$idTitle');

	$objPage = $this->Table()->Page();
	$objDoc = $objPage->Doc();

	// show "small" images for this title
//	if (!$this->hideImgs) {		// what was this for?
	    $ht = $this->ShopPage_Images();
	    if (is_null($ht)) {
		$this->RenderImgUnavail();
	    } else {
		$objDoc->NewText($ht);
	    }
//	}

	// list topics for this title
	$out = $this->ShopPage_Topics();
	$objDoc->NewText($out);

	// list available items as table
	$out = $this->ShopPage_Items();
	$objDoc->NewText($out);

/*	if (is_null($out)) {
	    $objSection->SectionHdr('This title is currently unavailable');
	}
*/
//	return $objSection->out."\n<!-- TITLE ID=$idTitle -->\n";
	$objDoc->NewText("\n<!-- TITLE ID=$idTitle -->\n");
    }
    function RenderImgUnavail() {
	$this->out .= '<table class=border cellpadding="5">'
	  .'<tbody><tr><td><table class="hdr" cellpadding="2">'
	  .'<tbody><tr><td align="center">'
	  .'<span class="page-title">No Images<br>Available<br></span>for this item<br><b>:-(</b>'
	  .'</td></tr></tbody></table>'
	  .'</td></tr></tbody></table>';
	return $this->out;
    }
    protected function ShopPage_Topics() {
	$db = $this->Engine();
	$tbl = $db->TitleTopic_Topics();
	$tbl->doBranch(TRUE);
	$rs = $tbl->GetTitle($this->KeyValue());
	if ($rs->hasRows()) {
//	    $txt = '<table align=right style="border: solid 1px; background: black;"><tr><td bgcolor=#333333>';
	    $txt = '<table align=right class="catalog-summary"><tr><td bgcolor=#333333>';
	    $txt .= '<b>'.$db->Topics()->IndexLink('Topics').'</b>:';
	    while ($rs->NextRow()) {
		$txt .= '<br>- '.$rs->ShopLink();
	    }
	    $txt .= '</td></tr></table>';
	    return $txt;
	} else {
	    return NULL;
	}
    }
    protected function ShopPage_Images() {
	$objImgs = $this->ListImages('sm');
	$out = NULL;
	if ($objImgs->hasRows()) {
	    while ($objImgs->NextRow()) {
		$strImgTag = $objImgs->AttrDispl;
		$urlRel = $objImgs->Spec;
		$idImg = $objImgs->ID;
		//$strImg = '<img src="'.KWP_IMG_MERCH.$urlRel.'"';
		$strImg = '<img src="'.$objImgs->WebSpec().'"';
		if ($strImgTag) {
		    $strImg .= ' title="'.$strImgTag.'"';
		}
		$strImg .= '>';
		$objImgBig = $objImgs->ImgForSize('big');
		if (is_object($objImgBig)) {
		    if ($objImgBig->FirstRow()) {
			$strImg = $objImgBig->Href().$strImg.'</a>';
		    }
		}
		$out .= $strImg;
	    }
	}
	return $out;
    }
    /*----
      HISTORY:
	2012-02-10 began rewriting to go straight to data (no cache)
    */
    protected function ShopPage_Items() {
	$idTitle = $this->KeyValue();
	$out = NULL;

	//$sql = 'SELECT * FROM qryTitles_ItTyps_ItTyps WHERE (ID_Title='.$idTitle.') ORDER BY ItTyp_Sort IS NULL, ItTyp_Sort;';
	//$sql = 'SELECT * FROM _title_ittyps WHERE (ID_Title='.$idTitle.') ORDER BY ItTypSort IS NULL, ItTypSort;';
	$sql = 'SELECT * FROM'
	  .' (cat_items AS i'
	  .' LEFT JOIN cat_ittyps AS it ON i.ID_ItTyp=it.ID)'
	  .' LEFT JOIN cat_ioptns AS io ON i.ID_ItOpt=io.ID'
	  .' WHERE isForSale AND i.ID_Title='.$idTitle
	  .' ORDER BY it.Sort,GrpSort,GrpDescr,i.ItOpt_Sort, io.Sort;';	// sorting may need to be tweaked
	$tblItems = $this->Engine()->Items();
	$rs = $tblItems->DataSQL($sql);
	$isItem = FALSE;
	if ($rs->hasRows()) {
	    if (KF_CART_ABSOLUTE) {
	      $urlCart = KWP_CART_ABS;
	    } else {
	      $urlCart = KWP_CART_REL;
	    }
	    $out .= '<form method=post action="'.$urlCart.'"><input type=hidden name=from value=browse-multi>';

	    $flagDisplayTogether = false;	// hard-coded for now

	    $txtTblHdr = $tblItems->Render_TableHdr();
	    $txtInStock = $txtOutStock = $txtBoth = '';

	    $idItTyp_old = NULL;
	    $strGrp_old = NULL;
	    $this->cntInStk = 0;
	    $this->cntOutStk = 0;

	    while ($rs->NextRow()) {
		$idItTyp = $rs->Value('ID_ItTyp');
		if ($idItTyp_old != $idItTyp) {
		    $idItTyp_old = $idItTyp;
		    // new type -- render type headers
		    $ar = $this->ShopPage_Items_TypeSection($rs);
		    $txtInStock .= NzArray($ar,'in');
		    $txtOutStock .= NzArray($ar,'out');
		    $txtBoth .= NzArray($ar,'both');
		}

		$strGrp = $rs->Value('GrpDescr');
		$qtyStk = $rs->Value('QtyIn_Stk');
		if ($strGrp != $strGrp_old) {
		    $strGrp_old = $strGrp;
		    // new group -- render group header

		    $strGrpCode = $rs->Value('GrpCode');
		    $out = '<tr class="group">';
		    $out .= '<td colspan=5> &mdash; '.$strGrp;
		    if ($strGrpCode) {
			$out .= ' <font color=#666666>(<font color=#666699>'.$strGrpCode.'</font>)</font>';
		    }
		    $out .= '</td>';
		    $out .= '</tr>';
// this should probably be a subroutine...
		    if ($flagDisplayTogether) {
			$txtBoth .= $out;
		    } else {
			if ($qtyStk > 0) {
			    $txtInStock .= $out;
			} else {
			    $txtOutStock .= $out;
			}
		    }
		} // END new group
    
		$txtLine = $rs->Render_TableRow();

		if ($flagDisplayTogether) {
		    $txtBoth .= $txtLine;
		} else {
		    if ($qtyStk > 0) {
			$txtInStock .= $txtLine;
		    } else {
			$txtOutStock .= $txtLine;
		    }
		}
	    } // END while next row

// format all the accumulated bits of text into one large string:
	    $txtTblOpen = '<table class=main><tbody>';
	    $txtTblFtr = '<tr><td colspan="4" align="right"><input value="Add to Cart" type="submit"></td></tr>';
	    $txtTblShut = '</tbody></table>';

	    if ($flagDisplayTogether) {
// Display in-stock and backordered items together
		$out .= $txtTblOpen.$txtTblHdr.$txtBoth.$txtTblFtr.$txtTblShut;
	    } else {
		if ($this->cntInStk) {
		    $txtClause = Pluralize($qtyStk,'This item is','These items are');
		    $out .= $txtTblOpen;
		    $out .= '<tr class=inStock><td colspan=5>'.$txtClause.' in stock:</td></tr>';
		    $out .= $txtTblHdr.$txtInStock.$txtTblFtr.$txtTblShut;
		}

		if ($this->cntOutStk) {
		    if (!empty($txtInStock)) {
			$out .= '<p>';
		    }
		    $txtClause = Pluralize($this->cntOutStk,'This item is','These '.$this->cntOutStk.' items are');

		    $out .= $txtTblOpen;
		    $out .= '<tr><td colspan=5>'.$txtClause.' <a href="'.KWP_HELP_NO_STOCK_BUT_AVAIL.'"><b>not in stock</b></a>';

		    $txtClause = Pluralize($this->cntOutStk,'it','them');

		    $out .= ', but we can (probably) <a href="'.KWP_HELP_POLICY_SHIP.'">get '.$txtClause.'</a>:</td></tr>';
		    $out .= $txtTblHdr.$txtOutStock.$txtTblFtr.$txtTblShut;
		}
	    }
	    $out .= '</form>';
	} else {
	    $objPage = $this->Table()->Page();
	    $out .= $objPage->NewSection('This title is currently unavailable');
	}
	return $out;
    }
    protected function ShopPage_Items_TypeSection($rs) {
	$txtTypPlur = $rs->Value('NamePlr');

	$txtLine = "\n<tr class=typeHdr><td colspan=3><b>$txtTypPlur</b>:</td></tr>";

	$flagDisplayTogether = FALSE;	// hard-coded for now
	if ($flagDisplayTogether) {
// displaying all items in a single listing
	    $arOut['both'] = $txtLine;
	} else {
// set flags to determine which stock-status sections to show
	    $qtyInStk = $rs->Value('QtyIn_Stk');
	    $isForSale = $rs->Value('isForSale');
	    $isOutStk = (($qtyInStk == 0) && $isForSale); 

	    if ($qtyInStk > 0) {
		// list in the "in stock" section
		$arOut['in'] = $txtLine;
		// add to count of lines in stock
		$this->cntInStk++;
	    }
	    if ($isOutStk) {
		// list in the "out of stock" section
		$arOut['out'] = $txtLine;
		// add to count of lines out of stock
		$this->cntOutStk++;
	    }
	}
	return $arOut;
    }
    protected function ShopPage_Items_OLD() {
	$idTitle = $this->KeyValue();
	$out = NULL;

	//$sql = 'SELECT * FROM qryTitles_ItTyps_ItTyps WHERE (ID_Title='.$idTitle.') ORDER BY ItTyp_Sort IS NULL, ItTyp_Sort;';
	$sql = 'SELECT * FROM _title_ittyps WHERE (ID_Title='.$idTitle.') ORDER BY ItTypSort IS NULL, ItTypSort;';
	$rsTypes = $this->Engine()->DataSet($sql);
	$isItem = FALSE;
	if ($rsTypes->hasRows()) {
	    if (KF_CART_ABSOLUTE) {
	      $urlCart = KWP_CART_ABS;
	    } else {
	      $urlCart = KWP_CART_REL;
	    }
	    $out .= '<form method=post action="'.$urlCart.'"><input type=hidden name=from value=browse-multi>';

	    $flagDisplayTogether = false;	// hard-coded for now

	    //$objStk = $this->Engine()->Items_Stock();
	    $tblItems = $this->Engine()->Items();
	    //$txtTblHdr = '<tr><th align=left>Option</th><th>Status</th><th align=right class=title-price>Price</th><th align=center class=orderQty>Order<br>Qty.</th><th><i>list<br>price</th></tr>';
	    $txtTblHdr = $tblItems->Render_TableHdr();
	    $txtInStock = $txtOutStock = '';

	    while ($rsTypes->NextRow()) {
		$idItTyp = $rsTypes->Value('ID_ItTyp');
		assert('$idItTyp');
		$sqlFilt = '(ID_Title='.$idTitle.') AND (ID_ItTyp='.$idItTyp.')';
		$sqlSort = 'GrpSort,GrpDescr,ItOpt_Sort';
		$rsItems = $tblItems->GetData($sqlFilt,NULL,$sqlSort);
		//$idItType = 0;

		$txtLine = '<tr class=typeHdr><td colspan=3><b>'.$rsTypes->Value('ItTypNamePlr').'</b>:</td></tr>';

		if ($flagDisplayTogether) {
// displaying all items in a single listing
		    $txtBoth .= $txtLine;
		} else {
// set flags to determine which stock-status sections to show
		    $cntInStock = $rsTypes->Value('cntInStock');
		    $cntForSale = $rsTypes->Value('cntForSale');
		    $cntOutStock = $cntForSale - $cntInStock;

		    if ($cntInStock > 0) {
			$txtInStock .= $txtLine;
		    }
		    if ($cntOutStock > 0) {
			$txtOutStock .= $txtLine;
		    }
		}
// iterate through items for this type:
		$strGrpLast = '';
		while ($rsItems->NextRow()) {
		    $strGrp = $rsItems->Value('GrpDescr');
		    $qtyStk = $rsItems->Value('QtyIn_Stk');
		    if ($strGrp != $strGrpLast) {
			$strGrpLast = $strGrp;
			$strGrpCode = $rsItems->Value('GrpCode');
			$out = '<tr class="group">';
			$out .= '<td colspan=5> &mdash; '.$strGrp;
			if ($strGrpCode) {
			    $out .= ' <font color=#666666>(<font color=#666699>'.$strGrpCode.'</font>)</font>';
			}
			$out .= '</td>';
			$out .= '</tr>';
// this should probably be a subroutine...
			if ($flagDisplayTogether) {
			  $txtBoth .= $out;
			} else {
			  if ($qtyStk > 0) {
			    $txtInStock .= $out;
			  } else {
			    $txtOutStock .= $out;
			  }
			}
		    }
//echo '<br>GOT TO #1 - qty='.$qtyStk;
		    if ($rsItems->Value('isForSale')) {
			$isItem = TRUE;
			$txtLine = $rsItems->Render_TableRow();

			if ($flagDisplayTogether) {
			    $txtBoth .= $txtLine;
			} else {
			    if ($qtyStk > 0) {
				$txtInStock .= $txtLine;
			    } else {
				$txtOutStock .= $txtLine;
			    }
			}
		    }
		}
	    }

	    if ($isItem) {
// format all the accumulated bits of text into one large string:
		$txtTblOpen = '<table class=main><tbody>';
		$txtTblFtr = '<tr><td colspan="4" align="right"><input value="Add to Cart" type="submit"></td></tr>';
		$txtTblShut = '</tbody></table>';

		if ($flagDisplayTogether) {
// Display in-stock and backordered items together
		    $out .= $txtTblOpen.$txtTblHdr.$txtBoth.$txtTblFtr.$txtTblShut;
		} else {
		    if ($txtInStock != '') {
			$txtClause = Pluralize($cntInStock,'This item is','These items are');
			$out .= $txtTblOpen;
			$out .= '<tr class=inStock><td colspan=5>'.$txtClause.' in stock:</td></tr>';
			$out .= $txtTblHdr.$txtInStock.$txtTblFtr.$txtTblShut;
		    }

		    if (!empty($txtOutStock)) {
			if (!empty($txtInStock)) {
			    $out .= '<p>';
			}
			$txtClause = Pluralize($cntOutStock,'This item is','These items are');

			$out .= $txtTblOpen;
			$out .= '<tr><td colspan=5>'.$txtClause.' <a href="'.KWP_HELP_NO_STOCK_BUT_AVAIL.'"><b>not in stock</b></a>';

			$txtClause = Pluralize($cntOutStock,'it','them');

			$out .= ', but we can (probably) <a href="'.KWP_HELP_POLICY_SHIP.'">get '.$txtClause.'</a>:</td></tr>';
			$out .= $txtTblHdr.$txtOutStock.$txtTblFtr.$txtTblShut;
		    } /**/
		}
	    } else {
		$out .= clsPageOutput::SectionHeader('This title is currently unavailable');
	    }
	    $out .= '</form>';
	}
	return $out;
    }
}
/*%%%%
  PURPOSE: extends clsImages to handle store UI interactions
*/
class clsImages_StoreUI extends clsImages {
    private $objPage;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsImage_StoreUI');
    }
    public function Page(clsVbzSkin $iPage=NULL) {
	if (!is_null($iPage)) {
	    $this->objPage = $iPage;
	}
	if (!is_object($this->objPage)) {
	    throw new exception('Internal error: must set page before trying to read it.');
	}
	return $this->objPage;
    }
}
class clsImage_StoreUI extends clsImage {
    /*-----
      ACTION: outputs a standalone page for larger image sizes - does not use skin
	(Was originally in clsImage, where skin was inaccessible.)
      HISTORY:
	2012-07-14 moved from clsImage to clsImage_StoreUI
	  Returns output instead of echoing it.
    */
    public function DoPage() {
	global $vbgImgSize;

	$objTitle = $this->Title();
	$strCatNum = $objTitle->CatNum();
	$strTitle = $objTitle->Name;
	$htTitleHref = $objTitle->Link();
	$htmlTitle = KS_STORE_NAME.' - '.$strCatNum.' &ldquo;'.$strTitle.'&rdquo;';
	$out = '<html><head><title>'.$htmlTitle.'</title></head>'
	    ."\n<body"
	    ."\n bgcolor=000044"
	    ."\n TEXT=CCFFFF"
	    ."\n LINK=33FF33"
	    ."\n VLINK=33CCFF"
	    ."\n ALINK=FFCC00"
	    ."\n TOPMARGIN=0"
	    ."\n LEFTMARGIN=0"
	    ."\n MARGINWIDTH=0"
	    ."\n MARGINHEIGHT=0"
	    .'>'
	    .'<center>'
	    .'<big>'.$htTitleHref.$strTitle.'</a></big><br><small>'.$strCatNum.' - title ID #'.$this->ID_Title.'</small>';

// show list of available image sizes (except th and sm)
	$objImgs = $this->ListImages_sameAttr();
	$strSizes = NULL;
	if ($objImgs->hasRows()) {
	    $strImgCount = 0;
	    while ($objImgs->NextRow()) {
		$strImgType = $objImgs->Ab_Size;
		if (!empty($strImgType)) {
		    if (($strImgType != 'th') && ($strImgType != 'sm')) {
			$strImgCount++;
			$strDesc = $vbgImgSize[$strImgType];
			if ($objImgs->ID == $this->ID) {
			    $strImgTag = '<b>'.$strDesc.'</b>';
			} else {
			    $strImgTag = $objImgs->Href(TRUE).$strDesc.'</a>';
			}
			if (!empty($strSizes)) {
			    $strSizes .= ' .. ';
			}
			$strSizes .= $strImgTag;
		    }
		}
	    }
	    if ($strImgCount > 1) {
		$ftSizes = '<tr>'
		  .'<td><font color=#aaaaaa>sizes</font> :</td>'
		  .'<td align=center>'.$strSizes.'</td>'
		  .'<td>: <font color=#aaaaaa>sizes</font></td>'
		  .'</tr>';
	    } else {
		$ftSizes = NULL;
	    }
	}

// show list of available images for this title at this size
	$strAttrs = NULL;
	$objImgs = $this->ListImages_sameSize();
//echo 'test';
	if ($objImgs->NextRow()) {
	    $intImgs = 0;
	    if ($objImgs->hasRows()) {
		while ($objImgs->NextRow()) {
		    $strImgFldr = $objImgs->AttrFldr;
		    $strImgDescr = $objImgs->AttrDispl;
		    $intImgs++;
		    if (!empty($strAttrs)) {
			$strAttrs .= ' .. ';
		    }
		    if ($objImgs->ID == $this->ID) {
			$strAttrs .= '<b>'.$strImgDescr.'</b>';
		    } else {
			$strAttrs .= $objImgs->Href(TRUE).$strImgDescr.'</a>';
		    }
		}
		if ($intImgs > 1) {
		    $ftAttrs = '<tr>'
		      .'<td><font color=#aaaaaa>views</font> :</td>'
		      .'<td align=center>'.$strAttrs.'</td>'
		      .'<td>: <font color=#aaaaaa>views</font></td>'
		      .'</tr>';
		} else {
		    $ftAttrs = NULL;
		}
	    }
	}
	if ((!empty($ftSizes)) || (!empty($ftAttrs))) {
	    $out .= '<table border=1><tr><td><table>';
	    $out .= $ftSizes.$ftAttrs;
	    $out .= '</table></td></tr></table>';
	} else {
	    $out .= '<br>';
	}
	$out .= $htTitleHref.'ordering page</a><br>';
	$out .= $this->Image_HTML();
	$out .= "\n</body>\n</html>";

	return $out;
    }
}