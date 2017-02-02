<?php
/*
  PURPOSE: Title classes that retrieve additional information
  HISTORY:
    2013-12-15 extracted from dropins/cat-local/title.php
      We need to know which of these are actually used, and from where.
    2015-09-06 Currently, none of these are being used -- the file is not referenced from index.dropin.php
*/
/*====
  PURPOSE: VbzAdminTitles with additional catalog information
*/
class VCA_Titles_info_Cat extends VbzAdminTitles {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('qryCat_Titles');
    }
    public function Search_forText_SQL($iFind) {
	$sqlFind = '"%'.$iFind.'%"';
	return "(Name LIKE $sqlFind) OR (Descr LIKE $sqlFind) OR (Search LIKE $sqlFind) OR (CatNum LIKE $sqlFind)";
    }
    public function SearchPage() {
	global $wgOut,$wgRequest;
	global $vgPage;

	$strThis = 'SearchCatalog';

	$strForm = $wgRequest->GetText('form');
	$doForm = ($strForm == $strThis);

	$strField = 'txtSearch-'.$strThis;
	$strFind = $wgRequest->GetText($strField);
	$htFind = '"'.htmlspecialchars($strFind).'"';

	$vgPage->UseHTML();
	$out = "\n<h2>Catalog Search</h2>"
	  ."\n<form method=post>"
	  .'Search for:'
	  ."\n<input name=$strField size=40 value=$htFind>"
	  ."\n<input type=hidden name=form value=$strThis>"
	  ."\n<input type=submit name=btnSearch value=Go>"
	  ."\n</form>";
	$wgOut->AddHTML($out); $out = '';

	if ($doForm && !empty($strFind)) {
	    $tblTitles = $this;
	    $tblItems = $this->Engine()->Items();
	    $tblImgs = $this->Engine()->Images();

	    $arTitles = NULL;

	    $rs = $tblTitles->Search_forText($strFind);
	    if ($rs->HasRows()) {
		while ($rs->NextRow()) {
		    $id = $rs->ID;
		    $arTitles[$id] = $rs->Values();
		}
	    }

	    $out .= "<br><b>Searching catalog for</b> $htFind:<br>";
	    $wgOut->AddHTML($out); $out = '';

	    $rs = $tblItems->Search_byCatNum($strFind);
	    if (is_object($rs)) {
		while ($rs->NextRow()) {
		    $id = $rs->Value('ID_Title');
		    if (!isset($arTitles[$id])) {
			$obj = $tblTitles->GetItem($id);
			$arTitles[$id] = $obj->Values();
		    }
		}
	    }

	    if (!is_null($arTitles)) {
		if (empty($obj)) {
		    $obj = $tblTitles->SpawnItem();
		}
		$out .= '<table align=left style="border: solid black 1px;"><tr><td>';
		$ftImgs = '';
		$isFirst = TRUE;
		foreach ($arTitles as $id => $row) {
		    $obj->Values($row);
		    $txtCatNum = $obj->CatNum();
		    $txtName = $obj->Value('Name');
		    if ($isFirst) {
			$isFirst = FALSE;
		    } else {
			$out .= "\n<br>";
		    }
		    $htLink = $obj->AdminLink($txtCatNum);
		    $out .= $htLink.' '.$txtName;

		    $txtTitle = $txtCatNum.' &ldquo;'.htmlspecialchars($txtName).'&rdquo;';
		    $ftImg = $tblImgs->Thumbnails($id,array('title'=>$txtTitle));
		    $ftImgs .= '<a href="'.$obj->AdminURL().'">'.$ftImg.'</a>';
		}
		$out .= '</td></tr></table>'.$ftImgs;
	    }

	    $wgOut->AddHTML($out); $out = '';
	}

	return $out;
    }
}
/*====
  PURPOSE: VbzAdminTitles with additional item (and stock) information
*/
class VbzAdminTitles_info_Item extends VbzAdminTitles {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('qryTitles_Item_info');
	  $this->ClassSng('VbzAdminTitle_info_Item');
    }
    public function Listing_forDept($iDeptObj) {
	global $wgOut;
	global $vgPage;
	global $sql;

	$vgPage->UseHTML();

	$objDept = $iDeptObj;
	$idDept = $objDept->ID;
//	$strSuppKey = strtolower($objSupp->CatKey);
//	$objRecs = $this->GetData('ID_Dept='.$idDept,'VbzAdminTitle','CatKey');
	//$objRecs = $this->DataSQL('SELECT t.ID_Title AS ID, t.* FROM qryCat_Titles_Item_stats AS t WHERE t.ID_Dept='.$idDept);
	$objRecs = $this->GetData('ID_Dept='.$idDept,NULL,'CatKey');

	$out = $objRecs->AdminList();
	$wgOut->addHTML($out);
    }
}
class VbzAdminTitle_info_Item extends VbzAdminTitle {
    protected $arBins;
    /*----
      ACTION: Renders a summary of dataset by location
    */
    public function AdminSummary() {
	$out = NULL;
	$arBins = NULL;
	$arTitleBins = NULL;
	if ($this->HasRows()) {
	    while ($this->NextRow()) {	// titles
		$arTitle = NULL;
		$rsItems = $this->Items();
		while ($rsItems->NextRow()) {	// items
		    $rsStock = $rsItems->Data_forStkItems();
		    if ($rsStock->HasRows()) {
			while ($rsStock->NextRow()) {	// stock items
			    if ($rsStock->Qty_inStock() > 0) {
				$idBin = $rsStock->Value('ID_Bin');
				$arBins[$idBin] = NzArray($arBins,$idBin)+1;
				$arTitle[$idBin] = NzArray($arTitle,$idBin)+1;
			    }
			}	// /stock items
		    }
		}	// /items
		$idTitle = $this->KeyValue();
		$arThis = NzArray_debug($arTitleBins,$idTitle);
		if (is_array($arThis)) {
		    $arTitleBins[$idTitle] = array_merge($arTitle,$arThis);
		} else {
		    if (is_array($arTitle)) {
			$arTitleBins[$idTitle] = $arTitle;
		    }
		}
	    }	// /titles
	}
	$this->StartRows();	// rewind the main recordset so it can be used again

	$arPlaces = NULL;
	foreach ($arBins as $idBin => $cnt) {
	    $rcBin = $this->Engine()->Bins($idBin);
	    if ($rcBin->IsRelevant()) {
		$idPlace = $rcBin->PlaceID();
		$arPlaces[$idPlace][$idBin] = $arBins[$idBin];
	    }
	}

	$out .= "\n<table>";
	foreach ($arPlaces as $idPlace => $arBins) {
	    $rcPlace = $this->Engine()->Places($idPlace);
	    $out .= "\n<tr><td align=right>".$rcPlace->AdminLink_name().':</td><td>';
	    foreach ($arBins as $idBin => $cnt) {
		$rcBin = $this->Engine()->Bins($idBin);
		$out .= ' '.$rcBin->AdminLink_name().':'.$cnt;
	    }
	    $out .= "</td></tr>";
	}
	$out .= "\n</table>";
//$out .= 'ARBINS:<pre>'.print_r($arTitleBins,TRUE).'</pre>';
	$this->arBins = $arTitleBins;	// save bins-for-titles data
	return $out;
    }
    /*----
      RETURNS: listing of titles in the current dataset
      HISTORY:
	2010-11-16 "in print" column now showing cntInPrint instead of cntForSale
    */
    public function AdminList(array $iarArgs=NULL) {
	$objRecs = $this;

	if ($objRecs->HasRows()) {
	    if (empty($this->arBins)) {
		$htBinHdr = NULL;
		$doBins = FALSE;
	    } else {
		$htBinHdr = '<th>Bins</th>';
		$doBins = TRUE;
	    }
	    $out = "\n<table class=sortable>"
	      ."\n<tr>"
		.'<th>ID</th>'
		.'<th>Name</th>'
		.'<th>Cat #</th>'
		.'<th><small>CatKey</small></th>'
		.'<th>SCat#</th>'
		.'<th>When Added</th>'
		.'<th title="number of item records"><small>item<br>recs</small></th>'
		.'<th title="number of items in print"><small>items<br>in print</small></th>'
		.'<th>stk<br>qty</th>'
		.$htBinHdr
	      .'</tr>';
	    $isOdd = TRUE;
	    while ($objRecs->NextRow()) {
		$ftID = $objRecs->AdminLink();
		$ftName = $objRecs->Name;
		$ftCatNum = $objRecs->CatNum();
		$ftCatKey = $objRecs->Row['CatKey'];
		$ftSCatNum = $objRecs->Row['Supplier_CatNum'];
		$ftWhen = $objRecs->DateAdded;

		// FUTURE: If we're using this on a dataset which does not have these fields,
		//	test for them and then retrieve them the slow way if not found.
		$qtyStk = $objRecs->qtyForSale;
		//$cntAva = $objRecs->cntForSale;
		$cntPrn = $objRecs->cntInPrint;
		$cntItm = $objRecs->cntItems;

		$isActive = (($qtyStk > 0) || ($cntPrn > 0));
		$isPassive = (nz($cntItm) == 0);

		$isOdd = !$isOdd;
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';

		if ($isActive) {
		    $wtStyle .= ' font-weight: bold;';
		}
		if ($isPassive) {
		    $wtStyle .= ' color: #888888;';
		}

		$htBins = NULL;
		$htBinCell = NULL;
		if ($doBins) {
		    $id = $this->KeyValue();
		    if (array_key_exists($id,$this->arBins)) {
			$arBins = $this->arBins[$id];
			foreach ($arBins as $idBin => $cnt) {
			    $rcBin = $this->Engine()->Bins($idBin);
			    if ($rcBin->IsRelevant()) {
				$htBins .= ' '.$rcBin->AdminLink_name().'('.$cnt.')';
			    }
			}
			$htBinCell = "<td>$htBins</td>";
		    }
		}

		$out .= "\n<tr style=\"$wtStyle\">"
		  ."<td>$ftID</td>"
		  ."<td>$ftName</td>"
		  ."<td>$ftCatNum</td>"
		  ."<td>$ftCatKey</td>"
		  ."<td>$ftSCatNum</td>"
		  ."<td>$ftWhen</td>"
		  ."<td align=right>$cntItm</td>"
		  ."<td align=right>$cntPrn</td>"
		  ."<td align=right>$qtyStk</td>"
		  .$htBinCell
		  .'</tr>';
	    }
	    $out .= "\n</table>";
	} else {
	    if (isset($iarArgs['none'])) {
		$out .= $iarArgs['none'];
	    } else {
		$out .= 'No titles found.';
	    }
	}
	return $out;
    }
}
