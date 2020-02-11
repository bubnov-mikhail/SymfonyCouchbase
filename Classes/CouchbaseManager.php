<?php

namespace Apperturedev\CouchbaseBundle\Classes;

use Couchbase\N1qlQuery;
use CouchbaseBucket;
use CouchbaseN1qlQuery;
use Doctrine\ORM\EntityManager;
use JMS\Serializer\Serializer;
use Doctrine\ORM\QueryBuilder;

/**
 * CouchbaseManager ORM entity manager.
 *
 * @author adrian
 */
class CouchbaseManager extends Functions
{
    private $entity;
    private $em;
    private $serializer;
    private $table;
    private $doctrine;

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
        $this->doctrine   = $doctrine;
        $this->table      = $doctrine
            ->getClassMetadata(get_class($this->entity))
            ->getTableName()
        ;
        $this->setSerializer($serializer);
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder() {
        return $this->doctrine
            ->createQueryBuilder()
            ->select($this->table)
            ->from('`' . $this->em->getName() .'`', $this->table)
            ->where($this->table.'.doctype = $doctype')
        ;
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
    public function findById($id, $format = 'object')
    {
        $qb = $this->getQueryBuilder();
        $qb->andWhere($this->table.'.id = $id');

        $result = $this->executeQueryBuilder($qb, ['id' => (int)$id]);

        return $result[0] ?? null;
    }

    /**
     * return all register about the entity in the expected format, object, array or crud value.
     *
     * @return Object|Object[]
     */
    public function findAll()
    {
        $qb = $this->getQueryBuilder();

        return $this->executeQueryBuilder($qb);
    }

    /**
     * return all register about the entity in the expected format, object, array or crud value.
     *
     * @return Object|Object[]
     */
    public function findBy(array $criterias)
    {
        $qb = $this->getQueryBuilder();
        foreach ($criterias as $field => $value) {
            $qb->andWhere($this->table.'.' . $field . ' = $' . $field);
        }
        $result = $this->executeQueryBuilder($qb, $criterias);

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
            $entities[] = $this->hydrateResult($value[$this->table], $format);
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

    /**
     * @param QueryBuilder $qb
     * @param array        $params
     *
     * @return Object|Object[]
     */
    private function executeQueryBuilder(QueryBuilder $qb, $params = [])
    {
        $query = $qb->getDql();
        $query = N1QLQuery::fromString($qb->getDql());
        $query->namedParams(array_merge(['doctype' => $this->table], $params));

        return $this->execute($query);
    }
}
