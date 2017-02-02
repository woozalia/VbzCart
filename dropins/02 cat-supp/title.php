<?php
/*
  HISTORY:
    2014-03-24 extracted from catalog.php
*/
/*====
  CLASS: catalog titles
*/
class VCTA_SCTitles extends clsTable {
    use ftLinkableTable;

    // ++ SETUP ++ //
    
    // CEMENT
    protected function TableName() {
	return 'ctg_titles';
    }
    // CEMENT
    protected function SingularName() {
	return KS_CLASS_SUPPCAT_TITLE;
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_SUPPCAT_TITLE;
    }
    
    // -- SETUP -- //
    // ++ RECORDS ++ //
    
    public function ActiveRecords() {
	return $this->SelectRecords('isActive');
    }
    public function List_forSource($idSrc) {
	$rs = $this->GetData('ID_Source='.$idSrc);
	return $rs;
    }
    public function List_forGroup($idGrp) {
	$rs = $this->GetData('ID_Group='.$idGrp.')');
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ ACTIONS ++ //
    
    public function Add($idLCTitle,$idGroup,$idSource) {
	$sqlFilt = "(ID_Title=$idLCTitle) AND (ID_Group=$idGroup) AND (ID_Source=$idSource)";
	$rs = $this->SelectRecords($sqlFilt);
	if ($rs->HasRows()) {
	    $rs->NextRow();
	    $txt = 'Updating SCTitle ID='.$rs->SelfLink();
	    if ($rs->IsActive()) {
		$txt .= ' - was active; no change';
	    } else {
		$txt .= ' - reactivating';
		$arUpd = array(
		  'isActive'	=> 'TRUE',
		  );
		$rs->Update($arUpd);
	    }
	    $arOut['obj'] = $rs;
	} else {
	    $txt = 'Adding';
	    $arIns = array(
	      'ID_Title'	=> $idLCTitle,
	      'ID_Group'	=> $idGroup,
	      'ID_Source'	=> $idSource,
	      'isActive'	=> 'TRUE',
	      );
	    $idNew = $this->Insert($arIns);
	    $arOut['id'] = $idNew;
	    $txt .= ' SCTitle ID='.$idNew;
	}
	$arOut['msg'] = $txt;
	return $arOut;
    }
    /* 2016-02-08 old version
    public function Add($iTitle,$iGroup,$iSource) {
	throw new exception('If anything is still calling this, it will need updating.');

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
    */
    
    // -- ACTIONS -- //

}
class VCRA_SCTitle extends clsDataSet {
    use ftLinkableRecord;

    // ++ TRAIT HELPERS ++ //
    
    public function SelfLink_name() {
	return $this->SelfLink($this->NameString());
    }

    // -- TRAIT HELPERS -- //
    // ++ CALLBACKS ++ //
    
    public function ListItem_Link() {
	return $this->SelfLink($this->NameString());
    }
    public function ListItem_Text() {
	return $this->NameString();
    }
    
    // -- CALLBACKS -- //
    // ++ FIELD VALUES ++ //

    public function IsActive() {
	return $this->Value('isActive');
    }
    protected function LCTitleID() {
	return $this->Value('ID_Title');
    }
    public function SourceID() {
	return $this->Value('ID_Source');
    }
    public function GroupID() {
	return $this->Value('ID_Group');
    }
    public function WhenDiscontinued() {
	return $this->Value('WhenDiscont');
    }
    public function Code() {
	return $this->Value('Code');
    }
    public function Descr() {
	return $this->Value('Descr');
    }
    public function Supp_CatNum() {
	return $this->Value('Supp_CatNum');
    }
    public function Notes() {
	return $this->Value('Notes');
    }

    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    protected function HasCode() {
	return !is_null($this->Code());
    }
    /*
      This table doesn't really have a "name" field, so we throw some other fields together
	to make a brief descriptor. Not sure how useful this is, but it does at least
	give us some idea of what each record is about.
    */
    protected function NameString() {
	$out = $this->GetKeyValue()
	  .' (s'
	  .$this->SourceID()
	  .' g'
	  .$this->GroupID()
	  .' t'
	  .$this->LCTitleID()
	  .')'
	  ;
	if ($this->HasCode()) {
	    $out .= ' '.$this->Code().' '.$this->Descr();
	}
	return $out;
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ TABLES ++ //
    
    protected function LCTitleTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_CATALOG_TITLES,$id);
    }
    protected function SCSourceTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_SUPPCAT_SOURCES,$id);
    }
    protected function SCGroupTable($id=NULL) {
	return $this->Engine()->Make(KS_CLASS_SUPPCAT_GROUPS,$id);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //

    // 2015-09-09 These will probably need fixes. Rename them when they do.

    public function TitleObj() {
	throw new exception('Call LCTitleRecord() instead.');
    }
    protected function LCTitleRecord() {
	$id = $this->LCTitleID();
	$rc = $this->LCTitleTable()->GetItem($id);
	return $rc;
    }
    public function SourceObj() {
	throw new exception('Call SCSourceRecord() instead.');
    }
    protected function SCSourceRecord() {
	$id = $this->SourceID();
	$rc = $this->SCSourceTable($id);
	return $rc;
    }
    public function GroupObj() {
	throw new exception('Call SCGroupRecord() instead.');
    }
    // PUBLIC so SC Source Title entry object can use it
    public function SCGroupRecord() {
	$id = $this->GroupID();
	$rc = $this->SCGroupTable($id);
	return $rc;
    }

    // -- RECORDS -- //
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

		$rcTitle = $this->LCTitleRecord();
		$rcSource = $this->SCSourceRecord();
		$rcGroup = $this->SCGroupRecord();
		$txtActive = $this->isActive()?'&radic;':'-';
		$txtTitle = $rcTitle->CatNum().' '.$rcTitle->NameString();
		$txtSource = $rcSource->Abbreviation();
		$txtGroup = $rcGroup->NameString();
		$dtWhenDisc = $this->WhenDiscontinued();
		if (is_null($dtWhenDisc)) {
		    $txtWhenDisc = '-';
		} else {
		    $xtd = new xtTime($dtWhenDisc);
		    $txtWhenDisc = $xtd->FormatSortable();
		}

		$ftID = $this->SelfLink();
		$ftTitle = $rcTitle->SelfLink($txtTitle);
		$ftSource = $rcSource->SelfLink($txtSource);
		$ftGroup = $rcGroup->SelfLink($txtGroup);
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
