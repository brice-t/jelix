<?php
/**
* @package    jelix
* @subpackage dao
* @version    $Id:$
* @author     Croes G�rald, Laurent Jouanneau
* @contributor Laurent Jouanneau
* @copyright  2001-2005 CopixTeam, 2005-2006 Laurent Jouanneau
* @link        http://www.jelix.org
* @licence  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
*
* Une partie du code est issue de la classe CopixDAODefinitionV1
* du framework Copix 2.3dev20050901. http://www.copix.org
* il est sous Copyright 2001-2005 CopixTeam (licence LGPL)
* Auteurs initiaux : Gerald Croes et Laurent Jouanneau
* Adapt�e et am�lior�e pour Jelix par Laurent Jouanneau
*/

/**
 * extract datas from a dao xml content
 * @package  jelix
 * @subpackage dao
 * @see jDaoCompiler
 */
class jDaoParser {
    /**
    * the properties list.
    * keys = field code name
    * values = jDaoProperty
    */
    private $_properties = array ();

    /**
    * all tables with their properties, and their own fields
    * keys = table code name
    * values = array()
    *          'name'=> table code name, 'realname'=>'real table name',
    *          'primarykey'=> attribute , 'pk'=> primary keys list
    *          'onforeignkey'=> attribute, 'fk'=> foreign keys list
    *          'fields'=>array(list of field code name)
    */
    private $_tables = array();

    /**
    * primary table code name
    */
    private $_primaryTable = '';

    /**
    * code name of foreign table with a outer join
    * @var array  of table code name
    */
    private $_ojoins = array ();

    /**
    * code name of foreign table with a inner join
    * @var array  of array(table code name, 0)
    */
    private $_ijoins = array ();

    /**
     * @var array list of jDaoMethod objects
     */
    private $_methods = array();

    /**
    * Constructor
    */
    function __construct(){
    }

    /**
    * parse a dao xml content
    * @param SimpleXmlElement $xml
    * @param int $debug  for debug only 0:parse all, 1:parse only datasource+record, 2;parse only datasource
    */
    public function parse( $xml, $debug=0){
        // -- tables
        if(isset ($xml->datasources) && isset ($xml->datasources[0]->primarytable)){
            $t = $this->_parseTable (0, $xml->datasources[0]->primarytable[0]);
            $this->_primaryTable = $t['name'];
            if(isset($xml->datasources[0]->primarytable[1])){
               throw new jDaoXmlException ('table.two.many');
            }
            foreach($xml->datasources[0]->foreigntable as $table){
                $this->_parseTable (1, $table);
            }
            foreach($xml->datasources[0]->optionalforeigntable as $table){
                $this->_parseTable (2, $table);
            }
        }else{
            throw new jDaoXmlException ('datasource.missing');
        }

        if($debug == 2) return;

        //add the record properties
        if(isset($xml->record) && isset($xml->record[0]->property)){
            foreach ($xml->record[0]->property as $prop){
            $p = new jDaoProperty ($prop->attributes(), $this);
            $this->_properties[$p->name] = $p;
            $this->_tables[$p->table]['fields'][] = $p->name;
            }
        }else
            throw new jDaoXmlException ('properties.missing');

        if($debug == 1) return;

        // get additionnal methods definition
        if(isset ($xml->factory) && isset ($xml->factory[0]->method)){
            foreach($xml->factory[0]->method as $method){
                $m = new jDaoMethod ($method, $this);
                if(isset ($this->_methods[$m->name])){
                    throw new jDaoXmlException ('method.duplicate',$m->name);
                }
                $this->_methods[$m->name] = $m;
            }
        }
    }

