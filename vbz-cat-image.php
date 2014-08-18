<?php
/*
  PART OF: VbzCart
  PURPOSE: classes for handling Images
    including Folders, since those are currently only used by Images.
  HISTORY:
    2012-05-08 split off base.cat.php from store.php
    2013-11-10 split off vbz-cat-image.php from base.cat.php
    2013-11-17 KS_IMG_SIZE_* constants; rewriting clsImages::Thumbnails()
      as Data_forTitle() -> Records_ForTitle()
*/

define('KS_IMG_SIZE_THUMB','th');
define('KS_IMG_SIZE_SMALL','sm');
define('KS_IMG_SIZE_LARGE','big');
define('KS_IMG_SIZE_ZOOM','zoom');

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
	    $out = $this->ParentRecord()->Spec();
	}
	$out .= $this->PathPart;
	return $out;
    }
    protected function ParentRecord() {
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

    // ++ STATIC ++ //

    const SIZE_THUMB	= KS_IMG_SIZE_THUMB;
    const SIZE_SMALL	= KS_IMG_SIZE_SMALL;
    const SIZE_LARGE	= KS_IMG_SIZE_LARGE;
    const SIZE_ZOOM	= KS_IMG_SIZE_ZOOM;


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
    /*----
      RETURNS: recordset of images for the given title in the given size
      HISTORY:
	2013-11-17 split off from Thumbnails(), which kind of shouldn't exist
      INPUT:
	idTitle = ID of title to look up
	sSize = key for size of image wanted - use KS_IMG_SIZE_* constant
    */
    public function Records_forTitle($idTitle,$sSize) {
	$sqlFilt = '(ID_Title='.$idTitle.') AND (Ab_Size="'.$sSize.'") AND isActive';
	$tbl = $this->Engine()->Images();
	$rs = $tbl->GetData($sqlFilt,NULL,'AttrSort');
	return $rs;
    }
    /*----
      RETURNS: recordset of images for the given titles in the given size
      PURPOSE: same as Records_forTitle, but accepts a comma-separated list of titles
      HISTORY:
	2013-11-18 created
	2014-03-22 renamed from Records_forTitles() to Records_forTitles_SQL()
    */
    public function Records_forTitles_SQL($sqlTitles,$sSize) {
	if (is_null($sqlTitles)) {
	    return NULL;
	} else {
	    $sqlFilt = '(ID_Title IN ('.$sqlTitles.')) AND (Ab_Size="'.$sSize.'") AND isActive';
	    $rs = $this->GetData($sqlFilt,NULL,'ID_Title, AttrSort');
	    return $rs;
	}
    }
/*
    public function Thumbnails($iTitle,array $iarAttr=NULL) {
	$sqlFilt = '(ID_Title='.$iTitle.') AND (Ab_Size="th") AND isActive';
	$objTbl = $this->objDB->Images();
	$objRows = $objTbl->GetData($sqlFilt,NULL,'AttrSort');
	return $objRows->Images_HTML($iarAttr);
    }
*/
}
class clsImage extends clsVbzRecs {
    static $arSzNames = array(
      KS_IMG_SIZE_THUMB => 'thumb',
      KS_IMG_SIZE_SMALL => 'small',
      KS_IMG_SIZE_LARGE => 'large',
      KS_IMG_SIZE_ZOOM => 'detail'
      );

// object cache
    protected $objTitle;

    // ++ FIELD ACCESS ++ //

    protected function FolderID() {
	return $this->Value('ID_Folder');
    }
    protected function Spec() {
	return $this->Value('Spec');
    }
    protected function TitleID() {
	return $this->Value('ID_Title');
    }
    public function AttrDescr() {
	return $this->Value('AttrDispl');
    }
    /*----
      HISTORY:
	2010-11-16 Modified to use new cat_folders data via ID_Folder
      TODO: rename to ShopSpec()
    */
    public function WebSpec() {
	return $this->FolderPath().$this->Spec();
    }
    public function ShopLink($sText) {
	$url = $this->WebSpec();
	$out = "<a href=\"$url\">$sText</a>";
	return $out;
    }
    /*----
      HISTORY:
	2010-11-16 Created
    */
    protected function FolderPath() {
	return $this->FolderRecord()->Spec();
    }

    // -- FIELD ACCESS -- //
    // ++ CLASS NAMES ++ //

    protected function TitlesClass() {
	return 'clsVbzTitles';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function FolderTable($id=NULL) {
	return $this->Engine()->Make('clsVbzFolders',$id);
    }
    protected function TitleTable($id=NULL) {
	return $this->Engine()->Make($this->TitlesClass(),$id);
    }

    // -- DATA TABLES ACCESS -- //
    // ++ DATA RECORDS ACCESS ++ //

    /*----
      HISTORY:
	2010-11-16 Created
    */
    public function FolderRecord() {
	return $this->FolderTable($this->FolderID());
    }
    public function TitleRecord() {
	$idTitle = $this->TitleID();
	if (is_null($idTitle)) {
	    return NULL;
	} else {
	    return $this->TitleTable($idTitle);
	}
    }

    // -- DATA RECORDS ACCESS -- //
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
    public function TitleObj() {
      if (!is_object($this->objTitle)) {
	  $this->objTitle = $this->objDB->Titles()->GetItem($this->ID_Title);
      }
      return $this->objTitle;
    }
    public function Data_forSameAttr() {
	$sqlFilt = 'isActive AND (ID_Title='.$this->ID_Title.')';
	$sAttr = $this->Value('AttrFldr');
	$sqlAttr = is_null($sAttr)?'IS NULL':('= "'.$sAttr.'"');
	//if ($this->AttrFldr) {
	  $sqlFilt .= " AND (AttrFldr $sqlAttr)";
	//}
	$objImgOut = $this->objDB->Images()->GetData($sqlFilt);

	return $objImgOut;
    }
    public function Data_forSameSize() {
	$sqlFilt = 'isActive AND (ID_Title='.$this->ID_Title.') AND (Ab_Size="'.$this->Ab_Size.'")';
//echo 'SQL: '.$sqlFilt;
	$objImgOut = $this->objDB->Images()->GetData($sqlFilt);
	return $objImgOut;
    }
    /*----
      TODO: rename or deprecate this -- the name is misleading; it's actually the href
	to the *title*...
    */
    public function Href($iAbs=false) {
	$strFldrRel = $this->AttrFldr;
	if ($strFldrRel) {
	    $strFldrRel .= '-';
	}
	$strFldrRel .= $this->Value('Ab_Size');

	if ($iAbs) {
	    $strFldr = $this->TitleObj()->URL().'/'.$strFldrRel;
	} else {
	    $strFldr = $strFldrRel;
	}
	return '<a href="'.$strFldr.'/">';
    }
}
