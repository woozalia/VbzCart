<?php
/*
  PART OF: VbzCart admin interface
  PURPOSE: classes for handling Item Types
  HISTORY:
    2016-01-19 started
*/

class vctaItemTypes extends vctItTyps {
    use ftLinkableTable;

    // ++ SETUP ++ //
    
    protected function SingularName() {
	return 'vcraItemType';
    }
    public function GetActionKey() {
	return KS_ACTION_CATALOG_ITEM_TYPE;
    }
    
    // -- SETUP -- //
    // ++ RECORDS ++ //
    
    public function DropDown_Records() {
	return $this->SelectRecords(NULL,'IFNULL(Sort,NameSng)');	// sort by Name
    }
    public function ActiveRecords() {
	$sqlFilt = 'isType';
	$sqlSort = 'Sort';
	$rs = $this->SelectRecords($sqlFilt,$sqlSort);
	return $rs;
    }
    
    // -- RECORDS -- //
}

class vcraItemType extends vcrItTyp {
    use ftLinkableRecord;

    // ++ TRAIT HELPERS ++ //
    
    protected function SelfLink_name() {
	return $this->SelfLink($this->NameSingular());
    }
    
    // -- TRAIT HELPERS -- //
    // ++ CALLBACKS ++ //

    public function ListItem_Text() {
	$out = $this->NameSingular();
	if (!$this->Value('isType')) {
	    $out = "[$out]";	// use brackets to indicate folder-types
	}
	if (!is_null($this->Value('Descr'))) {
	    $out .= ' - '.$this->Value('Descr');
	}
	return $out;
    }
    public function ListItem_Link() {
	return $this->SelfLink_name();
    }
    
    // -- CALLBACKS -- //
    // ++ OVERRIDES ++ //
    
    public function Description_forItem() {
	return $this->SelfLink(parent::Description_forItem());
    }

    // -- OVERRIDES -- //

}