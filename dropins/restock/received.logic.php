<?php
/*
  PURPOSE: classes for handling received restocks
    These are base logic classes. At the moment, they seem to be unused, but that may be because
      of functionality not yet ported from the old MediaWiki admin version.
  HISTORY:
    2013-12-18 created to reduce confusion
    2016-01-04 extracted clsRstkRcds and clsRstkRcd from received.php
*/
class vctRstksRcvd extends vcBasicTable {
    use vtRestockTable_logic;
    
    // ++ SETUP ++ //
/*
    public function __construct($db) {
	parent::__construct($db);
	  $this->ClassSng();
	  $this->Name();
	  $this->KeyName('ID');
	  $this->ActionKey(KS_ACTION_RESTOCK_RECEIVED);
    } */
    // CEMENT
    protected function TableName() {
	return KS_TABLE_RESTOCK_RECEIVED;
    }
    // CEMENT
    protected function SingularName() {
	return 'vcrRstkRcvd';
    }

    // -- SETUP -- //
    // ++ CLASS NAMES ++ //
    
    protected function RequestsClass() {	// admin class should override
	return KS_LOGIC_CLASS_RESTOCK_REQUESTS;
    }
    
    // -- CLASS NAMES -- //
    // ++ TABLES ++ //
    
    // PUBLIC so Records object can use it
    public function RequestTable($id=NULL) {
	return $this->GetConnection()->MakeTableWrapper($this->RequestsClass(),$id);
    }
    
    // -- TABLES -- //
    // ++ SQL CALCULATION ++ //
    
    protected function SQLstr_Sorter_Date() {
	return 'IFNULL(WhenReceived,IFNULL(WhenShipped,WhenDebited))';
    }
    
    // -- SQL CALCULATION -- //
}
class vcrRstkRcvd extends vcBasicRecordset {

    // ++ FIELD ACCESS ++ //
    
    // some of these need to be set when creating a received restock from a request.
    
    // PUBLIC so Request can set it
    public function SetRequestID($id) {
	return $this->SetFieldValue('ID_Request',$id);
    }
    protected function GetRequestID() {
	return $this->GetFieldValue('ID_Request');
    }
    
    // -- FIELD ACCESS -- //
    // ++ CLASS NAMES ++ //
    
    protected function LinesClass() {
	return KS_LOGIC_CLASS_RESTOCK_LINES_RECEIVED;
    }
    
    // -- CLASS NAMES -- //
    // ++ TABLES ++ //
    
    protected function LineTable($id=NULL) {
	return $this->Engine()->Make($this->LinesClass(),$id);
    }
    protected function RequestTable($id=NULL) {
	return $this->GetTableWrapper()->RequestTable($id);
    }
    
    // -- TABLES -- //
    // ++ RECORDS ++ //

    /*----
      RETURNS: object for the line in this received restock which has InvcLineNo=iLine
    */
    public function LineRecord($iLine) {
	throw new exception('Document what calls this, and why it needs to be public.');
	$sqlFilt = '(ID_Parent='.$this->ID.') AND (InvcLineNo='.$iLine.')';
	$rs = $this->LineTable()->GetData($sqlFilt);
	$rs->NextRow();	// load first/only row
	return $obj;
    }
    protected function LineRecords() {
	$sqlFilt = 'ID_Parent='.$this->GetKeyValue();
	$rs = $this->LineTable()->GetData($sqlFilt);
	return $rs;
    }
    // PUBLIC so line records can access it
    public function RequestRecord() {
	if ($this->HasRequest()) {
	    return $this->RequestTable($this->GetRequestID());
	} else {
	    return NULL;
	}
    }

    // -- RECORDS -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Deactivate all line-items for this restock shipment
      USAGE: Do this before refreshing from parsed invoice text.
    */
    public function ClearLines() {
	$arUpd = array('isActive'=>'FALSE');
	$tblLines = $this->LineTable();
	$tblLines->Update($arUpd,'ID_Parent='.$this->GetKeyValue());
    }

    // -- ACTIONS -- //
}
