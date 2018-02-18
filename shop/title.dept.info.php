<?php
class vcqtTitlesInfo_forDept_shop extends vcqtTitlesInfo_forDept {
    // ++ WEB OUTPUT ++ //
    
    public function RenderImages_forDept($idDept) {
	$sql = $this->SQL_forDeptPage_wTitleInfo($idDept,TRUE);
	$rs = $this->FetchRecords($sql);
	return $rs->RenderImages();
    }
}
