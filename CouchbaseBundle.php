<?php

namespace Apperturedev\CouchbaseBundle;

use Apperturedev\CouchbaseBundle\DependencyInjection\Compiler\ExclusionStratagyPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class CouchbaseBundle extends Bundle
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new ExclusionStratagyPass());
    }
}
