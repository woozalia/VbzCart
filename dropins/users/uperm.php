<?php
/*
  PURPOSE: user access control classes: available security permissions
  HISTORY:
    2013-12-29 started
*/
class VCM_UserPerms extends clsUserPerms {
    private $arData;

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng(KS_CLASS_ADMIN_USER_PERMISSION);
	  $this->ActionKey(KS_ACTION_USER_PERMISSION);
	$this->arData = NULL;
    }

    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	$this->arArgs = $arArgs;
	$out = $this->AdminListing();
	return $out;
    }

    // -- DROP-IN API -- //
    // ++ ADMIN INTERFACE ++ //

    protected function AdminListing() {
	$rs = $this->GetData();

	// set up header action-links
	$oPage = $this->Engine()->App()->Page();
	$arPage = $oPage->PathArgs();
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	  new clsActionLink_option($arPage,'add','id')
	  );
	$oPage->PageHeaderWidgets($arActs);

	if ($rs->HasRows()) {
	    $out = "\n<table class=listing>\n".VC_UserPerm::AdminLine_header();
	    while ($rs->NextRow()) {
		$out .= $rs->AdminLine();
	    }
	    $out .= "\n</table>";
	} else {
	    $out = '<i>(none defined)</i>';
	}
	return $out;
    }

    // -- ADMIN INTERFACE -- //
}
class VC_UserPerm extends clsUserPerm {

    // ++ STATIC ++ //

    static public function AdminLine_header() {
	return <<<__END__
  <tr>
    <th>ID</th>
    <th>Name</th>
    <th>Description</th>
    <th>Created</th>
  </tr>
__END__;
    }

    // -- STATIC -- //
    // ++ DROP-IN API ++ //

    /*----
      PURPOSE: execution method called by dropin menu
    */
    public function MenuExec(array $arArgs=NULL) {
	return $this->AdminPage();
    }

    // -- DROP-IN API -- //
    // ++ RENDER UI COMPONENTS ++ //

    /*----
      INPUT: if sName is not NULL, a checkbox will be included with each row.
	The checkbox will be named $sName[ID].
    */
    protected function AdminLine_edit($sName=NULL,$bSel=NULL) {
	$htID = $this->AdminLink();
	if (!is_null($sName)) {
	    $id = $this->KeyValue();
	    $htID .= clsHTML::CheckBox($sName,$bSel,$id);
	}

	$htName = htmlspecialchars($this->ValueNz('Name'));
	$htDescr = htmlspecialchars($this->ValueNz('Descr'));
	$htWhen = $this->ValueNz('WhenCreated');

	$out = <<<__END__
  <tr>
    <td>$htID</td>
    <td>$htName</td>
    <td>$htDescr</td>
    <td>$htWhen</td>
  </tr>
__END__;
	return $out;
    }
    /* 2014-03-01 currently not used
    public function RenderInlineList($sSep=', ') {
	$out = NULL;
	while ($this->NextRow()) {
	    if (!is_null($out)) {
		$out .= $sSep;
	    }
	    $ht = $this->AdminLink($this->Name(),$this->Descr());
	    $out .= $ht;
	}
	return $out;
    }
    */
    /*----
      ACTION: Show active assignments in table form
    */
    public function RenderAssigns() {
	if ($this->HasRows()) {
	    $ht = "\n<table class=listing>"
	      .static::AdminLine_header();
	    while ($this->NextRow()) {
		$ht .= $this->AdminLine();
	    }
	    $ht .= "\n</table>";
	} else {
	    $ht = '<i>(no permissions assigned)</i>';
	}
	return $ht;
    }
    /*----
      USAGE: Create a recordset of all the permissions that are currently assigned.
      ACTIONS: Renders a form showing *all* permissions (including those *not* assigned)
	with checkboxes to indicate which should be assigned after saving.
      NOTE: This is very similar in structure to UGroup->RenderEditableList(),
	but it is not the same function. (It could probably be abstracted as an HTML
	control class, but let's save that for later.)
    */
    public function RenderEditableList($sName=NULL) {
	$tbl = $this->Table;
	if (is_null($sName)) {
	    $sName = KS_ACTION_USER_PERMISSION;
	}

	// build arrays
	$arCur = $this->AsArray();
	$arAll = $tbl->AsArray();
	if (count($arAll) > 0) {

	    $ht = "\n<table class=listing>"
	      .static::AdminLine_header();
	    foreach ($arAll as $id => $row) {
		$isActive = array_key_exists($id,$arCur);
		$this->Values($row);
		$ht .= $this->AdminLine($sName,$isActive);
	    }
	    $ht .= "\n</table>"
	      ."\n<input type=submit name=btnSavePrms value='Save'>";
	} else {
	    $ht = '<i>(no permissions defined)</i>';
	}
	return $ht;
    }

