<?php
/*
  PURPOSE: admin queries for joining Titles to other tables
    For non-admin queries, see {vc lib root}/cat/title.info.php".
  HISTORY:
    2015-03-05 Started for active-no-image report, but will probably have other uses
*/

class vcqtaTitlesInfo extends VCTA_Titles {
    use vtQueryableTable_Titles;

    // ++ SETUP ++ //

    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->ClassSng('vcqraTitleInfo');
    }

    // -- SETUP -- //
    // ++ TABLES ++ //
    
    // RETURNS: The shopping version of this query-table, which is not a direct ancestor
    protected function ShopTable() {
	return $this->Engine()->Make('vcqtTitlesInfo');
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      TODO:
	* Include Titles that are available but not in stock.
	* Show quantities in stock, number of available items (for triaging photo shoots)
    */
    protected function Records_Active_noImage() {
	$qo = $this->ItemInfoQuery()->SQO_forSale();
	$qsImg = $this->ImageInfoQuery()->SQO_Source('im');
	$qsTtl = $this->SQO_Source('t');
	$qsDept = $this->SourceDepartments();
	$qsSupp = $this->SourceSuppliers();
	
	$qof = $qo->Select()->Fields();
	$qof->ClearFields();
	//$qof->SetFields($this->ItemInfoQuery()->Fields_forRender());
	$qof->SetFields(
	  array(
	    't.*',
	    'CatNum'	=> self::SQLfrag_CatNum()
	    )
	  );
	
	$qo->Select()->Source()->AddElements(
	  array(
	    new fcSQL_JoinElement($qsTtl,'i.ID_Title=t.ID'),
	    new fcSQL_JoinElement($qsImg,'im.ID_Title=i.ID_Title','LEFT JOIN'),
	    new fcSQL_JoinElement($qsDept,'t.ID_Dept=d.ID'),
	    new fcSQL_JoinElement($qsSupp,'t.ID_Supp=s.ID')
	    )
	  );
	$qo->Terms()->Filters()->AddCond('im.ID IS NULL');
	$qo->Terms()->UseTerm(new fcSQLt_Group(array('t.ID')));
	  
	$sql = $qo->Render();
	//die("SQL: $sql");
	$rs = $this->DataSQL($sql);
	return $rs;
    }
    /* 2016-03-23 Don't use this. It looks like these queries need to be carefully checked
      to make sure they include everything (mainly: optionless items, items available
      but not in stock) which makes it impractical to then try to retrofit them into an SQO.
      Not sure if this proves the impracticality of the SQO concept or just that it still
      needs some refinement.
    public function Records_forTitles_noTopic() {
	$tShop = $this->ShopTable();
	//* 2016-03-22 This mostly works, but seems to omit some titles.
	$qo = $tShop->SQO_forTopicPage_active(NULL);
	$tShop->SQOfrag_AddJoins_forCatNum($qo);
	/* /
	$qo = $this->ShopTable()->SQO_forTopicPage_all(NULL);
	//* /
	$tShop->SQOfrag_Sort_byCatNum($qo);
	$sql = $qo->Render();
	die("SQL: $sql");
	return $this->DataSQL($sql);
    }//*/
    
    // -- RECORDS -- //
    // ++ WEB UI ++ //
    
    public function RenderRows_Active_noImage() {
	$rs = $this->Records_Active_noImage();
	$out = $this->PageObject()->SectionHeader('Report: active + no images',NULL,'section-header-sub')
	  .$rs->AdminRows()
	  ;
	return $out;
    }
}
class vcqraTitleInfo extends VCRA_Title {
    use vctrTitleInfo;

    // ++ FIELD VALUES ++ //

    public function CatNum() {
	return $this->Value('CatNum');
    }
    
    // -- FIELD VALUES -- //
    // ++ WEB UI ++ //
    
    public function RenderRow_forNoTopicList() {
	$sCNum = $this->CatNum();
	$sName = $this->NameString();
	$sPopup = $this->RenderStatus_text();
	$htCNum = $this->SelfLink($sCNum,$sPopup);
	$out = "\t<li>$htCNum $sName</li>\n";
	return $out;
    }
}