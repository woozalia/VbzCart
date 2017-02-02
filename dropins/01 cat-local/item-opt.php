<?php
/*
  PART OF: VbzCart admin interface
  PURPOSE: classes for handling Item Options
  HISTORY:
    2015-11-17 started
*/

class vtItemOpts_admin extends clsItOpts {
    use ftLinkableTable;

    // ++ SETUP ++ //

    // OVERRIDE
    protected function SingularName() {
	return 'vrItemOpt_admin';
    }
    // CEMENT
    public function GetActionKey() {
	return KS_ACTION_CATALOG_ITEM_OPTION;
    }
    
    // -- SETUP -- //
    // ++ DATA RECORDS ACCESS ++ //
    
    public function GetData_forDropDown() {
	$sqlTbl = $this->NameSQL();
	$rs = $this->DataSQL("SELECT * FROM $sqlTbl ORDER BY Sort");
	return $rs;
    }
    public function ActiveRecords() {
	$sqlFilt = NULL;
	$sqlSort = 'Sort';
	$rs = $this->SelectRecords($sqlFilt,$sqlSort);
	return $rs;
    }
    
    // -- DATA RECORDS ACCESS -- //

}
class vrItemOpt_admin extends clsItOpt {
    use ftLinkableRecord;

    // ++ CALLBACKS ++ //

    public function ListItem_Text() {
	return $this->Description_forList();
    }
    public function ListItem_Link() {
	return $this->SelfLink($this->ListItem_Text());
    }
    
    // -- CALLBACKS -- //
    // ++ OVERRIDES ++ //
    
    public function Description_forItem() {
	return $this->SelfLink($this->Value('Descr'));
    }
    
}