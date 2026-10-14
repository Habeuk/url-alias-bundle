<?php
declare(strict_types = 1);

namespace Habeuk\UrlAliasBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Habeuk\UrlAliasBundle\Entity\EntityUrlAlias;

/**
 * @extends ServiceEntityRepository<EntityUrlAlias>
 */
class EntityUrlAliasRepository extends ServiceEntityRepository {

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, EntityUrlAlias::class);
  }

  /**
   * Retourne l'alias correspondant a un chemin donne.
   *
   * @param string $url Chemin sans slash initial (ex. "produit/mon-produit-12").
   */
  public function findOneByUrl(string $url): ?EntityUrlAlias {
    return $this->findOneBy([
      'url' => trim($url, '/')
    ]);
  }
}
