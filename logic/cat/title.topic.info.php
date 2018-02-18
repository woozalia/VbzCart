<?php
/*
  PURPOSE: Title-info function specific to the all-Topics listing exhibit page
  HISTORY:
    2018-02-17 extracted from vcqtTitlesInfo as part of tidying things up in order to fix the Department Titles exhibit
*/

class vcqtTitlesInfo_forTopic extends vcqtTitlesInfo {

    /*
      PRODUCES: record for each Title that has at least one Item for sale,
	with summary of what's available (options, price range) --
	filtered for the given Topic.
      NOTE: We could possibly allow more general filtering than just by Topic,
	but the filter would have to be constructed so as not to return multiple
	Titles because that would mess up the Option listing.
      PUBLIC so admin version of this query-table can build on it
    */
    /* 2018-02-17 This appears to be unused now.
    public function SQO_forTopicPage_active($idTopic) {
	if (is_null($idTopic)) {
	    // include all Titles, then filter for no Topic
	    $sqlTopicJoin = 'LEFT JOIN';
	    $arTopicFilt = array('tt.ID_Topic IS NULL');
	} else {
	    // include only Titles assigned to the Topic given
	    $sqlTopicJoin = 'JOIN';
	    $arTopicFilt = array('tt.ID_Topic='.$idTopic);
	}
	
	$oq = $this->ItemInfoQuery()->SQO_forSale();
	
	$sroTitle = new fcSQL_TableSource($this->Name(),'t');
	$sroTT = new fcSQL_TableSource('cat_title_x_topic','tt');
	$sroItOpt = new fcSQL_TableSource('cat_ioptns','io');
	
	$oq->Select()->Source()->AddElements(
	  array(
	    new fcSQL_JoinElement($sroTitle,'i.ID_Title=t.ID'),
	    new fcSQL_JoinElement($sroTT,'i.ID_Title=tt.ID_Title',$sqlTopicJoin),
	    new fcSQL_JoinElement($sroItOpt,'i.ID_ItOpt=io.ID','LEFT JOIN')	// include optionless items
	  )
	);
	$oqf = $oq->Select()->Fields();
	$oqf->ClearFields();
	$oqf->SetFields($this->Fields_active_forCompile_array());
	$oq->Terms()->UseTerm(new fcSQLt_Group(array('i.ID_Title')));
	
	$oq->Terms()->UseTerm(new fcSQLt_Filt('AND',$arTopicFilt));
	
	return $oq;
    } */
    /*----
      PRODUCES: record for each Title for the given Topic, regardless of availability
	OR, if idTopic is NULL, a record for each Title that is not assigned to any title
	(also regardless of availability).
      PURPOSE: It turns out that in order to produce a recordset only of Titles with NO
	active Items, we'd basically have to do the Item-Stock query all over again.
	This seems wasteful of CPU (...although it may ultimately turn out to be
	more efficient, but for now I'm guessing it isn't) -- so what we do instead
	is subtract that already-generated list from this one in code.
	
	Meanwhile, we can use this recordset to look up CatNums so we don't have to duplicate
	that effort in the active-titles recordset. Hurrah!
      NOTE: This is at least much simpler than the 'active' query because we don't even
	need to look at Item (or Stock or Options) information; we just need to join
	with Departments and Suppliers to get the CatNum.
      HISTORY:
	2017-05-28 made it explicit that $idTopic can be NULL
    */
    public function SQO_forTopicPage_all($idTopic=NULL) {
	if (is_null($idTopic)) {
	    // include all Titles, then filter for no Topic
	    $sqlTopicJoin = 'LEFT JOIN';
	    $arTopicFilt = array('tt.ID_Topic IS NULL');
	} else {
	    // include only Titles assigned to the Topic given
	    $sqlTopicJoin = 'JOIN';
	    $arTopicFilt = array('tt.ID_Topic='.$idTopic);
	}
    
	$sroThis = new fcSQL_TableSource($this->TableName(),'t');
	$sroTT = new fcSQL_TableSource('cat_title_x_topic','tt');
	$sroDept = new fcSQL_TableSource($this->DepartmentTable()->TableName(),'d');
	$sroSupp = new fcSQL_TableSource($this->SupplierTable()->TableName(),'s');
	
	$arJT = array(
	  new fcSQL_JoinElement($sroThis),	// Titles
	  new fcSQL_JoinElement($sroTT,'t.ID=tt.ID_Title',$sqlTopicJoin),
	  new fcSQL_JoinElement($sroDept,'t.ID_Dept=d.ID'),
	  new fcSQL_JoinElement($sroSupp,'t.ID_Supp=s.ID')
	  );
	$qJoin = new fcSQL_JoinSource($arJT);
	$qjSel = new fcSQL_Select($qJoin);
	$qjf = $qjSel->Fields();
	$qjf->ClearFields();
	$qjf->SetFields($this->Fields_all_forCompile_array());
	$qTerms = new fcSQL_Terms(
	  array(
	    new fcSQLt_Filt('AND',$arTopicFilt)
	    )
	  );
	$oq = new fcSQL_Query($qjSel,$qTerms);
	
	return $oq;
    }
    /*----
      HISTORY:
	2016-04-10 Fixed the filter to exclude removed stock and stock in disabled bins
	2018-02-17
	  Now including ItOptions
	  Extracted to vcqtTitlesInfo_forTopics but this may be temporary as I sort things out
	  DEPRECATED because we should be using SQL_forTopicPage()
    */
    protected function SQL_forTopicPage_active($idTopic) {
	return <<<__END__
SELECT 
    t.ID,
    SUM(sl.Qty) AS QtyInStock,
    COUNT((Qty > 0) OR isAvail) AS CountForSale,
    MIN(PriceSell) AS PriceMin,
    MAX(PriceSell) AS PriceMax,
    GROUP_CONCAT(DISTINCT itt.NameSng ORDER BY itt.Sort SEPARATOR ', ') AS ItTypes,
    GROUP_CONCAT(DISTINCT io.CatKey ORDER BY io.Sort SEPARATOR ', ') AS ItOptions
FROM
    `cat_items` AS i
    JOIN `cat_titles` AS t ON i.ID_Title = t.ID
    JOIN `cat_title_x_topic` AS tt ON i.ID_Title = tt.ID_Title
    JOIN cat_ittyps AS itt ON i.ID_ItTyp=itt.ID
    JOIN `cat_ioptns` AS io ON i.ID_ItOpt = io.ID
    LEFT JOIN `stk_lines` AS sl ON sl.ID_Item = i.ID
    JOIN`stk_bins` AS sb ON sl.ID_Bin = sb.ID

WHERE
    (tt.ID_Topic = $idTopic) AND (sl.Qty > 0) AND (sb.isEnabled)
GROUP BY i.ID_Title
__END__;
    }
    protected function SQL_forTopicPage($idTopic,$doInactive) {
	$sqlActive = $doInactive?'':' AND (CountForSale > 0)';
	return <<<__END__
SELECT 
    t.ID,
    SUM(sl.Qty) AS QtyInStock,
    COUNT((Qty > 0) OR i.isAvail) AS CountForSale,
    MIN(PriceSell) AS PriceMin,
    MAX(PriceSell) AS PriceMax,
    GROUP_CONCAT(DISTINCT itt.NameSng ORDER BY itt.Sort SEPARATOR ', ') AS ItTypes,
    GROUP_CONCAT(DISTINCT io.CatKey ORDER BY io.Sort SEPARATOR ', ') AS ItOptions
FROM
    `cat_items` AS i
    JOIN `cat_titles` AS t ON i.ID_Title = t.ID
    JOIN `cat_title_x_topic` AS tt ON i.ID_Title = tt.ID_Title
    JOIN cat_ittyps AS itt ON i.ID_ItTyp=itt.ID
    JOIN `cat_ioptns` AS io ON i.ID_ItOpt = io.ID
    LEFT JOIN `stk_lines` AS sl ON sl.ID_Item = i.ID
    JOIN`stk_bins` AS sb ON sl.ID_Bin = sb.ID

WHERE
    (tt.ID_Topic = $idTopic)$sqlActive AND (sb.isEnabled)
GROUP BY i.ID_Title
__END__;
    }
    /*----
      HISTORY:
	2018-02-17 written for revision of Topic exhibit page
    */
    protected function SQL_forTopicPage_wTitleInfo($idTopic,$doInactive) {
	$sqlCore = $this->SQL_forTopicPage($idTopic,$doInactive);
	$sql = <<<__END__
SELECT
    tp.ID,
    t.Name,
    t.ID_Dept,
    t.ID_Supp,
    t.CatKey,
    tp.QtyInStock,
    tp.CountForSale,
    tp.PriceMin,
    tp.PriceMax,
    tp.ItTypes,
    tp.ItOptions
FROM ($sqlCore) AS tp
  LEFT JOIN cat_titles AS t ON tp.ID=t.ID
__END__;
	return $sql;
    }
    
    // ++ ARRAY ++ //
    
    // USED BY: single-Topic exhibit page
    public function StatsArray_forTopic($idTopic) {
	$sqlAct = $this->SQL_forTopicPage_active($idTopic);
	$oqAll = $this->SQO_forTopicPage_all($idTopic);
	
	return $this->CompileResults($oqAll->Render(),$sqlAct);
    }
    
    // -- ARRAY -- //
}