   /**
   * parse a join definition
   */
   private function _parseTable ($typetable, $tabletag){
      $infos = $this->getAttr($tabletag, array('name','realname','primarykey','onforeignkey'));

      if ($infos['name'] === null )
         throw new jDaoXmlException ('table.name');

      if($infos['realname'] === null)
         $infos['realname'] = $infos['name'];

      if($infos['primarykey'] === null)
          throw new jDaoXmlException ('primarykey.missing');

      $infos['pk']=explode(',',$infos['primarykey']);
      unset($infos['primarykey']);

      if(count($infos['pk']) == 0 || $infos['pk'][0] == '')
          throw new jDaoXmlException ('primarykey.missing');

      if($typetable){ // pour les foreigntable et optionalforeigntable
          if($infos['onforeignkey'] === null)
             throw new jDaoXmlException ('foreignkey.missing');
          $infos['fk']=explode(',',$infos['onforeignkey']);
          unset($infos['onforeignkey']);
          if(count($infos['fk']) == 0 || $infos['fk'][0] == '')
             throw new jDaoXmlException ('foreignkey.missing');
          if(count($infos['fk']) != count($infos['pk']))
             throw new jDaoXmlException ('foreignkey.missing');
          if($typetable == 1){
             $this->_ijoins[]=$infos['name'];
          }else{
             $this->_ojoins[]=array($infos['name'],0);
          }
      }else{
          unset($infos['onforeignkey']);
      }

      $infos['fields'] = array ();
      $this->_tables[$infos['name']] = $infos;

      return $infos;
   }

    public function getAttr($tag, $requiredattr){
       $res=array();
       foreach($requiredattr as $attr){
          if(isset($tag[$attr]) && trim((string)$tag[$attr]) != '')
             $res[$attr]=(string)$tag[$attr];
          else
             $res[$attr]=null;
       }
       return $res;
    }

    /**
    * just a quick way to retrieve boolean values from a string.
    *  will accept yes, true, 1 as "true" values
    *  all other values will be considered as false.
    * @return boolean true / false
    */
    public function getBool ($value) {
        return in_array (trim ($value), array ('true', '1', 'yes'));
    }

    public function getProperties () { return $this->_properties; }
    public function getTables(){  return $this->_tables;}
    public function getPrimaryTable(){  return $this->_primaryTable;}
    public function getMethods(){  return $this->_methods;}
    public function getOuterJoins(){  return $this->_ojoins;}
    public function getInnerJoins(){  return $this->_ijoins;}
}



/**
 * objet comportant les donn�es d'une propri�t� d'un record DAO
 * @package  jelix
 * @subpackage dao
 */

class jDaoProperty {
    /**
    * the name of the property of the object
    */
    public $name = '';

    /**
    * the name of the field in table
    */
    public $fieldName = '';

    /**
    * give the regular expression that needs to be matched against.
    * @var string
    */
    public $regExp = null;

    /**
    * says if the field is required.
    * @var boolean
    */
    public $required = false;

    /**
    * Says if it's a primary key.
    * @var boolean
    */
    public $isPK = false;

    /**
    * Says if it's a foreign key
    * @var boolean
    */
    public $isFK = false;

    public $datatype;

    public $table=null;
    public $updateMotif='%s';
    public $insertMotif='%s';
    public $selectMotif='%s';
    public $sequenceName='';

    /**
    * the maxlength of the key if given
    * @var int
    */
    public $maxlength = null;
    public $minlength = null;

    public $ofPrimaryTable = true;

    /**
    * constructor.
    */
    function __construct ($params, $def){
        $needed = array('name', 'fieldname', 'table', 'datatype', 'required', 'minlength',
        'maxlength', 'regexp', 'sequence', 'updatemotif', 'insertmotif', 'selectmotif');

        $params = $def->getAttr($params, $needed);

        if ($params['name']===null){
            throw new jDaoXmlException ('missing.attr', array('name', 'property'));
        }
        $this->name       = $params['name'];
        $this->fieldName  = $params['fieldname'] !==null ? $params['fieldname'] : $this->name;
        $this->table      = $params['table'] !==null ? $params['table'] : $def->getPrimaryTable();

        $tables = $def->getTables();

        if(!isset( $tables[$this->table])){
            throw new jDaoXmlException ('property.unknow.table', $this->name);
        }

        $this->required   = $def->getBool ($params['required']);
        $this->maxlength  = $params['maxlength'] !== null ? intval($params['maxlength']) : null;
        $this->minlength  = $params['minlength'] !== null ? intval($params['minlength']) : null;
        $this->regExp     = $params['regexp'];


        $this->isPK = in_array($this->fieldName, $tables[$this->table]['pk']);
        if(!$this->isPK){
           $this->isFK = isset($tables[$this->table]['fk'][$this->fieldName]);
        }

        if ($params['datatype']===null){
            throw new jDaoXmlException ('missing.attr', array('datatype', 'property'));
        }
        $params['datatype']=trim(strtolower($params['datatype']));
        $this->needsQuotes = in_array ($params['datatype'], array ('string', 'date', 'datetime', 'time'));

        if (!in_array ($params['datatype'], array ('autoincrement', 'bigautoincrement', 'int', 'datetime', 'time',
                                    'integer', 'varchar', 'string', 'varchardate', 'date', 'numeric', 'double', 'float'))){
           throw new jDaoXmlException ('wrong.attr', array($params['datatype'], $this->fieldName));
        }
        $this->datatype = strtolower($params['datatype']);

        if(($this->datatype == 'autoincrement' || $this->datatype == 'bigautoincrement')
           && $params['sequence'] !==null){
            $this->sequenceName = $params['sequence'];
        }

        // on ignore les attributs *motif sur les champs PK et FK
        if(!$this->isPK && !$this->isFK){
            $this->updateMotif= $params['updatemotif']!==null ? $params['updatemotif'] :'%s';
            $this->insertMotif= $params['insertmotif']!==null ? $params['insertmotif'] :'%s';
            $this->selectMotif= $params['selectmotif']!==null ? $params['selectmotif'] :'%s';
        }

        // pas de motif update et insert pour les champs des tables externes
        if($this->table != $def->getPrimaryTable()){
            $this->updateMotif = '';
            $this->insertMotif = '';
            $this->required = false;
            $this->ofPrimaryTable = false;
        }else{
            $this->ofPrimaryTable=true;
        }
    }
}




