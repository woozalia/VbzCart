<?php
/*
  PURPOSE: classes for Image records JOINed to other tables to return extended information
  HISTORY:
    2016-02-13 created for Topic exhibit page
*/

class vcqtImagesInfo extends clsImages_StoreUI {

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('vcqrImageInfo');
    }
    
    // -- SETUP -- //
    // ++ TABLES ++ //
    
    protected function ItemInfoTable() {
	return $this->Engine()->Make('vcqtItemsInfo');
    }
    protected function TitleInfoTable() {
	return $this->Engine()->Make('vcqtTitlesInfo');
    }
    
    // -- TABLES -- //
    // ++ SQL ++ //
    
    /*----
      HISTORY:
	2016-02-14 I'm assuming this is ALL images for the Topic...
	  (correction: all *active Images* for *all Titles* for the Topic)
	  ...but is this really the function we should be using?
    */
    protected function SQLobj_forSize_forTopic_all($sSizeKey,$idTopic) {
	$oq = $this->TitleInfoTable()->SQLobj_forTopicPage_all($idTopic);
	
	$sroImg = new fcSQL_TableSource($this->Name(),'im');
	//$oq->Select()->Source()->AddElement(new fcSQL_JoinElement($sroImg,'im.ID_Title=i.ID_Title'));
	$oq->Select()->Source()->AddElement(new fcSQL_JoinElement($sroImg,'im.ID_Title=t.ID'));
	
	$oq->Select()->Fields()->Values(array(
	      'im.ID',
	      'im.ID_Folder',
	      'im.Spec',
	      'im.Ab_Size',
	      'im.AttrFldr',
	      'im.AttrDispl',
	      't.ID'=>'ID_Title',
	      //'QtyForSale',
	      //'isAvail'
	    )
	  );
	  
	$oq->Terms()->UseTerms(
	  array(
	    new fcSQLt_Filt('AND',array(
		'im.isActive',
		"im.Ab_Size='$sSizeKey'",
		"tt.ID_Topic='$idTopic'"
		)
	      )
	    )
	  );
	  
      // TODO: sort by AttrSort
      
	return $oq;
    }
    /* 2016-02-18 Apparently this is not used.
    protected function SQL_forSize_withTitle($sSizeKey) {
	$sqlThis = $this->NameSQL();
	$sql = <<<__END__
SELECT it.ID_Title,im.*
FROM $sqlThis AS im JOIN cat_items AS it ON im.ID_Item=it.ID
WHERE im.Ab_Size="$sSizeKey"
__END__;
	return $sql;
    }//*/
    
    // -- SQL -- //
    // ++ RECORDS ++ //
    
    /*----
      PURPOSE: get Image records for a given image size with Title ID
      HISTORY:
	2016-02-13 The current process for displaing images on a Topic page looks up
	the thumbnails for each image on a title-by-title basis, creating dozens
	of database requests per page.The plan is to replace this with a single call
	to get all the necessary image records, link to Items to get the Title ID for each,
	and then compile the results into an array which can be merged with the Title/Stock
	information array collected separately.
    */
    /* 2016-02-18 Apparently nothing uses this.
    public function GetRecords_forSize_withTitle($sSizeKey='th') {
	$sql = $this->SQL_forSize_withTitle($sSizeKey);
	$rs = $this->DataSQL($sql);
	return $rs;
    }//*/
    public function GetRecords_forSize_forTopic($sSizeKey,$idTopic) {
	$qo = $this->SQLobj_forSize_forTopic_all($sSizeKey,$idTopic);
	$sql = $qo->Render();
	$rs = $this->DataSQL($sql);
	return $rs;
    }

}
class vcqrImageInfo extends clsImage_StoreUI {

    /*----
      ACTION: Stuff the recordset into an array keyed on $sKey
	then store that in the master Titles array under '@img'
      TODO: This should probably be renamed according to what it
	does now, i.e. add image info to titie results
    */
    public function ResultArray_byTitle(array $arTitles) {
	if ($this->HasRows()) {
	    while ($this->NextRow()) {
		$row = $this->Values();
		$idTitle = $row['ID_Title'];
		$idImg = $row['ID'];
		unset($row['ID_Title']);
		unset($row['ID']);
		$arTitles['data'][$idTitle]['@img'][$idImg] = $row;
	    }
	}
	return $arTitles;
    }
    /*----
      INPUT:
	* $ar: output from vcqtTitlesInfo->StatsArray_forTopic()
      OUTPUT: returns array
	array['act']['text']: text listing of available titles
	array['act']['imgs']: thumbnails of available titles
	array['ret']['imgs']: thumbnails of unavailable titles
    */
/* 2016-02-16 This should mostly be in title-info, I think...
    public function RenderTitleResults(array $ar) {
	$arRows = $ar['data'];
	$arActive = $this->RenderActiveTitlesResult($ar['active'],$arRows);
	$ht = $this->RenderRetiredTitlesResult($ar['retired']);
	$arOut = array(
	  'act' => $arActive,
	  'ret' => $ht
	  );
	return $arOut;
    }
    protected function RenderActiveTitlesResult(array $arIDs,array $arRows) {
	$htText = NULL;
	$htImgs = NULL;
	if (count($arIDs) > 0) {
	    foreach ($arIDs as $id) {
		$this->Values($arRows[$id]);
		$arRes = $this->RenderActiveTitleResult();
		$htText .= $arRes['text'];
		$htImgs .= $arRes['imgs'];
	    }
	}
	$arOut = array(
	  'text' => $htText,
	  'imgs' => $htImgs
	  );
	return $arOut;
    }
    protected function RenderRetiredTitlesResult(array $ar) {
	return 'to be written';
    }
    protected function TitleHREF() {
    
    // this is weird, but figure out later.
    
    echo clsArray::Render($this->Values());
	//$sCatPath = $this->Value('CatPath');
	$sCatPath = $this->Values()['CatPath'];
	$url = KWP_CAT_REL.strtolower($sCatPath);
	$htHref = "<a href='$url'>";
	return $htHref;
    }
    protected function RenderImages_withLink() {
	$row = $this->Values();
	$htImgs = NULL;
	if (array_key_exists('@img',$row)) {	// if there's image data...
	    $arImgs = $row['@img'];
	    foreach ($arImgs as $idImg => $img) {
		$this->Values($img);
		$htImg = $this->RenderSingle();
	    }
	    $htImgs .= $this->TitleHREF().$htImg.'</a>';
	}
	return $htImgs;
    }
    protected function RenderActiveTitleResult() {
	$row = $this->Values();
	$sCatNum = $row['CatNum'];
	$nPrcLo = $row['MinPrice'];
	$nPrcHi = $row['MaxPrice'];
	if ($nPrcLo = $nPrcHi) {
	  $sPrice = $nPrcLo;
	} else {
	  $sPrice = $nPrcLo.'-'.$nPrcHi;
	}
	$sTitle = $row['Name'];
	$sSummary = $sPrice.' : '.$row['CatOpts'];
	$qInStock = $row['QtyForSale'];
	if ($qInStock > 0) {
	    $sSummary .= ' - '.$qInStock.' in stock';
	}
	$htHref = $this->TitleHREF();
	$htText = $htHref.$sCatNum.' '.$sTitle.'</a>: '.$sSummary;
	$htImgs = $this->RenderImages_withLink();
	
	$arOut['text'] = $htText;
	$arOut['imgs'] = $htImgs;
	return $arOut;
    }
    protected function RenderRetiredTitleResult() {
	// TODO
    }//*/
}