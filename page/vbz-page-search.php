<?php
/* ####
  FILE: page-search.php
  HISTORY:
    2012-07-13 extracting search page class from search/index.php
    2017-05-14 renamed from clsPageSearch -> vcPageSearch
*/
/*::::
  PURPOSE: search catalog database
*/
class vcPageSearch extends vcPage_shop {
    use ftFrameworkAccess;

    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forTagHTML() : string {
	return 'vcTag_html_search';
    }
    /*
    // CEMENT
    protected function Class_forPageContent() {
	return 'vcPageContent_search';
    }*/
    
    // -- SETUP -- //
    // ++ CEMENTING ++ //

    /*
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
    protected function MenuHome_new() {
	// this too
    }
    protected function ParseInput() {
	//parent::ParseInput();
	//$this->arReq = $_GET;
	//$this->strSearch = $this->SafeParam(nz($this->arReq['search']));
	//$strSearchRaw = clsArray::Nz($this->arReq,'search');
	//$this->strSearch = $this->Data()->SafeParam($strSearchRaw);

	// stuff that always gets set
	$this->GetSkinObject()->SetPageTitle('Catalog Search');
	//$this->Skin()->CtxtStr('');	// not sure what replaces this method
    }

    // -- CEMENTING -- //
    // ++ INTERNAL DATA ++ //
    
    protected function RequestValue_Search() {
	return $this->HTTP_RequestObject()->GetText('search');
    }
    
    // -- INTERNAL DATA -- //
    // ++ TABLES ++ //
    
    protected function ImageInfoQuery() {
	return $this->DatabaseObject()->MakeTableWrapper('vcqtImagesInfo');
    } */
    
    // -- TABLES -- //
    // ++ RENDERING ++ //