//--------------------------------------------------------
/**
 * objet d�crivant une m�thode DAO
 * @package  jelix
 * @subpackage dao
 */
class jDaoMethod {
   public $name;
   public $type;
   public $distinct='';
   private $_conditions = null;
   private $_parameters   = array();
   private $_parametersDefaultValues = array();
   private $_limit = null;
   private $_values = array();
   private $_def = null;
   private $_procstock=null;
   private $_body=null;

   function __construct ($method, $def){
      $this->_def = $def;

      $params = $def->getAttr($method, array('name', 'type', 'call','distinct'));

      if ($params['name']===null){
         throw new jDaoXmlException  ('missing.attr', array('name', 'method'));
      }

      $this->name  = $params['name'];
      $this->type  = $params['type'] ? strtolower($params['type']) : 'select';

      if (isset ($method->parameter)){
         foreach ($method->parameter as $param){
            $attr = $param->attributes();

            if (!isset ($attr['name'])){
                  throw new jDaoXmlException ('method.parameter.unknowname', array($this->name));
            }
            $this->_parameters[]=(string)$attr['name'];
            if (isset ($attr['default'])){
               $this->_parametersDefaultValues[(string)$attr['name']]=(string)$attr['default'];
            }
         }
      }

      if($this->type == 'sql'){
         if($params['call'] === null){
            throw new jDaoXmlException  ('method.procstock.name.missing');
         }
         $this->_procstock=$params['call'];
         return;
      }

      if($this->type == 'php'){
         if (isset ($method->body)){
            $this->_body = (string)$method->body;
         }else{
            throw new jDaoXmlException  ('method.body.missing');
         }
         return;
      }

      $this->_conditions = new jDaoConditions();
      if (isset ($method->conditions)){
         $this->_parseConditions($method->conditions[0],false);
      }

      if($this->type == 'update'){
         if(isset($method->values) && isset($method->values[0]->value)){
            foreach ($method->values[0]->value as $val){
               $this->_addValue($val);
            }
         }else{
               throw new jDaoXmlException ('method.values.undefine',array($this->name));
         }
         return;
      }

      if(($this->type == 'select' || $this->type == 'count') && strlen($params['distinct'])){
        $props = $this->_def->getProperties();

        if (!isset ($props[$params['distinct']])){
            throw new jDaoXmlException ('method.property.unknown', array($this->name, $params['distinct']));
        }
        $this->distinct=$params['distinct'];
      }

      if($this->type == 'count')
         return;

      if (isset ($method->order) && isset($method->order[0]->orderitem)){
         foreach($method->order[0]->orderitem as $item){
            $this->_addOrder ($item);
         }
      }

      if (isset($method->limit)){
         if(isset($method->limit[1])){
               throw new jDaoXmlException ('tag.duplicate', array('limit', $this->name));
         }
         if($this->type == 'select' || $this->type == 'selectfirst'){
            $this->_addLimit($method->limit[0]);
         }else{
            throw new jDaoXmlException ('method.limit.forbidden', $this->name);
         }
      }
   }

