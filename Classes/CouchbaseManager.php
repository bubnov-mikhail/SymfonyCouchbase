<?php

namespace Apperturedev\CouchbaseBundle\Classes;

use Couchbase\N1qlQuery;
use CouchbaseBucket;
use CouchbaseN1qlQuery;
use Doctrine\ORM\EntityManager;
use JMS\Serializer\Serializer;

/**
 * CouchbaseManager ORM entity manager.
 *
 * @author adrian
 */
class CouchbaseManager extends Functions
{
    /**
     * @var string
     */
    const SELECT_ALL_QUERY = 'SELECT %table%.* FROM %bucket% %table% WHERE %table%.doctype = "%table%"';

    /**
     * @var string
     */
    const SELECT_BY_QUERY = 'SELECT %table%.* FROM %bucket% %table% WHERE %table%.doctype = "%table%" AND %field% = %value%';

    private $entity;
    private $em;
    private $doctrine;
    private $serializer;
    private $table;

    /**
     * @param [Object]        $entity
     * @param CouchbaseBucket $em
     * @param EntityManager   $doctrine
     * @param Serializer      $serializer
     */
    public function __construct(
        $entity,
        CouchbaseBucket $em,
        EntityManager $doctrine,
        Serializer $serializer
    )
    {
        $this->em         = $em;
        $this->entity     = new $entity();
        $this->serializer = $serializer;
        $this->table      = $doctrine
            ->getClassMetadata(get_class($this->entity))
            ->getTableName()
        ;
        $this->setSerializer($serializer);
    }

    /**
     * Return the document by id
     * format object, array or crud value.
     *
     * @param int    $id
     * @param string $format
     *
     * @return Object
     */
    public function getById($id, $format = 'object')
    {
        $id  = (int)$id;
        $res = $this->classToArray($this->em->get($this->table . '_' . $id)->value);

        return $this->serializer->fromArray($res, get_class($this->entity));
    }

    /**
     * return all register about the entity in the expected format, object, array or crud value.
     *
     * @return Object|Object[]
     */
    public function getAll()
    {
        $query  = $this->getQuery(self::SELECT_ALL_QUERY);
        $result = $this->execute($query);

        return $result;
    }

    /**
     * return all register about the entity in the expected format, object, array or crud value.
     *
     * @return Object|Object[]
     */
    public function getBy($field, $value)
    {
        $query  = $this->getQuery(self::SELECT_BY_QUERY, ['%field%', '%value%'], [$field, $value]);
        $result = $this->execute($query);

        return $result;
    }

    /**
     * Del a document by id.
     *
     * @param $id
     *
     * @return array
     */
    public function deleleDocumemt($id)
    {
        try {
            $this->em->remove($this->table . '_' . $id);
        } catch (\Exception $e) {
            return ['Success' => true, 'msg' => 'Not Register'];
        }

        return ['Success' => true];
    }

    /**
     * @param string $sqlTemplate
     * @param array  $params
     * @param array  $values
     *
     * @return N1qlQuery
     */
    private function getQuery($sqlTemplate, $params = [], $values = [])
    {
        $search  = array_merge(['%table%', '%bucket%'], $params);
        $replace = array_merge([$this->table, $this->em->getName()], $values);
        $sql     = str_replace(
            $search,
            $replace,
            $sqlTemplate
        );

        return CouchbaseN1qlQuery::fromString($sql);
    }

    /**
     * execute the N1ql query.
     *
     * @param CouchbaseN1qlQuery $query
     * @param string             $format object, array or value
     *
     * @return Object|Object[]
     */
    private function execute(CouchbaseN1qlQuery $query, $format = 'object')
    {
        $entities = [];
        $res      = $this->em->query($query, true);

        if (is_object($res)) {
            $res = $this->classToArray($res);
        }

        if (count($res['rows']) === 0) {
            return null;
        }

        foreach ($res['rows'] as $value) {
            $entities[] = $this->hydrateResult($value, $format);
        }

        return $entities;
    }

    /**
     * @param array|mixed $data
     * @param string      $format
     *
     * @return array|Object
     */
    protected function hydrateResult($data, $format = 'object')
    {
        if ('value' === $format) {
            return $data;
        }

        $entity = $this->serializer->fromArray($data, get_class($this->entity));

        switch ($format) {
            case 'object':
                return $entity;
                break;
            case 'array':
                return $this->serializer->toArray($entity);
                break;
        }
    }
}
