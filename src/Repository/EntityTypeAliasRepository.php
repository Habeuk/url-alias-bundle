<?php
declare(strict_types = 1);

namespace Habeuk\UrlAliasBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Habeuk\UrlAliasBundle\Entity\EntityTypeAlias;

/**
 * @extends ServiceEntityRepository<EntityTypeAlias>
 */
class EntityTypeAliasRepository extends ServiceEntityRepository {

  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, EntityTypeAlias::class);
  }
}
