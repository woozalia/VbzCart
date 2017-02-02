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
    2016-10-25 Updated for db.v2
*/

define('KS_IMG_SIZE_THUMB','th');
define('KS_IMG_SIZE_SMALL','sm');
define('KS_IMG_SIZE_LARGE','big');
define('KS_IMG_SIZE_ZOOM','zoom');

class clsImages extends vcShopTable {

    // ++ STATIC DATA ++ //

    const SIZE_THUMB	= KS_IMG_SIZE_THUMB;
    const SIZE_SMALL	= KS_IMG_SIZE_SMALL;
    const SIZE_LARGE	= KS_IMG_SIZE_LARGE;
    const SIZE_ZOOM	= KS_IMG_SIZE_ZOOM;

    // -- STATIC DATA -- //
    // ++ SETUP ++ //
    
    // CEMENT
    protected function TableName() {
	return 'cat_images';
    }
    // CEMENT
    protected function SingularName() {
	return 'clsImage';
    }
    
    // -- SETUP -- //
    // ++ KLUGES ++ //
    
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
    
    // -- KLUGES -- //
    // ++ RECORDS ++ //

    public function GetImageRecord_byTitle_andCatKey($idTitle,$sCatKey) {
	$sqlFilt = "(ID_Title=$idTitle) AND (CatKey='$sCatKey') AND isActive";
	$sqlSort = 'AttrSort';
	$rs = $this->SelectRecords($sqlFilt,$sqlSort);
	$nRows = $rs->RowCount();
	if ($nRows == 1) {
	    $rs->NextRow();	// load the only row I've ever known
	} elseif ($nRows > 1) {
	    throw new exception($nRows." images for Title ID $idTitle match the key '$sCatKey'. (SQL: ".$rs->sqlMake.')');
	}
	return $rs;
    }
/*----
      RETURNS: recordset of images for the given title in the given size
      HISTORY:
	2013-11-17 split off from Thumbnails(), which kind of shouldn't exist
	2016-02-18 renamed from Records_forTitle() to ActiveRecords_forTitle()
      INPUT:
	idTitle = ID of title to look up
	sSize = key for size of image wanted - use KS_IMG_SIZE_* constant
    */
    public function ActiveRecords_forTitle($idTitle,$sSize) {
	$sqlFilt = '(ID_Title='.$idTitle.') AND (Ab_Size="'.$sSize.'") AND isActive';
	$rs = $this->SelectRecords($sqlFilt,'AttrSort');
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
class clsImage extends vcShopRecordset {
    static $arSzNames = array(
      KS_IMG_SIZE_THUMB => 'thumb',
      KS_IMG_SIZE_SMALL => 'small',
      KS_IMG_SIZE_LARGE => 'large',
      KS_IMG_SIZE_ZOOM => 'detail'
      );

// object cache
    protected $objTitle;

    // ++ FIELD ACCESS ++ //

    protected function TitleID() {
	return $this->GetFieldValue('ID_Title');
    }
    protected function FolderID() {
	return $this->GetFieldValue('ID_Folder');
    }
    protected function Spec() {	// alias; TODO: deprecate
	return $this->SpecPart();
    }
    protected function SpecPart() {
	return $this->GetFieldValue('Spec');
    }
    protected function IsActive() {
	return $this->Value('isActive');
    }
    public function AttrDescr() {
	return $this->Value('AttrDispl');
    }
    protected function Abbrev_forSize() {
	return $this->GetFieldValue('Ab_Size');
    }
    protected function Attrib_forFolder() {
	return $this->GetFieldValue('AttrFldr');
    }
    protected function Attrib_forDisplay() {
	return $this->GetFieldValue('AttrDispl');
    }
    protected function Attrib_forSort() {
	return $this->Value('AttrSort');
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
	throw new exception('image.ShopLink() is deprecated; call RenderPageLink() instead.');
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
	return 'vctTitles';
    }
    protected function FoldersClass() {
	return 'clsVbzFolders';
    }

    // -- CLASS NAMES -- //
    // ++ DATA TABLES ACCESS ++ //

    protected function FolderTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->FoldersClass(),$id);
    }
    protected function TitleTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->TitlesClass(),$id);
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
      ACTION: Get the image with the same title and attribute as the current one, but with the given size
    */
    public function ImgForSize($sSize) {
	if ($this->Attrib_forFolder()) {
	    $sqlAttr = '="'.$this->Attrib_forFolder().'"';
	} else {
	    $sqlAttr = ' IS NULL';
	}
	$sqlFilt = '(ID_Title='.$this->TitleID().') AND (AttrFldr'.$sqlAttr.') AND (Ab_Size="'.$sSize.'")';
	$rcImg = $this->GetTableWrapper()->SelectRecords($sqlFilt);
	return $rcImg;
    }
    public function TitleObj() {
      if (!is_object($this->objTitle)) {
	  $this->objTitle = $this->TitleTable()->GetRecord_forKey($this->TitleID());
      }
      return $this->objTitle;
    }
    public function Data_forSameAttr() {
	$sqlFilt = 'isActive AND (ID_Title='.$this->TitleID().')';
	$sAttr = $this->Attrib_forFolder();
	$sqlAttr = is_null($sAttr)?'IS NULL':('= "'.$sAttr.'"');
	//if ($this->AttrFldr) {
	  $sqlFilt .= " AND (AttrFldr $sqlAttr)";
	//}
	$objImgOut = $this->GetTableWrapper()->SelectRecords($sqlFilt);

	return $objImgOut;
    }
    public function Data_forSameSize() {
	$sqlFilt = 'isActive AND (ID_Title='.$this->TitleID().') AND (Ab_Size="'.$this->Abbrev_forSize().'")';
//echo 'SQL: '.$sqlFilt;
	$objImgOut = $this->GetTableWrapper()->SelectRecords($sqlFilt);
	return $objImgOut;
    }
}
