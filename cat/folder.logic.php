<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling image folders
    Eventually, might have to call this something more specific like "repositories",
      since it's not handling folders in general but rather folders where a bunch of
      particular stuff is located.
  HISTORY:
    2016-02-03 split Folder classes off from vbz-cat-image.php
*/
class clsVbzFolders extends vcShopTable {

    // ++ CEMENTING ++ //
    
    protected function TableName() {
	return 'cat_folders';
    }
    protected function SingularName() {
	return 'clsVbzFolder';
    }

    // -- CEMENTING -- //

    /*----
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
	* If table ever grows to a significant size, we might end up changing the filtering criteron.
    */
    public function DropDown($iName,$iDefault=NULL) {
	throw new exception('clsVbzFolders::DropDown() -- who calls this?');
	$dsRows = $this->GetData('Descr IS NOT NULL');
	return $dsRows->DropDown_for_rows($iName,$iDefault);
    }
    /*----
      PURPOSE: Finds the folder record which matches as much of the given URL as possible
      RETURNS: object for that folder, or NULL if no match found
      ASSUMES: folder list is not empty
      TO DO:
	does not yet handle adding new folders
	does not recursively check subfolders for improved match
      HISTORY:
	2011-01-30 created -- subfolders not implemented yet because no data to test with
    */
    public function FindBest($iURL) {
	if (strlen($iURL) > 0) {
	    $slURL = strlen($iURL);
	    $rs = $this->GetData('ID_Parent IS NULL');	// start with root folders
	    $arrBest = NULL;
	    $slBest = 0;
	    while ($rs->NextRow()) {
		$fp = $rs->Value('PathPart');
		$pos = strpos($iURL,$fp);	// does the folder appear in the URL?
		if ($pos === 0) {
		    $slFldr = strlen($fp);
		    if ($slFldr > $slBest) {
			$arrBest = $rs->Values();
			$slBest = $slFldr;
		    }
		}
	    }
	    if (is_array($arrBest)) {
		$rsFldr = $this->SpawnItem();
		$rsFldr->Values($arrBest);
		return $rsFldr;
	    }
	}
	return NULL;
    }
}
class clsVbzFolder extends vcBasicRecordset {

    // ++ DATA FIELD ACCESS ++ //

    protected function ParentID() {
	return $this->GetFieldValue('ID_Parent');
    }
    protected function HasParent() {
	return !is_null($this->ParentID());
    }
    protected function PathPart() {
	return $this->GetFieldValue('PathPart');
    }
    protected function Attrib_forFolder() {
	return $this->Value('AttrFldr');
    }

    // -- DATA FIELD ACCESS -- //
    // ++ DATA LOOKUP ++ //

    protected function ParentRecord() {
	return $this->Table()->GetItem($this->ParentID());
    }

    // -- DATA LOOKUP -- //
    // ++ DATA FIELD CALCULATIONS ++ //

    public function Spec() {
	$out = '';
	if ($this->HasParent()) {
	    $out = $this->ParentRecord()->Spec();
	}
	$out .= $this->PathPart();
	return $out;
    }

    // -- DATA FIELD CALCULATIONS -- //
    // ++ WEB UI ++ //

    /*----
      ACTION: Shows a drop-down selection box contining the rows in the current dataset
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
    */
    public function DropDown_for_rows($iName,$iDefault=NULL) {
	if ($this->HasRows()) {
	    $out = '<select name="'.$iName.'">';
	    while ($this->NextRow()) {
		$id = $this->GetKeyValue();
		if ($id == $iDefault) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$out .= '<option'.$htSelect.' value="'.$id.'">'.$this->Spec().'</option>';
	    }
	    $out .= '</select>';
	} else {
	    $out = 'No images matching filter';
	}
	return $out;
    }

    // -- WEB UI -- //

    /*----
      RETURNS: The rest of the URL after this folder's PathPart is removed from the beginning
      USED BY: bulk image entry admin routine
	TODO: Move to admin class
    */
    public function Remainder($iSpec) {
	$fsFldr = $this->Value('PathPart');
	$slFldr = strlen($fsFldr);
	$fsRest = substr($iSpec,$slFldr);
	return $fsRest;
    }
}
