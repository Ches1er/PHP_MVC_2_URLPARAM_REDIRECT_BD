<?php
/**
 * Created by PhpStorm.
 * User: mamedov
 * Date: 13.02.2019
 * Time: 19:39
 */

namespace core\db;
use core\Configurator;

class DBQueryBuilder
{
    private $dbh;

    private $query_parts = [
        "query_type"=>null,
        "where" => [],
        "having" => [],
        "order" => [],
        "groupby"=>[],
        "limit" => null,
        "offset" => null,
        "join" => [],
        "fields" => [],
        "table" => null,
        "values"=>[],
    ];

    /**
     * DBQueryBuilder constructor.
     */
    public function __construct($name = "notes")
    {
        $this->dbh = (new DBConnection($name))->createDBConnection();
    }

    //Prepare fields, values and statement

    private static function _field($f)
    {
        return "`" . str_replace('.', '`.`', $f) . "`";
    }
    private static function _value($f)
    {
        return "\"" .$f. "\"";
    }
    private function stmt($q){
        $stmt= $this->dbh->prepare($q);
        $stmt->execute();
    }
    private function isAggregate(string $field):bool{
        $pattern = '/.*\(.*\)/';
        return preg_match($pattern,$field);
    }

    //INSERT

    public function insert(string $table,array $fields,array $values):void{
        $quotes_fields = array_map(function ($f) {
            return self::_field($f);}, $fields);
        $quotes_values = array_map(function ($f) {
            return self::_value($f);}, $values);
        $fields = implode(", ",$quotes_fields);
        $values = implode(", ",$quotes_values);
        $q = "INSERT INTO `{$table}` ({$fields}) 
                VALUES ({$values})";
        $this->stmt($q);
    }


    //DELETE

    public function delete(string $table):DBQueryBuilder{
        $this->query_parts["table"]="`".$table."`";
        return $this;
    }

    public function execDel():void {
        $q = "DELETE FROM {$this->query_parts["table"]} WHERE ";
        foreach ($this->query_parts["where"] as $w){
            $q.=" {$w[0]} ";
            if(count($w)>1) $q.="({$w[1]} {$w[2]} {$w[3]})";
        }
        $this->stmt($q);
    }

    //UPDATE

    public function update(string $table,array $fields,array $values):DBQueryBuilder{
        $this->query_parts["table"]="`".$table."`";
        $this->query_parts["fields"]=array_map(function ($f) {
            return self::_field($f);}, $fields);
        $this->query_parts["values"]=array_map(function ($f) {
            return self::_value($f);}, $values);
        return $this;
    }

    public function execUpdate():void {
        $q = "UPDATE {$this->query_parts["table"]} SET ";
        // do assignment list field=value;
        for ($i=0;$i<count($this->query_parts["fields"]);$i++){
            $q.="{$this->query_parts["fields"][$i]}={$this->query_parts["values"][$i]}";
            if (!end($this->query_parts["fields"]))$q.=",";
        }
        //check where
        if(!empty($this->query_parts["where"])){
            $q.=" WHERE";
            foreach ($this->query_parts["where"] as $w){
                $q.=" {$w[0]} ";
                if(count($w)>1) $q.="({$w[1]} {$w[2]} {$w[3]})";
            }
        }
        $this->stmt($q);
    }

    //SELECT

    public function select(array $fields)
    {
        $this->query_parts["fields"] = array_map(function ($f) {
            if ($this->isAggregate($f))return $f;
            else return self::_field($f);
        }, $fields);
        return $this;
    }

    public function from(string $table){
        $this->query_parts["table"] = self::_field($table);
        return $this;
    }

    //WHERE

    private function _where($type,$field,$sign,$value,bool $native){
        if($value===null) {
            $value = $sign;
            $sign = "=";
        }
        if(!$native) $field = self::_field($field);
        if(!$native && $value[0]!="?" && $value[0]!=":" && !is_integer($value)) $value=$this->dbh->quote($value);
        $this->query_parts["where"][] = [$type,$field,$sign,$value];
    }

