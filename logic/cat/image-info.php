<?php
/*
  PURPOSE: classes for Image records JOINed to other tables to return extended information
  HISTORY:
    2016-02-13 created for Topic exhibit page
    2016-10-25 Updated for db.v2
*/

trait vtTableAccess_ImagesInfo {
    protected function ImageInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtImagesInfo');
    }
}

class vcqtImagesInfo extends vctImages_StoreUI {
    use ftQueryableTable;

    // ++ OVERRIDES ++ //

    protected function SingularName() {
	return 'vcqrImageInfo';
    }
    
    // -- OVERRIDES -- //
    // ++ TABLES ++ //
    
    protected function ItemInfoQuery() {
	return $this->Engine()->Make('vcqtItemsInfo');
    }
    protected function TitleInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtTitlesInfo');
    }
    protected function TitleTable() {
	return $this->Engine()->Make('vctTitles');
    }
    
    // -- TABLES -- //
    // ++ SQL ++ //

    /*----
      GENERATES: Active image records for the given size and filter
    */
    protected function SQO_forSize($sSizeKey,$sqlFilt) {
	$qsoImg = new fcSQL_TableSource($this->TableName(),'im');
	$qse = new fcSQL_Select($qsoImg);
	$qte = new fcSQL_Terms(
	  array(
	    new fcSQLt_Filt('AND',array(
		'isActive',
		"Ab_Size='$sSizeKey'",
		$sqlFilt
		)
	      )
	    )
	    // TODO: sort by image attribute
	  );
	
	$qo = new fcSQL_Query($qse,$qte);
	return $qo;
    }
   
    /*----
      GENERATES: Image records for the given size, attached to Title records
      PURPOSE: for cases where we need Title information, e.g. ID_Dept, but not availability
    */
    protected function SQO_forSize_wTitle($sSizeKey) {
	
	// table sources for JOIN
	$qoSrcTtl = $this->TitleInfoQuery()->SQO_Source('t');
	$qoSrcImg = $this->SQO_Source('im');
	
	// combine tables into JOIN source
	$qoJoinSrc = new fcSQL_JoinSource(array(
	    new fcSQL_JoinElement($qoSrcImg),
	    new fcSQL_JoinElement($qoSrcTtl,'im.ID_Title=t.ID')
	    )
	  );
	$qo = new fcSQL_Query(
	  new fcSQL_Select(
	    $qoJoinSrc
	  ),
	  new fcSQL_Terms(
	    array(
	      new fcSQLt_Filt(
		'AND',
		array(
		  'im.isActive',
		  "Ab_Size='$sSizeKey'"
		)
	      )
	    )  
	  )
	);
	return $qo;
    }
    /*----
      HISTORY:
	2016-02-14 I'm assuming this is ALL images for the Topic...
	  (correction: all *active Images* for *all Titles* for the Topic)
	  ...but is this really the function we should be using?
    */
    protected function SQO_forSize_forTopic_all($sSizeKey,$idTopic) {
	$qo = $this->SQO_forSize($sSizeKey,"tt.ID_Topic=$idTopic");
	
	$qosTT = new fcSQL_TableSource('cat_title_x_topic','tt');
	$qosIm = $qo->Select()->Source();
	$arJT = array(
	  new fcSQL_JoinElement($qosIm),	// Images
	  new fcSQL_JoinElement($qosTT,'tt.ID_Title=im.ID_Title')
	  );
	$qj = new fcSQL_JoinSource($arJT);
	$qo->Select()->Source($qj);
	
	$qof = $qo->Select()->Fields();
	$qof->ClearFields();
	$qof->SetFields(array(
	      'im.ID',
	      'im.ID_Folder',
	      'im.Spec',
	      'im.Ab_Size',
	      'im.AttrFldr',
	      'im.AttrDispl',
	      'im.ID_Title',
	      //'QtyForSale',
	      //'isAvail'
	    )
	  );
	  
	$qo->Terms()->UseTerms(
	  array(
	    new fcSQLt_Filt('AND',array(
		'im.isActive',
		"im.Ab_Size='$sSizeKey'",
		//"tt.ID_Topic='$idTopic'"
		)
	      )
	    )
	  );
	  
      // TODO: sort by AttrSort
      
	return $qo;
    }
    
    // -- SQL -- //
    // ++ RECORDS ++ //

    protected function GetRecords_forSize($sSizeKey,$sqlFilt) {
	$qo = $this->SQO_forSize($sSizeKey,$sqlFilt);
	$sql = $qo->Render();
	$rs = $this->FetchRecords($sql);
	return $rs;
    }
    public function GetRecords_forThumbs($sqlFilt) {
	return $this->GetRecords_forSize(self::SIZE_THUMB,$sqlFilt);
    }
    public function GetRecords_forThumbs_forDept($idDept) {
	$qo = $this->SQO_forSize_wTitle(self::SIZE_THUMB,$idDept);
	$qo->Terms()->Filters()->AddCond('ID_Dept='.$idDept);
	$qo->Select()->Fields()->SetFields(
	  array(
	    'im.ID',
	    'im.ID_Title',
	    'im.ID_Folder',
	    'ID_Dept',
	    'im.Spec',
	    'im.AttrDispl'
	  )
	);
	$sql = $qo->Render();
	$rs = $this->FetchRecords($sql);
	return $rs;
    }
    public function GetRecords_forThumbs_forTopic($idTopic) {
	$qo = $this->SQO_forSize_forTopic_all(self::SIZE_THUMB,$idTopic);
	$sql = $qo->Render();
	$rs = $this->FetchRecords($sql);
	return $rs;
    }
    /*----
      PURPOSE: get Image records for a given image size with Title ID for the given Topic ID
	This requires joining to Title-x-Topic in order to check Topic IDs.
      HISTORY:
	2016-02-13 The current process for displaying images on a Topic page looks up
	  the thumbnails for each image on a title-by-title basis, creating dozens
	  of database requests per page.The plan is to replace this with a single call
	  to get all the necessary image records, link to Items to get the Title ID for each,
	  and then compile the results into an array which can be merged with the Title/Stock
	  information array collected separately.
    */
    public function GetRecords_forSize_forTopic($sSizeKey,$idTopic) {
	$qo = $this->SQO_forSize_forTopic_all($sSizeKey,$idTopic);
	$sql = $qo->Render();
	$rs = $this->FetchRecords($sql);
	return $rs;
    }

}
class vcqrImageInfo extends vcrImage_StoreUI {

    /*----
      ACTION: Stuff the recordset into an array keyed on Title ID.
	That is, for each array[Title ID], there will be a sub-array
	consisting of all the image records for that Title ID.
      INPUT can be either NULL or an array containing Title data,
	which must be keyed on Title ID (i.e. array[ID Title] has
	a sub-array of information about that Title).
    */
    public function Collate_byTitle(array $arTitles=NULL) {
	if ($this->HasRows()) {
	    while ($this->NextRow()) {
		$row = $this->GetFieldValues();
		$idTitle = $row['ID_Title'];
		$idImg = $row['ID'];
		unset($row['ID_Title']);
		unset($row['ID']);
		$arTitles['data'][$idTitle]['@img'][$idImg] = $row;
	    }
	}
	return $arTitles;
    }
}