<?php

/**
 *
 * @author    Thomas Lundquist <thomasez@redpill-linpro.com>
 * @copyright 2011 Thomas Lundquist
 * @license   http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 *
 */

namespace RedpillLinpro\GamineBundle\Manager;

abstract class BaseManager
{
    /*
     * Remember to put these in the Manages extending this one.
     * Right now they are all the same but I define different names here.
     * Or rather, they have to be defined in the object extending this one.
     */

    /**
     * @var \RedpillLinpro\GamineBundle\Gamine
     */
    protected $gamine_service;
    
    /**
     * @var \RedpillLinpro\GamineBundle\Services\ServiceInterface
     */
    protected $access_service;

    /**
     * @var \Doctrine\Common\Annotations\AnnotationReader
     */
    protected static $_reader = null;

    /**
     * @var \ReflectionClass
     */
    protected $_reflectedclass = null;
    
    protected $collection_resource;
    protected $entity_resource;

    protected $model;
    protected $_id_property = null;
    protected $_id_column = null;
    protected $_data_array_identifiable = null;

    /**
     * Get an Annotationn reader object
     *
     * @return \Doctrine\Common\Annotations\AnnotationReader
     */
    public static function getAnnotationsReader()
    {
        if (self::$_reader === null) {
            self::$_reader = new \Doctrine\Common\Annotations\AnnotationReader(new \Doctrine\Common\Cache\ArrayCache());
            self::$_reader->setEnableParsePhpImports(true);
            self::$_reader->setDefaultAnnotationNamespace('RedpillLinpro\\GamineBundle\\Annotations\\');
        }
        return self::$_reader;
    }

    public function __construct($access_service, $gamine_service)
    {
        $this->access_service = $access_service;
        $this->gamine_service = $gamine_service;
        $rc = new \ReflectionClass(get_called_class());
        $resource_annotation = $this->getResourceAnnotation($rc);
        if ($resource_annotation instanceof \RedpillLinpro\GamineBundle\Annotations\Resources) {
            if ($resource_annotation->collection) {
                $this->collection_resource = $resource_annotation->collection;
            }
            if ($resource_annotation->entity) {
                $this->entity_resource = $resource_annotation->entity;
            }
        }
        $model_annotation = $this->getModelAnnotation($rc);
        if ($model_annotation instanceof \RedpillLinpro\GamineBundle\Annotations\Model) {
            if ($model_annotation->name) {
                $this->model = $model_annotation->name;
            }
        }
    }

    /**
     * Get a reflection class object valid for this static class, so we don't
     * have to instantiate a new one for each instance with the overhead that
     * comes with it
     * 
     * @return \ReflectionClass
     */
    public function getReflectedClass()
    {
        if ($this->_reflectedclass === null) {
            $this->_reflectedclass = new \ReflectionClass($this->model);
        }
        return $this->_reflectedclass;
    }

    /**
     * This method is called internally from the class. It reads through the 
     * annotated properties to find which columns and resultset array keys is
     * defined as the identifier columns
     * 
     * This is needed for auto-populating object's id value for new objects, as
     * well as being able to return a proper array representation of the object
     * to the manager for storage.
     */
    protected function _populateAnnotatedIdValues()
    {
        if ($this->_data_array_identifiable === null) {
            foreach ($this->getReflectedClass($this->model)->getProperties() as $property) {
                if ($id_annotation = $this->getIdAnnotation($property)) {
                    if (!$column_annotation = $this->getColumnAnnotation($property))
                        throw new Exception('You must set the Id annotation on a property annotated with @Column');

                    $this->_id_column = ($column_annotation->name) ? $column_annotation->name : $property->name;
                    $this->_id_property = $property->name;
                    $this->_data_array_identifiable = true;
                    break;
                }
            }
            if ($this->_data_array_identifiable === null)
                $this->_data_array_identifiable = false;
        }
    }
    
    /**
     * Returns the identifier column, used by the manager when finding which
     * data array column to use as the identifier value
     * 
     * @return string
     */
    public function getDataArrayIdentifierColumn()
    {
        $this->_populateAnnotatedIdValues();
        return $this->_id_column;
    }
    
    /**
     * Returns the identifier property, used by the entity when finding which
     * property to use as the identifier value
     * 
     * @return string
     */
    public function getDataArrayIdentifierProperty()
    {
        $this->_populateAnnotatedIdValues();
        return $this->_id_property;
    }
    
    public function hasDataArrayIdentifierProperty()
    {
        return (bool) $this->_data_array_identifiable;
    }
    
    public function getResourceRoute($routename)
    {
        if (!array_key_exists($routename, static::$resource_routes))
            throw new Exception('This route does not exist in the static array property $resource_routes on this manager');
                
        return static::$resource_routes[$routename];
    }

    
    /**
     * @return \RedpillLinpro\GamineBundle\Services\ServiceInterface
     */
    public function getAccessService()
    {
        return $this->access_service;
    }

