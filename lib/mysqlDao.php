<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
function getQuery($query){
	$obj=null;    
	$obj['RESULT']=DAOQuerySQL($query);
	return $obj;	
}


function saveRecord($TABLE,$objItem){
	$obj=null;
    $obj['SQL']="";
	//LIST COL
	$colup='';
	$col='';
	$val='';
	$where='';	
	$i=0;
	foreach($objItem as $k=>$v){
		$exp=explode("-",$k);		
		if (($k!='ACT')&&($exp[0]!='PK')&&($k!='CAPTCHA')){
			$i++;
			
			//EDIT VAR
			if ($k=='PASSWD') $v=md5($v);
			if ($i>1){
				$colup.=",".$k."='".cleanParam($v)."'";				
			}else{
				$colup.=$k."='".cleanParam($v)."'";
			}
			
			
			//ADD VAR
			if ($i>1){
				$col.=",".$k;
				$val.=",'".cleanParam($v)."'";
			}else{
				$col.=$k;
				$val.="'".cleanParam($v)."'";
			}
			
		}
		
		//GET WHERE KEY
		if (sizeof($exp)>1){
				$where=' where '.$exp[1]."='".cleanParam($v)."'";
		}
	}
	
	if (strtoupper($objItem['ACT'])=="ADD"){
        $obj['SQL']="insert into {$TABLE} (".$col.") values (".$val.")";		
	}else if(strtoupper($objItem['ACT'])=="EDIT"){
		$obj['SQL']="UPDATE {$TABLE} set ".$colup;
		$obj['SQL'].=$where;
	}
			
    $obj['RESULT']=DAOExecuteSQL($obj['SQL']);
	return $obj;    
}


function getRecord($TABLE,$COND){
	$obj=null;    
	
	$obj['SQL']="SELECT * from {$TABLE} where 1=1";

	foreach($COND as $k=>$v){
		if (($k!='LIMIT') && ($k!='ORDER') && ($k!='KEYWORD') && ($k!='ACT')){
			if($v!=''){
				$obj['SQL'].=" AND {$k}='".cleanParam($v)."'";
			}
		}
	}
	
	if(isset($COND['KEYWORD'])&& $COND['KEYWORD']!=''){
		$obj['SQL'].=" AND (DESCRIPTION like '%".$COND['KEYWORD']."%' or KEYWORD like '%".$COND['KEYWORD']."%') ";
	}

	if(isset($COND['ORDER'])&& $COND['ORDER']!=''){
		$obj['SQL'].=" ORDER by ".$COND['ORDER'];	
	}	
		
		
	
	if(isset($COND['LIMIT'])&& $COND['LIMIT']!=''){
		$obj['SQL'].=" LIMIT ".$COND['LIMIT'];
	}else{
		$obj['SQL'].=" LIMIT 1";
	}
	
	
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;	
}


function getRecord2($TABLE,$COND){
	$obj=null;    
	
	$obj['SQL']="SELECT * from {$TABLE} where 1=1";

	foreach($COND as $k=>$v){
		if (($k!='LIMIT') && ($k!='ORDER') && ($k!='KEYWORD') && ($k!='ACT')){
			if($v!=''){
				$obj['SQL'].=" AND {$k}='".cleanParam($v)."'";
			}
		}
	}
	
	if(isset($COND['KEYWORD'])&& $COND['KEYWORD']!=''){
		$obj['SQL'].=" AND (DESCRIPTION like '%".$COND['KEYWORD']."%' or KEYWORD like '%".$COND['KEYWORD']."%') ";
	}

	if(isset($COND['ORDER'])&& $COND['ORDER']!=''){
		$obj['SQL'].=" ORDER by ".$COND['ORDER'];	
	}	
		
		
	
	if(isset($COND['LIMIT'])&& $COND['LIMIT']!=''){
		$obj['SQL'].=" LIMIT ".$COND['LIMIT'];
	}else{
		$obj['SQL'].=" LIMIT 1";
	}
	
	
    $obj['RESULT']=DAOQuerySQL2($obj['SQL']);
	return $obj;	
}


function countRecord($TABLE,$COND){
	$obj=null;    
	
	$obj['SQL']="SELECT count(1) as TOTAL from {$TABLE} where 1=1";

	foreach($COND as $k=>$v){
		if (($k!='LIMIT') && ($k!='ORDER') && ($k!='KEYWORD') && ($k!='ACT')){
			if($v!=''){
				$obj['SQL'].=" AND {$k}='".$v."'";
			}
		}
	}
	
	if(isset($COND['KEYWORD'])&& $COND['KEYWORD']!=''){
		$obj['SQL'].=" AND (DESCRIPTION like '%".$COND['KEYWORD']."%' or KEYWORD like '%".$COND['KEYWORD']."%') ";
	}	
		
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;	
}



function personName($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * from tbl_writer where 1=1";

    if(isset($objItem['EMAIL'])&& $objItem['EMAIL']!='')
        $obj['SQL'].=" AND EMAIL='".$objItem['EMAIL']."'";	
	
	if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";		
	
	$obj['SQL'].=" order by FULLNAME";	
		
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;	
}



function getConfig($objItem=null){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_config WHERE 1=1";
    
    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";
	 
	if(isset($objItem['CATEGORY'])&& $objItem['CATEGORY']!='')
        $obj['SQL'].=" AND CATEGORY='".$objItem['CATEGORY']."'";	
	
	if(isset($objItem['CKEY'])&& $objItem['CKEY']!='')
        $obj['SQL'].=" AND CKEY='".$objItem['CKEY']."'";
	
	if(isset($objItem['STATUS'])&& $objItem['STATUS']!='')
        $obj['SQL'].=" AND STATUS='".$objItem['STATUS']."'";	
				
	$obj['SQL'].=" ORDER BY ID ASC";					
	   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}


