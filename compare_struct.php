<pre><?php

/**
 * @author : Uday Shiwakoti
 *
 * @created_date  : 7th-May-2013
 * @modified_date  : 7th-May-2013
 * @package : DbCompare
 * @version : 0.01
 * 
 * This class will generate the script of mysql after comparing two database.
 * This is just my start but there are lots of places we can improve and make robost script.
 * 
 * @license GPL v3 http://www.gnu.org/licenses/quick-guide-gplv3.html
 * 
 * @todo :
 *    find keys and index of table and generate script.
 *    
 * 
 */

class DbCompare{

private $master_db=NULL;
private $child_db=NULL;
private $conn;

    function __construct($host,$user,$pass,$master_db,$child_dbs){
            $this->master_db=$master_db;
            $this->child_db=$child_dbs;
            
            $this->conn=  mysql_connect($host,$user,$pass) or die('Unable to connect database');
            mysql_select_db($master_db,$this->conn);

    }


    function getTables(){
        $sql='SHOW TABLES';
        $result=  mysql_query($sql);
        
        $output=array();
        while($row=  mysql_fetch_array($result,MYSQL_NUM)){
            $output[]=$row[0];    
        }
        return $output;
    }   
    
    function get_table_desc($table_name){
         $output=array();
        $sql='DESC '.$table_name;
        
        $result=  mysql_query($sql);
        while($row=  mysql_fetch_array($result,MYSQL_ASSOC)){
            $output[$row['Field']]=$row;    
        }
        return $output;
    }

   
    
    function compare_table($table_name=NULL){
        $output=array();
        $m_output=$this->get_table_desc($this->master_db.'.'.$table_name);
        $c_output=$this->get_table_desc($this->child_db.'.'.$table_name);
        
      
        $output['SRC_DB']=$this->master_db;
        $output['DST_DB']=$this->child_db;
        $output['TABLE']=$table_name;
        
        if(count($c_output)==0){
            $output['RESULT']='NO_TABLE_FOUND';
            $output['FIELDS']=$m_output;
        }else
           $output['RESULT']='FIELD_CHANGE';
         
        
        
        
        
        
        
        if($output['RESULT']=='FIELD_CHANGE'){
                $compare_diff=array();
                
                
                foreach($m_output as $k=>$v){
                   $src_compare=$v;

                   $tar_compare=$c_output[$k];
                   if(!isset($c_output[$k])){
                        $compare_diff['ADD'][$k]= $src_compare;
                   }else{
                       $diff_val=array_diff($src_compare, $tar_compare);
                        if(count($diff_val)>0){
                            $compare_diff['CHANGE'][$k]= $src_compare;
                        }
                   }

                }
                
                 foreach($c_output as $k=>$v){
                        if(!isset($m_output[$k]))
                            $compare_diff['REMOVE'][$k]= $v;
                }
                
                if(count($compare_diff)>0)
                    $output['COMPARE_DIFF']=$compare_diff;
                else
                    $output['RESULT']='NO_CHANGE';
        }
        
       return  $output;
    }

    function printAllCreateAlterAddSql(){
        
            $tables=$this->getTables();
            foreach($tables as $table){
                $table_data=$this->compare_table($table);

                $sql=NULL;
                if($table_data['RESULT']=='NO_CHANGE'){
                    continue;
                }elseif(isset($table_data['COMPARE_DIFF'])){

                    if(isset($table_data['COMPARE_DIFF']['ADD'])){
                        foreach($table_data['COMPARE_DIFF']['ADD'] as $k=>$row){
                        $sql.='ALTER TABLE '.$table_data['TABLE'].' ADD COLUMN `'.$k.'` '.$row['Type'];

                            if($row['Null']=='NO')
                                $sql.=' NOT NULL';

                            if(trim($row['Default'])!='')
                                $sql.=" DEFAULT '".$row['Default']."'";

                           $sql.=";\n"; 
                        }

                      #  echo $sql.";\n";
                    }else if(isset($table_data['COMPARE_DIFF']['CHANGE'])){
                        foreach($table_data['COMPARE_DIFF']['CHANGE'] as $k=>$row){

                        $sql='ALTER TABLE '.$table_data['TABLE'].' CHANGE `'.$k.'` `'.$k.'` '.$row['Type'];

                            if($row['Null']=='NO')
                                $sql.=' NOT NULL';

                                if(trim($row['Default'])!='')
                                    $sql.=" DEFAULT '".$row['Default']."'";

                            $sql.=";\n";    
                        }

                       # echo $sql.";\n";
                    }


                }elseif($table_data['RESULT']=='NO_TABLE_FOUND'){
                   # print_r($table_data);
                     $sql='CREATE TABLE '.$table_data['TABLE'].' ( ';
                     $prefix=" \n\t";
                     foreach($table_data['FIELDS'] as $k=>$row){
                         $dt=$row['Type'];
                        # $dt=  str_replace('unsigned', '',strtolower($dt));
                        $sql.=$prefix.'`'.$k.'` '.$dt;

                        if($row['Null']=='NO')
                            $sql.=' NOT NULL';
                          if(trim($row['Default'])!='')
                                $sql.=" DEFAULT '".$row['Default']."'";

                        $prefix="\n\t ,";
                        }
                       $sql.="\n);\n";

                }
                echo $sql."\n\n";
            
            }
        
    }
    
    
    function printDropSql(){
        
         $tables=$this->getTables();


             foreach($tables as $table){
                $table_data=$this->compare_table_remove($table);
                $sql=NULL;
               # print_r($table_data);
                if($table_data['RESULT']=='NO_TABLE_FOUND'){
                     $sql.='DROP TABLE IF EXISTS `'.$table_data['TABLE'].'`';
                     $sql.=";\n"; 

                }elseif(isset($table_data['COMPARE_DIFF']['REMOVE'])){

                    foreach($table_data['COMPARE_DIFF']['REMOVE'] as $k=>$v){
                       $sql="ALTER TABLE  `".$table_data['TABLE']."` DROP `".$k."`";
                       $sql.=";\n"; 
                    }
                }else{
                    continue;
                }
                echo $sql."\n\n";                       



            }
        
    }
    
     function compare_table_remove($table_name){
        $output=array();
        $m_output=$this->get_table_desc($this->master_db.'.'.$table_name);
        $c_output=$this->get_table_desc($this->child_db.'.'.$table_name);
      
        $output['SRC_DB']=$this->master_db;
        $output['DST_DB']=$this->child_db;
        $output['TABLE']=$table_name;
        
          if(count($c_output)==0){
            $output['RESULT']='NO_TABLE_FOUND';
          }else{
              
                 foreach($m_output as $k=>$v){
                     if(!isset($c_output[$k])){
                           $output['COMPARE_DIFF']['REMOVE'][$k]=$k;
                     }

             
                }
              
          }
        
        
          return $output;
        
    }
}






$host='localhost';
$user='root';
$pass='root';
$master_db='master_database';
$child_db='child_database';
$obj=new DbCompare($host,$user,$pass,$master_db,$child_db);
$obj->printAllCreateAlterAddSql();
$obj2=new DbCompare($host,$user,$pass,$child_db,$master_db);
$obj2->printDropSql();

