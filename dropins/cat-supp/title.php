<?php
/*
  HISTORY:
    2014-03-24 extracted from catalog.php
*/
/*====
  CLASS: catalog titles
*/
class VCTA_SCTitles extends clsTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ctg_titles');
	  $this->KeyName('ID');
	  $this->ClassSng('VCRA_SCTitle');
    }
    public function List_forSource($iSrc) {
	$objRows = $this->GetData('ID_Source='.$iSrc);
	return $objRows;
    }
    public function List_forGroup($iGrp) {
	$objRows = $this->GetData('ID_Group='.$iGrp.')');
	return $objRows;
    }
    public function Add($iTitle,$iGroup,$iSource) {
	$sqlFilt = '(ID_Title='.$iTitle.') AND (ID_Group='.$iGroup.') AND (ID_Source='.$iSource.')';
	$rsFnd = $this->GetData($sqlFilt);
	$arChg = array(
	  'isActive'	=> TRUE
	  );
	if ($rsFnd->HasRows()) {
	    $txt = 'Updating ID='.$this->ID;
	    if ($rsFnd->isActive) {
		$txt .= ' - was active; no change';
	    } else {
		$txt .= ' - reactivating';
	    }
	    $this->Update($arChg);
	} else {
	    $txt = 'Adding';
	    $arChg['ID_Title'] = $iTitle;
	    $arChg['ID_Group'] = $iGroup;
	    $arChg['ID_Source'] = $iSource;
	    $this->Insert($arChg);

	    $rsFnd = NULL;
	}
	$arOut['obj'] = $rsFnd;
	$arOut['msg'] = $txt;
	return $arOut;
    }
}
class VCRA_SCTitle extends clsDataSet {

    // ++ BOILERPLATE ++ //

    public function AdminLink($iText=NULL,$iPopup=NULL,array $iarArgs=NULL) {
	return clsMenuData_helper::_AdminLink($this,$iText,$iPopup,$iarArgs);
    }

    // -- BOILERPLATE -- //
    // ++ DATA FIELDS ACCESS ++ //

    public function SourceID() {
	return $this->Value('ID_Source');
    }
    public function GroupID() {
	return $this->Value('ID_Group');
    }

    // -- DATA FIELDS ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    public function TitleObj() {
	$id = $this->ID_Title;
	$obj = $this->Engine()->Titles()->GetItem($id);
	return $obj;
    }
    public function SourceObj() {
	$id = $this->ID_Source;
	$obj = $this->Engine()->CtgSrcs()->GetItem($id);
	return $obj;
    }
    public function GroupObj() {
	$id = $this->ID_Group;
	$obj = $this->Engine()->CtgGrps()->GetItem($id);
	return $obj;
    }

    // -- DATA RECORDS ACCESS -- //
    // ++ ADMIN WEB UI ++ //

    public function AdminList() {
	if ($this->HasRows()) {
	    $out = <<<__END__
<table>
  <tr>
    <th>ID</th>
    <th>A?</th>
    <th>Title</th>
    <th>Source</th>
    <th>Group</th>
    <th>Gone</th>
    <th>SC#</th>
    <th>Notes</th>
  </tr>
__END__;
	    $isOdd = TRUE;

	    while ($this->NextRow()) {
		$ftStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$htAttr = 'style="'.$ftStyle.'"';
		$isOdd = !$isOdd;

		$objTitle = $this->TitleObj();
		$objSource = $this->SourceObj();
		$objGroup = $this->GroupObj();
		$txtActive = $this->isActive?'&radic;':'-';
		$txtTitle = $objTitle->CatNum().' '.$objTitle->Name;
		$txtSource = $objSource->Abbr;
		$txtGroup = $objGroup->Name;
		$dtWhenDisc = $this->WhenDiscont;
		if (is_null($dtWhenDisc)) {
		    $txtWhenDisc = '-';
		} else {
		    $xtd = new xtTime($dtWhenDisc);
		    $txtWhenDisc = $xtd->FormatSortable();
		}

		$ftID = $this->AdminLink();
		$ftTitle = $objTitle->AdminLink($txtTitle);
		$ftSource = $objSource->AdminLink($txtSource);
		$ftGroup = $objGroup->AdminLink($txtGroup);
		$sSuppCatNum = $this->Value('Supp_CatNum');
		$sNotes = $this->Value('Notes');
		$out .= <<<__END__
  <tr $htAttr>
    <td>$ftID</td>
    <td>$txtActive</td>
    <td>$ftTitle</td>
    <td>$ftSource</td>
    <td>$ftGroup</td>
    <td>$txtWhenDisc</td>
    <td>$sSuppCatNum</td>
    <td>$sNotes</td>
  </tr>
__END__;
	    }
	    $out .= "\n<table>";
	} else {
	    $out = 'No titles found.';
	}
	return $out;
    }

    // -- ADMIN WEB UI -- //
}