   public function getConditions (){ return $this->_conditions;}
   public function getParameters (){ return $this->_parameters;}
   public function getParametersDefaultValues (){ return $this->_parametersDefaultValues;}
   public function getLimit (){ return $this->_limit;}
   public function getValues (){ return $this->_values;}
   public function getProcStock (){ return $this->_procstock;}
   public function getBody (){ return $this->_body;}

    private function _parseConditions($conditions, $subcond=true){
        if (isset ($conditions['logic'])){
            $kind = strtoupper((string)$conditions['logic']);
        }else{
            $kind = 'AND';
        }

        if ($subcond){
            $this->_conditions->startGroup ($kind);
        }else{
            $this->_conditions->condition->glueOp =$kind;
        }

        foreach($conditions->children() as $op=>$cond){
            if($op !='conditions')
                $this->_addCondition ($op,$cond);
            else
                $this->_parseConditions ($cond);
        }

        if ($subcond) {
            $this->_conditions->endGroup();
        }

    }


    /*
      <eq         property="foo" value="" expr=""/>
      <noteq      property="foo" value="" expr=""/>
      <lt         property="foo" value="" expr=""/>
      <gt         property="foo" value="" expr=""/>
      <lteq       property="foo" value="" expr=""/>
      <gteq       property="foo" value="" expr=""/>
      <in         property="foo" value="" expr=""/>
      <notin      property="foo" value="" expr=""/>
      <between    property="foo" min="" max="" exprmin="" exprmax=""/>
      <notbetween property="foo" min="" max="" exprmin="" exprmax=""/>
      <isnull     property="foo"/>
      <notisnull  property="foo"/>
    */


   private $_op = array('eq'=>'=', 'neq'=>'<>', 'lt'=>'<', 'gt'=>'>', 'lteq'=>'<=', 'gteq'=>'>=',
        'like'=>'LIKE', 'isnull'=>'IS NULL', 'isnotnull'=>'IS NOT NULL','in'=>'IN', 'notin'=>'NOT IN',
        'binary_op'=>'dummy');
      // 'between'=>'BETWEEN',  'notbetween'=>'NOT BETWEEN',

   private $_attrcond = array('property', 'value', 'expr', 'operator', 'driver'); //, 'min', 'max', 'exprmin', 'exprmax'

   private function _addCondition($op, $cond){

      $attr = $this->_def->getAttr($cond, $this->_attrcond);

      $field_id = ($attr['property']!==null? $attr['property']:'');

      if(!isset($this->_op[$op])){
         throw new jDaoXmlException ('method.condition.unknown', array($this->name, $op));
      }

      $operator = $this->_op[$op];

      $props = $this->_def->getProperties();

      if (!isset ($props[$field_id])){
         throw new jDaoXmlException ('method.property.unknown', array($this->name, $field_id));
      }

      if($this->type=='update'){
         if($props[$field_id]->table != $this->_def->getPrimaryTable()){
            throw new jDaoXmlException ('method.property.forbidden', array($this->name, $field_id));
         }
      }

      if($attr['value']!==null && $attr['expr']!==null){
         throw new jDaoXmlException ('method.condition.valueexpr.together', array($this->name, $op));
      }else if($attr['value']!==null){
         if($op == 'isnull' || $op =='isnotnull'){
            throw new jDaoXmlException ('method.condition.valueexpr.notallowed', array($this->name, $op,$field_id));
         }
         if($op == 'binary_op') {
            if (!isset($attr['operator']) || empty($attr['operator'])) {
                throw new jDaoXmlException ('method.condition.operator.missing', array($this->name, $op,$field_id));
            }
            if (isset($attr['driver']) && !empty($attr['driver'])) {
                if (jDaoCompiler::$dbDriver != $attr['driver']) {
                    throw new jDaoXmlException ('method.condition.driver.notallowed', array($this->name, $op,$field_id));
                }
            }
            $operator = $attr['operator'];
         }
         $this->_conditions->addCondition ($field_id, $operator, $attr['value']);
      }else if($attr['expr']!==null){
         if($op == 'isnull' || $op =='isnotnull'){
            throw new jDaoXmlException ('method.condition.valueexpr.notallowed', array($this->name, $op, $field_id));
         }
         if(($op == 'in' || $op =='notin')&& !preg_match('/^\$[a-zA-Z0-9_]+$/', $attr['expr'])){
            throw new jDaoXmlException ('method.condition.innotin.bad.expr', array($this->name, $op, $field_id));
         }
         if($op == 'binary_op') {
            if (!isset($attr['operator']) || empty($attr['operator'])) {
                throw new jDaoXmlException ('method.condition.operator.missing', array($this->name, $op,$field_id));
            }
            if (isset($attr['driver']) && !empty($attr['driver'])) {
                if (jDaoCompiler::$dbDriver != $attr['driver']) {
                    throw new jDaoXmlException ('method.condition.driver.notallowed', array($this->name, $op,$field_id));
                }
            }
            $operator = $attr['operator'];
         }
         $this->_conditions->addCondition ($field_id, $operator, $attr['expr'], true);
      }else{
          if($op != 'isnull' && $op !='isnotnull'){
              throw new jDaoXmlException ('method.condition.valueexpr.missing', array($this->name, $op, $field_id));
          }
      }
   }

