<?php

/**
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Doctrine\Bundle\DoctrineCacheBundle\DependencyInjection\Definition;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use function sprintf;

/**
 * Couchbase definition.
 */
class CouchbaseDefinition extends CacheDefinition
{
    /**
     * {@inheritDoc}
     */
    public function configure($name, array $config, Definition $service, ContainerBuilder $container)
    {
        $couchbaseConf = $config['couchbase'];
        $connRef       = $this->getConnectionReference($name, $couchbaseConf, $container);

        $service->addMethodCall('setCouchbase', [$connRef]);
    }

    /**
     * @param string $name
     * @param array  $config
     *
     * @return Reference
     */
    private function getConnectionReference($name, array $config, ContainerBuilder $container)
    {
        if (isset($config['connection_id'])) {
            return new Reference($config['connection_id']);
        }

        $host      = $config['hostnames'];
        $user      = $config['username'];
        $pass      = $config['password'];
        $bucket    = $config['bucket_name'];
        $connClass = '%doctrine_cache.couchbase.connection.class%';
        $connId    = sprintf('doctrine_cache.services.%s_couchbase.connection', $name);
        $connDef   = new Definition($connClass, [$host, $user, $pass, $bucket]);

        $connDef->setPublic(false);
        $container->setDefinition($connId, $connDef);

        return new Reference($connId);
    }
}
