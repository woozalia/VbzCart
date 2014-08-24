<?php
/* ####
  FILE: page-search.php
  HISTORY:
    2012-07-13 extracting search page class from search/index.php
*/
/*
  CLASS: clsPageSearch
  PURPOSE: search catalog database
*/
class clsPageSearch extends clsVbzPage_Browse {
    private $arReq;
    private $strSearch;

    // ++ ABSTRACT OVERRIDES ++ //

    protected function BaseURL() {
	return KWP_SHOP_SEARCH;
    }
    protected function MenuPainter_new() {
	// this may need reorganization
    }
    protected function HandleInput() {
    	$this->DoContent();
	// this may need reorganization
    }
    protected function PreSkinBuild() {
	// this may need reorganization
    }
    protected function PostSkinBuild() {
	// this may need reorganization
    }

    // -- ABSTRACT OVERRIDES -- //

    protected function ParseInput() {
	//parent::ParseInput();
	$this->arReq = $_GET;
	//$this->strSearch = $this->SafeParam(nz($this->arReq['search']));
	$strSearchRaw = NzArray($this->arReq,'search');
	$this->strSearch = $this->Data()->SafeParam($strSearchRaw);

	// stuff that always gets set
	$this->Skin()->PageTitle('Catalog Search');
	//$this->Skin()->CtxtStr('');	// not sure what replaces this method
    }
/*
    public function TitleStr($iText=NULL) {
	return 'Catalog Search';
    }
    public function TCtxtStr($iText=NULL) {
	return '';
    }
    // 2013-11-15 not sure what this is/was used for
    protected function NameStr($iText=NULL) {
	return 'search the catalog';
    }
*/
/*
    protected function HandleInput() {
	parent::HandleInput();
	$this->strWikiPg	= 'search';
	$this->strTitle		= 'Catalog Search';
	$this->strName		=
	$this->strTitleContext	= '';
	//$this->strSideXtra	= '<dt><b>Cat #</b>: '.$this->strReq;
    }
*/
    public function DoContent() {
	$sSearch = $this->strSearch;
	$htSearch = htmlspecialchars($sSearch);
	$oSkin = $this->Skin();

	// search entry form
	$ht = <<<__END__
<table class=catalog-summary>
  <tr><td>
    <form method=get>Search for:
      <input size=50 name=search value="$sSearch">
      <input type=submit value="Go">
    </form>
  </td></tr>
</table>
__END__;
	//$tItems = $this->Data()->Items();

	$sSearch = $this->strSearch;

	if (empty($sSearch)) {
	    // nothing has been entered yet, so don't bother searching
	    // LATER: print instructions or stats or something
	    $ht .= 'Please enter some text to search for.';
	} else {
	    $tTitles = $this->Data()->Titles();
	    $arTi1 = $tTitles->SearchRecords_forText($sSearch);

	    $tTopics = $this->Data()->Topics();
	    //$htTo = $tTopics->DoSearch($sSearch,'',', ');
	    $rsTo = $tTopics->SearchRecords_forText($sSearch);

	    // LIST TOPICS FOUND

	    if (is_null($rsTo) || ($rsTo->RowCount() == 0)) {
		$ht = $oSkin->SectionHdr('No matching topics found.');
		$arTi = $arTi1;	// final list is title matches only
	    } else {
		$htTo = NULL;
		while ($rsTo->NextRow()) {
		    if (!is_null($htTo)) {
			$htTo .= '; ';
		    }
		    $htTo .= $rsTo->ShopLink_name();
		}
		$ht = $oSkin->SectionHdr('&darr; Found Topics')
		  .'<p class="catalog-summary">'.$htTo.'</p>';

		// FIND TITLES FOR FOUND TOPICS

		$rsTi = $rsTo->TitleRecords_forRows();
		$arTi2 = $rsTi->asKeyedArray();
		$arTi = clsArray::Merge($arTi1,$arTi2);	// merge titles-found and titles-for-topics-found
	    }

	    if (count($arTi) == 0) {
		$ht .= 'No matches found.<br>';
	    } else {
		$arTiAvail = $arTiInact = array();
		foreach ($arTi as $id => $arRow) {
		    $rsTi->Values($arRow);
		    if ($rsTi->IsForSale()) {
			$arTiAvail[$id] = $arRow;
		    } else {
			$arTiInact[$id] = $arRow;
		    }
		}

		// SHOW ACTIVE TITLES (thumbnails and text)

		$htImgAvail = NULL;
		$htTitles = NULL;
		foreach ($arTiAvail as $id => $arRow) {
		    $rsTi->Values($arRow);

		    // build thumbnail display
		    $htImgAvail .= $rsTi->RenderImages_forRow(clsImages::SIZE_THUMB);

		    // build text listing also
		    $sName = $rsTi->NameStr();
		    $sCatNum = $rsTi->CatNum();
		    $htLink = $rsTi->ShopLink($sCatNum);
		    $sStats = $tTitles->StatString_forTitle($id);
		    $htTitles .= "\n$htLink &ldquo;$sName&rdquo; - $sStats<br>";
		}
		if (is_null($htImgAvail)) {
		    $ht .= $oSkin->SectionHdr('No active titles found.');
		} else {
		    $ht .= $oSkin->SectionHdr('&darr; Found Titles - Available')
		    .'<p class="catalog-summary">'
		    .$htTitles
		    .$htImgAvail
		    .'</p>';
		}

		// SHOW INACTIVE TITLES (text only)

		$htTitles = NULL;
		foreach ($arTiInact as $id => $arRow) {
		    $rsTi->Values($arRow);
		    $sName = $rsTi->NameStr();
		    $sCatNum = $rsTi->CatNum();
		    $htLink = $rsTi->ShopLink($sCatNum);
		    // TODO
		    $htTitles .= "\n$htLink &ldquo;$sName&rdquo;<br>";
		}
		if (!is_null($htTitles)) {
		    $ht .= $oSkin->SectionHdr('&darr; Found Titles - <b>Not</b> Available')
		      .'<p class="catalog-summary"><small>'
		      .'These titles are <b>not</b> currently available:<br>'
		      .$htTitles
		      .'</small>'
		      .'</p>'
		      ;
		}
	    }
	}
	$oSkin->Content('main',$ht);
    }
}
