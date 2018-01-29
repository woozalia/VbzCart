<?php
/*
  HISTORY:
    2016-02-06 Extracting title entry code from source.php
    2017-04-24 Updated for Ferreteria compatibility... but why is this a separate class? It's only used in one place.
*/

// I wanted these to be class constants, but you can't concatenate values there.
define('KS_SOURCE_ENTRY_PREFIX','srcEnt_');
define('KS_SOURCE_ENTRY_HTNAME_TITLES_ENTRY_TEXT',	KS_SOURCE_ENTRY_PREFIX.'EntryText');
define('KS_SOURCE_ENTRY_HTNAME_TITLES_ENTRY_BUTTON',	KS_SOURCE_ENTRY_PREFIX.'EntryBtn');
define('KS_SOURCE_ENTRY_HTNAME_CHANGE_NOTES',		KS_SOURCE_ENTRY_PREFIX.'ChangeNotes');
define('KS_SOURCE_ENTRY_HTNAME_GROUPS_CHOSEN',		KS_SOURCE_ENTRY_PREFIX.'ChosenGrps');
define('KS_SOURCE_ENTRY_HTNAME_DEPT_CHOSEN',		KS_SOURCE_ENTRY_PREFIX.'ChosenDept');
define('KS_SOURCE_ENTRY_HTNAME_CHECK_CHANGE_TITLE',	KS_SOURCE_ENTRY_PREFIX.'ChangeTitle');
define('KS_SOURCE_ENTRY_HTNAME_CHECK_RENAME_TO_NEW',	KS_SOURCE_ENTRY_PREFIX.'RenameTitle');

class vcTitleEntryManager {

    // ++ SETUP ++ //
    
    public function __construct(vcraSCSource $rcSource) {
	$this->SourceRecord($rcSource);
    }
    private $rcSource;
    protected function SourceRecord(vcraSCSource $rcSource=NULL) {
	if (!is_null($rcSource)) {
	    $this->rcSource = $rcSource;
	}
	return $this->rcSource;
    }
    protected function EngineObject() {
	return fcApp::Me()->GetDatabase();
    }
    /*
    private $oDB;
    protected function EngineObject(fcDataConn_CliSrv $oDB=NULL) {
	if (!is_null($oDB)) {
	    $this->oDB = $oDB;
	}
	return $this->oDB;
    }*/
    /*
    private $oPage;
    protected function PageObject(clsPageBasic $oPage=NULL) {
	if (!is_null($oPage)) {
	    $this->oPage = $oPage;
	}
	return $this->oPage;
    }*/
    
    // -- SETUP -- //
    // ++ TABLES ++ //
    
