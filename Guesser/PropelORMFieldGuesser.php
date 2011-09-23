<?php

namespace Admingenerator\GeneratorBundle\Guesser;

use Doctrine\Common\Util\Inflector;

use Symfony\Component\Locale\Exception\NotImplementedException;

use Symfony\Component\Form\Extension\Core\ChoiceList\ArrayChoiceList;


class PropelORMFieldGuesser
{

    private $cache = array();

    private $metadata = array();

    private static $current_class;


    protected function getMetadatas($class = null)
    {
        if ($class) {
            self::$current_class = $class;
        }

        return $this->getTable(self::$current_class);
    }

    public function getDbType($class, $fieldName)
    {
        if ( $relation = $this->getRelation($fieldName, $class)) {
            return \RelationMap::MANY_TO_ONE === $relation->getType() ? 'model' : 'collection';
        }

        return $this->getColumn($class, $fieldName)  ? $this->getColumn($class, $fieldName)->getType() : 'VARCHAR';
    }

    protected function getRelation($fieldName, $class = null)
    {
        $table = $this->getMetadatas($class);

        foreach ($table->getRelations() as $relation) {
            if (Inflector::classify($fieldName) == $relation->getName()) {
                return $relation;
            }
        }

        return false;
    }

    public function getPhpName($class, $fieldName)
    {
        $column = $this->getColumn($class, $fieldName);

        if ($column) {

            return $column->getPhpName();
        }
    }

    public function getFormType($dbType)
    {
        switch($dbType) {
            case \PropelColumnTypes::BOOLEAN:
            case \PropelColumnTypes::BOOLEAN_EMU:
                return 'checkbox';
            case \PropelColumnTypes::TIMESTAMP:
            case \PropelColumnTypes::BU_TIMESTAMP:
                return 'datetime';
            case \PropelColumnTypes::DATE:
            case \PropelColumnTypes::BU_DATE:
                return 'date';
            case \PropelColumnTypes::TIME:
                return 'time';
            case \PropelColumnTypes::FLOAT:
            case \PropelColumnTypes::REAL:
            case \PropelColumnTypes::DOUBLE:
            case \PropelColumnTypes::DECIMAL:
                return 'number';
            case \PropelColumnTypes::TINYINT:
            case \PropelColumnTypes::SMALLINT:
            case \PropelColumnTypes::INTEGER:
            case \PropelColumnTypes::BIGINT:
            case \PropelColumnTypes::NUMERIC:
                return 'integer';
            case \PropelColumnTypes::CHAR:
            case \PropelColumnTypes::VARCHAR:
                return 'text';
            case \PropelColumnTypes::LONGVARCHAR:
            case \PropelColumnTypes::BLOB:
            case \PropelColumnTypes::CLOB:
            case \PropelColumnTypes::CLOB_EMU:
                return 'textarea';
            case 'model':
                return 'model';
            case 'collection':
                return 'propel_double_list';
            default:
                throw new NotImplementedException('The dbType "'.$dbType.'" is not yet implemented');
        }
    }

    public function getFilterType($dbType)
    {
         switch($dbType) {
             case \PropelColumnTypes::BOOLEAN:
             case \PropelColumnTypes::BOOLEAN_EMU:
                return 'choice';
                break;
             case \PropelColumnTypes::TIMESTAMP:
             case \PropelColumnTypes::BU_TIMESTAMP:
             case \PropelColumnTypes::DATE:
             case \PropelColumnTypes::BU_DATE:
                return 'date_range';
                break;
             case 'collection':
                return 'model';
                break;
         }

         return $this->getFormType($dbType);
    }

    public function getFormOptions($formType, $dbType, $columnName)
    {
        if (\PropelColumnTypes::BOOLEAN == $dbType || \PropelColumnTypes::BOOLEAN_EMU == $dbType) {
            return array('required' => false);
        }

        if ('model' == $formType) {
            $relation = $this->getRelation($columnName);
            if ($relation) {
                if (\RelationMap::MANY_TO_ONE === $relation->getType()) {
                    return array('class' => $relation->getForeignTable()->getClassname(), 'multiple' => false);
                } else { // Many to many

                    return array('class' => $relation->getLocalTable()->getClassname(), 'multiple' => false);
                }
            }
        }

        if ('propel_double_list' == $formType) {
            $relation = $this->getRelation($columnName);
            if ($relation) {
                if (\RelationMap::MANY_TO_ONE === $relation->getType()) {
                    return array('class' => $relation->getForeignTable()->getClassname());
                } else { // Many to many

                    return array('class' => $relation->getLocalTable()->getClassname());
                }
            }
        }

        return array('required' => $this->isRequired($columnName));
    }

    protected function isRequired($fieldName)
    {
        if ($column = $this->getColumn(self::$current_class, $fieldName)) {
            return $column->isNotNull();
        }

        return false;
    }

    public function getFilterOptions($formType, $dbType, $ColumnName)
    {
        $options = array('required' => false);

        if (\PropelColumnTypes::BOOLEAN == $dbType || \PropelColumnTypes::BOOLEAN_EMU == $dbType)
        {
            $choices = new ArrayChoiceList(array(
                    0 => 'No',
                    1 => 'Yes'
                    ));

           $options['choice_list'] = $choices;
           $options['empty_value'] = 'Yes or No';
        }

         if ('model' == $dbType) {
             return array_merge($this->getFormOptions($formType, $dbType, $ColumnName), $options);
         }

        if ('collection' == $dbType) {
             return array_merge($this->getFormOptions($formType, $dbType, $ColumnName), $options, array('multiple'=>false));
         }

        return $options;
    }


    protected function getTable($class)
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        if (class_exists($queryClass = $class.'Query')) {
            $query = new $queryClass();

            return $this->cache[$class] = $query->getTableMap();
        }

        throw new \LogicException('Can\'t find query class '.$queryClass);
    }

    protected function getColumn($class, $property)
    {
        if (isset($this->cache[$class.'::'.$property])) {
            return $this->cache[$class.'::'.$property];
        }

        $table = $this->getTable($class);

        if ($table && $table->hasColumn($property)) {
            return $this->cache[$class.'::'.$property] = $table->getColumn($property);
        }
    }

}