function getDownload($objItem=null){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_download WHERE STATUS=1";
    
    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";
	 
	$obj['SQL'].=" ORDER BY ID DESC";					
	   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}


function countDownload($objItem=null){
	$obj=null;
    
	$obj['SQL']="SELECT count(1) as TOTAL FROM tbl_download WHERE STATUS=1";
    
    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";
	 

    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}

function getMenu($objItem=null){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_menu WHERE STATUS=1";
    
    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";
	 
	if(isset($objItem['STATUS'])&& $objItem['STATUS']!='')
        $obj['SQL'].=" AND STATUS='".$objItem['STATUS']."'";

	if(isset($objItem['POS'])&& $objItem['POS']!='')
        $obj['SQL'].=" AND POS='".$objItem['POS']."'";		
				
	$obj['SQL'].=" ORDER BY ORDNUM ASC";					
	   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}


function getSubMenu($objItem=null){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_submenu WHERE STATUS=1";
    
    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";
	
	if(isset($objItem['MENU_ID'])&& $objItem['MENU_ID']!='')
        $obj['SQL'].=" AND MENU_ID='".$objItem['MENU_ID']."'";
	 
	if(isset($objItem['STATUS'])&& $objItem['STATUS']!='')
        $obj['SQL'].=" AND STATUS='".$objItem['STATUS']."'";	
				
	$obj['SQL'].=" ORDER BY ORDNUM ASC";					
	   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}


function getSubSubMenu($objItem=null){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_subsubmenu WHERE STATUS=1";
    
    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";
	
	if(isset($objItem['SUBMENU_ID'])&& $objItem['SUBMENU_ID']!='')
        $obj['SQL'].=" AND SUBMENU_ID='".$objItem['SUBMENU_ID']."'";
	 
	if(isset($objItem['STATUS'])&& $objItem['STATUS']!='')
        $obj['SQL'].=" AND STATUS='".$objItem['STATUS']."'";	
				
	$obj['SQL'].=" ORDER BY ORDNUM ASC";					
	   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}

function getJadwal($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * from tbl_jadwal where 1=1";

    if(isset($objItem['TANGGAL'])&& $objItem['TANGGAL']!='')
        $obj['SQL'].=" AND TANGGAL='".$objItem['TANGGAL']."'";

	if(isset($objItem['AREA'])&& $objItem['AREA']!='')
        $obj['SQL'].=" AND AREA='".$objItem['AREA']."'";	
	
	if(isset($objItem['BULAN'])&& $objItem['BULAN']!='')
        $obj['SQL'].=" AND BULAN=month('".$objItem['TANGGAL']."')";
	
	
	if(isset($objItem['TANGGAL'])&& $objItem['TANGGAL']!='')
        $obj['SQL'].=" AND TANGGAL='".$objItem['TANGGAL']."'";
	
	$obj['SQL'].=" order by TANGGAL";	
		
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;	
}

function getStatic($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * from tbl_static where 1=1";

    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";	
	
	if(isset($objItem['TITLE'])&& $objItem['TITLE']!='')
        $obj['SQL'].=" AND TITLE='".$objItem['TITLE']."'";		
	
	if(isset($objItem['SEO'])&& $objItem['SEO']!='')
        $obj['SQL'].=" AND SEO='".$objItem['SEO']."'";		
	
	$obj['SQL'].=" order by TITLE";	
		
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;	
}

function getIndikator($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * from tbl_indikator where STATUS=1";

    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";	
		
	
	$obj['SQL'].=" order by NO ASC";	
		
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;	
}


function getQuiz($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * from tbl_quiz where STATUS=1 ";
    
	if(isset($objItem['CURRENT'])&& $objItem['CURRENT']!='')
        $obj['SQL'].=" AND START_DATE<=CURDATE() AND END_DATE>=CURDATE()";		
	
	if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";		
	
	$obj['SQL'].=" order by ID DESC";	
		
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;	
}

function countAnswer($objItem){
	$obj=null;
    
	$obj['SQL']="select count(1) as TOTAL from tbl_quiz_answer where QUIZ_ID='".$objItem['QUIZ_ID']."' and EMAIL='".$objItem['EMAIL']."'";    
		
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);	
	
	return $obj;
}


function saveAdvokasi($objItem){
	$obj=null;    
	
	$objItem['NAME']=filterOnly($objItem['NAME']);
	$objItem['EMAIL']=filterOnly($objItem['EMAIL']);	
	$objItem['ADDRESS']=filterOnly($objItem['ADDRESS']);
	$objItem['PHONE']=filterOnly($objItem['PHONE']);
	$objItem['IDENTITY_NO']=filterOnly($objItem['IDENTITY_NO']);
	$objItem['MARITAL_STATUS']=filterOnly($objItem['MARITAL_STATUS']);
	$objItem['SECRET']=filterOnly($objItem['SECRET']);
	$objItem['CHRONOLOGY']=filterOnly($objItem['CHRONOLOGY']);
	$objItem['EXPECTANCY']=filterOnly($objItem['EXPECTANCY']);
	
	
	$obj['SQL']="insert into tbl_advokasi (NAME,EMAIL,ADDRESS,PHONE,IDENTITY_NO,MARITAL_STATUS,SECRET,CHRONOLOGY,EXPECTANCY,REP_TIMESTAMP,STATUS) values ('".$objItem['NAME']."','".$objItem['EMAIL']."','".$objItem['ADDRESS']."','".$objItem['PHONE']."','".$objItem['IDENTITY_NO']."','".$objItem['MARITAL_STATUS']."','".$objItem['SECRET']."','".$objItem['CHRONOLOGY']."','".$objItem['EXPECTANCY']."',NOW(),0)";    
		
    $obj['RESULT']=DAOExecuteSQL($obj['SQL']);
	
	
	if(!$obj['RESULT']){
	$obj['SQL']="update tbl_participant set	
	NAME='".$objItem['NAME']."',
	ADDRESS='".$objItem['ADDRESS']."',
	PHONE='".$objItem['PHONE']."',
	TWITTER='".$objItem['TWITTER']."',
	FACEBOOK'".$objItem['FACEBOOK']."',
	NIM='".$objItem['NIM']."') 
	where='".$objItem['EMAIL']."'";    
		
    $obj['RESULT']=DAOExecuteSQL($obj['SQL']);	
	}
	
	return $obj;	
}


