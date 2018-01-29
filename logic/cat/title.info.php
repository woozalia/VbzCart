<?php
/*
  PURPOSE: handles Title-based recordsets that also include item and stock summary data
  HISTORY:
    2016-02-11 started
    2016-02-18 more or less working
    2016-10-25 Updated for db.v2
*/
class vctCatDepartments_queryable extends vctDepts {
    use ftQueryableTable;
    use vtTableAccess_Supplier;

    // ++ CLASSES ++ //
    
    protected function SuppliersClass() {
	return 'vctCatSuppliers_queryable';
    }
    
    // -- CLASSES -- //
    // ++ SQO PIECES ++ //

    public function SourceSuppliers() {
	return $this->SupplierTable()->SQO_Source('s');
	//return new fcSQL_TableSource($this->SupplierTable()->Name(),'s');
    }

    // -- SQO PIECES -- //
}
class vctCatSuppliers_queryable extends vctSuppliers {
    use ftQueryableTable;
}
trait vtQueryableTable_Titles {
    use ftQueryableTable;

    // ++ CLASSES ++ //
    
    // need to use queryable versions of the basic logic tables
    
    protected function DepartmentsClass() {
	return 'vctCatDepartments_queryable';
    }
    
    // -- CLASSES -- //
    // ++ SQL FRAGMENTS ++ //
    
    /*----
      HISTORY:
	2017-08-12 Department-related logic moved into vctDepts.
    */
    static public function SQLfrag_CatNum() {
	$sqlDept = vctDepts::SQLfrag_CatNum();
	return "CONCAT($sqlDept,'-',UPPER(t.CatKey))";
//	return "UPPER(CONCAT_WS('-',s.CatKey,d.CatKey,t.CatKey))";
    }
    /*----
      HISTORY:
	2017-08-12 Department-related logic moved into vctDepts.
    */
    static public function SQLfrag_CatPath() {
	$sqlDept = vctDepts::SQLfrag_CatPath();
	return "CONCAT($sqlDept,'/',LOWER(t.CatKey))";
//	return "LOWER(CONCAT_WS('/',s.CatKey,IFNULL(d.PageKey,d.CatKey),t.CatKey))";
    }
    
    // -- SQL FRAGMENTS -- //
    // ++ SQO PIECES ++ //

    protected function SourceDepartments() {
	return $this->DepartmentTable()->SQO_Source('d');
	//return new fcSQL_TableSource($this->DepartmentTable()->Name(),'d');
    }
    protected function SourceSuppliers() {
	return $this->DepartmentTable()->SourceSuppliers();
    }
    
    // -- SQO PIECES -- //
}

class vcqtTitlesInfo extends vctShopTitles {
    use vtQueryableTable_Titles;
    use vtTableAccess_Supplier,vtTableAccess_ItemType;
  
    // ++ CEMENTING ++ //

    protected function SingularName() {
	return 'vcqrTitleInfo';
    }

    // -- CEMENTING -- //
    // ++ SQL CALCULATION ++ //

