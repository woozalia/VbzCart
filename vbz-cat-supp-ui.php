<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Supplier UI (non-CMS)
  HISTORY:
    2013-11-13 extracted from page-cat.php
*/
/*%%%%
  PURPOSE: extends clsSuppliers to handle store UI interactions
*/
class clsSuppliers_StoreUI extends clsSuppliers {
    private $objPage;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('clsSupplier_StoreUI');
    }

    // OBJECT ACCESS

    public function Page(clsVbzPage $iPage=NULL) {
	if (!is_null($iPage)) {
	    $this->objPage = $iPage;
	}
	if (!is_object($this->objPage)) {
	    throw new exception('Internal error: must set page before trying to read it.');
	}
	return $this->objPage;
    }

    // ACTIONS

    /*----
      HISTORY:
	2013-11-21 rewritten to use Skin, not Doc
    */
    public function DoHomePage() {
	$rc = $this->DataSet_forStore();
	$ht = NULL;
	if ($rc->hasRows()) {
	    $oPage = $this->Page();
	    $oSkin = $oPage->Skin();
	    $oSkin->SectionHdr('Suppliers');

	    // accumulate data

	    $sKeyLast = NULL;
	    $ar = NULL;
	    while ($rc->NextRow()) {
		$sSuppKey = $rc->Value('CatKey');

		if ($sSuppKey != $sKeyLast) {
		    // supplier has changed
		    $sKeyLast = $sSuppKey;
		    $sKeyLink = strtolower($sSuppKey).'/';
		    $ar[$sSuppKey] = array(
		      1 => ('<b><a href="'.$sKeyLink.'">'.$rc->Value('Name').'</a></b>'),
		      2 => NULL
		      );
		    $isFirst = TRUE;
		} else {
		    $isFirst = FALSE;
		}

		$sItType = $rc->Value('ItemType');
		if ($sItType == '') {
		    $sItType = '?id'.$rc->KeyString();
		}
		$ar[$sSuppKey][2]  .= ($isFirst?'':', ').' <b>'.$rc->Value('ItemCount').'</b> '.$sItType;
	    }

	    // display the results

	    $ht = "\n<table class='catalog-summary'>";
	    foreach ($ar as $idSupp => $arRow) {
		$ht .= "\n<tr>";
		foreach ($arRow as $htCell) {
		    $ht .= "\n\t<td>$htCell</td>";
		}
		$ht .= "\n</tr>";
	    }
	    $ht .= "\n</table>";

	    return $ht;
	} else {
	    throw new exception('The catalog appears to be empty.');
	}
    }

/*
// 2013-11-21 original version
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
*/
}

class clsSupplier_StoreUI extends clsSupplier {

    // ACTIONS

    /*----
      HISTORY:
	2012-05-10 extracted from clsSuppliers and renamed from DeptsPage_forStore() to DoDeptsPage()
	2012-05-11 no longer returns rendered output, but leaves it in Doc() object
    */
    public function DoDeptsPage() {
	$ar = $this->PageData_forStore();
	$out =
	  $this->Render_Supp_ItTyps($ar)
	  .$this->Engine()->App()->Skin()->SectionHdr('Departments:')
	  .$this->Render_Dept_ItTyps($ar)
	  ;
	return $out;
    }
    /*----
      ACTION: render HTML description of all item types available for this Supplier
      HISTORY:
	2013-11-18 written to eliminate use of cached table(s)
    */
    protected function Render_Supp_ItTyps(array $ar) {
	$arIT = $ar['it'];
	$arS = $ar['s-it'];

	// blank object for Item Types
	$oIT = $this->Engine()->ItTyps()->SpawnItem();

	$ht = "\n<table class='catalog-summary'><tr><td>";

	$htIT = NULL;
	foreach ($arS as $id => $cnt) {
	    if ($cnt > 0) {
		$oIT->Values($arIT[$id]);
		$sIT = $oIT->Name($cnt);
		if (!is_null($htIT)) {
		    $htIT .= ', ';
		}
		$htIT .= '<b>'.$cnt.'</b> '.$sIT;
	    }
	}
	$ht .= "\n$htIT\n</td></tr></table>";
	return $ht;
    }
    /*----
      ACTION: render HTML description of all item types available
	for each Department in this Supplier
      HISTORY:
	2013-11-18 written as a replacement for DoPiece_Dept_ItTyps()
	  to eliminate use of cached table(s)
    */
    protected function Render_Dept_ItTyps(array $ar) {
	$arIT = $ar['it'];
	$arDIT = $ar['d-it'];
	$arD = $ar['d'];

	$oIT = $this->Engine()->ItTyps()->SpawnItem();
	$oD = $this->Engine()->Depts()->SpawnItem();

	$urlSupp = $this->URL_rel();

	$ht = "\n<table class='catalog-summary'>";
	$isOdd = FALSE;
	foreach ($arDIT as $idDept => $arItTyps) {
	    $isOdd = !$isOdd;

	    $htRow = '';
	    foreach ($arItTyps as $id => $cnt) {
		if ($cnt > 0) {
		    $oIT->Values($arIT[$id]);
		    $sIT = $oIT->Name($cnt);
		    if ($htRow != '') {
			$htRow .= ', ';
		    }
		    $htRow .= '<b>'.$cnt.'</b> '.$sIT;
		}
	    }
	    if ($isOdd) {
		$htCSS = 'catalog-stripe';
	    } else {
		$htCSS = 'catalog';
	    }
	    $arDept = $arD[$idDept];
	    $urlDept = $urlSupp.$arDept['PageKey'].'/';
	    $sName = $arDept['Name'];
	    $htDept = "<a href=\"$urlDept\">$sName</a>";
	    $ht .= "\n<tr class='$htCSS'><td>$htDept</td><td>$htRow</td></tr>";
	}

	$ht .= "\n</table>";
	return $ht;
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
