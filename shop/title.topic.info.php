<?php
class vcqtTitlesInfo_forTopic_shop extends vcqtTitlesInfo_forTopic {

    // ++ WEB OUTPUT ++ //
    
    public function RenderImages_forTopic($idTopic) {
	$sql = $this->SQL_forTopicPage_wTitleInfo($idTopic,TRUE);
	$rs = $this->FetchRecords($sql);
	return $rs->RenderImages();
   }

    // -- WEB OUTPUT -- //

}