    /*----
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
    // RETURNS: SQO for Titles with at least one active Item
    public function SQO_active() {
	$qo = $this->ItemInfoQuery()->SQO_forSale();
	$qo->Select()->Fields(new fcSQL_Fields(array(
	      't.ID',
	      'QtyForSale' => 'SUM(sl.Qty)'	// alias => source
	      )
	    )
	  );
	$qo->Terms()->UseTerm(new fcSQLt_Group(array('i.ID_Title')));
	$qst = new fcSQL_TableSource($this->TableName(),'t');
	$qo->Select()->Source()->AddElements(
	  array(
	    new fcSQL_JoinElement($qst,'i.ID_Title=t.ID'),
	    )
	  );
	return $qo;
    }
    // RETURNS: SQO for all Titles, with availability fields
    public function SQO_availability() {
	$qo = $this->ItemInfoQuery()->SQO_Stats('ID_Title');
	$qo->Select()->Fields(new fcSQL_Fields(array(
	      't.ID',
	      'QtyForSale' => 'SUM(sl.Qty)'	// alias => source
	      )
	    )
	  );
	//$qo->Terms()->UseTerm(new fcSQLt_Group(array('i.ID_Title')));
	$qst = new fcSQL_TableSource($this->Name(),'t');
	$qo->Select()->Source()->AddElements(
	  array(
	    new fcSQL_JoinElement($qst,'i.ID_Title=t.ID'),
	    )
	  );
	return $qo;
    }
    
    // // ++ PAGE/COMPILE PIECES ++ // //
    
    // OUTPUT: array containing all the fields that Compile needs to see
    protected function Fields_active_forCompile_array() {
	return array(
	  't.ID',
	  //'t.Name',	// saved in master
	  'QtyInStock' => 'SUM(sl.Qty)',
	  'CountForSale' => 'COUNT((Qty>0) OR isAvail)',
	  'PriceMin' => 'MIN(PriceSell)',
	  'PriceMax' => 'MAX(PriceSell)',
	  'CatOpts' => "GROUP_CONCAT(DISTINCT io.CatKey ORDER BY io.Sort SEPARATOR ', ')"
	);
    }
    protected function Fields_all_forCompile_array() {
	return array(
	  't.ID',
	  't.Name',
	  'CatNum' => self::SQLfrag_CatNum(),
	  'CatPath' => self::SQLfrag_CatPath(),
	);
    }
    // PUBLIC so admin class can use it (maybe it should be there)
    public function SQOfrag_AddJoins_forCatNum(fcSQL_Query $qo) {
	$sroDept = $this->SourceDepartments();
	$sroSupp = $this->SourceSuppliers();
	$qo->Select()->Source()->AddElements(
	  array(
	    new fcSQL_JoinElement($sroDept,'t.ID_Dept=d.ID'),
	    new fcSQL_JoinElement($sroSupp,'t.ID_Supp=s.ID')
	    )
	  );
	$qo->Select()->Fields()->SetFields($this->Fields_all_forCompile_array());
    }
    // PUBLIC so admin class can use it (maybe it should be there)
    public function SQOfrag_Sort_byCatNum(fcSQL_Query $qo) {
	$qo->Terms()->UseTerm(new fcSQLt_Sort(array('CatNum')));
    }
    
    // // -- PAGE/COMPILE PIECES -- // //
    // // ++ DEPARTMENT PAGE ++ // //
    
    protected function SQO_forDeptPage_active($idDept) {
	$oq = $this->ItemInfoQuery()->SQO_forSale();
	//$sroTitle = new fcSQL_TableSource($this->Name(),'t');
	$sroTitle = $this->SQO_Source('t');
	$sroItOpt = new fcSQL_TableSource('cat_ioptns','io');
	$oq->Select()->Source()->AddElements(
	  array(
	    new fcSQL_JoinElement($sroTitle,'i.ID_Title=t.ID'),
	    new fcSQL_JoinElement($sroItOpt,'i.ID_ItOpt=io.ID','LEFT JOIN'),
	    )
	  );
	$qof = $oq->Select()->Fields();
	$qof->ClearFields();
	$qof->SetFields($this->Fields_active_forCompile_array());
	$oq->Terms()->UseTerm(new fcSQLt_Group(array('i.ID_Title')));
	
	$oq->Terms()->Filters()->AddCond('ID_Dept='.$idDept);
		
	return $oq;
    }
    protected function SQO_forDeptPage_all($idDept) {
	$sroThis = $this->SQO_Source('t');
	$sroDept = $this->SourceDepartments();
	$sroSupp = $this->SourceSuppliers();
	
	$arJT = array(
	  new fcSQL_JoinElement($sroThis),	// Titles
	  new fcSQL_JoinElement($sroDept,'t.ID_Dept=d.ID'),
	  new fcSQL_JoinElement($sroSupp,'t.ID_Supp=s.ID')
	  );
	$qJoin = new fcSQL_JoinSource($arJT);
	$qjSel = new fcSQL_Select($qJoin);
	$qof = $qjSel->Fields();
	$qof->ClearFields();
	$qof->SetFields($this->Fields_all_forCompile_array());
	$qTerms = new fcSQL_Terms(
	  array(
	    new fcSQLt_Filt('AND',
	      array(
		't.ID_Dept='.$idDept
		)
	      )
	    )
	  );
	$oq = new fcSQL_Query($qjSel,$qTerms);
	
	return $oq;
    }
    
    // // -- DEPARTMENT PAGE -- // //
    // // ++ TOPIC EXHIBIT ++ // //
    
    /*
      PRODUCES: record for each Title that has at least one Item for sale,
	with summary of what's available (options, price range) --
	filtered for the given Topic.
      NOTE: We could possibly allow more general filtering than just by Topic,
	but the filter would have to be constructed so as not to return multiple
	Titles because that would mess up the Option listing.
      PUBLIC so admin version of this query-table can build on it
    */
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
    }
    /*----
      HISTORY:
	2016-04-10 Fixed the filter to exclude removed stock and stock in disabled bins
    */
    protected function SQL_forTopicPage_active($idTopic) {
    
	return <<<__END__
SELECT 
    t.ID,
    SUM(sl.Qty) AS QtyInStock,
    COUNT((Qty > 0) OR isAvail) AS CountForSale,
    MIN(PriceSell) AS PriceMin,
    MAX(PriceSell) AS PriceMax,
    GROUP_CONCAT(DISTINCT io.CatKey
        ORDER BY io.Sort
        SEPARATOR ', ') AS CatOpts
FROM
    `cat_items` AS i
        JOIN
    `cat_titles` AS t ON i.ID_Title = t.ID
        JOIN
    `cat_title_x_topic` AS tt ON i.ID_Title = tt.ID_Title
        LEFT JOIN
    `cat_ioptns` AS io ON i.ID_ItOpt = io.ID
	LEFT JOIN
    `stk_lines` AS sl ON sl.ID_Item = i.ID
        JOIN
    `stk_bins` AS sb ON sl.ID_Bin = sb.ID

WHERE
    (tt.ID_Topic = $idTopic) AND (sl.Qty > 0) AND (sb.isEnabled)
GROUP BY i.ID_Title
__END__;
    }
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
    
    // // -- TOPIC EXHIBIT -- // //

    // USED BY ItemInfoTable
    public function SQO_Title_CatNums(fcSQL_Terms $qTerms=NULL) {
	$sroTitle = $this->SQO_Source('t');
	$sroDept = new fcSQL_TableSource($this->DepartmentTable()->TableName(),'d');
	$sroSupp = new fcSQL_TableSource($this->SupplierTable()->TableName(),'s');
	$arJT = array(
	  new fcSQL_JoinElement($sroTitle),
	  new fcSQL_JoinElement($sroDept,'t.ID_Dept=d.ID'),
	  new fcSQL_JoinElement($sroSupp,'t.ID_Supp=s.ID')
	  );
 	$qJoin = new fcSQL_JoinSource($arJT);
	$qjSel = new fcSQL_Select($qJoin);
	$qf = $qjSel->Fields();
	$qf->SetFields($this->Fields_all_forCompile_array());
	$oq = new fcSQL_Query($qjSel,$qTerms);
	return $oq;
    }
    
    /*----
      PURPOSE: Generate records for all titles,	as constrained by $sqlFilt, containing
	(where available)the information typically displayed with active Titles.
      NOTE: It took considerable jiggering around to get this query to the point where
	it both renders in a reasonable amount of time AND gets the right answer, so
	for now I'm not going to even try to retrofit it into a SQO -- though possibly
	the Stock Info class would work better with the stock query as it is given here.
      HISTORY:
	2016-03-06 shop-search needs ID_Dept
    */
    public function SQL_ExhibitInfo($sqlFilt) {
	$sqlWhere = is_null($sqlFilt)?NULL:"WHERE $sqlFilt";
	$sqlCatNum = self::SQLfrag_CatNum();
	$sqlCatPath = self::SQLfrag_CatPath();
	return <<<__END__
SELECT 
    t.ID, t.ID_Dept, ID_Supp, t.Name, t.CatKey, QtyAvail, QtyInStock,
    PriceMin,
    PriceMax,
    CatOpts,
    $sqlCatNum AS CatNum,
    $sqlCatPath AS CatPath
FROM
    `cat_titles` AS t
        JOIN cat_depts AS d ON t.ID_Dept=d.ID
        JOIN cat_supp AS s ON t.ID_Supp=s.ID
        JOIN
    (SELECT 
        ID_Title,
        COUNT(IsAvail) AS QtyAvail,
        SUM(IF(isStock,Qty,0)) AS QtyInStock,
	MIN(PriceSell) AS PriceMin,
        MAX(PriceSell) AS PriceMax,
        GROUP_CONCAT(DISTINCT CatKey ORDER BY Sort SEPARATOR ', ') AS CatOpts
    FROM
        cat_items AS i
    JOIN cat_ioptns AS io ON i.ID_ItOpt=io.ID
    JOIN (SELECT 
        sl.ID_Item,
            sl.Qty,
            sb.isForSale,
            sb.isEnabled,
            sb.WhenVoided,
            ((sl.Qty > 0)
                AND sb.isForSale
                AND sb.isEnabled
                AND (sb.WhenVoided IS NULL)) AS isStock
    FROM
        stk_lines AS sl
    LEFT JOIN `stk_bins` AS sb ON sl.ID_Bin = sb.ID) AS sl ON sl.ID_Item = i.ID
    GROUP BY ID_Title) AS i ON i.ID_Title = t.ID
$sqlWhere
__END__;
    }
    
    protected function SQL_Title_CatNums() {
	$sql = <<<__END__
SELECT t.ID, CONCAT_WS('-',s.CatKey,d.CatKey,t.CatKey) AS CatNum
FROM cat_titles AS t
  LEFT JOIN cat_depts AS d ON t.ID_Dept=d.ID
  LEFT JOIN cat_supp AS s ON t.ID_Supp=s.ID
__END__;
      return $sql;
    }
    // RETURNS: SQL for Title-based recordset with ItemType availability information
    protected function SQL_byItemType_active() {
	$qt = $this->ItemInfoQuery();
	$sqlItems = $qt->SQL_byItemType_active();
	
	$sql = <<<__END__
SELECT * FROM cat_titles AS t LEFT JOIN ($sqlItems) AS i ON i.ID_Title = i.ID GROUP BY 
__END__;
	die('WORKING HERE: '.__FILE__.' line '.__LINE__);
    }

    // -- SQL CALCULATION -- //
    // ++ ARRAYS ++ //

    // USED BY: Department exhibit page
    public function StatsArray_forDept($idDept) {
	$oqAct = $this->SQO_forDeptPage_active($idDept);
	$oqAll = $this->SQO_forDeptPage_all($idDept);

	return $this->CompileResults($oqAll->Render(),$oqAct->Render());
    }
    // USED BY: Topic exhibit page
    public function StatsArray_forTopic($idTopic) {
	//$oqAct = $this->SQO_forTopicPage_active($idTopic);
	$sqlAct = $this->SQL_forTopicPage_active($idTopic);
	$oqAll = $this->SQO_forTopicPage_all($idTopic);
	
	return $this->CompileResults($oqAll->Render(),$sqlAct);
    }
    /*----
      INPUT: SQL to retrieve Title records with additional info
	$sqlAll: all Title records (active and inactive), with CatNum, as filtered
	$sqlActive: active Title records with availability info
    */
    public function CompileResults($sqlAll,$sqlActive) {
	$rs = $this->FetchRecords($sqlAll);
	$arMaster = $rs->CompileMaster();

	if (is_array($arMaster)) {
	    $rs = $this->FetchRecords($sqlActive);
	    $ar = $rs->CompileActive($arMaster);
	} else {
	    // there are no titles
	    $ar = NULL;
	}
	
	return $ar;
    }
    // PUBLIC so records can access it
    private $arItStats;
    public function ItemStats() {
	if (empty($this->arItStats)) {
	    //$this->arItStats = $this->ItemStatsArray();	// to be written
	    throw new exception('Need to generate stats array first.');
	}
	return $this->arItStats;
    }//*/
    
    // -- ARRAYS -- //
    // ++ RECORDS ++ //

    public function ExhibitRecords($sqlFilt) {
	$sql = $this->SQL_ExhibitInfo($sqlFilt);
	$rs = $this->FetchRecords($sql);
	return $rs;
    }
    public function GetRecords_forDeptExhibit($idDept) {
	$sql = $this->SQL_ExhibitInfo('ID_Dept='.$idDept);
	$rs = $this->FetchRecords($sql);
	return $rs;
    }
    /* 2016-10-25 Seems to be disused, but not deleting yet just in case.
    // This pulls together all the Title-based information from both stock and items (but not images)
    public function GetRecords_withCatNum_andItemStats_forTopic($idTopic) {
	throw new exception('Is anything actually using this?');
	$oq = $this->SQLobj_withCatNum_andItemStats_forTopic($idTopic);
	
	$sql = $oq->Render();
	
	echo "SQL (wCatNum & ItemStats): <pre>$sql</pre>";
	
	$rs = $this->DataSQL($sql);
	return $rs;
    }
    public function GetRecords_withCatNum_forTopic($idTopic,$sqlSort='CatNum') {
	$sql = <<<__END__
SELECT
  CONCAT_WS('-',s.CatKey,d.CatKey,t.CatKey) AS CatNum,
  t.Name
FROM `cat_titles` AS t
JOIN cat_depts AS d ON t.ID_Dept=d.ID
JOIN cat_supp AS s ON t.ID_Supp=s.ID
JOIN (

SELECT 
    i.ID_Title, SUM(Qty) AS QtyTotal
FROM
	cat_items AS i
		JOIN
    stk_lines AS sl ON sl.ID_Item = i.ID
        JOIN
    stk_bins AS sb ON sl.ID_Bin = sb.ID
WHERE
    (sl.Qty > 0)
        AND (sb.isForSale)
        AND (sb.isEnabled)
        AND (sb.WhenVoided IS NULL)
        
GROUP BY sl.ID_Item

) AS s ON s.ID_Title=t.ID

JOIN cat_title_x_topic AS tt ON tt.ID_Title=t.ID
WHERE tt.ID_Topic=$idTopic
ORDER BY $sqlSort
__END__;

	$rs = $this->DataSQL($sql);
	return $rs;
    } */
    
    // -- RECORDS -- //
}
// PURPOSE: So admin class can share functionality with this, despite lack of descent
trait vctrTitleInfo {