function getBanner($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * from tbl_banners where STATUS=1 AND START_DATE<=CURDATE() AND END_DATE>=CURDATE()";

    if(isset($objItem['POS'])&& $objItem['POS']!='')
        $obj['SQL'].=" AND POS='".$objItem['POS']."'";	
	
	if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";		
	
	$obj['SQL'].=" order by ORDERNUM ASC";	
		
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;	
}


function getMedia($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_media where STATUS=1 ";
	
	if(isset($objItem['ID'])&& $objItem['ID']!='')
     	$obj['SQL'].=" AND ID='".$objItem['ID']."'";
	
	if(isset($objItem['TIPE'])&& $objItem['TIPE']!='')
     	$obj['SQL'].=" AND TIPE='".$objItem['TIPE']."'";
	
	if(isset($objItem['TITLE'])&& $objItem['TITLE']!='')
     	$obj['SQL'].=" AND TITLE like '%".$objItem['TITLE']."%'";		
		
	$obj['SQL'].=" ORDER BY ID DESC";
	if(isset($objItem['LIMIT'])&& $objItem['LIMIT']!=''){
		$obj['SQL'].=" LIMIT ".$objItem['LIMIT'];
	}else{
		$obj['SQL'].=" LIMIT 1";
	}
	
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    	
}


function getCountMedia($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT count(1) as  TOTAL FROM tbl_media where STATUS=1 ";
	
	if(isset($objItem['ID'])&& $objItem['ID']!='')
     	$obj['SQL'].=" AND ID='".$objItem['ID']."'";
	
	if(isset($objItem['TIPE'])&& $objItem['TIPE']!='')
     	$obj['SQL'].=" AND TIPE='".$objItem['TIPE']."'";
	
	if(isset($objItem['TITLE'])&& $objItem['TITLE']!='')
     	$obj['SQL'].=" AND TITLE like '%".$objItem['TITLE']."%'";		
		
	$obj['SQL'].=" ORDER BY ID DESC";
	if(isset($objItem['LIMIT'])&& $objItem['LIMIT']!=''){
		$obj['SQL'].=" LIMIT ".$objItem['LIMIT'];
	}else{
		$obj['SQL'].=" LIMIT 1";
	}
	
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    	
}

function getMediaAdd($objItem=null){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_media_add WHERE STATUS=1";
    
    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";
	 
	if(isset($objItem['MEDIA_ID'])&& $objItem['MEDIA_ID']!='')
        $obj['SQL'].=" AND MEDIA_ID='".$objItem['MEDIA_ID']."'";			
	
						  
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}

function getAddImage($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * from tbl_add_image where STATUS=1";
    
	
	if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";		
	
	if(isset($objItem['ARTICLE_ID'])&& $objItem['ARTICLE_ID']!='')
        $obj['SQL'].=" AND ARTICLE_ID='".$objItem['ARTICLE_ID']."'";
	
	$obj['SQL'].=" order by SEQNUMBER ASC";	
		
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;	
}


function getFocusNews($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_focus_article where 1=1";
	
	if(isset($objItem['ID'])&& $objItem['ID']!='')
     	$obj['SQL'].=" AND ID='".$objItem['ID']."'";
	
	if(isset($objItem['FOCUS_ID'])&& $objItem['FOCUS_ID']!='')
     	$obj['SQL'].=" AND FOCUS_ID='".$objItem['FOCUS_ID']."'";		
		
	$obj['SQL'].=" ORDER BY ID DESC";
	
	
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    	
}


function getBreaking($objItem=null){
	$obj=null;
    
	$obj['SQL']="SELECT * from tbl_sekilasinfo where STATUS=1 AND PUBLISH_TIMESTAMP <= '".date('Y-m-d H:i:s')."'";

    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";	
	
	$obj['SQL'].=" order by PUBLISH_TIMESTAMP DESC";

	if(isset($objItem['END'])&& $objItem['END']>0){
		$obj['SQL'].=" LIMIT ".$objItem['FIRST'].",".$objItem['END']."";
	}else{
		$obj['SQL'].=" LIMIT 1";
	}
		
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;	
}