    protected function SCGroupTable($id=NULL) {
	return $this->EngineObject()->MakeTableWrapper(KS_CLASS_SUPPCAT_GROUPS,$id);
    }
    protected function SCTitleTable($id=NULL) {
	return $this->EngineObject()->MakeTableWrapper(KS_CLASS_SUPPCAT_TITLES,$id);
    }
    protected function LCTitleTable($id=NULL) {
	return $this->EngineObject()->MakeTableWrapper(KS_CLASS_CATALOG_TITLES,$id);
    }
    protected function TopicTable($id=NULL) {
	return $this->EngineObject()->MakeTableWrapper(KS_CLASS_CATALOG_TOPICS,$id);
    }
    protected function TitleTopicTable() {
	return $this->EngineObject()->MakeTableWrapper('vctTitlesTopics');
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //
    
    protected function SupplierRecord() {
	return $this->SourceRecord()->SupplierRecord();
    }
    protected function DepartmentRecord() {
	return $this->SourceRecord()->DepartmentTable($this->DepartmentID());
    }

    // -- RECORDS -- //
    // ++ FIELD VALUES ++ //
    
    protected function SourceID() {
	return $this->SourceRecord()->GetKeyValue();
    }
    protected function SupplierID() {
	return $this->SourceRecord()->SupplierID();
    }
    protected function DepartmentID() {
	return $this->Received_DepartmentID();
    }
    
    // -- FIELD VALUES -- //
    // ++ MAIN API ++ //
    
    public function DoEnter() {
	$out = NULL;
    
	if ($this->DidRequestChange()) {
	    $out .= $this->DoMakeChanges();
	}
	
	$out .= "\n<form method=post>"
	  ."\n<table><tr><td valign=top>"
	  .$this->DoParseTitles()		// parse text entry input, if any
	  .$this->Render_ActionOptions()	// things that can be done (depends on DoParseTitles())
	  ."\n</td><td valign=top>"
	  .$this->Render_ParsedTitles()		// parsing results, if any
	  ."\n</td></tr></table>"
	  ."\n<table style='float: right;'><tr><td>"
	  .$this->Render_TextForm()		// show the text entry form
	  ."\n</td></tr></table>"
	  ."\n</form>"
	  ;

	return $out;
    }
    
    /* 2017-05-29 rewriting this
    public function DoMain() {
	$oPathIn = fcApp::Me()->GetKioskObject()->GetInputObject();
	$oFormIn = fcHTTP::Request();
	
	$out = NULL;
	$isReqEnter = $oPathIn->GetBool('enter');
	$isReqDone = $oFormIn->GetBool('btnDone');
	$doEnter = $isReqEnter && !$isReqDone;
	if ($doEnter) {	// if not in entry mode, do nothing
	
	    $isReqParse = $oFormIn->GetBool('btnParse');	
	    // wait, maybe we don't need this if we always parse unless we're done
	    //$sTopicBtnCheck = $this->TopicControl()->CtrlName_Btn_Check();
	    //$isReqTopic = $oPage->ReqArgBool($sTopicBtnCheck);
	    if ($this->DidRequestChange()) {
		$out .= $this->DoMakeChanges();
	    }
	    
	    $out .= "\n<form method=post>";
	    $out .= "\n<table><tr><td valign=top>";
	    $out .= $this->DoParseTitles();		// parse text entry input, if any
	    $out .= $this->Render_ActionOptions();	// things that can be done (depends on DoParseTitles())
	    $out .= "\n</td><td valign=top>";
	    $out .= $this->Render_ParsedTitles();	// parsing results, if any
	    $out .= "\n</td></tr></table>";
	    $out .= $this->Render_TextForm();		// show the text entry form
	    $out .= "\n</form>";
	}
	return $out;
    } */
    
    // -- MAIN API -- //
    // ++ PROCESSING ++ //
    
    protected function DoParseTitles() {
	$sText = $this->Received_TitlesText();
	$arOpts = array(
	  'def val'	=> '<font color=red>please enter a descriptive title</font>'
	  );
	$arTitles = fcsStringBlock::ParseTextLines($sText,$arOpts);
	$this->Parsed_TitlesArray($arTitles);	// save the results
    }
    protected function DoMakeChanges() {
    
	$rcSource = $this->SourceRecord();
	
	$out = NULL;
    
	// add new titles to local catalog
	
	  // TODO

	// make changes to existing titles
	
	$arTitles = $this->Received_TitlesChosen();
	if (count($arTitles)>0) {

	    // add chosen topics
	    $tTT = $this->TitleTopicTable();
	    $arTopics = $this->Received_TopicsChosen();
	    $arGroups = $this->Received_GroupsChosen();
	    
	    foreach ($arTitles as $idTitle => $onDummy) {

		if (is_array($arTopics)) {
		    $cnt = 0;
		    foreach ($arTopics as $idTopic) {
			$tTT->SetPair($idTitle,$idTopic,TRUE);
			$cnt++;
		    }
		    $out .= $cnt.' topic'.fcString::Pluralize($cnt).' added<br>';
		}
	    
		// add chosen groups
		if (is_array($arGroups)) {
		    foreach ($arGroups as $idx => $idGroup) {
			$ar = $rcSource->MakeTitle($idTitle,$idGroup);
			$out .= $ar['msg'].'<br>';
		    }
		}

	    }
	}
	$out = is_null($out)?'Nothing to change.':$out;
	return $out;
    }
    
    // -- PROCESSING -- //
    // ++ RENDERING ++ //
    
    protected function Render_TextForm() {
	$htnText = KS_SOURCE_ENTRY_HTNAME_TITLES_ENTRY_TEXT;
	$htnBtn = KS_SOURCE_ENTRY_HTNAME_TITLES_ENTRY_BUTTON;
	$htTitles = $this->Received_TitlesText();
	
	$oHdr = new fcSectionHeader('Enter Titles');
	
	$out = $oHdr->Render()
	  . <<<__END__

<input type=submit name="$htnBtn" value="Check These Titles:">
<br><span class="line-notes">supplier title cat#, then space, then title's name</span>
<br><textarea name="$htnText" cols=40 rows=30>$htTitles</textarea>
__END__;
	return $out;
    }
    /*----
      PURPOSE: This is where the user can select various things to be done to the titles:
	* Assign them to a Department
	* Assign them to SC Groups
	* Assign them to Topics
    */
    protected function Render_ActionOptions() {
	$out = NULL;

	if (!is_null($this->Parsed_TitlesArray())) {
	
	    $out .= (new fcSectionHeader('Action Options'))->Render();

	    // -- department chooser
	    $idDept = $this->Received_DepartmentID();
	    $out .= $oPage->ActionHeader('Department',array(),'section-header-subsub');
	    $out .= $this->SupplierRecord()->Depts_DropDown(
	      KS_SOURCE_ENTRY_HTNAME_DEPT_CHOSEN,
	      $idDept,
	      '-- choose a department--'
	      );

	    // -- CT groups chooser
	    $out .= (new fcSectionHeader('Groups'))->Render();
	    $rs = $this->SCGroupTable()->Active_forSupplier($this->SupplierID(),'Sort');
	    $out .= $rs->MultiSelect(
	      KS_SOURCE_ENTRY_HTNAME_GROUPS_CHOSEN,
	      TRUE,
	      'size=20'
	      );

	    // -- topic chooser
	    $out .= $this->RenderTopicEditor();
	}
	
	return $out;
    }
    protected function Render_ParsedTitles() {

	$arTitles = $this->Parsed_TitlesArray();
	if (is_array($arTitles)) {
	    $out .= (new fcSectionHeader('Titles Entered'))->Render();
	
	    // 2011-02-06 allow restriction to selected dept
	    if (empty($idDept)) {
		$sqlBase = '(ID_Supp='.$this->SupplierID().')';
		$sSupp = $this->SupplierRecord()->SelfLink_name();
		$out .= "\nMatches for supplier $sSupp:";
	    } else {
		$sqlBase = '(ID_Dept='.$this->DepartmentID().')';
		$sDept = $this->DepartmentRecord()->SelfLink_name();
		$out .= "\nMatches for $sDept matches:";
	    }

	    $isOdd = TRUE;
	    $db = $this->EngineObject();
	    $out .= <<<__END__

<table class=listing>
  <tr>
    <th>ID</th>
    <th>Cat #</th>
    <th>Name</th>
    <th>Topics</th>
    <th>Groups</th>
  </tr>
__END__;
	    $arTitlesChecked = $this->Received_TitlesChosen();
	    foreach ($arTitles as $sCatKey => $sNameEnt) {
		$cssClass = $isOdd?'odd':'even';
		$isOdd = !$isOdd;

		// is this line already entered as a title record?
		$sqlCatKey = $db->Sanitize_andQuote($sCatKey);
		$sqlFilt = "$sqlBase AND (CatKey=$sqlCatKey)";
		$rcLCTitle = $this->LCTitleTable()->SelectRecords($sqlFilt);
		$isInLC = $rcLCTitle->HasRows();
		// if it's a title record...
		if ($isInLC) {
		    $rcLCTitle->NextRow();	// load the record found
		    $idTitle = $rcLCTitle->GetKeyValue();
		    $sName = $rcLCTitle->NameString();
		    $htName = fcString::EncodeForHTML($sName);
		    $htNameEnt = fcString::EncodeForHTML($sNameEnt);

		    // show checkbox to modify this title to the selected groups
		    $htPopup = "add &ldquo;$htName&rdquo; to selected groups and topics";
		    $htCN = KS_SOURCE_ENTRY_HTNAME_CHECK_CHANGE_TITLE;
		    $htCNA = $htCN.'['.$idTitle.']';	// control name array element
		    $htCked = array_key_exists($idTitle,$arTitlesChecked)?' checked':'';
		    $htChkTChg = "<input type=checkbox title='$htPopup' name='$htCNA'$htCked>";
		    if ($sNameEnt != $sName) {
			$htPopup = "rename &ldquo;$htName&rdquo; to &ldquo;$htNameEnt&rdquo;";
			$htCN = KS_SOURCE_ENTRY_HTNAME_CHECK_RENAME_TO_NEW;
			$htCNA = $htCN.'['.$idTitle.']';
			$htGrpChk = "<input type=checkbox title='$htPopup' name='$htCNA'>";
		    } else { $htGrpChk = NULL; }
		    
		    // Does it have any SC Groups assigned yet?
		    $idSource = $this->SourceID();
		    $sqlFilt = "isActive AND (ID_Title=$idTitle) AND (ID_Source=$idSource)";
		    $rsSCTitle = $this->SCTitleTable()->SelectRecords($sqlFilt);
		    $isInSC = $rsSCTitle->HasRows();

		    if ($isInSC) {
			$htGroups = '';
			$cntGrps = 0;
			while ($rsSCTitle->NextRow()) {
			    $rcCGroup = $rsSCTitle->SCGroupRecord();
			    if ($cntGrps) {
				$htGroups .= '<br>';
			    }
			    $cntGrps++;
			    $htGroups .= $rcCGroup->SelfLink_friendly();
			}
			$htGroups .= '<br>';
		    } else {
			$htGroups = '';
		    }

		    $sCatKey = $rcLCTitle->CatKey();
		    $htTopics = $rcLCTitle->TopicList_ft();
		    $idTitle = $rcLCTitle->GetKeyValue();
		    $htTitle = $rcLCTitle->SelfLink();
		    $htPopup = "add &ldquo;$htName&rdquo; to entered topics";
		    $htTTChk = "<input title='$htPopup' type=checkbox name=addTopic[$idTitle]>";
		      /* $out: $htName is just filler here, but I wanted the array to be key-val
			in case we have a need for the value later on. */
		    $out .= <<<__END__
  <tr class=$cssClass>
    <td style="white-space:nowrap">$htChkTChg$htTitle</td>
    <td>$sCatKey</td>
    <td>$htGrpChk$htName</td>
    <td rowspan=2>$htTopics</td>
    <td rowspan=2>$htGroups</td>
  </tr>
__END__;
		} // if ($isInLC)
		else {
		    $htName = fcString::EncodeForHTML($sNameEnt);
		}

		// show the entered information:
		$out .= <<<__END__
  <tr class=$cssClass>
    <td><span title="what you typed">(sic)</span></td>
    <td><i>$sCatKey</i></td>
    <td><i>$sNameEnt</i></td>
__END__;
		if (!$isInLC) {
		    $out .= '<td><input type=checkbox name=addToCat['.$strCatKey.'] checked>add to catalog</td>';
		}
		$out .= '</tr>';
		// if available, show the information found in the db:
	    }
	    $htRV_Notes = fcString::EncodeForHTML($this->Received_ChangeNotes());
	    $htCN_Notes = KS_SOURCE_ENTRY_HTNAME_CHANGE_NOTES;
	    // for visual clarity, put action button in same column as checkboxes
	    $out .= <<<__END__

  <tr>
    <td colspan=7 style="border-top: 1px solid black;"></td>
  </tr>
  <tr>
    <td colspan=3>Notes: <input type=text name="$htCN_Notes" size=25 value="$htRV_Notes"></td>
    <td colspan=4 align=center>
      <input type=submit name=btnChange value="Make Changes">
      <input type=submit name=btnDone value="Done">
    </td>
  </tr>
</table>
__END__;
	} // IF titles entered
	else { $out = NULL; }
	return $out;
    }
    /*----
      RENDERS: Edit controls to specify a list of topics
    */
    protected function RenderTopicEditor() {
	$ctrlList = $this->TopicControl();
	$doExpect = $this->DidRequestTopics() || $this->DidRequestChange();

	$out = (new fcSectionHeader('Topics'))->Render();

	$out .= $ctrlList->HandleInput($doExpect);
	$out .= "\n<br>";
	$out .= $ctrlList->RenderForm_Entry(TRUE);

	return $out;
    }
    
    // -- RENDERING -- //
    // ++ EXTERNAL WIDGET ++ //
    
    /*----
      HISTORY:
	2016-02-05 Uncommenting the function-variable contents and trying to figure out
	  how to make this integrate properly with the larger Title entry form.
    */
    private $oTopicCtrl;    
    protected function TopicControl() {
	if (!isset($this->oTopicCtrl)) {

	    $tblTitleTopics = $this->TitleTopicTable();
	    $me = $this;
	    $arOpts = $this->TopicTable()->TopicListing_base_array();
	    $arOpts['name']		= __CLASS__.'_topics';
	    $arOpts['btnChk_Text']	= 'Prepare topics:';
	    $arOpts['txtConf_list']	= 'Prepared';
	    $arOpts['btnChg_Text']	= NULL;

	    $s = <<<__END__

[[input-text]]<br>[[input-instruct]]
<label style="white-space: nowrap;">[[choice-prefix]]</label>
<label style="white-space: nowrap;">[[choice-comma]]</label>
<label style="white-space: nowrap;">[[choice-space]]</label>
__END__;
	    $ctrlList = new clsWidget_ShortList();
	    $ctrlList->Options($arOpts);
	    $ctrlList->OptionValue_layout($s);

	    $this->oTopicCtrl = $ctrlList;
	}

	return $this->oTopicCtrl;
    }
    
    // -- EXTERNAL WIDGET -- //
    // ++ INTERNAL DATA ++ //
    
    private $arTitles;
    protected function Parsed_TitlesArray(array $ar=NULL) {
	if (!is_null($ar)) {
	    $this->arTitles = $ar;
	}
	return $this->arTitles;
    }
    
    // -- INTERNAL DATA -- //
    // ++ FORM INPUTS ++ //
    
    protected function DidRequestTopics() {
	return $this->TopicControl()->DidRequestCheck();
    }
    protected function DidRequestChange() {
	return fcHTTP::Request()->GetBool('btnChange');		// the "make changes" button
    }
    protected function Received_TitlesText() {
	return fcHTTP::Request()->GetString(KS_SOURCE_ENTRY_HTNAME_TITLES_ENTRY_TEXT);
    }
    protected function Received_DepartmentID() {
    	return fcHTTP::Request()->GetInt(KS_SOURCE_ENTRY_HTNAME_DEPT_CHOSEN);
    }
    protected function Received_GroupsChosen() {
	return fcHTTP::Request()->GetArray(KS_SOURCE_ENTRY_HTNAME_GROUPS_CHOSEN);
    }
    protected function Received_TopicsChosen() {
	return $this->TopicControl()->Data_toChange();
    }
    protected function Received_TitlesChosen() {
	return fcHTTP::Request()->GetArray(KS_SOURCE_ENTRY_HTNAME_CHECK_CHANGE_TITLE);
    }
    protected function Received_ChangeNotes() {
	return fcHTTP::Request()->GetString(KS_SOURCE_ENTRY_HTNAME_CHANGE_NOTES);
    }
    
    // -- FORM INPUTS -- //
    // ++ ACTIONS ++ //
    
    protected function LinkTitlesAndTopics(array $arTitles,array $arTopics) {
	die('TO BE WRITTEN');
    }
    
    // -- ACTIONS -- //

}
