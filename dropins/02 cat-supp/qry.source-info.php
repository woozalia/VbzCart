<?php
/*
  PURPOSE: classes for displaying Supplier Catalog Sources, with Supplier info
  HISTORY:
    2016-01-27 started
*/

class vcqtaSCSources_wSupplier extends vctaSCSources_base {
    
    // ++ SETUP ++ //
/*    
    public function __construct($db) {
	parent::__construct($db);
	  $this->ClassSng('vcqraSCSource_wSupplier');
    }
*/
    // -- SETUP -- //
    // ++ SQL CALCULATIONS ++ //
    
    protected function AdminRecords_SQL() {
	$sql = <<<__END__
SELECT 
    cs.*, s.Name AS SuppName
FROM
    ctg_sources AS cs
        LEFT JOIN
    cat_supp AS s ON cs.ID_Supplier = s.ID
ORDER BY (s.isActive = 0), s.Name, cs.Abbr DESC, cs.Name DESC
__END__;
	return $sql;
    }
    
    // -- SQL CALCULATIONS -- //
    // ++ RECORDS ++ //
    
    protected function AdminRecords() {
	$rs = $this->FetchRecords($this->AdminRecords_SQL());
	return $rs;
    }
    
    // -- RECORDS -- //
    // ++ ADMIN UI PAGES ++ //

    public function AdminPage() {
	$rs = $this->AdminRecords();
	$out = $rs->AdminRows();
	return $out;
    }

    // -- ADMIN UI PAGES -- //

}
// This is just so we can use parent:: to access unaltered trait functions.
class vcqraSCSource_wSupplier_base extends vcraSCSource {
    use ftShowableRecord;

    protected function AdminRows_settings_columns() {
	$arCols = array(
	    'ID'	=> 'ID',
	    'Name'	=> 'Name',
	    'Abbr'	=> 'Abbr.',
	    'DateAvail'	=> 'Available',
	    'ID_Supercede'	=> 'Replaced by',
	    'isCloseOut'	=> 'Closeout?'
	  );
	return $arCols;
    }
}
class vcqraSCSource_wSupplier extends vcqraSCSource_wSupplier_base {

    // ++ FIELD VALUES ++ //
    
    protected function SupplierName() {
	return $this->Value('SuppName');
    }
	
    // -- FIELD VALUES -- //
    // ++ ADMIN UI CALLBACKS ++ //
    
    protected function AdminRows_start() {
	return "\n<table class=listing>";
    }
    protected function AdminRows_row() {
	static $idSupp = NULL;
	
	$out = NULL;
	if ($this->SupplierID() != $idSupp) {
	    $idSupp = $this->SupplierID();
	    $sSupp = $this->SupplierName();
	    $rcSupp = $this->SupplierTable()->SpawnItem();
	    $rcSupp->SetKeyValue($idSupp);
	    $htSupp = $rcSupp->SelfLink($sSupp);
	    $out .= "\n<tr><td colspan=6 class='table-section-header'>$htSupp</td></tr>";
	}
	$out .= parent::AdminRows_row();
	return $out;
    }
    protected function AdminRow_CSSclass() {
	if ($this->IsActive()) {
	    return parent::AdminRow_CSSclass();
	} else {
	    return 'inact';
	}
    }
    protected function AdminField($sField) {
	switch($sField) {
	  case 'ID':
	    $val = $this->SelfLink();
	    break;
	  case 'ID_Supercede':
	    if ($this->IsSuperceded()) {
		$val = $this->SupercedeRecord()->SelfLink_name();
	    } else {
		$val = '-';
	    }
	    break;
	  case 'DateAvail':
	    $val = clsDate::NzDate($this->DateAvailable());
	    break;
	  case 'isCloseOut':
	    $val = $this->IsCloseout()?'YES':'no';
	    break;
	  default:
	    $val = $this->Value($sField);
	}
	return "<td>$val</td>";
    }
    
    // -- ADMIN UI CALLBACKS -- //
}