function getNews($objItem){
    
	$obj=null;
    	
	$obj['SQL']="SELECT * , TIMEDIFF(NOW(),PUBLISH_TIMESTAMP) AS selisih FROM tbl_article";
	$obj['SQL'].=" WHERE STATUS=1 and PUBLISH_TIMESTAMP <= '".date('Y-m-d H:i:s')."'";
	
	if(isset($objItem['ID'])&& $objItem['ID']!='')
     	$obj['SQL'].=" AND ID='".$objItem['ID']."'";
	
	if(isset($objItem['NID'])&& $objItem['NID']!='')
     	$obj['SQL'].=" AND ID<>'".$objItem['NID']."'";	
		
	if(isset($objItem['NNID'])&& $objItem['NNID']!='')
     	$obj['SQL'].=" AND ID  NOT IN ".$objItem['NNID'];	
	
	if(isset($objItem['CATEGORY'])&& $objItem['CATEGORY']!='')
     	$obj['SQL'].=" AND CATEGORY='".$objItem['CATEGORY']."'";	
	
	if(isset($objItem['UPPERDECK'])&& $objItem['UPPERDECK']!='')
     	$obj['SQL'].=" AND UPPERDECK like '%".$objItem['UPPERDECK']."%'";
		
	if(isset($objItem['NCATEGORY'])&& $objItem['NCATEGORY']!='')
     	$obj['SQL'].=" AND CATEGORY  NOT IN ".$objItem['NCATEGORY'];				
		
	if(isset($objItem['SUBCATEGORY'])&& $objItem['SUBCATEGORY']!='')
     	$obj['SQL'].=" AND SUBCATEGORY='".$objItem['SUBCATEGORY']."'";	
	
	if(isset($objItem['EDITORPICK'])&& $objItem['EDITORPICK']!='')
     	$obj['SQL'].=" AND EDITORPICK='".$objItem['EDITORPICK']."'";	
		
	if(isset($objItem['TIPE'])&& $objItem['TIPE']!='')
     	$obj['SQL'].=" AND TIPE='".$objItem['TIPE']."'";	
		
	if(isset($objItem['KEYWORD'])&& $objItem['KEYWORD']!='')
     	$obj['SQL'].=" AND KEYWORD like '%".$objItem['KEYWORD']."%'";		
	
	if(isset($objItem['PUBLISH_DATE'])&& $objItem['PUBLISH_DATE']!='')
     	$obj['SQL'].=" AND date(PUBLISH_TIMESTAMP)='".$objItem['PUBLISH_DATE']."'";		
				
	$obj['SQL'].=" ORDER BY PUBLISH_TIMESTAMP DESC ";
	
	if(isset($objItem['LIMIT'])&& $objItem['LIMIT']!=''){
			$obj['SQL'].=" LIMIT ".$objItem['LIMIT'];
		}else{
			$obj['SQL'].=" LIMIT 1";
		}
   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;

}




function getCountNews($objItem){
    
	$obj=null;
    	
	$obj['SQL']="SELECT count(1) as TOTAL FROM tbl_article";
	$obj['SQL'].=" WHERE STATUS=1 and PUBLISH_TIMESTAMP <= '".date('Y-m-d H:i:s')."'";
	
	if(isset($objItem['ID'])&& $objItem['ID']!='')
     	$obj['SQL'].=" AND ID='".$objItem['ID']."'";
	
	if(isset($objItem['NID'])&& $objItem['NID']!='')
     	$obj['SQL'].=" AND ID<>'".$objItem['NID']."'";	
		
	if(isset($objItem['NNID'])&& $objItem['NNID']!='')
     	$obj['SQL'].=" AND ID  NOT IN ".$objItem['NNID'];	
	
	if(isset($objItem['CATEGORY'])&& $objItem['CATEGORY']!='')
     	$obj['SQL'].=" AND CATEGORY='".$objItem['CATEGORY']."'";

	if(isset($objItem['UPPERDECK'])&& $objItem['UPPERDECK']!='')
     	$obj['SQL'].=" AND UPPERDECK like '%".$objItem['UPPERDECK']."%'";	
		
	if(isset($objItem['NCATEGORY'])&& $objItem['NCATEGORY']!='')
     	$obj['SQL'].=" AND CATEGORY  NOT IN ".$objItem['NCATEGORY'];				
	
	if(isset($objItem['SUBCATEGORY'])&& $objItem['SUBCATEGORY']!='')
     	$obj['SQL'].=" AND SUBCATEGORY='".$objItem['SUBCATEGORY']."'";
	
	if(isset($objItem['TIPE'])&& $objItem['TIPE']!='')
     	$obj['SQL'].=" AND TIPE='".$objItem['TIPE']."'";	
		
	if(isset($objItem['KEYWORD'])&& $objItem['KEYWORD']!='')
     	$obj['SQL'].=" AND KEYWORD like '%".$objItem['KEYWORD']."%'";		
	
	if(isset($objItem['PUBLISH_DATE'])&& $objItem['PUBLISH_DATE']!='')
     	$obj['SQL'].=" AND date(PUBLISH_TIMESTAMP)='".$objItem['PUBLISH_DATE']."'";		
					   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;

}

function getNewsRelated($objItem){
	
	$KEYWORD=explode(",",$objItem['KEYWORD']);
	
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_article ";
	$obj['SQL'].=" WHERE STATUS=1 and PUBLISH_TIMESTAMP <= '".date('Y-m-d H:i:s')."'";
		
	
	if(isset($objItem['NID'])&& $objItem['NID']!='')
     	$obj['SQL'].=" AND ID<>'".$objItem['NID']."'";			
		
	if(isset($objItem['CATEGORY'])&& $objItem['CATEGORY']!='')
     	$obj['SQL'].=" AND CATEGORY='".$objItem['CATEGORY']."'";				
	
	
	if(isset($KEYWORD[0])&& $KEYWORD[0]!='')
     	$obj['SQL'].=" AND KEYWORD like '%".$KEYWORD[0]."%'";
		
	$obj['SQL'].=" AND PUBLISH_TIMESTAMP > (curdate()-interval 1 MONTH)";					
		
	$obj['SQL'].=" ORDER BY  RAND () ";
	$obj['SQL'].=" LIMIT ".$objItem['LIMIT'];
   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}

function upcount($objItem){
	$obj=null;
    	
	$obj['SQL']="UPDATE tbl_article set HIT='".$objItem['HIT']."' WHERE ID='".$objItem['ID']."'";
		
    $obj['RESULT']=DAOExecuteSQL($obj['SQL']);
	return $obj;    	
}