    /* 2017-05-27 old; moved to vcPageContent_search and updated
    public function DoContent() {
	$oSkin = $this->GetSkinObject();

	// search entry form
	$ht = $this->RenderSearchForm();
	//$tItems = $this->Data()->Items();

	$sSearch = $this->RequestValue_Search();

	if (empty($sSearch)) {
	    // nothing has been entered yet, so don't bother searching
	    // LATER: print instructions or stats or something
	    $ht .= 'Please enter some text to search for.';
	} else {
	    $tTitles = $this->TitleInfoQuery();
	    //$arTi1 = $tTitles->SearchRecords_forText($sSearch);
	    $rsTi = $tTitles->Search_forText($sSearch);
	    $arTi = $rsTi->asKeyedArray();
	    
	    $tTopics = $this->TopicTable();
	    //$htTo = $tTopics->DoSearch($sSearch,'',', ');
	    $rsTo = $tTopics->Search_forText($sSearch);

	    // LIST TOPICS FOUND AS TEXT

	    if (is_null($rsTo) || ($rsTo->RowCount() == 0)) {
		$ht = $oSkin->SectionHdr('No matching topics found.');
	    } else {
		$htTo = NULL;
		while ($rsTo->NextRow()) {
		    if (!is_null($htTo)) {
			$htTo .= ' - ';
		    }
		    $htTo .= $rsTo->ShopLink_name();
		    $sName = $rsTo->NameString();
		    if (strpos($sName,$sSearch) === FALSE) {
			
			$sFnd = $rsTo->VariantsText();
			if (strpos($sFnd,$sSearch) !== FALSE) {
			    $htTo .= " (aka: $sFnd)";
			}
			$sFnd = $rsTo->WrongText();
			if (strpos($sFnd,$sSearch) !== FALSE) {
			    $htTo .= " (as: $sFnd)";
			}

		    }
		}
		$ht = $oSkin->SectionHdr('&darr; Found Topics')
		  .'<p class="catalog-summary">'.$htTo.'</p>';

		// FIND TITLES FOR FOUND TOPICS

		$arTixTo = $rsTo->TitleIDs_forRows_Array();	// Titles from Topics
		// merge Titles-from-Topics list into Titles array:
		if (is_array($arTixTo)) {
		    foreach ($arTixTo as $idTitle) {
			if (!array_key_exists($idTitle,$arTi)) {
			    $arTi[$idTitle] = NULL;	// Title is in list, but we have no data for it
			}
		    }
		}
		//$arTiFinal = clsArray::Merge($arTi,$arTixTo);	// merge titles-found and titles-for-topics-found
	    }

	    if (count($arTi) == 0) {
		$ht .= 'No matches found.<br>';
	    } else {
	    
		// PROCESS ALL TITLES
	    		
		// -- get thumbnails for all titles
		
		// build SQL list of Title IDs
		$sqlIDs = NULL;
		foreach ($arTi as $id => $rec) {
		    $sqlIDs .= $id.',';
		}
		$sqlIDs = rtrim($sqlIDs,',');	// remove trailing comma
		
		$rsTi = $tTitles->ExhibitRecords("t.ID IN ($sqlIDs)");
		$rsIm = $this->ImageInfoQuery()->GetRecords_forThumbs("ID_Title IN ($sqlIDs)");
		$arIm = $rsIm->Collate_byTitle();
		
		// -- sort titles by status (active/retired)
		
		$arTiAct = $arTiRet = NULL;
		while ($rsTi->NextRow()) {
		    $arRow = $rsTi->GetFieldValues();
		    $id = $arRow['ID'];
		    if ($rsTi->IsForSale()) {
			$arTiAct[$id] = $arRow;
		    } else {
			$arTiRet[$id] = $arRow;
		    }
		}

		// RENDER ACTIVE TITLES (thumbnails and text)
		$htImgAct = NULL;
		$htTiAct = NULL;
		foreach ($arTiAct as $id => $arRow) {
		    //$this->ClearFields();	// 2016-11-11 no idea what this is supposed to do
		    $rsTi->SetFieldValues($arRow);

		    // build thumbnail display
		    if (fcArray::Exists($arIm['data'],$id)) {
			// if there are any images...
			$arImRow = $arIm['data'][$id]['@img'];
			$htImgAct .= $rsTi->RenderThumbs_forRow($arImRow);
		    }

		    $htTiAct .= $rsTi->SummaryLine_HTML_line();
		}
		
		// RENDER INACTIVE TITLES (text only)

		$htTiRet = NULL;
		if (is_array($arTiRet)) {
		    foreach ($arTiRet as $id => $arRow) {
			$rsTi->Values($arRow);
			$sName = $rsTi->NameString();
			$sCatNum = $rsTi->CatNum();
			$htLink = $rsTi->ShopLink($sCatNum);
			// TODO
			$htTiRet .= "\n$htLink &ldquo;$sName&rdquo;<br>";
		    }
		}
		
		// OUTPUT RESULTS
		
		if (is_null($htTiAct)) {
		    $ht .= $oSkin->SectionHdr('No active titles found.');
		} else {
		    $ht .= $oSkin->SectionHdr('&darr; Found Titles - Available')
		    .'<p class="catalog-summary">'
		    .$htTiAct
		    .$htImgAct
		    .'</p>';
		}

		if (!is_null($htTiRet)) {
		    $ht .= $oSkin->SectionHdr('&darr; Found Titles - <b>Not</b> Available')
		      .'<p class="catalog-summary"><small>'
		      .'These titles are <b>not</b> currently available:<br>'
		      .$htTiRet
		      .'</small>'
		      .'</p>'
		      ;
		}
	    }
	}
	$oSkin->Content('main',$ht);
    }
    // RETURNS: HTML for form
    /* 2017-05-27 moved to vcPageContent_search
    protected function RenderSearchForm() {
	$sSearch = $this->RequestValue_Search();
	$htSearch = fcString::EncodeForHTML($sSearch);
	return <<<__END__
<table class=catalog-summary>
  <tr><td>
    <form method=get>Search for:
      <input size=50 name=search value="$htSearch">
      <input type=submit value="Go">
    </form>
  </td></tr>
</table>
__END__;
    } */
    
    // -- RENDERING -- //

}
class vcTag_html_search extends vcTag_html_shop {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forTag_body() {
	return 'vcTag_body_search';
    }

    // -- SETUP -- //

}
class vcTag_body_search extends vcTag_body_shop {

    // ++ SETUP ++ //
    
