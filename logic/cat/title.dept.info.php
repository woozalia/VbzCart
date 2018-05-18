<?php
/*
  PURPOSE: Title-info function specific to single-Department exhibit pages
  HISTORY:
    2018-02-17 extracted from vcqtTitlesInfo as part of fixing the Department Titles exhibit
*/
class vcqtTitlesInfo_forDept extends vcqtTitlesInfo {
    /*----
      PURPOSE: SQL for pulling together stock and price info when sub-title-granularity filtering is not needed
      NOTES:
	This is just SQL_forTopicPage() with minor tweaks.
	It *could* be moved up to vcqtTitleInfo if any other subclasses need it.
      HISTORY:
	2018-05-13 cat_ittyps and cat_ioptns need to be *LEFT* JOIN or else a lot of stuff gets left out. Fixed.
    */
    protected function SQL_forInfo($doInactive) {
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
    LEFT JOIN cat_ittyps AS itt ON i.ID_ItTyp=itt.ID
    LEFT JOIN `cat_ioptns` AS io ON i.ID_ItOpt = io.ID
    LEFT JOIN `stk_lines` AS sl ON sl.ID_Item = i.ID
    JOIN`stk_bins` AS sb ON sl.ID_Bin = sb.ID
WHERE
    (sb.isEnabled)$sqlActive
GROUP BY i.ID_Title
__END__;
    }    /*----
      HISTORY:
	2018-02-17 adapted from SQL_forTopicPage_wTitleInfo() for revision of Dept exhibit page
    */
    protected function SQL_forDeptPage_wTitleInfo($idDept,$doInactive) {
	$sqlCore = $this->SQL_forInfo($doInactive);
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
WHERE t.ID_Dept=$idDept
__END__;
	return $sql;
    }    /*----
      PURPOSE: loads data needed to display catalog views for this department
      HISTORY
	2010-11-12 disabled automatic cache update
	2010-11-16 changed sorting field from cntInPrint to cntForSale
	2011-02-02 using _dept_ittyps now instead of qryItTypsDepts_ItTyps
	  Also added "AND (cntForSale)" to WHERE clause -- not listing titles with nothing to sell
	2013-11-18 rewriting
	2016-02-28
	  * moved Data_forStore() from dept.shop to title.info
	  * split into SQO_forDeptExhibit() and GetRecords_forDeptExhibit()
	  * TODO: possibly this could be better integrated with other SQO methods
    */
    protected function SQO_forDeptExhibit($idDept) {
	$db = $this->Engine();

	// TABLE SOURCEs
	$osItem = new fcSQL_TableSource($this->ItemTable()->Name(),'i');
	$osTitle = new fcSQL_TableSource($this->Name(),'t');
	$osItTyp = new fcSQL_TableSource($this->ItemTypeTable()->Name(),'it');
	// query object
	$oq = vcqtStockLinesInfo::SQO_forItemStatus();
	// add JOIN elements to JOIN SOURCE
	$oj = $oq->Select()->Source();
	$oj->AddElement(new fcSQL_JoinElement($osItem,'sl.ID_Item=i.ID'));
	$oj->AddElement(new fcSQL_JoinElement($osTitle,'i.ID_Title=t.ID'));
	$oj->AddElement(new fcSQL_JoinElement($osItTyp,'i.ID_ItTyp=it.ID'));
	$oq->Select()->Fields()->SetFields(
	  array(
	    'i.ID_Title',
	    'i.ID_ItTyp',
	    'i.PriceSell',
	    'i.ItOpt_Sort',
	    'i.CatSfx',
	    't.CatKey',
	    'it.NameSng',
	    'it.NamePlr',
	    't.Name',
	    't.CatKey',
	    )
	  );
	$oq->Terms()->Filters()->AddCond('t.ID_Dept='.$idDept);
	$oq->Terms()->UseTerm(new fcSQLt_Sort(array('t.CatKey','i.ItOpt_Sort')));
	return $oq;
    }

}