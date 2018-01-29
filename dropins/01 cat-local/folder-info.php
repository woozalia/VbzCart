<?php
/*
  PURPOSE: Folder records with additional calculated fields, for admin UI purposes
  HISTORY:
    2017-07-18 started because I need a project that doesn't take brainz
*/
class vctaFoldersInfo extends vctaFolders implements fiLinkableTable, fiEventAware {
    use ftLinkableTable;
    
    // ++ SETUP ++ //
    
    protected function SingularName() {
	return 'vcraFolderInfo';
    }
    
    // -- SETUP -- //
    // ++ SQL BITS ++ //

    // PURPOSE: provides name of table for SELECT queries; can also return a JOIN
    protected function SourceString_forSelect() {
	$sqlBase = $this->TableName_Cooked().' as f';
	$sqlInfo = <<<__END__
	  LEFT JOIN
	    (SELECT 
	      SUM(ABS(isActive)) AS ActiveCount,
	      SUM(ABS(!isActive)) AS InactiveCount,
	      ID_Folder
	    FROM
	      cat_images
	    GROUP BY ID_Folder) AS i ON i.ID_Folder = f.ID
__END__;
	return $sqlBase.$sqlInfo;	// default; override for joins
    }
    // PURPOSE: provides field list for SELECT queries
    protected function FieldsString_forSelect() {
	return '*';
    }

    // -- SQL BITS -- //
}
class vcraFolderInfo extends vcraFolder {

    // ++ WEB UI ++ //

    public function AdminRows_settings_columns() {
	return array(
	    '!ID'	=> 'ID',
	    //'ID_Parent'	=> 'Parent',
	    'PathPart'	=> 'Path',
	    'Descr'	=> 'Description',
	    'ActiveCount'	=> '# active',
	    'InactiveCount'	=> '# inactive'
	  );
    }

    // -- WEB UI -- //

}