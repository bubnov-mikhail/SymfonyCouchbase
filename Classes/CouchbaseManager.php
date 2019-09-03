<?php

namespace Apperturedev\CouchbaseBundle\Classes;

use Couchbase\ViewQuery;
use JMS\Serializer\Serializer;
use Doctrine\ORM\EntityManager;
use CouchbaseBucket;
use CouchbaseViewQuery;

/**
 * CouchbaseManager ORM entity manager.
 *
 * @author adrian
 */
class CouchbaseManager extends Functions
{
    private $entity;
    private $em;
    private $doctrine;
    private $serializer;

    /**
     * @param [Object]        $entity
     * @param CouchbaseBucket $em
     * @param EntityManager   $doctrine
     * @param Serializer      $serializer
     */
    public function __construct(
        $entity, CouchbaseBucket $em, EntityManager $doctrine, Serializer $serializer
    )
    {
        $this->em         = $em;
        $this->entity     = new $entity();
        $this->doctrine   = $doctrine;
        $this->serializer = $serializer;
        $this->setSerializer($serializer);
    }

    /**
     * Return the document by id
     * format object, array or crud value.
     *
     * @param int $id
     * @param string $format
     *
     * @return Object
     */
    public function getById($id, $format = 'object')
    {
        $id    = (int)$id;
        $table = $this->doctrine->getClassMetadata(get_class($this->entity))->getTableName();
        $res   = $this->classToArray($this->em->get($table.'_'.$id)->value);

        return $this->serializer->fromArray($res, get_class($this->entity));
    }

    /**
     * return all register about the entity in the expected format, object, array or crud value.
     *
     * @param string $format the expected, object, array or value
     *
     * @return Object|Object[]
     */
    public function getAll($format = 'object')
    {
        $table = $this->doctrine->getClassMetadata(get_class($this->entity))->getTableName();
        $query = CouchbaseViewQuery::from($table, 'id');

        return $this->execute($query, $format);
    }

    /**
     * get ViewQuery
     *
     * @param string $field the view name or Object Propierty
     *
     * @return ViewQuery
     */
    public function getQuery($field)
    {
        $table = $this->doctrine->getClassMetadata(get_class($this->entity))->getTableName();
        $query = CouchbaseViewQuery::from($table, $field);

        return $query;
    }

    /**
     * execute the view query.
     *
     * @param \CouchbaseViewQuery $query
     * @param string $format object, array or value
     *
     * @return Object|Object[]
     */
    public function execute(CouchbaseViewQuery $query, $format = 'object')
    {
        $entities = [];
        $res = $this->em->query($query, true);

        if (is_object($res)) {
            $res = $this->classToArray($res);
        }

        if ($res['total_rows'] === 0 && count($res['rows']) === 0) {
            return null;
        }

        foreach ($res['rows'] as $value) {
            if ('value' != $format) {
                $this->entity = $this->serializer->fromArray($value['value'], get_class($this->entity));
            }
            switch ($format) {
                case 'object':
                    $entities[] = $this->entity;
                    break;
                case 'array':
                    $entities[] = $this->serializer->toArray($this->entity);
                    break;
                case 'value':
                    $entities[] = $value['value'];
                    break;
            }
        }

        return (count($entities) === 1) ? $entities[0] : $entities;
    }

    /**
     * Execute N1QL query.
     *
     * @param ViewQuery $query
     *
     * @return Object|Object[]
     */
    public function query($query)
    {
        $sql = CouchbaseN1qlQuery::fromString($query);

        return $this->em->query($sql, true);
    }

    /**
     * Truncate All documents of a Entity.
     *
     * @return array
     */
    public function truncateDocumemts()
    {
        $data = $this->getAll('value');
        $name = $this->doctrine->getClassMetadata(get_class($this->entity))->getTableName();
        if (null != $data) {
            foreach ($data as $value) {
                $this->em->remove($name.'_'.$value['id']);
            }
            $this->em->remove($name.'_id');

            return ['Success' => true];
        } else {
            return ['Success' => true, 'msg' => 'Not Registers'];
        }
    }

    /**
     * Del a document by id.
     *
     * @param $id
     *
     * @return array
     */
    public function delDocumemt($id)
    {
        $name = $this->doctrine->getClassMetadata(get_class($this->entity))->getTableName();
        try {
            $this->em->remove($name.'_'.$id);
        } catch (\Exception $e) {
            return ['Success' => true, 'msg' => 'Not Register'];
        }

        return ['Success' => true];
    }
}