    // ++ FIELD VALUES ++ //

    protected function QtyInStock() {
	return $this->Value('QtyInStock');
    }
    protected function PriceMinimum() {
	return $this->GetFieldValue('PriceMin');
    }
    protected function PriceMaximum() {
	return $this->GetFieldValue('PriceMax');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //
    
    // PURPOSE: Some queries calculate this, and some don't; let's fail gracefully.
    protected function CatOptString($sPfx=' : ') {
	if ($this->FieldIsSet('CatOpts')) {
	    return $sPfx.$this->GetFieldValue('CatOpts');
	} else {
	    return NULL;
	}
    }
    
    // -- FIELD CALCULATIONS -- //
    // ++ UI PIECES ++ //
    
    protected function RenderPriceRange() {
	$nPrcLo = $this->PriceMinimum();
	$nPrcHi = $this->PriceMaximum();
	if ($nPrcLo == $nPrcHi) {
	  $sPrice = fcMoney::Format_withSymbol($nPrcLo);
	} else {
	  $sPrice = 
	    fcMoney::Format_withSymbol($nPrcLo)
	    .' - '
	    .fcMoney::Format_withSymbol($nPrcHi)
	    ;
	}
	return $sPrice;
    }
    protected function RenderStatus_text() {
	$out = $this->RenderPriceRange().$this->CatOptString();
	$qInStock = $this->QtyInStock();
	if ($qInStock > 0) {
	    $out .= ' - '.$qInStock.' in stock';
	}
	return $out;
    }
    protected function RenderSummary_text() {
	return $this->CatNum()
	.' &ldquo;'
	.$this->TitleString()
	.'&rdquo;: '
	.$this->RenderStatus_text()
	;
    }
    
    // -- UI PIECES -- //

}
class vcqrTitleInfo extends vcrShopTitle {
    use vctrTitleInfo;
    use vtTableAccess_ImagesInfo;

