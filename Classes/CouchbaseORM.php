<?php

namespace Apperturedev\CouchbaseBundle\Classes;

use CouchbaseCluster;
use Doctrine\ORM\EntityManager;
use JMS\Serializer\Exclusion\ExclusionStrategyInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;

/**
 * CouchbaseORM MANAGER.
 *
 * @author adrian
 */
class CouchbaseORM extends Functions
{
    /**
     * @var \Couchbase\Bucket
     */
    private $em;

    /**
     * @var EntityManager
     */
    private $doctrine;

    /**
     * @var Object
     */
    private $_entity;

    /**
     * @var Serializer
     */
    private $serializer;

    /** @var ExclusionStrategyInterface */
    private $exclusionStrategy;

    /**
     * @param CouchbaseCluster $em
     * @param EntityManager    $doctrine
     * @param Serializer       $serializer
     * @param array|null       $buckets
     */
    public function __construct(
        CouchbaseCluster $em,
        EntityManager $doctrine,
        Serializer $serializer,
        ExclusionStrategyInterface $exclusionStrategy,
        $bucketName = 'default',
        array $buckets = null
    )
    {
        $bucket           = $buckets[$bucketName]['bucket_name'] ?? null;
        $bucketPassword   = $buckets[$bucketName]['bucket_password'] ?? '';
        $this->em         = $em->openBucket($bucket, $bucketPassword);
        $this->doctrine   = $doctrine;
        $this->serializer = $serializer;
        $this->setSerializer($serializer);
        $this->exclusionStrategy = $exclusionStrategy;
    }

    /**
     * Get the Couchbase manager.
     *
     * @return \CouchbaseBucket
     */
    public function getEm()
    {
        return $this->em;
    }

    /**
     * Get JMS Serializer.
     *
     * @return Serializer
     */
    public function getSer()
    {
        return $this->serializer;
    }

    /**
     * Save the entity Object on Couchbase
     * If id is null create a new one and add automatically to the Entity Object.
     *
     * @param string $class
     *
     * @return \Couchbase\Document|array
     *
     * @throws \Exception
     */
    public function save($class)
    {
        $table = $this->doctrine->getClassMetadata(get_class($class))->getTableName();
        if (null === $class->getId()) {
            $this->setObjectId($class, $this->setNextId($class));
        }
        $name            = $table . '_' . $class->getId();
        $data            = $this->serializer->toArray($class, $this->getContext());
        $data['doctype'] = $table;
        $debug           = $this->em->upsert($name, $data);
        if (null === $debug->error) {
            $this->addPrimaryIndex();

            return $debug;
        }

        throw new \RuntimeException('Something went wrong!');
    }

    private function addPrimaryIndex()
    {
        $indexes = $this->em->manager()->listN1qlIndexes();
        foreach ($indexes as $index) {
            if ($index->isPrimary) {
                return;
            }
        }

        $this->em->manager()->createN1qlPrimaryIndex('', false, false);
    }

    /**
     * set the id.
     *
     * @param string    $class
     * @param int|mixed $id
     *
     * @throws \ReflectionException
     */
    private function setObjectId($class, $id)
    {
        $reflection = new \ReflectionObject($class);
        $property   = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($class, $id);
    }

    /**
     * Get CouchbaseORM manager.
     *
     * @param string $entityname
     *
     * @return CouchbaseManager
     */
    public function getRepository($entityname)
    {
        $entity        = $this->doctrine->getClassMetadata($entityname)->getName();
        $this->_entity = new CouchbaseManager(
            $entity, $this->em, $this->doctrine, $this->serializer
        );

        return $this->_entity;
    }

    /**
     * Get the last id of a Entity Object.
     *
     * @param string $class
     *
     * @return int
     * @throws \Exception
     */
    public function getLastId($class)
    {
        $table = $this->doctrine->getClassMetadata(get_class($class))->getTableName();

        try {
            $value = $this->em->get($table . '_id');
            $id    = ($value->value->id > 1) ? ($value->value->id - 1) : 1;
        } catch (\Exception $e) {
            $id = $this->setId($class);
        }

        return $id;
    }

    /**
     * Set next ID.
     *
     * @param string $class
     *
     * @return int
     *
     * @throws \RuntimeException
     */
    private function setNextId($class)
    {
        $table = $this->doctrine->getClassMetadata(get_class($class))->getTableName();

        try {
            $getDoc = $this->em->get($table . '_id');
            $id     = $getDoc->value->id;
        } catch (\Exception $e) {
            return $this->setId($class);
        }

        $debug = $this->em->replace($table . '_id', ['id' => $id + 1]);
        if (null === $debug->error) {
            return $id;
        }

        throw new \RuntimeException('Something went wrong!');
    }

    /**
     * Set id if don't exist.
     *
     * @param string $class
     * @param bool   $save
     *
     * @return int
     *
     * @throws \RuntimeException
     */
    private function setId($class, $save = false)
    {
        $table = $this->doctrine->getClassMetadata(get_class($class))->getTableName();

        try {
            $id = $this->em->get($table . '_id');
        } catch (\Exception $e) {
            $id    = ($save) ? 2 : 1;
            $data  = ['id' => $id];
            $debug = $this->em->insert($table . '_id', $data);
            if ($debug->error !== null) {
                throw new \RuntimeException('Something went wrong!');
            }
        }

        return $id;
    }

    private function getContext()
    {
        $context = new SerializationContext();
        $context->setSerializeNull(true);
        $context->addExclusionStrategy($this->exclusionStrategy);

        return $context;
    }
}