    public function where($field,$sign,$value=null,bool $native=false){
        $this->_where("",$field,$sign,$value,$native);
        return $this;
    }
    public function andWhere($field,$sign,$value=null,bool $native=false){
        $this->_where("AND",$field,$sign,$value,$native);
        return $this;
    }
    public function orWhere($field,$sign,$value=null,bool $native=false){
        $this->_where("OR",$field,$sign,$value,$native);
        return $this;
    }
    private function _groupWhere(callable $where,$type){
        if($type!=null) $this->query_parts["where"][]=[$type];
        $this->query_parts["where"][]=["("];
        $where($this);
        $this->query_parts["where"][]=[")"];
        return $this;
    }
    public function whereGroup(callable $where){
        return $this->_groupWhere($where,null);
    }
    public function andWhereGroup(callable $where){
        return $this->_groupWhere($where,"AND");
    }
    public function orWhereGroup(callable $where){
        return $this->_groupWhere($where,"OR");
    }

    //LIMIT and OFFSET

    public function limit(int $limit,int $offset=null){
        $this->query_parts["limit"]=$limit;
        $this->query_parts["offset"]=$offset;
        return $this;
    }

    //ORDER BY

    public function orderBY(string $column,string $direction=null){
        $this->query_parts["order"][]=$column;
        $this->query_parts["order"][]=$direction;
        return $this;
    }

    //GROUP BY

    public function groupBy(array $fields){
        $this->query_parts["groupby"]=implode(",",$fields);
        return $this;
    }

    //HAVING

    private function _having($type,$field,$sign,$value,bool $native){
        if($value===null) {
            $value = $sign;
            $sign = "=";
        }
        if(!$native) $field = self::_field($field);
        if(!$native && $value[0]!="?" && $value[0]!=":" && !is_integer($value)) $value=$this->dbh->quote($value);
        $this->query_parts["having"][] = [$type,$field,$sign,$value];
    }

    public function having($field,$sign,$value=null,bool $native=false){
        $this->_having("",$field,$sign,$value,$native);
        return $this;
    }
    public function andHaving($field,$sign,$value=null,bool $native=false){
        $this->_having("AND",$field,$sign,$value,$native);
        return $this;
    }
    public function orHaving($field,$sign,$value=null,bool $native=false){
        $this->_having("OR",$field,$sign,$value,$native);
        return $this;
    }
    private function _groupHaving(callable $where,$type){
        if($type!=null) $this->query_parts["having"][]=[$type];
        $this->query_parts["having"][]=["("];
        $where($this);
        $this->query_parts["having"][]=[")"];
        return $this;
    }
    public function havingGroup(callable $having){
        return $this->_groupHaving($having,null);
    }
    public function andHavingGroup(callable $having){
        return $this->_groupHaving($having,"AND");
    }
    public function orHavingGroup(callable $having){
        return $this->_groupHaving($having,"OR");
    }

    //SELECT builder

    private function buildSelect(){
        $fields = empty($this->query_parts["fields"])?"*":implode(", ",$this->query_parts["fields"]);

        $q = "SELECT {$fields} FROM {$this->query_parts["table"]}";

        //GROUP BY check

        if (!empty($this->query_parts["groupby"])){
            $q.=" GROUP BY {$this->query_parts["groupby"]}";

            //HAVING check
            if(!empty($this->query_parts["having"])){
                $q.=" HAVING";
                foreach ($this->query_parts["having"] as $h){
                    $q.=" {$h[0]} ";
                    if(count($h)>1) $q.="({$h[1]} {$h[2]} {$h[3]})";
                }
            }
        }

        //WHERE check

        if(!empty($this->query_parts["where"])){
            $q.=" WHERE";
            foreach ($this->query_parts["where"] as $w){
                $q.=" {$w[0]} ";
                if(count($w)>1) $q.="({$w[1]} {$w[2]} {$w[3]})";
            }
        }

        //ORDER BY check

        if (!empty($this->query_parts["order"])){
            $column = $this->query_parts["order"][0];
            $direction = $this->query_parts["order"][1];
            $q.=" ORDER BY {$column}";
            if(!is_null($direction))$q.=" {$direction}";
        }

        //LIMIT and OFFSET check

        if (!empty($this->query_parts["limit"])){
            $q.=" LIMIT {$this->query_parts["limit"]}";
            if (!is_null($this->query_parts["offset"]))$q.=" OFFSET {$this->query_parts["offset"]}";
        }
        echo $q;
        return $q;
    }

    //Get result from SELECT type query

    public function execSelect($data=[]):array{
        $q = $this->buildSelect();
        $stmt= $this->dbh->prepare($q);
        $stmt->execute($data);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

}
