<?php
declare(strict_types = 1);
namespace Habeuk\UrlAliasBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Habeuk\UrlAliasBundle\Entity\EntityTypeAlias;
use Habeuk\UrlAliasBundle\Entity\EntityUrlAlias;
use Habeuk\UrlAliasBundle\Shared\Doctrine\AliasEntitiesBase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Retourne l'URL publique d'une entite aliasable.
 *
 * Trois niveaux de lecture, du moins couteux au plus couteux :
 * - cache statique (duree : une requete HTTP, evite les appels repetes en boucle) ;
 * - cache applicatif Symfony (pool 'cache.app', injecte par CacheInterface) ;
 * - requete SQL en dernier recours.
 *
 * NOTE : la version d'origine s'appuyait sur un service maison CacheService
 * (APCu). Il n'existe pas dans le socle : on utilise donc le contrat de cache
 * Symfony. Le comportement observe est identique, mais l'implementation du pool
 * reste configurable par l'application.
 *
 * @author stephane
 */
class EntityGetAlias {

  /**
   * Duree de vie de l'alias en cache applicatif, en secondes (24h).
   */
  private const CACHE_TTL = 86400;

  /**
   * Cache statique, vide a chaque requete HTTP.
   *
   * @var array<string, array<int, string|null>>
   */
  private static array $menus = [];

  public function __construct(private EntityManagerInterface $em, private CacheInterface $cache) {}

  /**
   * Retourne l'URL d'alias d'une entite (avec slash initial), ou null.
   */
  public function generateUrlByEntity(AliasEntitiesBase $entity): ?string {
    $baseEntity = $entity::class;
    $id = $entity->getId() ?? 0;

    // Niveau 1 : cache statique (1 requete HTTP)
    if (array_key_exists($baseEntity, self::$menus) && array_key_exists($id, self::$menus[$baseEntity])) {
      return self::$menus[$baseEntity][$id];
    }

    // Niveau 2 : cache applicatif Symfony
    $cacheKey = $this->buildCacheKey($baseEntity, $id);
    $link = $this->cache->get($cacheKey, function (ItemInterface $item) use ($entity): ?string {
      $item->expiresAfter(self::CACHE_TTL);
      return $this->fetchAliasFromDatabase($entity);
    });

    // Remplissage du cache statique
    self::$menus[$baseEntity][$id] = $link;

    return $link;
  }

  /**
   * Recupere l'alias depuis la base de donnees.
   */
  private function fetchAliasFromDatabase(AliasEntitiesBase $entity): ?string {
    // Alias non auto-genere : directement sur l'entite
    if (! $entity->isAutoGenerateAlias()) {
      return $entity->getUrlAlias();
    }

    $id = $entity->getId() ?? 0;

    $qb = $this->em->createQueryBuilder()
      ->select('e.urlAlias as url_alias', 'e.id', 'eua.url')
      ->from($entity::class, 'e')
      ->innerJoin(EntityUrlAlias::class, 'eua', 'WITH', 'eua.entityId = e.id')
      ->innerJoin(EntityTypeAlias::class, 'eta', 'WITH', 'eta.id = eua.typeAlias')
      ->andWhere('e.id = :e_id')
      ->setParameter('e_id', $id)
      ->setMaxResults(1);

    /** @var array<string, string|null>|null $data */
    $data = $qb->getQuery()->getOneOrNullResult();
    if ($data === null)
      return null;
    if (isset($data['url']) && $data['url'] !== '') {
      return '/' . $data['url'];
    }
    return null;
  }

  /**
   * Precharge les alias d'une liste d'entites en une seule requete par classe.
   *
   * A appeler avant une boucle d'affichage (ex: listing de 50 produits) : sans
   * cela, chaque entite declenche sa propre requete.
   *
   * @param AliasEntitiesBase[] $entities
   */
  public function preloadAliases(array $entities): void {
    if ($entities === []) {
      return;
    }

    /** @var array<class-string, array<int>> $byClass */
    $byClass = [];
    foreach ($entities as $entity) {
      $id = $entity->getId();
      if ($id === null) {
        continue;
      }
      // Deja resolu dans cette requete HTTP : on ne fait rien.
      if (array_key_exists($entity::class, self::$menus) && array_key_exists($id, self::$menus[$entity::class])) {
        continue;
      }
      // Alias porte par l'entite elle-meme : aucune requete necessaire.
      if (! $entity->isAutoGenerateAlias()) {
        self::$menus[$entity::class][$id] = $entity->getUrlAlias();
        continue;
      }
      $byClass[$entity::class][] = $id;
    }

    // Une requete par classe d'entite.
    foreach ($byClass as $class => $ids) {
      $ids = array_values(array_unique($ids));
      if ($ids === []) {
        continue;
      }

      $qb = $this->em->createQueryBuilder()
        ->select('e.id AS eid', 'eua.url AS url')
        ->from($class, 'e')
        ->innerJoin(EntityUrlAlias::class, 'eua', 'WITH', 'eua.entityId = e.id')
        ->innerJoin(EntityTypeAlias::class, 'eta', 'WITH', 'eta.id = eua.typeAlias')
        ->andWhere('e.id IN (:ids)')
        ->setParameter('ids', $ids);

      /** @var list<array<string, string|null>> $results */
      $results = $qb->getQuery()->getArrayResult();

      // Cache negatif : toute entite non trouvee est memorisee a null pour
      // eviter une nouvelle requete dans la meme requete HTTP.
      foreach ($ids as $id) {
        self::$menus[$class][$id] = null;
      }
      foreach ($results as $row) {
        if (isset($row['url']) && $row['url'] !== '') {
          self::$menus[$class][(int) $row['eid']] = '/' . $row['url'];
        }
      }

      // Memorisation en cache applicatif.
      foreach ($ids as $id) {
        $value = self::$menus[$class][$id];
        $this->cache->get($this->buildCacheKey($class, $id), function (ItemInterface $item) use ($value): ?string {
          $item->expiresAfter(self::CACHE_TTL);
          return $value;
        });
      }
    }
  }

  /**
   * Invalide le cache d'un alias (a appeler lors d'une mise a jour).
   */
  public function invalidateAlias(string $entityClass, int $id): void {
    $this->cache->delete($this->buildCacheKey($entityClass, $id));

    // Nettoyer aussi le cache statique si la meme requete l'a deja charge
    if (isset(self::$menus[$entityClass][$id])) {
      unset(self::$menus[$entityClass][$id]);
    }
  }

  /**
   * Construit la cle de cache applicatif d'un alias.
   */
  private function buildCacheKey(string $entityClass, int $id): string {
    return 'entity_alias_' . hash('xxh128', $entityClass . '_' . $id);
  }
}