    // ++ TABLES ++ //
    
    protected function ItemInfoQuery() {
	return $this->Engine()->Make('vcqtImagesInfo');
    }
    /*
    protected function ImageInfoQuery() {
	return $this->GetConnection()->MakeTableWrapper('vcqtImagesInfo');
    }*/
    
    // -- TABLES -- //
    // ++ FIELD VALUES ++ //

    public function CatNum() {
	return $this->GetFieldValue('CatNum');
    }
    protected function TitleString() {
	return $this->GetFieldValue('Name');
    }
    protected function QtyInStock() {
	return $this->GetFieldValue('QtyInStock');
    }
    protected function QtyAvailable() {
	return $this->GetFieldValue('QtyAvail');
    }
    // NOTE: This is a calculated field.
    protected function CatalogOptionsList() {
	return $this->GetFieldValue('CatOpts');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    protected function TitleQuoted() {
	return '&ldquo;'.$this->TitleString().'&rdquo;';
    }
    public function IsForSale() {
	return ($this->QtyInStock() > 0) || ($this->QtyAvailable() > 0);
    }
    /*
      INPUT: recordset data from $this->Table()->ExhibitRecords()
      OUTPUT: item's availability status: options, prices, stock
    */ /* call RenderSummary_html() instead
    protected function StatusLine_HTML() {
	return 'StatusLine_HTML(): to be written';
    }
    protected function StatusLine_Text() {
	return 'StatusLine_Text(): to be written';
    } */
    /*----
      INPUT: recordset data from $this->Table()->ExhibitRecords()
      OUTPUT: summary line: title (quoted), availability status
    */
    public function SummaryLine_HTML_line() {
	$sCatNum = $this->CatNum();
	$htLink = $this->ShopLink($sCatNum);
	$sName = $this->NameString();
	$sStats = $this->RenderStatus_HTML_line();
	return "\n$htLink &ldquo;$sName&rdquo; &ndash; $sStats<br>";
    }
    /*----
      OUTPUT: SummaryLine as table row (HTML)
      NOTE: NOT YET TESTED
    */
    public function SummaryLine_HTML_row() {
	$sCatNum = $this->CatNum();
	$htLink = $this->ShopLink($sCatNum);
	$sName = $this->NameString();
	$sStats = $this->RenderStatus_HTML_cells();
	return "\n<tr><td>$htLink</td><td>&ldquo;$sName&rdquo;</td>$sStats</tr>";
    }
    public function SummaryLine_Text() {
	$sCatNum = $this->CatNum();
	$sName = $this->NameString();
	$sStats = $this->RenderStatus_text();
	return "$sCatNum &ldquo;$sName&rdquo; - $sStats<br>";
    }
    public function ImageTitle() {
	return $this->SummaryLine_Text();
    }
    protected function TitleHREF() {
	$sCatPath = $this->GetFieldValue('CatPath');
	//$sCatPath = $this->CatPage();
	$url = vcGlobals::Me()->GetWebPath_forCatalogPages().$sCatPath;
	$id = $this->GetKeyValue();
	$htHref = "<a href='$url'>";
	return $htHref;
    }
    protected function RenderStatus_HTML_line() {
	$qInStock = $this->QtyInStock();
	$out = "\n"
	  .$this->RenderPriceRange()
	  .' ('
	  .$this->CatalogOptionsList()
	  .')'
	  ;
	if ($qInStock > 0) {
	    $out .= " &ndash; $qInStock in stock";
	}
	return $out;
    }
    /*----
      OUTPUT: RenderStatus_HTML as table cells
    */
    protected function RenderStatus_HTML_cells() {
	$qInStock = $this->QtyInStock();
	$out = "<td>"
	  .$this->RenderPriceRange()
	  .'</td><td>'
	  .$this->CatalogOptionsList()
	  .'</td>'
	  ;
	if ($qInStock > 0) {
	    $out .= "<td>$qInStock in stock</td>";
	}
	return $out;
    }
    /*----
      NOTE: This seems to basically duplicate SummaryLine_HTML_line(),
	but using a slightly different set of fields.
      TODO: (2016-04-10) straighten this out so we don't have two functions basically
	doing the same thing.
    */
    protected function RenderSummary_HTML_line() {
	$out = "\n"
	  .$this->TitleHREF()
	  .$this->CatNum()
	  .' &ldquo;'
	  .$this->TitleString()
	  .'&rdquo;</a>: '
	  .$this->RenderStatus_HTML_line()
	  .'<br>'
	  ;
	return $out;
    }
    static private $isOdd=FALSE;
    protected function RenderSummary_HTML_row() {
	self::$isOdd = !self::$isOdd;
	$cssClass = self::$isOdd?'catalog-stripe':'catalog';
	$out = "\n<tr class='$cssClass'>"
	  .'<td>'.$this->TitleHREF().$this->CatNum().'</a></td>'
	  .'<td>&ldquo;'.$this->TitleHREF().$this->TitleString().'</a>&rdquo;</td>'
	  .$this->RenderStatus_HTML()
	  .'</tr>'
	  ;
	return $out;
    }
    protected function RenderSummary_inactive() {
	$sText = $this->CatNum()
	.' &ldquo;'
	.$this->TitleString()
	.'&rdquo;'
	;
	return $sText;
    }

    // -- FIELD CALCULATIONS -- //
    // ++ ARRAYS ++ //
    
    protected function ItemStats() {
	return $this->Table()->ItemStats()[$this->KeyValue];
    }
    protected function ItemsForSale() {
	return $this->ItemStats()['QtyForSale'];
    }
    // ACTION: compile master array of all titles in recordset
    public function CompileMaster() {
	// 2016-11-06 This should now accomplish the same thing as the old code:
	$ar = $this->FetchRows_asKeyedArray();
	
	/* 2016-11-06 old code
	if ($this->HasRows()) {
	    $sKeyName = $this->GetKeyName();
	    while ($this->NextRow()) {
		$row = $this->GetFieldValues();
		$id = $row[$sKeyName];
		unset($row[$sKeyName]);
		$ar[$id] = $row;
	    }
	} else {
	  $ar = NULL;
	} */

	return $ar;
    }
    /*----
      ACTION: add additional info for active titles
      RETURNS: structured array
    */
    public function CompileActive(array $ar) {
	$arKeysAll = array_keys($ar);

	// 2016-11-06 new code
	$arActive = $this->FetchRows_asKeyedArray();
	if (is_null($arActive)) {
	    // nothing is active
	    $arKeysActive = array();
	    // ...so all are retired:
	    $arKeysRetired = $arKeysAll;
	} else {
	    // some things are active
	    // active rows have additional fields which need to be merged into the master array:
	    foreach ($arActive as $id => $arRow) {
		$ar[$id] = array_merge($ar[$id],$arRow);
	    }
	    
	    // calculate the retired key list:
	    $arKeysActive = array_keys($arActive);			// get keys for active rows
	    $arKeysRetired = array_diff($arKeysAll,$arKeysActive);	// the retired list is [all minus active]
	}

	/* 2016-11-06 old code
	if ($this->HasRows()) {
	    $sKeyName = $this->GetKeyName();
	    while ($this->NextRow()) {
		$row = $this->GetFieldValues();
		$id = $row[$sKeyName];
		unset($row[$sKeyName]);
		$ar[$id] = array_merge($ar[$id],$row);
		$arKeysActive[] = $id;
	    }
	    $arKeysRetired = array_diff($arKeysAll,$arKeysActive);
	} else {
	    $arKeysRetired = $arKeysAll;
	}
	*/
	
	$arOut['data'] = $ar;
	$arOut['active'] = $arKeysActive;
	$arOut['retired'] = $arKeysRetired;
	
	return $arOut;
    }
    /*
      ASSUMES: There is at least one row
      OUTPUT: via Stats_ForSaleText(), Stats_RetiredText()
    */
    public function GatherStats() {
	while ($this->NextRow()) {
	    $sCatNum = $this->CatNum();
	    $htLink = $this->ShopLink($sCatNum);
	    $sName = $this->NameString();
	    $sStats = $this->ItemSummary();

	    $htTitle = "\n$htLink &ldquo;$sName&rdquo;";
	    $qAct = $this->ItemsForSale();	// TODO: make sure this isn't another lookup
	    if ($qAct > 0) {
		$htForSaleTxt .= $htTitle.' - '.$sStats.'<br>';
	    } else {
		if (!is_null($htRetiredTxt)) {
		    $htRetiredTxt .= ' / ';
		}
		$htRetiredTxt .= $htTitle;
	    }
	}
	$this->Stats_ForSaleText($htForSaleTxt);
	$this->Stats_RetiredText($htRetiredTxt);
    }
    private $sStatsForSale;
    public function Stats_ForSaleText($s=NULL) {
	if (!is_null($s)) {
	    $this->$sStatsForSale = $s;
	}
	return $this->$sStatsForSale;
    }
    private $sStatsRetired;
    public function Stats_RetiredText($s=NULL) {
	if (!is_null($s)) {
	    $this->$sStatsRetired = $s;
	}
	return $this->$sStatsRetired;
    }
    /*----
      INPUT:
	* $ar: output from $this->StatsArray_for*()
      OUTPUT: returns array
	array['act']['text']: text listing of available titles
	array['act']['imgs']: thumbnails of available titles
	array['ret']['imgs']: thumbnails of unavailable titles
    */
    public function RenderTitleResults(array $ar) {
	$arRows = $ar['data'];
	if (array_key_exists('active',$ar)) {
	    $arActive = $this->RenderActiveTitlesResult($ar['active'],$arRows);
	} else {
	    $arActive = NULL;
	}
	if (array_key_exists('retired',$ar)) {
	    $ht = $this->RenderRetiredTitlesResult($ar['retired'],$arRows);
	} else {
	    $ht = NULL;
	}
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
		$this->ClearFields();
		$this->SetFieldValues($arRows[$id]);
		$this->SetKeyValue($id);
		$arRes = $this->RenderActiveTitleResult();
		$htText .= $arRes['text'];
		$htImgs .= $arRes['imgs'];
	    }
	}
	