    // CEMENT
    protected function Class_forPageContent() {
	return 'vcPageContent_search';
    }
    
    // -- SETUP -- //
    // ++ EVENTS ++ //

    // CreateElements: parent creates header, navbar, content
    // CEMENT
    protected function OnRunCalculations(){}
    
    // -- EVENTS -- //

}
class vcPageContent_search extends vcPageContent_shop {
    use vtTableAccess_ImagesInfo;

    // ++ FRAMEWORK ++ //
    
    protected function GetConnection() {	// used by vtTableAccess_ImagesInfo
	return vcApp::Me()->GetDatabase();
    }
    
    // -- FRAMEWORK -- //
    // ++ EVENTS ++ //
  
//    protected function OnCreateElements() {}
    protected function OnRunCalculations() {
	
	$sSearch = $this->RequestValue_Search();
	if (is_null($sSearch)) {
	    $sTitle = 'search';
	    $htTitle = 'Catalog Search';
	} else {
	    $htSearch = fcHTML::FormatString_SafeToOutput($sSearch);
	    $sTitle = 'search: '.$htSearch;
	    $htTitle = "Searching Catalog for &ldquo;$htSearch&rdquo;...";
	}
	
	$oPage = fcApp::Me()->GetPageObject();
	//$oPage->SetPageTitle($sTitle);
	$oPage->SetBrowserTitle($sTitle);
	$oPage->SetContentTitle($htTitle);
    }
    public function Render() {
	$out = $this->RenderSearchForm()
	  .$this->RenderSearchResults()
	  ;
	
	return $out;
    }

    // -- EVENTS -- //
    // ++ TABLES ++ //

    protected function TitleInfoQuery() {
	return fcApp::Me()->GetDatabase()->MakeTableWrapper('vcqtTitlesInfo');
    }
    protected function TopicTable() {
	return fcApp::Me()->GetDatabase()->MakeTableWrapper('vctShopTopics');
    }

    // -- TABLES -- //
    // ++ INPUT ++ //

    protected function RequestValue_Search() {
	$oFormIn = fcHTTP::Request();
	return $oFormIn->GetString('search');
    }

    // -- INPUT -- //
    // ++ OUTPUT ++ //

