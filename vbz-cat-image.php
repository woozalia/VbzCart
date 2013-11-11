<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Images
    including Folders, since those are currently only used by Images.
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-image.php from base.cat.php
*/
class clsVbzFolders extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_folders');
	  $this->KeyName('ID');
	  $this->ClassSng('clsVbzFolder');
    }
    /*----
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
	* If table ever grows to a significant size, we might end up changing the filtering criteron.
    */
    public function DropDown($iName,$iDefault=NULL) {
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
class clsVbzFolder extends clsDataSet {
    public function Spec() {
	$out = '';
	if (!is_null($this->ID_Parent)) {
	    $out = $this->ParentObj()->Spec();
	}
	$out .= $this->PathPart;
	return $out;
    }
    protected function ParentObj() {
	return $this->Table->GetItem($this->ID_Parent);
    }
    /*----
      ACTION: Shows a drop-down selection box contining the rows in the current dataset
      FUTURE:
	* This method really belongs with Admin functions, since it will never be used in the standalone store
    */
    public function DropDown_for_rows($iName,$iDefault=NULL) {
	if ($this->HasRows()) {
	    $out = '<select name="'.$iName.'">';
	    while ($this->NextRow()) {
		if ($this->ID == $iDefault) {
		    $htSelect = " selected";
		} else {
		    $htSelect = '';
		}
		$out .= '<option'.$htSelect.' value="'.$this->ID.'">'.$this->Spec().'</option>';
	    }
	    $out .= '</select>';
	} else {
	    $out = 'No shipments matching filter';
	}
	return $out;
    }
    /*----
      RETURNS: The rest of the URL after this folder's PathPart is removed from the beginning
      USED BY: bulk image entry admin routine
    */
    public function Remainder($iSpec) {
	$fsFldr = $this->Value('PathPart');
	$slFldr = strlen($fsFldr);
	$fsRest = substr($iSpec,$slFldr);
	return $fsRest;
    }
}
class clsImages extends clsVbzTable {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('cat_images');
	  $this->KeyName('ID');
	  $this->ClassSng('clsImage');
    }
    public function Update(array $iSet,$iWhere) {
	$iSet['WhenEdited'] = 'NOW()';
	parent::Update($iSet,$iWhere);
	$this->Touch(__METHOD__.' WHERE='.$iWhere);
    }
    public function Insert(array $iData) {
	$iData['WhenAdded'] = 'NOW()';
	parent::Insert($iData);
	$this->Touch(__METHOD__);
    }
    public function Thumbnails($iTitle,array $iarAttr=NULL) {
	$sqlFilt = '(ID_Title='.$iTitle.') AND (Ab_Size="th") AND isActive';
	$objTbl = $this->objDB->Images();
	$objRows = $objTbl->GetData($sqlFilt,NULL,'AttrSort');
	return $objRows->Images_HTML($iarAttr);
    }
}
class clsImage extends clsVbzRecs {
// object cache
    protected $objTitle;

    /*----
      HISTORY:
	2010-11-16 Modified to use new cat_folders data via ID_Folder
    */
    public function WebSpec() {
	//return KWP_IMG_MERCH.$this->Spec;
	return $this->FolderPath().$this->Spec;
    }
    /*----
      HISTORY:
	2010-11-16 Created
    */
    public function FolderObj() {
	return $this->objDB->Folders()->GetItem($this->ID_Folder);
    }
    /*----
      HISTORY:
	2010-11-16 Created
    */
    public function FolderPath() {
	return $this->FolderObj()->Spec();
    }
    /*-----
      ACTION: Generate the HTML code to display all images in the current dataset
    */
    public function Images_HTML(array $iarAttr=NULL) {
	if ($this->HasRows()) {
	    $out = '';
	    while ($this->NextRow()) {
		$out .= $this->Image_HTML($iarAttr);
	    }
	    return $out;
	} else {
	    return NULL;
	}
    }
    /*-----
      ACTION: Generate the HTML code to display an image for the current row
    */
    public function Image_HTML(array $iarAttr=NULL) {
	$htDispl = $this->AttrDispl;
	if (!empty($htDispl)) {
	    nzApp($iarAttr['title'],' - '.$htDispl);
	}
	$iarAttr['src'] = $this->WebSpec();
	$htAttr = ArrayToAttrs($iarAttr);
	return '<img'.$htAttr.'>';
    }
    /*-----
      ACTION: Get the image with the same title and attribute but with the given size
    */
    public function ImgForSize($iSize) {
	if ($this->AttrFldr) {
	    $sqlAttr = '="'.$this->AttrFldr.'"';
	} else {
	    $sqlAttr = ' IS NULL';
	}
	$sqlFilt = '(ID_Title='.$this->ID_Title.') AND (AttrFldr'.$sqlAttr.') AND (Ab_Size="'.$iSize.'")';
	$objImgOut = $this->objDB->Images()->GetData($sqlFilt);
	return $objImgOut;
    }
    public function Title() {
      if (!is_object($this->objTitle)) {
	  $this->objTitle = $this->objDB->Titles()->GetItem($this->ID_Title);
      }
      return $this->objTitle;
    }
  public function ListImages_sameAttr() {
    $sqlFilt = 'isActive AND (ID_Title='.$this->ID_Title.')';
    if ($this->AttrFldr) {
      $sqlFilt .= ' AND (AttrFldr="'.$this->AttrFldr.'")';
    }
    $objImgOut = $this->objDB->Images()->GetData($sqlFilt);

    return $objImgOut;
  }
    public function ListImages_sameSize() {
	$sqlFilt = 'isActive AND (ID_Title='.$this->ID_Title.') AND (Ab_Size="'.$this->Ab_Size.'")';
//echo 'SQL: '.$sqlFilt;
	$objImgOut = $this->objDB->Images()->GetData($sqlFilt);
	return $objImgOut;
    }
    public function Href($iAbs=false) {
	$strFldrRel = $this->AttrFldr;
	if ($strFldrRel) {
	    $strFldrRel .= '-';
	}
	$strFldrRel .= $this->Ab_Size;

	if ($iAbs) {
	    $strFldr = $this->Title()->URL().'/'.$strFldrRel;
	} else {
	    $strFldr = $strFldrRel;
	}
	return '<a href="'.$strFldr.'/">';
    }
}
