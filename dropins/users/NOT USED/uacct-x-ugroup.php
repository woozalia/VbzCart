<?php
/*
  PURPOSE: handles assignments of user accounts to security groups
  HISTORY:
    2013-12-19 started
*/
class clsUAcct_x_UGroup extends clsTable_abstract {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('user_x_ugroup');
    }

    // ++ DATA TABLE ACCESS ++ //

    protected function GroupTable() {
	return $this->Engine()->Make(KS_CLASS_USER_GROUPS);
    }

    // -- DATA TABLE ACCESS -- //
    // ++ DATA RECORD ACCESS ++ //

    /*----
      RETURN: Records for all groups to which the given user belongs
    */
    public function UGroupRecords($idUAcct) {
	$sql = 'SELECT ug.*'
	  .' FROM '.KS_TABLE_UACCT_X_UGROUP.' AS axg'
	  .' LEFT JOIN '.KS_TABLE_USER_GROUP.' AS ug'
	  .' ON ug.ID=axg.ID_UGrp';
	$rs = $this->DataSQL($sql,KS_CLASS_USER_GROUP);
	$rs->Table($this->GroupTable());
	return $rs;
    }

    // -- DATA RECORD ACCESS -- //
    // ++ ACTIONS ++ //

    /*----
      INPUT:
	$idAcct : user to which we are assigning groups
	arGrps[id] = (arbitrary value) : user should be assigned to group 'id'
    */
    public function SetUGroups($idAcct, array $arGrps) {
	$this->Engine()->TransactionOpen();
	// first, delete any existing assignments:
	$sql = 'DELETE FROM '.$this->NameSQL().'FROM '.KS_TABLE_UACCT_X_UGROUP.' WHERE ID_User='.$idAcct;
	$ok = $this->Engine()->Exec($sql);

	// next, add any specified by the form:
	foreach ($arGrps as $idGrp => $on) {
	    $this->Insert(array(
	      'ID_User'=>$idAcct,
	      'ID_UGrp'=>$idGrp
	      )
	    );
//	    $sql = $this->sqlExec;
//	    echo "SQL: $sql<br>";
	}

	$this->Engine()->TransactionSave();
    }

    // -- ACTIONS -- //
}