<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Apperturedev\CouchbaseBundle\Classes;


use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Usefull Functions
 *
 * @author adrian
 */
class Functions
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var function
     */
    private $_prototype;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var SerializationContext
     */
    private $context;

    /**
     * Seting outside serializer
     *
     * @param Serializer $serializer
     *
     * @return boolean
     */
    public function setSerializer($serializer)
    {
        $this->serializer = $serializer;
        $context          = new SerializationContext();
        $this->context    = $context->setSerializeNull(true);

        return true;
    }


    /**
     * Transform object to Json.
     *
     * Will return one object to json
     *
     *     $this->toJson($class);
     *
     * @param Object $class
     *
     * @return string
     */
    public function toJSon($class)
    {
        $encoders    = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer  = new Serializer($normalizers, $encoders);

        return $serializer->serialize($class, 'json');
    }

    /**
     * object to array, witout JMS serializer
     *
     * @param Object $class
     *
     * @return array
     */
    public function onArray($class)
    {

        $reflection = new \ReflectionObject($class);
        foreach ($reflection->getProperties() as $property) {
            // Override visibility
            if (!$property->isPublic()) {
                $property->setAccessible(true);
            }
            $array[$property->name] = $property->getValue($class);
        }

        return $array;
    }

    /**
     * Symfony standard normalizer object to array
     *
     * @param Object $class
     *
     * @return string
     */
    public function objecttoArray($class)
    {
        $normalizers = new PropertyNormalizer();

        return $normalizers->normalize($class);
    }


    /**
     * Improved symfony normalizer
     *
     * @param Object $class
     *
     * @return array
     */
    public function toArray($class)
    {
        $json = $this->onArray($class);

        return $json;
    }

    /**
     * Convert an array to Entity object transforming to json and json to object
     *
     * @param array  $array
     * @param Object $class
     */
    public function toObject($array, &$class)
    {
        $encoders    = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer  = new Serializer($normalizers, $encoders);
        $class       = $serializer->deserialize(json_encode($array), get_class($class), 'json');
    }

    /**
     * Convert a Json Sring in a Entity Object
     *
     * @param type $json
     * @param type $class
     */
    public function JsontoObjectold($json, &$class)
    {
        $encoders    = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer  = new Serializer($normalizers, $encoders);
        $class       = $serializer->deserialize($json, get_class($class), 'json');
    }

    /**
     * JMS Serializer Json string to Entity Object
     *
     * @param type $json
     * @param type $class
     *
     * @return boolean
     */
    public function JsontoObject($json, &$class)
    {
        $class = $this->serializer->deserialize($json, get_class($class), 'json', $this->context);

        return true;
    }

    /**
     * Set the id of private id from Array
     *
     * @param array  $array
     * @param Object $class
     *
     * @throws \ReflectionException
     */
    public function getObject($array, &$class)
    {
        $this->name = get_class($class);
        $entity     = $this->newInstance();
        $this->toObject($array, $entity);
        $reflection = new \ReflectionObject($entity);
        $property   = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $array['id']);
        $class = $entity;
    }

    /**
     * @return function|mixed
     */
    private function newInstance()
    {
        if ($this->_prototype === null) {
            $this->_prototype = unserialize(
                sprintf('O:%d:"%s":0:{}', strlen($this->name), $this->name)
            );
        }

        return clone $this->_prototype;
    }

    /**
     * Json String to Array
     *
     * @param string $json
     *
     * @return array
     */
    public function JsonToArray($json)
    {
        $json   = json_decode($json);
        $result = [];
        foreach ($json as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Json Object to Array
     *
     * @param string $json
     *
     * @return array
     */
    public function JsonObjectToArray($json)
    {
        $result = [];
        foreach ($json as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param Object $data
     *
     * @return Object
     */
    public function classToArray($data)
    {
        return json_decode(json_encode($data), true);
    }
}