function getNewsOther($objItem){
	$objResult=null;
    
	$strSQL="SELECT * FROM jnwnews ";
	$strSQL.=" WHERE publish_status=1 and MediaAbbreviation='JNW' and capekdehpublished_at <= '".date('Y-m-d H:i:s')."'";
		
	if(isset($objItem['nid'])&& $objItem['nid']!='')
     	$strSQL.=" AND id<>'".$objItem['nid']."'";	
	
	if(isset($objItem['NewsCategory1_id'])&& $objItem['NewsCategory1_id']!='')
     	$strSQL.=" AND NewsCategory1_id='".$objItem['NewsCategory1_id']."'";	
		
	if(isset($objItem['NewsCategory2_id'])&& $objItem['NewsCategory2_id']!='')
     	$strSQL.=" AND NewsCategory2_id='".$objItem['NewsCategory2_id']."'";	

	if(isset($objItem['NewsCategory3_id'])&& $objItem['NewsCategory3_id']!='')
     	$strSQL.=" AND NewsCategory3_id='".$objItem['NewsCategory3_id']."'";		
		
	if(isset($objItem['quality'])&& $objItem['quality']!='')
     	$strSQL.=" AND quality='".$objItem['quality']."'";			
		
	$strSQL.=" AND capekdehpublished_at > (CURDATE()-interval 1 month)";	
		
		
	$strSQL.=" ORDER BY RAND() ";
	$strSQL.=" LIMIT ".$objItem['FIRST'].",".$objItem['END']."";
   
    $objResult=DAOQuerySQL($strSQL);
	return $objResult;
    //return $strSQL;
	
}

function getPopularTag($objItem=null) {
	$obj = null;
	$obj['SQL'] = "SELECT * from tbl_article where STATUS=1 ORDER BY HIT DESC limit 10"; 
	$obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;
}

function getNewsPopular($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_article ";
	$obj['SQL'].=" WHERE STATUS=1 and PUBLISH_TIMESTAMP <= '".date('Y-m-d H:i:s')."'";
		
	if(isset($objItem['NID'])&& $objItem['NID']!='')
     	$obj['SQL'].=" AND ID<>'".$objItem['NID']."'";	
	
	if(isset($objItem['CATEGORY'])&& $objItem['CATEGORY']!='')
     	$obj['SQL'].=" AND CATEGORY='".$objItem['CATEGORY']."'";
	
	if(isset($objItem['UPPERDECK'])&& $objItem['UPPERDECK']!='')
     	$obj['SQL'].=" AND UPPERDECK='".$objItem['UPPERDECK']."'";
		
	$obj['SQL'].=" AND PUBLISH_TIMESTAMP > (CURDATE() - interval 7 DAY)";	
		
		
	$obj['SQL'].=" ORDER BY HIT DESC ";
	$obj['SQL'].=" LIMIT ".$objItem['FIRST'].",".$objItem['END']."";
   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}

function getMediaUrl($objItem){
	// ARTIKEL/ID/TITLE/	
	
	$search = array("`","quot",".","(",")","'", "\"","/", ":", ",", "!", ".", "$", "'", "+", "%", "&",'lsquo;',"rsquo;","?","rlm;",";", " ","<i>","</i>");  
    $replace = array("","","","","","","-","-","","","","","","","","","","","","","","","-","",""); 
					 
	$seo=str_replace("\\","",(str_replace($search, $replace, strtolower(trim($objItem['TITLE'])))));
	$seo=str_replace("\\","",(str_replace($search, $replace, urlencode($seo))));	
	
	$result=ROOT_URL.'/view/'.$objItem['ID'].'/'.$seo.'/';
	return $result;
}

function getMediaUrlMobile($objItem){
	// ARTIKEL/ID/TITLE/	
	
	$search = array("`","quot",".","(",")","'", "\"","/", ":", ",", "!", ".", "$", "'", "+", "%", "&",'lsquo;',"rsquo;","?","rlm;",";", " ","<i>","</i>");  
    $replace = array("","","","","","","-","-","","","","","","","","","","","","","","","-","",""); 
					 
	$seo=str_replace("\\","",(str_replace($search, $replace, strtolower(trim($objItem['TITLE'])))));
	$seo=str_replace("\\","",(str_replace($search, $replace, urlencode($seo))));	
	
	$result=MROOT_URL.'/view/'.$objItem['ID'].'/'.$seo.'/';
	return $result;
}

 
function getNewsURL($objItem){
	// ARTIKEL/ID/TITLE/	
	
	$search = array("`","quot",".","(",")","'", "\"","/", ":", ",", "!", ".", "$", "'", "+", "%", "&",'lsquo;',"rsquo;","?","rlm;",";", " ","<i>","</i>");  
    $replace = array("","","","","","","-","-","","","","","","","","","","","","","","","-","",""); 
					 
	$seo=str_replace("\\","",(str_replace($search, $replace, strtolower(trim($objItem['TITLE'])))));
	$seo=str_replace("\\","",(str_replace($search, $replace, urlencode($seo))));
	
	$var['ID']=$objItem['CATEGORY'];
	$cat=getCategory($var);
	$cat=strtolower($cat['RESULT'][0]['SEO']);
	
	$result=ROOT_URL.'/read/'.$objItem['ID'].'/'.$seo.'/';
	return $result;
}

function getNewsURLMobile($objItem){
	// ARTIKEL/ID/TITLE/	
	
	$search = array("`","quot",".","(",")","'", "\"","/", ":", ",", "!", ".", "$", "'", "+", "%", "&",'lsquo;',"rsquo;","?","rlm;",";", " ","<i>","</i>");  
    $replace = array("","","","","","","-","-","","","","","","","","","","","","","","","-","",""); 
					 
	$seo=str_replace("\\","",(str_replace($search, $replace, strtolower(trim($objItem['TITLE'])))));
    $seo=str_replace("\\","",(str_replace($search, $replace, urlencode($seo))));	
	
	$var['ID']=$objItem['CATEGORY'];
	$cat=getCategory($var);
	$cat=strtolower($cat['RESULT'][0]['SEO']);
	
	$result=MROOT_URL.'/read/'.$objItem['ID'].'/'.$seo.'/';
	return $result;
}


