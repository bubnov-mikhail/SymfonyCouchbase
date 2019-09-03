<?php

namespace Apperturedev\CouchbaseBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class ExclusionStratagyPass
 */
class ExclusionStratagyPass implements CompilerPassInterface
{
    /**
     * @var string
     */
    const TAG_NAME = 'fredpalas.couchbase_bundle.exclusion_strategy';

    /**
     * @var string
     */
    const SERIVCE_ID = 'fredpalas.couchbase_bundle.exclusion_strategy';

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(self::SERIVCE_ID)) {
            return;
        }

        $definition = $container->findDefinition(self::SERIVCE_ID);
        $taggedServices = $container->findTaggedServiceIds(self::TAG_NAME);

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addStrategy', [new Reference($id)]);
        }
    }
}