    protected function RenderSearchForm() {
	$sSearch = $this->RequestValue_Search();
	$htSearch = fcString::EncodeForHTML($sSearch);
	return <<<__END__
<table class=catalog-summary>
  <tr><td>
    <form method=get>Search for:
      <input size=50 name=search value="$htSearch">
      <input type=submit value="Go">
    </form>
  </td></tr>
</table>
__END__;
    }
    public function RenderSearchResults() {
	$sSearch = $this->RequestValue_Search();

	if (is_null($sSearch)) {
	    // nothing has been entered yet, so don't bother searching
	    // LATER: print instructions or stats or something
	    $out .= '<div class=content>Please enter some text to search for.</div>';
	} else {
	    $tTitles = $this->TitleInfoQuery();
	    //$arTi1 = $tTitles->SearchRecords_forText($sSearch);
	    $rsTi = $tTitles->Search_forText($sSearch);
	    $arTi = $rsTi->asKeyedArray();
	    
	    $tTopics = $this->TopicTable();
	    //$htTo = $tTopics->DoSearch($sSearch,'',', ');
	    $rsTo = $tTopics->Search_forText($sSearch);

	    // LIST TOPICS FOUND AS TEXT

	    if (is_null($rsTo) || ($rsTo->RowCount() == 0)) {
		$oHdr = new fcSectionHeader('No matching topics found.');
		$out = $oHdr->Render();
	    } else {
		$htTo = NULL;
		while ($rsTo->NextRow()) {
		    if (!is_null($htTo)) {
			$htTo .= ' - ';
		    }
		    $htTo .= $rsTo->ShopLink_name();
		    $sName = $rsTo->NameString();
		    if (strpos($sName,$sSearch) === FALSE) {
			
			$sFnd = $rsTo->VariantsText();
			if (strpos($sFnd,$sSearch) !== FALSE) {
			    $htTo .= " (aka: $sFnd)";
			}
			$sFnd = $rsTo->WrongText();
			if (strpos($sFnd,$sSearch) !== FALSE) {
			    $htTo .= " (as: $sFnd)";
			}

		    }
		}
		$oHdr = new fcSectionHeader('&darr; Found Topics');
		$out = $oHdr->Render()
		  .'<p class="catalog-summary">'.$htTo.'</p>'
		  ;

		// FIND TITLES FOR FOUND TOPICS

		$arTixTo = $rsTo->TitleIDs_forRows_Array();	// Titles from Topics
		// merge Titles-from-Topics list into Titles array:
		if (is_array($arTixTo)) {
		    foreach ($arTixTo as $idTitle) {
			if (!array_key_exists($idTitle,$arTi)) {
			    $arTi[$idTitle] = NULL;	// Title is in list, but we have no data for it
			}
		    }
		}
		//$arTiFinal = clsArray::Merge($arTi,$arTixTo);	// merge titles-found and titles-for-topics-found
	    }

	    if (count($arTi) == 0) {
		$out .= '<div class=content>No matches found.</div>';
	    } else {
	    
		// PROCESS ALL TITLES
	    		
		// -- get thumbnails for all titles
		
		// build SQL list of Title IDs
		$sqlIDs = NULL;
		foreach ($arTi as $id => $rec) {
		    $sqlIDs .= $id.',';
		}
		$sqlIDs = rtrim($sqlIDs,',');	// remove trailing comma
		
		$rsTi = $tTitles->ExhibitRecords("t.ID IN ($sqlIDs)");
		$rsIm = $this->ImageInfoQuery()->GetRecords_forThumbs("ID_Title IN ($sqlIDs)");
		$arIm = $rsIm->Collate_byTitle();
		
		// -- sort titles by status (active/retired)
		
		$arTiAct = $arTiRet = NULL;
		while ($rsTi->NextRow()) {
		    $arRow = $rsTi->GetFieldValues();
		    $id = $arRow['ID'];
		    if ($rsTi->IsForSale()) {
			$arTiAct[$id] = $arRow;
		    } else {
			$arTiRet[$id] = $arRow;
		    }
		}

		// RENDER ACTIVE TITLES (thumbnails and text)
		$htImgAct = NULL;
		$htTiAct = NULL;
		foreach ($arTiAct as $id => $arRow) {
		    //$this->ClearFields();	// 2016-11-11 no idea what this is supposed to do
		    $rsTi->SetFieldValues($arRow);

		    // build thumbnail display
		    if (fcArray::Exists($arIm['data'],$id)) {
			// if there are any images...
			$arImRow = $arIm['data'][$id]['@img'];
			$htImgAct .= $rsTi->RenderThumbs_forRow($arImRow);
		    }

		    $htTiAct .= $rsTi->SummaryLine_HTML_line();
		}
		
		// RENDER INACTIVE TITLES (text only)

		$htTiRet = NULL;
		if (is_array($arTiRet)) {
		    foreach ($arTiRet as $id => $arRow) {
			$rsTi->SetFieldValues($arRow);
			$sName = $rsTi->NameString();
			$sCatNum = $rsTi->CatNum();
			$htLink = $rsTi->ShopLink($sCatNum);
			// TODO
			$htTiRet .= "\n$htLink &ldquo;$sName&rdquo;<br>";
		    }
		}
		
		// OUTPUT RESULTS
		
		if (is_null($htTiAct)) {
		    $oHdr = new fcSectionHeader('No active titles found.');
		    $out .= $oHdr->Render();
		} else {
		    $oHdr = new fcSectionHeader('&darr; Found Titles - Available');
		    $out .= $oHdr->Render()
		    .'<p class="catalog-summary">'
		    .$htTiAct
		    .$htImgAct
		    .'</p>';
		}

		if (!is_null($htTiRet)) {
		    $oHdr = new fcSectionHeader('&darr; Found Titles - <b>Not</b> Available');
		    $out .= $oHdr->Render()
		      .'<p class="catalog-summary"><small>'
		      .'These titles are <b>not</b> currently available:<br>'
		      .$htTiRet
		      .'</small>'
		      .'</p>'
		      ;
		}
	    }
	}
	return $out;
    }

    // ++ OUTPUT ++ //
}
