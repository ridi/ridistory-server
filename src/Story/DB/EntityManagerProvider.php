<?php
namespace Story\DB;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

/**
 * Doctrine EntityManager를 제공하는 싱글턴 클래스
 * @package Story\DB
 */
class EntityManagerProvider
{
    private static $IS_DEV_MODE = true;

    /** Entity가 갱신되었을 경우 아래 NAMESPACE를 반드시 바꿔줘야함 */
    const METADATA_CACHE_NAMESPACE = "STORY_METADATA_2";

    /**
     * @var EntityManager
     */
    private static $entity_manager;

    public static function getEntityManager()
    {
        if (!isset(self::$entity_manager)) {
            self::$entity_manager = self::createEntityManager();
            return self::$entity_manager;
        }
        return self::$entity_manager;
    }

    protected static function createEntityManager()
    {
        $config = Setup::createAnnotationMetadataConfiguration(
            array(__DIR__ . "/../Entity"),
            self::$IS_DEV_MODE
        );
        $config->getMetadataCacheImpl()->setNamespace(self::METADATA_CACHE_NAMESPACE);

        global $app;
        $conn = $app['db'];

        // obtaining the entity manager
        $entity_manager = EntityManager::create($conn, $config);
        $platform = $entity_manager->getConnection()->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'string');
        $platform->registerDoctrineTypeMapping('bit', 'integer');

        return $entity_manager;
    }
}