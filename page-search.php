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

    protected function ParseInput() {
	//parent::ParseInput();
	$this->arReq = $_GET;
	//$this->strSearch = $this->SafeParam(nz($this->arReq['search']));
	$strSearchRaw = NzArray($this->arReq,'search');
	$this->strSearch = $this->Data()->SafeParam($strSearchRaw);
    }
    public function TitleStr($iText=NULL) {
	return 'Catalog Search';
    }
    public function TCtxtStr($iText=NULL) {
	return '';
    }
    protected function NameStr($iText=NULL) {
	return 'search the catalog';
    }
/*
    protected function HandleInput() {
	parent::HandleInput();
	$this->strWikiPg	= 'search';
	$this->strTitle		= 'Catalog Search';
	$this->strName		= 
	$this->strTitleContext	= '';
	$this->strHdrXtra	= '';
	//$this->strSideXtra	= '<dt><b>Cat #</b>: '.$this->strReq;
    }
*/
    public function DoContent() {
	echo '<table class=catalog-summary><tr><td>';	// LATER: use a more appropriate style-name

	$strSearch = $this->strSearch;
	
	echo '<form method=get>Search for: '
	  .'<input size=50 name=search value="'.htmlspecialchars($strSearch).'">'
	  .'<input type=submit value="Go">'
	  .'</form>';	// later we'll add options
	echo '</td></tr></table>';

	$objTitles = $this->Data()->Titles();
	$objItems = $this->Data()->Items();

      // first try an exact match:
	$strFind = $this->strSearch;

	if (empty($strFind)) {
	    // nothing has been entered yet, so don't bother searching
	    // LATER: print instructions or stats or something
	    $out = 'Please enter some text to search for.';
	} else {
	    // create object to handle title list
	    $lstTitles = new clsTitleLister($this->Data()->Titles(),$this->Data()->Images());

	    // Title name search
	    $objRows = $objTitles->Search_forText($strFind);
	    if ($objRows->HasRows()) {
		while ($objRows->NextRow()) {
		    $id = $objRows->ID;
		    $lstTitles->Add($id,$objRows->Values());
		}
	    }
	    // Catalog number search
	    $objRows = $objItems->Search_byCatNum($strFind);
	    $out = '';
	    if (!is_null($objRows)) {
		while ($objRows->NextRow()) {
		    $id = $objRows->ID_Title;
		    $lstTitles->Add($id);
		}
	    }
	    if ($lstTitles->Count()) {
		$ar = $lstTitles->Render();
		$ftTextActive = $ar['txt.act'];
		$ftTextRetired = $ar['txt.ret'];
		$ftImgs = $ar['img'];

		$ftText = '<h3>Titles Available</h3>'.$ftTextActive;
		if (empty($ftTextActive)) {
		    $ftText .= 'No matches found.';
		}
		if (!empty($ftTextRetired)) {
		    $ftText .= '<h3>Titles Not Available</h3><small>These titles are not currently available:<br>'.$ftTextRetired.'</small>';
		}
		$ftTitleText = $ftText;
		$ftTitleImgs = $ftImgs;
	    } else {
		$ftTitleText = '';
		$ftTitleImgs = '';
		// if search is only one word, then we need a different message from this:
		$ftTitleMsg = 'No matches found; try entering fewer words or a shorter word-fragment.';
		// to be implemented
	    }

	    // Topic search
	    $sqlFilt = 
	      '(Name LIKE "%'.$strFind.'%") OR '.
	      '(Variants LIKE "%'.$strFind.'%") OR '.
	      '(Mispeled LIKE "%'.$strFind.'%")';
	    $rsTopics = $this->Data()->Topics()->GetData($sqlFilt);
	    $ftTextActive = '';
	    $ftTextRetired = '';
	    if ($rsTopics->HasRows()) {
		$rsTopics->doBranch(TRUE);
		while ($rsTopics->NextRow()) {
		    // for each topic found, look up all the titles:
		    $id = $rsTopics->KeyValue();
		    $rsTitles = $rsTopics->Titles();	// list of Titles for current Topic
		    $cntTiAll = 0;
		    $cntTiAct = 0;
		    if ($rsTitles->HasRows()) {
			while ($rsTitles->NextRow()) {
			    $lstTitles->Add($rsTitles->KeyValue(),$rsTitles->Values());
/*
			    $cntTiAll++;
			    $idTitle = $rsTitles->KeyValue();
			    $objTitle = $objTitles->GetItem($idTitle);
			    $arStats = $objTitle->Indicia();
			    $cntTiAct += $arStats['cnt.active'];
*/
			    
			}
		    }
		    $ar = $lstTitles->Render();
		    $cntTiAct = $ar['cnt.act'];
		    $cntTiAll = $ar['cnt.all'];
		    $ftText = $rsTopics->LinkOpen().$rsTopics->Name.'</a>: ';
		    if ($cntTiAct > 0) {
			$txtTitles = $cntTiAll.' title'.Pluralize($cntTiAll).', '.$cntTiAct.' active';
			$ftTextActive .= $ftText.$txtTitles.'<br>';
		    } else {
			$ftTextRetired .= $ftText.$cntTiAll.' inactive title'.Pluralize($cntTiAll).'<br>';
		    }
		    $ftText = '<h3>Active Topics</h3>'.$ftTextActive.'<h3>Inactive Topics</h3>'.$ftTextRetired;
		}
		$ftTopicText = $ftText;
	    } else {
		$ftTopicText = 'No matching topics found.';
	    }

	    $out = '<table>';
	    $out .= '<tr bgcolor="#440088"><th colspan=2>Topic Search</th></tr>';
	    $out .= '<tr bgcolor="#000000"><td colspan=2>'.$ftTopicText.'</td></tr>';
	    $out .= '<tr bgcolor="#440088"><th colspan=2>Title Search</th></tr>';
	    $out .= '<tr bgcolor="#440066"><th>Names</th><th>Thumbnails</th></tr>';
	    if (!empty($ftTitleMsg)) {
		$out .= '<tr bgcolor="#440066"><td colspan=2>'.$ftTitleMsg.'</td></tr>';
	    }
	    $out .= '<tr><td bgcolor="#000000" valign=top>'.$ftTitleText.'</td><td valign=top>'.$ftTitleImgs.'</td></tr>';
	    $out .= '</table>';
	}
	echo $out;
    }
}