function getCategory($objItem=null){	
	$obj=null;
	$obj['SQL']="SELECT * from tbl_category where STATUS=1";
	
	if(isset($objItem['ID'])&& $objItem['ID']!='')
     	$obj['SQL'].=" AND ID='".$objItem['ID']."'";	
	
	if(isset($objItem['SEO'])&& $objItem['SEO']!='')
     	$obj['SQL'].=" AND SEO='".$objItem['SEO']."'";
	
	$obj['SQL'].=" order by NO ASC";
	
	
	$obj['RESULT']=DAOQuerySQL($obj['SQL']);
		
	return $obj;
}

function getSubCategory($objItem=null){	
	$obj=null;
	$obj['SQL']="SELECT * from tbl_subcategory where STATUS=1";
	
	if(isset($objItem['ID'])&& $objItem['ID']!='')
     	$obj['SQL'].=" AND ID='".$objItem['ID']."'";

	if(isset($objItem['CATEGORY_ID'])&& $objItem['CATEGORY_ID']!='')
     	$obj['SQL'].=" AND CATEGORY_ID='".$objItem['CATEGORY_ID']."'";	
	
	if(isset($objItem['TYPE'])&& $objItem['TYPE']!='')
     	$obj['SQL'].=" AND TYPE='".$objItem['TYPE']."'";
	
	if(isset($objItem['SEO'])&& $objItem['SEO']!='')
     	$obj['SQL'].=" AND SEO='".$objItem['SEO']."'";	
	
	$obj['SQL'].=" ORDER BY POS ASC";	
	
	$obj['RESULT']=DAOQuerySQL($obj['SQL']);
		
	return $obj;
}


function getAdvertorial($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM jnwnews WHERE id IN ".$objItem['id']."";	
		 
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;
    //return $strSQL;
	
}


function getPolling($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_polling WHERE 1=1";
    
    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";	
	 
	if(isset($objItem['STATUS'])&& $objItem['STATUS']!='')
        $obj['SQL'].=" AND STATUS='".$objItem['STATUS']."'";	
				
	$obj['SQL'].=" ORDER BY ID DESC";					
	   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}

function getPollingOption($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_polling_option WHERE 1=1";
    
    if(isset($objItem['ID'])&& $objItem['ID']!='')
        $obj['SQL'].=" AND ID='".$objItem['ID']."'";	
	 
	if(isset($objItem['POLLING_ID'])&& $objItem['POLLING_ID']!='')
        $obj['SQL'].=" AND POLLING_ID='".$objItem['POLLING_ID']."'";	
				
	$obj['SQL'].=" ORDER BY ID DESC";					
	   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}

//------------------------------TWITTER-------------------------------------------------
function getSocial($objItem){
	$objResult=null;
    
	$strSQL="SELECT * FROM tbl_social where STATUS='1' AND CATEGORY='".$objItem['SOCIAL_CATEGORY']."'";
	
    $objResult=DAOQuerySQL($strSQL);
	return $objResult;
    //return $strSQL;
	
}


//---------------------------------------RSS--------------------------------------------
function getRss(){
	$objResult=null;
    
	$strSQL="SELECT * FROM `tbl_article`  WHERE concat(tanggal,' ',`hour`) <= '".date('Y-m-d H:i:s')."' and Article_Status=1  order by concat(tanggal,' ',`hour`) desc LIMIT 100";
	
    $objResult=DAOQuerySQL($strSQL);
	return $objResult;
    //return $strSQL;
	
}



//------------------------------UTIL-------------------------------------------------
function tanggal($tanggal,$ftanggal)
{
$tgl=substr($tanggal,8,2);
$bulan=substr($tanggal,5,2);
$tahun=substr($tanggal,0,4);

$waktu=substr($tanggal,10,9);
if(strlen($waktu)>0){
$twaktu=explode(":",$waktu);
$jam=$twaktu[0];
$menit=$twaktu[1];
$detik=$twaktu[2];
if ($jam>24){
	$jam=$twaktu[0]-24;
}
//$waktu=$jam.':'.$menit.':'.$detik;
$waktu=$jam.':'.$menit;
}


$hari=date('w',mktime(0,0,0,$bulan,$tgl,$tahun));

switch ($hari) {
case 0: $hari="Minggu";
break;
case 1: $hari="Senin";
break;
case 2: $hari="Selasa";
break;
case 3: $hari="Rabu";
break;
case 4: $hari="Kamis";
break;
case 5: $hari="Jum'at";
break;
case 6: $hari="Sabtu";
break;
}

$bulanx='';
switch ($bulanx) {
case 1: $bulan="Januari";
break;
case 2: $bulan="Februari";
break;
case 3: $bulan="Maret";
break;
case 4: $bulan="April";
break;
case 5: $bulan="Mei";
break;
case 6: $bulan="Juni";
break;
case 7: $bulan="Juli";
break;
case 8: $bulan="Agustus";
break;
case 9: $bulan="September";
break;
case 10: $bulan="Oktober";
break;
case 11: $bulan="November";
break;
case 12: $bulan="Desember";
break;
}

	if ($ftanggal=="tipe1"){
		echo "$tgl $bulan $tahun";
	}else if($ftanggal=="tipe2"){
		echo "$hari, $tgl $bulan $tahun";    
	}else if($ftanggal=="tipe3"){
		echo "$hari, $tgl/$bulan/$tahun $waktu WIB";
	}else if($ftanggal=="tipe4"){
		echo "$hari, $tgl/$bulan/$tahun  $waktu WIB";
	}
}