    // -- RENDER UI COMPONENTS -- //
    // ++ ADMIN INTERFACE ++ //

    public function AdminLine($sName=NULL,$bSel=NULL) {
	$htID = $this->AdminLink();
	if (!is_null($sName)) {
	    $id = $this->KeyValue();
	    $htID .= clsHTML::CheckBox($sName,$bSel,$id);
	}
	$htName = htmlspecialchars($this->ValueNz('Name'));
	$htDescr = htmlspecialchars($this->ValueNz('Descr'));
	$htWhen = $this->ValueNz('WhenCreated');

	$out = <<<__END__
  <tr>
    <td>$htID</td>
    <td>$htName</td>
    <td>$htDescr</td>
    <td>$htWhen</td>
  </tr>
__END__;

	return $out;
    }
    protected function AdminPage() {
	$oPage = $this->Engine()->App()->Page();

	$doSave = $oPage->ReqArgBool('btnSave');
	if ($doSave) {
	    $this->PageForm()->Save(NULL,array('id'=>FALSE,'edit'=>FALSE));
	}

	// set up header action-links
	$arPage = $oPage->PathArgs();
	$arActs = array(
	  // (array $iarData,$iLinkKey,$iGroupKey=NULL,$iDispOff=NULL,$iDispOn=NULL,$iDescr=NULL)
	  new clsActionLink_option($arPage,'edit')
	  );
	$oPage->PageHeaderWidgets($arActs);

	$doEdit = $oPage->PathArg('edit') || $this->IsNew();

	// generate a non-editing URL for the form action
	//$urlForm = $oPage->SelfURL(array('id'=>FALSE));

	$out = "\n<form method=post id='uperm.AdminPage'>"
	  .$this->PageForm()->RenderForm($doEdit)
	  ."\n</form>"
	  .$this->EventListing();
	return $out;
    }
    protected function PageForm() {
	if (empty($this->frmPage)) {
	    $frmPage = new clsForm_recs($this);

	    $frmPage->AddField(new clsField('ID'),			new clsCtrlHTML_ReadOnly());
	    $frmPage->AddField(new clsField('Name'),		new clsCtrlHTML(array('size'=>20)));
	    $frmPage->AddField(new clsField('Descr'),		new clsCtrlHTML(array('size'=>60)));
	    $frmPage->AddField(new clsFieldTime('WhenCreated'),	new clsCtrlHTML_ReadOnly());
	    $frmPage->NewVals(array('WhenCreated'=>'NOW()'));

	    $tplt = <<<__END__
<table>
  <tr><td align=right><b>ID</b>:</td><td>{{ID}}</td></tr>
  <tr><td align=right><b>Name</b>:</td><td>{{Name}}</td></tr>
  <tr><td align=right><b>Description</b>:</td><td>{{Descr}}</td></tr>
  <tr><td align=right><b>Created</b>:</td><td>{{WhenCreated}}</td></tr>
</table>
__END__;
	    $frmPage->FormTemplate($tplt);
	    $this->frmPage = $frmPage;
	}
	return $this->frmPage;
    }
}