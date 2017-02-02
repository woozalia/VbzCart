<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Supplier UI (non-CMS)
  HISTORY:
    2013-11-13 extracted from page-cat.php
    2016-01-22 revamping the URL-to-page lookup process to be more sensible and dynamic
*/

/*::::
  PURPOSE: extends vctSuppliers to handle store UI interactions
*/
class vctSuppliers_shop extends vctSuppliers {
    use ftCacheableTable;
    use vtTableAccess_ItemType;

    // ++ OVERRIDES ++ //
    
    protected function SingularName() {
	return 'vcrSupplier_shop';
    }

    // -- OVERRIDES -- //
    // ++ FRAMEWORK ++ //
    
    protected function PageObject() {
	return fcApp::Me()->GetPageObject();
    }
    
    // -- FRAMEWORK -- //
    // ++ RECORDS ++ //
    
    // PUBLIC so Page object can call it
    public function LookupExhibitRecord(array $arThap) {
	
	// get the Supplier key:
	$sSupp = array_pop($arThap);
	// get the Supplier:
	$rcSupp = $this->GetRecord_byCatKey($sSupp);
	if ($rcSupp->HasRows()) {
	    $anyMore = count($arThap) > 0;
	    if ($anyMore) {
		// pass on the exhibit search to the found supplier:
		$rcExh = $rcSupp->LookupExhibitRecord($arThap);
	    } else {
		// end of request, so we've found the target exhibit
		$rcExh = $rcSupp;
	    }
	} else {
	    $this->PageObject()->AddErrorMessage("Not sure what page you're looking for; there is no supplier \"$sSupp\".");
	    $rcExh = NULL;
	}
	return $rcExh;
    }

    // -- RECORDS -- //
    // ++ WEB PAGES ++ //