function remote_file_exists($objVar) 
{ 
	
  //CHECK INI CONFIG 	
  if(ini_get('allow_url_fopen') == 0) 
  { 
    trigger_error('ERROR: allow_url_fopen is not enabled on this server', E_USER_WARNING); 
    return(FALSE); 
  }
  
  
  //CHECK IF IMAGE ALREADY EXIST
  if(($handle = @fopen($objVar['folder']."/thumb/".$objVar['name'], 'r'))&& ($handle = @fopen($objVar['folder']."/thumb/".$objVar['name'], 'r')) )
  { 
    fclose($handle); 
    return(TRUE); 
  }else{
  //COPY IMAGE WHEN NOT EXIST
  
	//------if not exist create folder using news date-----
	if(!file_exists($objVar['folder'])) mkdir($objVar['folder']); 		
	if(!file_exists($objVar['folder']."/thumb")) mkdir($objVar['folder']."/thumb/"); 		
	if(!file_exists($objVar['folder']."/view/")) mkdir($objVar['folder']."/view/");
		
	//-----copy thumbnail and view -------
	
	//CHECK SOURCE 1
	$vimgold=IMG_SOURCE;	
	
	
	$varold = array("[IDPIC]","[K]", "[MD5]", "[SIZE]");
	$varnew_thumb   = array($objVar['id'], $objVar['k'], $objVar['md5'], "thumb");
	$varnew_view   = array($objVar['id'], $objVar['k'], $objVar['md5'], "view");
	
	//build source url
	$varnew_thumb = str_replace($varold, $varnew_thumb,$vimgold);
	$varnew_view = str_replace($varold, $varnew_view,$vimgold);
			
	//Copy if source exist
	if($handle = @fopen($varnew_view, 'r'))
	{
	//copy original to local folder	
	copy($varnew_view, $objVar['folder']."/view/".$objVar['name']);
	//copy thumb to local folder
	copy($varnew_view, $objVar['folder']."/thumb/".$objVar['name']);			
	//resize view to thumb image
	create_thumbnail_preserve_ratio($objVar['folder']."/view/".$objVar['name'], $objVar['folder']."/thumb/".$objVar['name'], '250');		
	}	
  }  
  return(FALSE); 
}

function create_thumbnail_preserve_ratio($source, $destination, $thumbWidth)
{
    //$extension = get_image_extension($source);
	$extension = pathinfo($source, PATHINFO_EXTENSION);
    $size = getimagesize($source);
    $imageWidth  = $size[0];
    $imageHeight = $size[1];
	$newWidth  = 250;
	$newheight = 170;	
	
	
	
    if ($imageWidth > $thumbWidth || $imageHeight > $thumbWidth)
    {
        // Calculate the ratio
        $xscale = ($imageWidth/$thumbWidth);
        $yscale = ($imageHeight/$thumbWidth);
        $newWidth  = ($yscale > $xscale) ? round($imageWidth * (1/$yscale)) : round($imageWidth * (1/$xscale));
        $newHeight = ($yscale > $xscale) ? round($imageHeight * (1/$yscale)) : round($imageHeight * (1/$xscale));
		
		
		$newImage = imagecreatetruecolor($newWidth, $newHeight);

    switch ($extension)
    {
        case 'jpeg':
        case 'jpg':
            $imageCreateFrom = 'imagecreatefromjpeg';
            $store = 'imagejpeg';
            break;

        case 'png':
            $imageCreateFrom = 'imagecreatefrompng';
            $store = 'imagepng';
            break;

        case 'gif':
            $imageCreateFrom = 'imagecreatefromgif';
            $store = 'imagegif';
            break;

        default:
            return false;
    }

    $container = $imageCreateFrom($source);
    imagecopyresampled($newImage, $container, 0, 0, 0, 0, $newWidth, $newHeight, $imageWidth, $imageHeight);
    return $store($newImage, $destination);
    }else{
		//error_log("ukuran gambar kekecilan", 3, "/var/tmp/jurnas-debug.log");
	}

    
}


function getPhotos($objItem){	
	$image=null;		
	$objItem['k']='f';
	if($objItem['kf_id']<1){
		$objItem['id']=$objItem['kg_id'];
		$objItem['k']='g';
	}
	if(remote_file_exists($objItem)){
		$file_status=true;
		$image['VIEW']=IMG_URL.$objItem['folder']."/view/".$objItem['name'];	
		$image['THUMB']=IMG_URL.$objItem['folder']."/thumb/".$objItem['name'];										
	}

    return $image;
}


function getImage($objItem){
if($objItem['IMAGE_FROM']>0){
	$objVar['NAME']=$objItem['IMAGE'];
	$list=getImageFile($objVar);   
	$list=$list['RESULT'][0];
	$expImg=explode('.',$objItem['IMAGE']);
	if (strlen($objItem['IMAGE'])>0){
		$image['VIEW']=PHOTO_URL."/posts/".$list['ADDRESS_URL']."/".$expImg[0].'_1.'.$expImg[1];
		$image['THUMB']=PHOTO_URL."/posts/".$list['ADDRESS_URL']."/".$expImg[0].'_2.'.$expImg[1];
	}else{
		$image['VIEW']=PHOTO_URL."/images/noimage.png";
		$image['THUMB']=PHOTO_URL."/images/noimage.png";	
	}
}else{
	if (strlen($objItem['IMAGE'])>0){
		$image['VIEW']=PHOTO_URL."/foto_berita/".$objItem['IMAGE'];
		$image['THUMB']=PHOTO_URL."/foto_berita/".$objItem['IMAGE'];
		$image['ORI']=PHOTO_URL."/foto_berita/".$objItem['IMAGE'];
	}else{
		$image['VIEW']=PHOTO_URL."/images/noimage.png";
		$image['THUMB']=PHOTO_URL."/images/noimage.png";	
	}
}

return $image;
}


