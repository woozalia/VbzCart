<?php
/*
  PURPOSE: admin queries for joining Titles to other tables
    For non-admin queries, see {vc lib root}/cat/title.info.php".
  HISTORY:
    2015-03-05 Started for active-no-image report, but will probably have other uses
*/

class vcqtaTitlesInfo extends vctAdminTitles {
    use vtQueryableTable_Titles;

    // ++ SETUP ++ //

    protected function SingularName() {
	return 'vcqraTitleInfo';
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
	$rs = $this->FetchRecords($sql);
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ WEB UI ++ //
    
    public function RenderRows_Active_noImage() {
	$rs = $this->Records_Active_noImage();
	$oHdr = new fcSectionHeader('Report: active + no images');
	$out = $oHdr->Render()
	  .$rs->AdminRows()
	  ;
	return $out;
    }
}
class vcqraTitleInfo extends vcrAdminTitle implements fiLinkableRecord {
    use vctrTitleInfo;

    // ++ FIELD VALUES ++ //

    public function CatNum() {
	return $this->GetFieldValue('CatNum');
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