    /*----
      RENDERS: catalog home page, i.e. list of suppliers and a summary of what they have
      HISTORY:
	2013-11-21 rewritten to use Skin, not Doc
      FIELDS NEEDED: CatKey, Name, ItemType, ID, ItemCount
    */
    public function DoHomePage() {
	$rc = $this->DataSet_forStore();
	$ht = NULL;
	if ($rc->hasRows()) {
	    $oPage = $this->PageObject();
	    $oPage->RenderSectionHeader('Suppliers');

	    // accumulate data

	    $sKeyLast = NULL;
	    $ar = NULL;
	    $tItTyps = $this->ItemTypeTable();
	    $tSupps = $this;
	    while ($rc->NextRow()) {
		$idItTyp = $rc->GetFieldValue('ID_ItTyp');
		if ($idItTyp == 0) {
		    // 2016-12-04 For now, we have to let this pass until we can get the admin pages back up.
		    // TODO: reinstate this error -- or at least log it
		    //echo fcArray::Render($rc->GetFieldValues());
		    //throw new exception("Apparently an Item has an Item Type of zero.");
		} else {
		    $rcItTyp = $tItTyps->GetRecord_Cached($idItTyp);
		    if (!$rcItTyp->HasRow()) {
			throw new exception("No ItemType found for ID=$idItTyp.");
		    }
		    $idSupp = $rc->GetFieldValue('ID_Supp');
		    $rcSupp = $tSupps->GetRecord_Cached($idSupp);
		    $sSuppKey = $rcSupp->GetFieldValue('CatKey');

		    if ($sSuppKey != $sKeyLast) {
			// supplier has changed
			$sKeyLast = $sSuppKey;
			
			//$sKeyLink = strtolower($sSuppKey).'/';
			//$url = $oPage->BaseURL_rel().$sKeyLink;
			//$htLink = '<b><a href="'.$url.'">'.$rc->Value('Name').'</a></b>';
			$htLink = $rcSupp->ShopLink_name();
			$ar[$sSuppKey] = array(
			  1 => ($htLink),
			  2 => NULL
			  );
			$isFirst = TRUE;
		    } else {
			$isFirst = FALSE;
		    }

		    $nCount = $rc->GetFieldValue('QtyForSale');
		    $sItType = $rcItTyp->QuantityName($nCount);
		    if ($sItType == '') {
			$sItType = '?id'.$rc->KeyString();
		    }
		    $ar[$sSuppKey][2]  .= ($isFirst?'':', ')." <b>$nCount</b> $sItType";
		}
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

    // -- WEB PAGES -- //

}

class vcrSupplier_shop extends vcrSupplier {
    use vtFrameworkAccess;
    use vtrSupplierShop;

    // ++ FIELD CALCULATIONS ++ //

    public function SelfLink($sText=NULL) {
	throw new exception('2016-12-02 SelfLink() is deprecated; call ShopLink().');
	if (is_null($sText)) {
	    $sText = $this->NameString();
	}
	$url = $this->ShopURL();
	$out = "<a href='$url'>$sText</a>";
	return $out;
    }
    
    public function Link() {
	throw new exception('2016-12-03 Link() is deprecated; call ShopLink().');
    }
    public function URL() {
	throw new exception('2016-11?-?? URL() deprecated; call ShopURL() instead.');
	//return KWP_CAT_REL.strtolower($this->CatKey()).'/';
    }

    // -- FIELD CALCULATIONS -- //
    // ++ CLASS NAMES ++ //
    
    protected function DepartmentsClass() {
	return KS_CLASS_SHOP_DEPARTMENTS;
    }
    protected function TitlesClass() {
	return 'vctShopTitles';
    }
    
    // -- CLASS NAMES -- //
    // ++ RECORDS ++ //

    // PUBLIC so Table object can call it
    public function LookupExhibitRecord(array $arThap) {
	$sNext = array_pop($arThap);	// might be Dept or Title
	$sFork = NULL;
	$anyMore = count($arThap) > 0;
	// look for a Department:
	$rcDept = $this->GetDepartmentRecord_byCatKey($sNext);
	if ($rcDept->HasRows()) {
	    if ($anyMore) {
		// pass on remainder of request to found Department
		$rcExh = $rcDept->LookupExhibitRecord($arThap);
		//$sFork = 'requesting lookup from Department';
	    } else {
		// we've found the target exhibit
		$rcExh = $rcDept;
		//$sFork = 'using Department as exhibit';
	    }
	} else {
	    // no matching Department, so try Titles:
	    $rcTitle = $this->GetTitleRecord_byCatKey($sNext);
	    if ($rcTitle->HasRows()) {
		if ($anyMore) {
		    $rcExh = $rcTitle->LookupExhibitRecord($arThap);
		    //$sFork = 'requesting lookup from Title';
		} else {
		    $sql = $this->GetConnection()->sql;
		    // we've found the target exhibit
		    $rcExh = $rcTitle;
		    //$sFork = 'using Title as exhibit';
		}
	    } else {
		// can't find what they're looking for, so show the Supplier page and an error message:
		$htSupp = $this->ShopLink($this->CatKey());
		$sMsg = <<<__END__
We're not sure what page you're looking for; supplier $htSupp has no department or title abbreviated "$sNext". Here's what we do have:
__END__;
		$this->PageObject()->AddErrorMessage($sMsg);
		$rcExh = $this;	// page to display
		//$sFork = 'looking up Department';
	    }
	}
	/* TODO: log this as an error
	if (is_null($rcExh)) {
	    throw new exception("Internal error: Exhibit lookup for [$sNext] failed when $sFork.");
	}*/
	return $rcExh;
    }
    
    // -- RECORDS -- //
    // ++ WEB PAGES ++ //

    public function ExhibitSuperTitle() {
	return 
	  '<a href="'.KWP_CAT_REL
	  .'">Suppliers</a>: <b>'.$this->NameString().'</b>:'
	  ;
    }
    public function ExhibitMainTitle() {
	return $this->NameString();
    }
    public function ExhibitContent() {
	return $this->DoDeptsPage();
    }
    
    /*----
      RENDERS: list of Departments for this Supplier
      HISTORY:
	2012-05-10 extracted from clsSuppliers and renamed from DeptsPage_forStore() to DoDeptsPage()
	2012-05-11 no longer returns rendered output, but leaves it in Doc() object
    */
    public function DoDeptsPage() {
	$ar = $this->PageData_forStore();
	$out =
	  $this->Render_Supp_ItTyps($ar)
	  .$this->PageObject()->RenderSectionHeader('Departments:')
	  .$this->Render_Dept_ItTyps($ar)
	  ;
	return $out;
    }

    // -- WEB PAGES -- //
    // ++ WEB PAGE SECTIONS ++ //

    /*----
      ACTION: render HTML description of all item types available for this Supplier
      HISTORY:
	2013-11-18 written to eliminate use of cached table(s)
    */
    protected function Render_Supp_ItTyps(array $ar) {
	$arIT = $ar['it'];
	$arS = $ar['s-it'];

	if (is_null($arS)) {
	    $sName = $this->NameString();
	    $out = "Nothing by $sName is currently available.";
	} else {
	
	    // blank object for Item Types
	    $oIT = $this->ItemTypeTable()->SpawnRecordset();

	    $out = "\n<table class='catalog-summary'><tr><td>";

	    $htIT = NULL;
	    foreach ($arS as $id => $cnt) {
		if ($cnt > 0) {
		    $oIT->SetFieldValues($arIT[$id]);
		    $sIT = $oIT->QuantityName($cnt);
		    if (!is_null($htIT)) {
			$htIT .= ', ';
		    }
		    $htIT .= '<b>'.$cnt.'</b> '.$sIT;
		}
	    }
	    $out .= "\n$htIT\n</td></tr></table>";
	}
	return $out;
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

	if (is_null($arDIT)) {
	    $sName = $this->NameString();
	    $out = "$sName has no departments.";	// maybe remove this message depending on how it looks
	} else {
	
	    $oIT = $this->ItemTypeTable()->SpawnRecordset();
	    $oD = $this->DepartmentTable()->SpawnRecordset();

	    $urlSupp = $this->ShopURL();

	    $out = "\n<table class='catalog-summary'>";
	    $isOdd = FALSE;
	    foreach ($arDIT as $idDept => $arItTyps) {
		$isOdd = !$isOdd;

		$htRow = '';
		foreach ($arItTyps as $id => $cnt) {
		    if ($cnt > 0) {
			$oIT->SetFieldValues($arIT[$id]);
			$sIT = $oIT->QuantityName($cnt);
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
		$sPgKey = $arDept['PageKey'];
		if (is_null($sPgKey)) {
		    $sPgKey = $arDept['CatKey'];
		}
		if (is_null($sPgKey)) {
		    $uriPgKey = '';
		} else {
		    $uriPgKey = strtolower($sPgKey).'/';
		}
		$sName = $arDept['Name'];
		$urlDept = $urlSupp.$uriPgKey;
		$htDept = "<a href=\"$urlDept\">$sName</a>";
		$out .= "\n<tr class='$htCSS'><td>$htDept</td><td>$htRow</td></tr>";
	    }

	    $out .= "\n</table>";
	}
	return $out;
    }

    // -- WEB PAGE SECTIONS -- //
}