	if ($this->RenderOption_UseTable()) {
	    $htText = "\n<table class='catalog-summary'>"
	      ."\n<tr><th>Catalog #</th><th>Name</th><th>$ Price</th><th>Options</th></tr>"
	      .$htText
	      ."\n</tr></table>"
	      ;
	}
	
	$arOut = array(
	  'text' => $htText,
	  'imgs' => $htImgs
	  );
	return $arOut;
    }
    
    // -- ARRAYS -- //
    // ++ UI ELEMENTS ++ //
    
    // 2016-04-10 Not sure if this is actually useful. Changed to FALSE.
    static private $bRendOpt_UseTable=FALSE;
    public function RenderOption_UseTable() {
	return self::$bRendOpt_UseTable;
    }
    
    // USED BY: Search page
    public function RenderThumbs_forRow(array $arImRow) {
	$rcImg = $this->ImageInfoQuery()->SpawnRecordset();
	$htImg = NULL;
	$sTitle = $this->RenderSummary_text();
	foreach ($arImRow as $idImg => $arImg) {
	    $rcImg->SetFieldValues($arImg);
	    $htImg .= $rcImg->RenderInline_row($sTitle,vctImages::SIZE_THUMB);
	}
	$htHref = $this->TitleHREF();
	return $htHref.$htImg.'</a>';
    }//*/
    protected function RenderImages_withLink($sText) {
	$row = $this->GetFieldValues();
	$out = NULL;
	if (array_key_exists('@img',$row)) {	// if there's image data...
	    $arImgs = $row['@img'];
	    $rcImg = $this->ImageInfoQuery()->SpawnRecordset();
	    $htImg = NULL;
	    foreach ($arImgs as $idImg => $arImgRow) {
		$rcImg->SetFieldValues($arImgRow);
		$htImg .= $rcImg->RenderSingle($sText,vctImages::SIZE_THUMB);
	    }
	    $out .= $this->TitleHREF().$htImg."</a>\n";
	}
	return $out;
    }
    protected function RenderActiveTitleResult() {
	$sText = $this->RenderSummary_text();
	$arOut['text'] = $this->RenderSummary_html_line();
	$arOut['imgs'] = $this->RenderImages_withLink($sText);
	//*/
	return $arOut;
    }
    protected function RenderRetiredTitlesResult(array $arIDs,array $arRows) {
	$htImgs = NULL;
	if (count($arIDs) > 0) {
	    foreach ($arIDs as $id) {
		$this->ClearFields();	// otherwise we get images being assigned to imageless titles
		$this->SetFieldValues($arRows[$id]);
		$htImgs .= $this->RenderRetiredTitleResult();
	    }
	}
	return $htImgs;
    }
    protected function RenderRetiredTitleResult() {
	$sText = $this->RenderSummary_inactive();
	$out = $this->RenderImages_withLink($sText);
	return $out;
    }
}