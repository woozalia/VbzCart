<?php
/*
  PURPOSE: handles assignments of security groups to permissions
  HISTORY:
    2014-01-02 started
*/
class clsUGroup_x_UPerm extends clsTable_abstract {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ugroup_x_uperm');
    }

    // ++ DATA TABLE ACCESS ++ //

    protected function PermTable() {
	return $this->Engine()->Make(KS_CLASS_USER_PERMISSIONS);
    }

    // -- DATA TABLE ACCESS -- //

    // ++ DATA RECORD ACCESS ++ //

    /*----
      RETURN: Records for all permissions assigned to the given group
    */
    public function UPermRecords($idUGroup) {
	$sql = 'SELECT up.*'
	  .' FROM '.KS_TABLE_UGROUP_X_UPERM.' AS axp'
	  .' LEFT JOIN '.KS_TABLE_USER_PERMISSION.' AS up'
	  .' ON up.ID=axp.ID_UPrm';
	$rs = $this->DataSQL($sql,KS_CLASS_USER_PERMISSION);
	$rs->Table($this->PermTable());
	return $rs;
    }

    // -- DATA RECORD ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      INPUT:
	$idGroup : group to which we are assigning permissions
	arPerms[id] = (any value) : user should be assigned to group 'id'
    */
    public function SetUPerms($idGroup, array $arPerms) {
	$this->Engine()->TransactionOpen();
	// first, delete any existing assignments:
	$sql = 'DELETE FROM '.$this->NameSQL().'FROM '.KS_TABLE_UGROUP_X_UPERM.' WHERE ID_UGrp='.$idGroup;
	$ok = $this->Engine()->Exec($sql);

	// next, add any specified by the form:
	foreach ($arPerms as $idPerm => $on) {
	    $this->Insert(array(
	      'ID_UGrp'=>$idGroup,
	      'ID_UPrm'=>$idPerm
	      )
	    );
//	    $sql = $this->sqlExec;
//	    echo "SQL: $sql<br>";
	}

	$this->Engine()->TransactionSave();
    }

    // -- ACTIONS -- //
}