   private function _addOrder($order){
      $attr = $this->_def->getAttr($order, array('property','way'));

      $way  = ($attr['way'] !== null ? $attr['way']:'ASC');

      if(substr ($way,0,1) == '$'){
         if(!in_array (substr ($way,1),$this->_parameters)){
            throw new jDaoXmlException ('method.orderitem.parameter.unknow', array($this->name, $way));
         }
      }

      if ($attr['property'] != ''){
          $prop =$this->_def->getProperties();
         if(isset($prop[$attr['property']])){
               $this->_conditions->addItemOrder($attr['property'], $way);
         }else{
               throw new jDaoXmlException ('method.orderitem.bad', array($attr['property'], $this->name));
         }
      }else{
         throw new jDaoXmlException ('method.orderitem.property.missing', array($this->name));
      }




   }

   private function _addValue($attr){
      $attr = $this->_def->getAttr($attr, array('property','value','expr'));

      $prop = $attr['property'];
      $props =$this->_def->getProperties();

      if ($prop === null){
         throw new jDaoXmlException ('method.values.property.unknow', array($this->name, $prop));
         return false;
      }

      if(!isset($props[$prop])){
         throw new jDaoXmlException ('method.values.property.unknow', array($this->name, $prop));
         return false;
      }

      if($props[$prop]->table != $this->_def->getPrimaryTable()){
         throw new jDaoXmlException ('method.values.property.bad', array($this->name,$prop ));
         return false;
      }
      if($props[$prop]->isPK){
         throw new jDaoXmlException ('method.values.property.pkforbidden', array($this->name,$prop ));
         return false;
      }

      if($attr['value']!==null && $attr['expr']!==null){
         throw new jDaoXmlException ('method.values.valueexpr', array($this->name, $prop));
      }else if($attr['value']!==null){
         $this->_values [$prop]= array( $attr['value'], false);
      }else if($attr['expr']!==null){
         $this->_values [$prop]= array( $attr['expr'], true);
      }

   }

   private function _addLimit($limit){
      $attr = $this->_def->getAttr($limit, array('offset','count'));

      extract($attr);

      if( $offset === null){
         throw new jDaoXmlException ('missing.attr',array('offset','limit'));
      }
      if($count === null){
         throw new jDaoXmlException ('missing.attr',array('count','limit'));
      }

      if(substr ($offset,0,1) == '$'){
         if(in_array (substr ($offset,1),$this->_parameters)){
            $offsetparam=true;
         }else{
            throw new jDaoXmlException ('method.limit.parameter.unknow', array($this->name, $offset));
         }
      }else{
         if(is_numeric ($offset)){
            $offsetparam=false;
            $offset = intval ($offset);
         }else{
            throw new jDaoXmlException ('method.limit.badvalue', array($this->name, $offset));
         }
      }

      if(substr ($count,0,1) == '$'){
         if(in_array (substr ($count,1),$this->_parameters)){
            $countparam=true;
         }else{
            throw new jDaoXmlException ('method.limit.parameter.unknow', array($this->name, $count));
         }
      }else{
         if(is_numeric($count)){
            $countparam=false;
            $count=intval($count);
         }else{
            throw new jDaoXmlException ('method.limit.badvalue', array($this->name, $count));
         }
      }
      $this->_limit= compact('offset', 'count', 'offsetparam','countparam');
   }
}
?>
