<?php
/*
2014-01-19 The table for which this code was written does not seem to exist anymore.
  I must have folded it into the main (system) event_log table.
*/
class VbzAdminOrderEvents extends clsSysEvents {
    public function __construct($iDB) {
	parent::__construct($iDB);
	  $this->Name('ord_event');
	  $this->ClassSng('VbzAdminOrderEvent');
    }
}
class VbzAdminOrderEvent extends clsSysEvent {

    static protected function WhoHTML(array $row) {
	$htUnknown = '<span style="color: #888888;">?</span>';
die('<pre>'.print_r($row,TRUE).'</pre>');
	if (array_key_exists('SysUser',$row)) {
	    $strSysUser	= $row['SysUser'];
	    $hasSysUser = TRUE;
	} else {
	    $strSysUser	= NULL;
	    $hasSysUser	= FALSE;
	}
	$strMachine	= $row['Machine'];
	$strVbzUser	= $row['VbzUser'];

	$htSysUser	= is_null($strSysUser)?$htUnknown:$strSysUser;
	$htMachine	= is_null($strMachine)?$htUnknown:$strMachine;
	$htVbzUser	= is_null($strVbzUser)?$htUnknown:$strVbzUser;

	$out = $htVbzUser;
	if ($hasSysUser) {
	    $out .= '/'.$htSysUser;
	}
	$out .= '@'.$htMachine;

	return $htWho;
    }

    public function AdminTable(array $iarArgs) {
echo 'TABLE: '.$this->Table()->Name();
	if ($this->hasRows()) {
	    $out = <<<__END__
<table>
  <tr>
    <th>ID</th>
    <th>Started</th>
    <th>Finished</th>
    <th>Where</th>
    <th>Who</th>
    <th>What</th>
  </tr>
__END__;
	    $isOdd = TRUE;
	    $strDateLast = NULL;
	    while ($this->NextRow()) {
		$wtStyle = $isOdd?'background:#ffffff;':'background:#eeeeee;';
		$isOdd = !$isOdd;

		$row = $this->Row;
		$id = $row['ID'];

		$htWho = static::WhoHTML($row);
		$htWhat = $row['Descr'];
		if (!empty($row['Notes'])) {
		    $htWhat .= " ''{$row['Notes']}''";
		}

		$strWhenSt	= $row['WhenStarted'];
		$strWhenFi	= $row['WhenFinished'];
		$dtWhenSt	= strtotime($strWhenSt);
		$dtWhenFi	= strtotime($strWhenFi);
		$strDateSt	= is_null($strWhenSt)?'-':(date('Y-m-d',$dtWhenSt));
		$strDateFi	= is_null($strWhenFi)?'-':(date('Y-m-d',$dtWhenFi));
		$strTimeSt	= is_null($strWhenSt)?'-':(date('H:i',$dtWhenSt));
		$strTimeFi	= is_null($strWhenFi)?'-':(date('H:i',$dtWhenFi));
		$strDateLater = empty($dtWhenFi)?$strDateSt:$strDateFi;
		if ($strDateLater != $strDateLast) {
		    $strDateLast = $strDateLater;
		    $out .= "\n|- style=\"background: #444466; color: #ffffff;\"\n| colspan=6 | '''$strDateLast'''";
		}

		$out .= <<<__END__
  <tr style="$wtStyle">
    <td>$id</td>
    <td>$strTimeSt</td>
    <td>$strTimeFi</td>
    <td>{$row['EvWhere']}</td>
    <td>$htWho</td>
    <td>$htWhat</td>
  </tr>
</table>
__END__;
	    }
	} else {
	    $strDescr = $iarArgs['descr'];
	    $out = "\nNo events$strDescr.";
	}
	return $out;
    }
}
