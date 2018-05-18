<?php
/*
  PURPOSE: handles Title-based recordsets that also include item and stock summary data
  HISTORY:
    2016-02-11 started
    2016-02-18 more or less working
    2016-10-25 Updated for db.v2
    2018-02-09 moved:
      vctCatDepartments_queryable to dept.query.php
      vctCatSuppliers_queryable to supp.query.php
      Not sure either of these are actually necessary
*/
trait vtQueryableTable_Titles {
    use ftQueryableTable;

    // ++ CLASSES ++ //
    
    /*----
      PURPOSE: we need to use queryable versions of the basic logic tables,
	mainly so we have SQO_Source() for building SQO
      HISTORY:
	2018-02-14 This was commented out on 2/9 because I didn't know what it was for;
	  now re-enabling because of SQO_Source.
    */
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

class vcqtTitlesInfo extends vctTitles {
    use vtQueryableTable_Titles;
    use vtTableAccess_Supplier,vtTableAccess_ItemType;
  
    // ++ SETUP ++ //

    // CEMENT
    protected function SingularName() {
	return 'vcqrTitleInfo';
    }

    // -- SETUP -- //
    // ++ SQL ++ //

    // TESTED 2018-02-16
    protected function SQL_forStockStatus_byLine() {
	$fs = __DIR__.'/qryStockStatus_byLine.sql';
	$sql = file_get_contents($fs);
	return $sql;
    }
    // TESTED 2018-02-16
    protected function SQL_forStockStatus_byItem() {
	$sqlCore = $this->SQL_forStockStatus_byLine();
	
	$sql = <<<__END__
SELECT 
  ID_Item,
  QtyInStock,
  i.PriceSell,
  i.CatSfx,
  i.ID_Title,
  i.ID_ItTyp,
  i.ID_ItOpt
FROM
  (SELECT 
    ID_Item,
    SUM(QtyForSale) AS QtyInStock
  FROM ($sqlCore) AS sl
  GROUP BY ID_Item) AS si
LEFT JOIN cat_items AS i ON i.ID=si.ID_Item	
__END__;
	return $sql;
    }
    // NOT TESTED
    public function SQL_forStockStatus_byTitle($sqlFilt) {
	$sqlWhere = is_null($sqlFilt)?'':"WHERE $sqlFilt";
	$sqlCore = $this->SQL_forStockStatus_byItem();
	$sql = <<<__END__
SELECT
  t.ID,
  t.ID_Dept,
  t.ID_Supp,
  QtyInStock,
  PriceMin,
  PriceMax,
  t.Name,
  t.CatKey,
  ItTypes,
  ItOptions
FROM
  (SELECT
    ID_Title,
    SUM(QtyInStock) AS QtyInStock,
    MIN(PriceSell) AS PriceMin,
    MAX(PriceSell) AS PriceMax,
    GROUP_CONCAT(DISTINCT itt.NameSng ORDER BY itt.Sort SEPARATOR ', ') AS ItTypes,
    GROUP_CONCAT(DISTINCT iop.CatKey ORDER BY iop.Sort SEPARATOR ',') AS ItOptions
  FROM ($sqlCore) AS si
  JOIN cat_ittyps AS itt ON si.ID_ItTyp=itt.ID
  JOIN cat_ioptns AS iop ON si.ID_ItOpt=iop.ID
  GROUP BY ID_Title) AS st
LEFT JOIN cat_titles AS t ON t.ID=st.ID_Title
$sqlWhere
__END__;
	return $sql;
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
	2018-04-18 added SQL code so we can get ItTypes:
	  JOIN cat_ittyps AS itt ON i.ID_ItTyp=itt.ID 
	  GROUP_CONCAT(DISTINCT itt.NameSng ORDER BY itt.Sort SEPARATOR ', ') AS ItTypes,
	  This makes it even more similar to SQL_forStockStatus_byTitle().
	    How are they different? Why don't they share code?
	  Also changed QtyAvail to CountForSale for consistency with Topic exhibit query.
    */
    public function SQL_ExhibitInfo($sqlFilt) {
	$sqlWhere = is_null($sqlFilt)?NULL:"WHERE $sqlFilt";
	$sqlCatNum = self::SQLfrag_CatNum();
	$sqlCatPath = self::SQLfrag_CatPath();
	return <<<__END__
/*ExhibitInfo*/ SELECT 
    t.ID, t.ID_Dept, ID_Supp, t.Name, t.CatKey, CountForSale, QtyInStock,
    PriceMin,
    PriceMax,
    ItOptions,
    ItTypes,
    $sqlCatNum AS CatNum,
    $sqlCatPath AS CatPath
FROM
    `cat_titles` AS t
        JOIN cat_depts AS d ON t.ID_Dept=d.ID
        JOIN cat_supp AS s ON t.ID_Supp=s.ID
        JOIN
    (SELECT 
        ID_Title,
        COUNT(IsAvail) AS CountForSale,
        SUM(IF(isStock,Qty,0)) AS QtyInStock,
	MIN(PriceSell) AS PriceMin,
        MAX(PriceSell) AS PriceMax,
        GROUP_CONCAT(DISTINCT CatKey ORDER BY io.Sort SEPARATOR ', ') AS ItOptions,
	GROUP_CONCAT(DISTINCT itt.NameSng ORDER BY itt.Sort SEPARATOR ', ') AS ItTypes
    FROM
        cat_items AS i
    JOIN cat_ittyps AS itt ON i.ID_ItTyp=itt.ID
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
    /* 2018-02-16 seems to be unused (was incomplete anyway)
    // RETURNS: SQL for Title-based recordset with ItemType availability information
    protected function SQL_byItemType_active() {
	$qt = $this->ItemInfoQuery();
	$sqlItems = $qt->SQL_byItemType_active();
	
	$sql = <<<__END__
SELECT * FROM cat_titles AS t LEFT JOIN ($sqlItems) AS i ON i.ID_Title = i.ID GROUP BY 
__END__;
	die('WORKING HERE: '.__FILE__.' line '.__LINE__);
    } */

    // -- SQL -- //
    // ++ SQO ++ //

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
	  'ItOptions' => "GROUP_CONCAT(DISTINCT io.CatKey ORDER BY io.Sort SEPARATOR ', ')"
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
    // // ++ OTHER ++ // //

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
    

    // // -- OTHER -- // //

    // -- SQO -- //
    // ++ ARRAYS ++ //

    // USED BY: Department exhibit page
    public function StatsArray_forDept($idDept) {
	$oqAct = $this->SQO_forDeptPage_active($idDept);
	$oqAll = $this->SQO_forDeptPage_all($idDept);

	return $this->CompileResults($oqAll->Render(),$oqAct->Render());
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
    
    // -- RECORDS -- //
}
// PURPOSE: So admin class can share functionality with this, despite lack of descent
trait vctrTitleInfo {

    // ++ FIELD VALUES ++ //

    protected function QtyInStock() {
	return $this->GetFieldValue('QtyInStock');
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
	if ($this->FieldIsSet('ItOptions')) {
	    return $sPfx.$this->GetFieldValue('ItOptions');
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
	.$this->NameString()
	.'&rdquo;: '
	.$this->RenderStatus_text()
	;
    }
    
    // -- UI PIECES -- //

}
class vcqrTitleInfo extends vcBasicRecordset {
    use vtrTitle;
    use vtrTitle_shop;
    use vctrTitleInfo;
    use vtTableAccess_ImagesInfo;

    // ++ TABLES ++ //
    
    // 2018-02-10 if anything is still using this, it will error out
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
    protected function QtyInStock() {
	return $this->GetFieldValue('QtyInStock');
    }
    
    // 2018-04-16 Apparently some queries generate one and some generate the other. TODO: FIX THIS
    
    protected function QtyAvailable() {
	//throw new exception('Call CountForSale() instead of QtyAvailable().');	// 2018-02-17
	return $this->GetFieldValue('CountForSale');					// 2018-04-18 was QtyAvail
    }
    protected function CountForSale() {
	throw new exception('Call QtyAvailable() instead of CountForSale().');	// 2018-04-16
	return $this->GetFieldValue('CountForSale');
    }
    
    // --
    
    protected function CatalogTypesList() {
	return $this->GetFieldValue('ItTypes');
    }
    // NOTE: This is a calculated field.
    protected function CatalogOptionsList() {
	return $this->GetFieldValue('ItOptions');
    }
    
    // -- FIELD VALUES -- //
    // ++ FIELD CALCULATIONS ++ //

    protected function TitleQuoted() {
	return '&ldquo;'.$this->TitleString().'&rdquo;';
    }
    // 2018-02-17 This may be overcalculating: doesn't CountForSale() include QtyInStock()>0?
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
    protected function RenderCatalogSummary_HTML() {
	$out = 
	  $this->CatalogTypesList()
	  .' - '
	  .$this->CatalogOptionsList()
	  .' ('
	  .$this->RenderPriceRange()
	  .')'
	  ;
	return $out;
    }
    protected function RenderStatus_HTML_line() {
	$qInStock = $this->QtyInStock();
	$out = $this->RenderCatalogSummary_HTML();
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
	  .$this->NameString()
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
    /*----
      HISTORY:
	2018-02-16 created for home page random-titles display
    */
    public function RenderImages_withLink_andSummary() {
	$htTitle = $this->NameString();
	$htStatus = $this->RenderCatalogSummary_HTML();
	$qStock = $this->QtyInStock();
	$htStock = "$qStock in stock";
	$htPopup = "&ldquo;$htTitle&rdquo;%attr%\n$htStatus\n$htStock";
	$htImgs = $this->RenderImages_forRow($htPopup,vctImages::SIZE_THUMB);
	$htTitle = $this->ShopLink($htImgs);
	return $htTitle;
    }
    protected function RenderSummary_inactive() {
	$sText = $this->CatNum()
	.' &ldquo;'
	.$this->NameString()
	.'&rdquo;'
	;
	return $sText;
    }

    // -- FIELD CALCULATIONS -- //
    // ++ ARRAYS ++ //
    
    // 2018-02-10 if anything is still calling this, it will error out
    protected function ItemStats() {
	return $this->Table()->ItemStats()[$this->KeyValue];
    }
    /* 2018-02-08 This appears to be unused.
    protected function ItemsForSale() {
	return $this->ItemStats()['QtyForSale'];
    } */
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
      /* 2018-02-07 apparently nothing is using these?
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
    */
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
    
    public function RenderImages() {
	$outYes = $outNo = NULL;
	while ($this->NextRow()) {
	    $htTitle = $this->RenderImages_withLink_andSummary();
	    if ($this->IsForSale()) {
		$outYes .= $htTitle;
	    } else {
		$outNo .= $htTitle;
	    }
	}
	
	if (is_null($outYes)) {
	    $htYes = '<span class=content>There are currently no titles available for this topic.</spaN>';
	} else {
	    $oSection = new vcHideableSection('hide-available','Titles Available',$outYes);
	    $htYes = $oSection->Render();
	}
	if (is_null($outNo)) {
	    $htNo = NULL;	// no point in mentioning lack of unavailable titles
	} else {
	    $oSection = new vcHideableSection('show-retired','Titles NOT available',$outNo);
	    $oSection->SetDefaultHide(TRUE);
	    $htNo = $oSection->Render();
	}
	return $htYes.$htNo;
     }
    
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