    /**
     * @return \RedpillLinpro\GamineBundle\Gamine
     */
    public function getGamineService()
    {
        return $this->gamine_service;
    }

    /**
     * @return RedpillLinpro\GamineBundle\Annotations\Resources
     */
    public function getResourceAnnotation($rc)
    {
        return self::getAnnotationsReader()->getClassAnnotation($rc, 'RedpillLinpro\\GamineBundle\\Annotations\\Resources');
    }

    /**
     * Returns an Id annotation for a specified property if it exists
     * 
     * @param \ReflectionProperty $property
     * 
     * @return RedpillLinpro\GamineBundle\Annotations\Id
     */
    public function getIdAnnotation($property)
    {
        return self::getAnnotationsReader()->getPropertyAnnotation($property, 'RedpillLinpro\\GamineBundle\\Annotations\\Id');
    }
    
    /**
     * Returns a Column annotation for a specified property if it exists
     * 
     * @param \ReflectionProperty $property
     * 
     * @return RedpillLinpro\GamineBundle\Annotations\Column
     */
    public function getColumnAnnotation($property)
    {
        return self::getAnnotationsReader()->getPropertyAnnotation($property, 'RedpillLinpro\\GamineBundle\\Annotations\\Column');
    }
    
    /**
     * @return RedpillLinpro\GamineBundle\Annotations\Model
     */
    public function getModelAnnotation($rc)
    {
        return self::getAnnotationsReader()->getClassAnnotation($rc, 'RedpillLinpro\\GamineBundle\\Annotations\\Model');
    }

    public function getCollectionResource()
    {
        return $this->collection_resource;
    }

    public function getEntityResource()
    {
        return $this->entity_resource;
    }

    public function getModelClassname()
    {
        return $this->model;
    }

    public function getInstantiatedModel()
    {
        $classname = $this->getModelClassname();
        $object = new $classname();
        $object->injectGamineEntityManager($this);
        
        return $object;
    }

    public function findAll($params = array())
    {
        $objects = array();
        foreach ($this->access_service->findAll($this->getCollectionResource(), $params) as $o) {
            $object = $this->getInstantiatedModel();
            $object->fromDataArray($o);
            $objects[] = $object;
        }

        return $objects;
    }

    public function findOneById($id, $params = array())
    {
        $resource = $this->getEntityResource();
        $data = $this->access_service->findOneById(
                $resource, $id, $params);

        if (!$data) {
            return null;
        }

        $object = $this->getInstantiatedModel();
        $object->fromDataArray($data);

        return $object;
    }

    public function findByKeyVal($key, $val, $params = array())
    {
        $objects = array();

        foreach ($this->access_service->findByKeyVal(
                $this->getCollectionResource(), $key, $val, $params) as $o) {
            $object = $this->getInstantiatedModel();
            $object->fromDataArray($o);
            $objects[] = $object;
        }

        return $objects;
    }

    public function save(\RedpillLinpro\GamineBundle\Model\BaseModelAnnotation $object)
    {
        $classname = $this->getModelClassname();
        if (!$object instanceof $classname) {
            throw new \InvalidArgumentException('This is not an object I can save, it must be of the same classname defined in this manager');
        }

        $object->injectGamineEntityManager($this);
        $is_new = !(bool) $object->getDataArrayIdentifierValue();

        $do_continue = true;
        if (method_exists($this, 'beforeSave')) {
            $do_continue = $this->beforeSave($object);
        }
        
        if ($do_continue) {
            // Save can do both insert and update with MongoDB.
            $new_data = $this->access_service->save($object, $this->getEntityResource());
            $result = true;

            if (isset($new_data[$this->getDataArrayIdentifierColumn()])) {
                $object->setDataArrayIdentifierValue($new_data[$this->getDataArrayIdentifierColumn()]);
            }

            if (method_exists($this, 'afterSave')) {
                $this->afterSave($object, $is_new);
            }
        } else {
            $result = false;
        }
        
        return $result;
    }

    public function remove($object)
    {

        $classname = $this->getModelClassname();
        if (!$object instanceof $classname) {
            throw new \InvalidArgumentException('This is not an object I can delete, it must be of the same classname defined in this manager');
        }

        if (!$object->getDataArrayIdentifierValue()) {
            throw new \InvalidArgumentException('This is not an object I can delete since it does not have a entity identifier value');
        }

        if (method_exists($this, 'beforeRemove')) {
            $this->beforeRemove($object);
        }
        
        // Save can do both insert and update with MongoDB.
        $status = $this->access_service->remove($object->getDataArrayIdentifierValue(), $this->getEntityResource());

        if (method_exists($this, 'afterRemove')) {
            $status = $this->afterRemove($object, $status);
        }
        
        return $status;
    }

}
