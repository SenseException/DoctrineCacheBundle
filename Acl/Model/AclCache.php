<?php

namespace Doctrine\Bundle\DoctrineCacheBundle\Acl\Model;

use Doctrine\Common\Cache\CacheProvider;
use InvalidArgumentException;
use ReflectionProperty;
use Symfony\Component\Security\Acl\Model\AclCacheInterface;
use Symfony\Component\Security\Acl\Model\AclInterface;
use Symfony\Component\Security\Acl\Model\ObjectIdentityInterface;
use Symfony\Component\Security\Acl\Model\PermissionGrantingStrategyInterface;
use function serialize;
use function unserialize;

/**
 * This class is a wrapper around the actual cache implementation.
 */
class AclCache implements AclCacheInterface
{
    /** @var CacheProvider */
    private $cache;

    /** @var PermissionGrantingStrategyInterface */
    private $permissionGrantingStrategy;

    /**
     * Constructor
     */
    public function __construct(CacheProvider $cache, PermissionGrantingStrategyInterface $permissionGrantingStrategy)
    {
        $this->cache                      = $cache;
        $this->permissionGrantingStrategy = $permissionGrantingStrategy;
    }

    /**
     * {@inheritdoc}
     */
    public function evictFromCacheById($primaryKey)
    {
        if (! $this->cache->contains($primaryKey)) {
            return;
        }

        $key = $this->cache->fetch($primaryKey);

        $this->cache->delete($primaryKey);
        $this->evictFromCacheByKey($key);
    }

    /**
     * {@inheritdoc}
     */
    public function evictFromCacheByIdentity(ObjectIdentityInterface $oid)
    {
        $key = $this->createKeyFromIdentity($oid);

        $this->evictFromCacheByKey($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getFromCacheById($primaryKey)
    {
        if (! $this->cache->contains($primaryKey)) {
            return null;
        }

        $key = $this->cache->fetch($primaryKey);
        $acl = $this->getFromCacheByKey($key);

        if (! $acl) {
            $this->cache->delete($primaryKey);

            return null;
        }

        return $acl;
    }

    /**
     * {@inheritdoc}
     */
    public function getFromCacheByIdentity(ObjectIdentityInterface $oid)
    {
        $key = $this->createKeyFromIdentity($oid);

        return $this->getFromCacheByKey($key);
    }

    /**
     * {@inheritdoc}
     */
    public function putInCache(AclInterface $acl)
    {
        if ($acl->getId() === null) {
            throw new InvalidArgumentException('Transient ACLs cannot be cached.');
        }

        $parentAcl = $acl->getParentAcl();

        if ($parentAcl !== null) {
            $this->putInCache($parentAcl);
        }

        $key = $this->createKeyFromIdentity($acl->getObjectIdentity());

        $this->cache->save($key, serialize($acl));
        $this->cache->save($acl->getId(), $key);
    }

    /**
     * {@inheritdoc}
     */
    public function clearCache()
    {
        return $this->cache->deleteAll();
    }

    /**
     * Unserialize a given ACL.
     *
     * @param string $serialized
     *
     * @return AclInterface
     */
    private function unserializeAcl($serialized)
    {
        $acl      = unserialize($serialized);
        $parentId = $acl->getParentAcl();

        if ($parentId !== null) {
            $parentAcl = $this->getFromCacheById($parentId);

            if ($parentAcl === null) {
                return null;
            }

            $acl->setParentAcl($parentAcl);
        }

        $reflectionProperty = new ReflectionProperty($acl, 'permissionGrantingStrategy');

        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($acl, $this->permissionGrantingStrategy);
        $reflectionProperty->setAccessible(false);

        $aceAclProperty = new ReflectionProperty('Symfony\Component\Security\Acl\Domain\Entry', 'acl');

        $aceAclProperty->setAccessible(true);

        foreach ($acl->getObjectAces() as $ace) {
            $aceAclProperty->setValue($ace, $acl);
        }

        foreach ($acl->getClassAces() as $ace) {
            $aceAclProperty->setValue($ace, $acl);
        }

        $aceClassFieldProperty = new ReflectionProperty($acl, 'classFieldAces');

        $aceClassFieldProperty->setAccessible(true);

        foreach ($aceClassFieldProperty->getValue($acl) as $aces) {
            foreach ($aces as $ace) {
                $aceAclProperty->setValue($ace, $acl);
            }
        }

        $aceClassFieldProperty->setAccessible(false);

        $aceObjectFieldProperty = new ReflectionProperty($acl, 'objectFieldAces');

        $aceObjectFieldProperty->setAccessible(true);

        foreach ($aceObjectFieldProperty->getValue($acl) as $aces) {
            foreach ($aces as $ace) {
                $aceAclProperty->setValue($ace, $acl);
            }
        }

        $aceObjectFieldProperty->setAccessible(false);

        $aceAclProperty->setAccessible(false);

        return $acl;
    }

    /**
     * Returns the key for the object identity
     *
     * @return string
     */
    private function createKeyFromIdentity(ObjectIdentityInterface $oid)
    {
        return $oid->getType() . '_' . $oid->getIdentifier();
    }

    /**
     * Removes an ACL from the cache
     *
     * @param string $key
     */
    private function evictFromCacheByKey($key)
    {
        if (! $this->cache->contains($key)) {
            return;
        }

        $this->cache->delete($key);
    }

    /**
     * Retrieves an ACL for the given key from the cache
     *
     * @param string $key
     *
     * @return AclInterface|null
     */
    private function getFromCacheByKey($key)
    {
        if (! $this->cache->contains($key)) {
            return null;
        }

        $serialized = $this->cache->fetch($key);

        return $this->unserializeAcl($serialized);
    }
}