function getImageFile($objItem){
	$obj=null;
    
	$obj['SQL']="SELECT * FROM tbl_photos WHERE 1=1";
    
    if(isset($objItem['NAME'])&& $objItem['NAME']!='')
        $obj['SQL'].=" AND NAME='".$objItem['NAME']."'";	
				
	$obj['SQL'].=" ORDER BY ID DESC";					
	   
    $obj['RESULT']=DAOQuerySQL($obj['SQL']);
	return $obj;    
}

function selisihWaktu($waktu){
$waktulalu;
$twaktu=explode(":",$waktu);
$jam=$twaktu[0];
$menit=$twaktu[1];
if (($jam>0) and ($jam<=24)) {    	
	$waktulalu=intval($jam)." jam ".intval($menit)." menit yang lalu";		
}else if ($jam<1){
	$waktulalu=intval($menit)." menit yang lalu";		
}else{
	$waktulalu=floor($jam/24)." hari ".($jam%24)." jam yang lalu";
}

return $waktulalu;
}


function waktuPublish($waktu){
$waktupublish=$waktu;
$twaktu=explode(":",$waktu);
$jam=$twaktu[0];
$menit=$twaktu[1];
$detik=$twaktu[2];
if ($jam>24){
	$jam=$twaktu[0]-24;
}
$waktupublish=$jam.':'.$menit.':'.$detik;

return $waktupublish;
}

function doLog($text)
{
  // open log file
  $filename = "debug.log";
  $fh = fopen($filename, "a") or die("Could not open log file.");
  fwrite($fh, date("d-m-Y, H:i")." - $text\n") or die("Could not write file!");
  fclose($fh);
}


function numberOnly($text){
$string=null;
$string = preg_replace('/[^0-9]/', '', $text);

return $string;
}

function filterOnly($text){
$string=null;
$string = preg_replace('/[^a-zA-Z0-9 -@,.:{}!]+/', '', $text);

return $string;
}


function numbTextOnly($text){
$string=null;
$string = preg_replace('/[^a-zA-Z0-9 -]+/', '', $text);

return $string;
}


function allowOnly($text){
$string=null;
$search = array(";","<",">");  
$replace = array("","",""); 
					 
$string=str_replace($search, $replace, $text);		

return $string;
}

function extractVid($vidurl){
	$yturl='';
	$vd=explode("?v=",$vidurl);
	$yturl="http://www.youtube.com/embed/".$vd[1];
	return $yturl;
}

function getVidID($objItem){
	$yturl='';
	$vd=explode("?v=",$objItem['VIDEO']);
	$yturl=$vd[1];
	return $yturl;
}
		
function makeDInt($angka){
	if ($angka < -0.0000001)
	{
		return ceil($angka-0.0000001);
	}else { 
		return floor($angka+0.0000001); 
	}
}

function hijriah($tanggal)
{
$tgl=substr($tanggal,8,2);
$bulan=substr($tanggal,5,2);
$tahun=substr($tanggal,0,4);
$hari=date('w',mktime(0,0,0,$bulan,$tgl,$tahun));


switch ($hari) {
case 0: $hari="Al-Ahad";
break;
case 1: $hari="Al-Itsnain";
break;
case 2: $hari="Ats-tsulatsa";
break;
case 3: $hari="Al-Arbi'aa";
break;
case 4: $hari="Al-Khomis";
break;
case 5: $hari="Al-Jumuah";
break;
case 6: $hari="As-Sabt";
break;
}

	$array_bulan = array("Muharram", "Safar", "Rabiul Awwal", "Rabiul Akhir",
						 "Jumadil Awwal","Jumadil Akhir", "Rajab", "Sya'ban", 
						 "Ramadhan","Syawwal", "Zulqaidah", "Zulhijjah");
					 
$date = makeDInt(substr($tanggal,8,2));
$month = makeDInt(substr($tanggal,5,2));
$year = makeDInt(substr($tanggal,0,4));

if (($year>1582)||(($year == "1582") && ($month > 10))||(($year == "1582") && ($month=="10")&&($date >14)))
{
	$jd = makeDInt((1461*($year+4800+makeDInt(($month-14)/12)))/4)+
	makeDInt((367*($month-2-12*(makeDInt(($month-14)/12))))/12)-
	makeDInt( (3*(makeDInt(($year+4900+makeDInt(($month-14)/12))/100))) /4)+
	$date-32075; 
} 
else
{
	$jd = 367*$year-makeDInt((7*($year+5001+makeDInt(($month-9)/7)))/4)+
	makeDInt((275*$month)/9)+$date+1729777;
}

$wd = $jd%7;
$l = $jd-1948440+10632;
$n=makeDInt(($l-1)/10631);
$l=$l-10631*$n+354;
$z=(makeDInt((10985-$l)/5316))*(makeDInt((50*$l)/17719))+(makeDInt($l/5670))*(makeDInt((43*$l)/15238));
$l=$l-(makeDInt((30-$z)/15))*(makeDInt((17719*$z)/50))-(makeDInt($z/16))*(makeDInt((15238*$z)/43))+29;
$m=makeDInt((24*$l)/709);
$d=$l-makeDInt((709*$m)/24);
$y=30*$n+$z-30;

$g = $m-1;
$final = "$hari, $d $array_bulan[$g] $y H";

return $final;
}



function cleanParam($var){       
	$result=null;	
	$search = array("select","insert","update","union","delete",'concat','outfile',"'");      
	$replace = array("","","","","","","","`");
	$result=str_replace("\\","",(str_ireplace($search, $replace, $var)));				
	$result=strip_tags($result);		
	return $result;
}
?>
