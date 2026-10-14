<?php
declare(strict_types = 1);
namespace Habeuk\UrlAliasBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
use Habeuk\UrlAliasBundle\Entity\EntityTypeAlias;
use Habeuk\UrlAliasBundle\Entity\EntityUrlAlias;
use Habeuk\UrlAliasBundle\Shared\Doctrine\AliasEntitiesBase;

/**
 * Genere et maintient l'alias d'URL d'une entite.
 *
 * @author stephane
 */
class UrlAliasGenerator {

  /**
   * Nombre maximum de tentatives pour trouver une URL unique.
   */
  private const MAX_UNIQUE_ATTEMPTS = 1000;

  public function __construct(private EntityManagerInterface $em, private TokenReplacer $replacer) {}

  /**
   * Permet de generer un alias pour une entite.
   * On a 2 options generations automatique à partir de l'URL et generation
   * manuelle directement dans le contenu.
   * OPTION:1
   * Generation manuelle.
   * Les differents cas:
   * ## 1- L'alias existe deja et contient la meme URL que le lien generer.( on
   * passe )
   * ## 2- L'alias existe deja mais l'url est different du lien generer.
   * ## 2-1 Si ce lien existe deja alors il appartient forcement à une autre
   * entité, on compte les occurences plus 1.
   * ## 2-2 si le lien n'existe pas encore, on fait une mise à jour de l'alias.
   * ## 3 L'alias n'existe pas, et le lien n'existe pas, on cree un nouveau.
   * ## 4 L'alias n'existe pas et le lien existe. alors ce lien appartient à un
   * autre contenu on cree, on compte les occurences plus 1.
   */
  public function generate(AliasEntitiesBase $entity): EntityUrlAlias {
    if ($entity instanceof Proxy) {
      $parentClass = get_parent_class($entity);
      if (false === $parentClass) {
        throw new \LogicException('Impossible de determiner la classe parente du proxy.');
      }
      $class = $parentClass;
    }
    else {
      $class = $entity::class;
    }
    $id = $entity->getId();
    if ($id === null)
      throw new \LogicException('Entity must be persisted.');
    if (! $entity->isAutoGenerateAlias()) {
      return $this->generationManuelle($entity, $class);
    }
    return $this->generationAutomatique($entity, $class);
  }

  /**
   * Genere l'URL a partir du modele defini dans EntityTypeAlias.
   */
  private function generationAutomatique(AliasEntitiesBase $entity, string $class): EntityUrlAlias {
    $entityId = $entity->getId();
    if (null === $entityId) {
      throw new \LogicException('Entity must be persisted.');
    }
    $qb = $this->em->createQueryBuilder();
    $qb->select('eta as type_alias', 'u.url as old_url', 'u.id');
    $qb->from(EntityTypeAlias::class, 'eta');
    $qb->where('eta.entityClass = :eta_entityClass');
    $qb->andWhere('eta.isDefault = :eta_isDefault');
    $qb->setParameter('eta_isDefault', true);
    $qb->leftJoin(EntityUrlAlias::class, 'u', 'WITH', 'u.typeAlias = eta.id and u.entityId = :u_entityId');
    $qb->setParameter('u_entityId', $entityId);
    $qb->setParameter('eta_entityClass', $class);
    $qb->setMaxResults(1);
    $result = $qb->getQuery()->getOneOrNullResult();
    if ($result === null) {
      throw new \LogicException("No default EntityTypeAlias for $class");
    }
    /**
     *
     * @var EntityTypeAlias $typeAlias
     */
    $typeAlias = $result['type_alias'];
    $model = $typeAlias->getModel();
    if (null === $model) {
      throw new \LogicException('EntityTypeAlias model must be defined.');
    }
    $new_url = $this->replacer->replaceTokens($model, $entity);
    if ($new_url === $result['old_url'] && isset($result['id'])) {
      $entityUrlAlias = $this->em->getRepository(EntityUrlAlias::class)->find($result['id']);
      if (null === $entityUrlAlias) {
        throw new \LogicException('EntityUrlAlias introuvable pour cet identifiant.');
      }
      return $entityUrlAlias;
    }

    $new_url = $this->generateUniqueUrl($new_url, $typeAlias, $entity);
    // cas mise a jour
    if (isset($result['old_url']) && $result['old_url'] !== '') {
      /**
       *
       * @var EntityUrlAlias|null $entityUrlAlias
       */
      $entityUrlAlias = $this->em->getRepository(EntityUrlAlias::class)->find($result['id']);
      if (null === $entityUrlAlias) {
        throw new \LogicException('EntityUrlAlias introuvable pour cet identifiant.');
      }
      $entityUrlAlias->setUrl($new_url);
    }
    else {
      $entityUrlAlias = new EntityUrlAlias();
      $entityUrlAlias->setEntityId($entityId);
      $entityUrlAlias->setTypeAlias($typeAlias);
      $entityUrlAlias->setUrl($new_url);
      $this->em->persist($entityUrlAlias);
    }
    return $entityUrlAlias;
  }

  /**
   * 1/3 : On recupere l'alias s'il existe deja.
   * 2/3 : On verifie que l'url est unique, si non on genere une URL unique.
   * 3/3 : On sauvegarde/maj l'entité EntityUrlAlias et si l'url a changé on
   * sauvegarde aussi l'entite AliasEntitiesBase.
   */
  private function generationManuelle(AliasEntitiesBase $entity, string $class): EntityUrlAlias {
    $new_url = $entity->getUrlAlias();
    if (null === $new_url) {
      throw new \LogicException('Entity url alias must be defined.');
    }
    $entityId = $entity->getId();
    if (null === $entityId) {
      throw new \LogicException('Entity must be persisted.');
    }
    $qb = $this->em->createQueryBuilder();
    $qb->select('u as entity_url');
    $qb->from(EntityUrlAlias::class, 'u');
    $qb->where('u.entityId = :u_entityId');
    // Permet de verifier si une url a une valeur identique.
    $qb->addSelect('(CASE WHEN EXISTS  (select 1 FROM ' . EntityUrlAlias::class . ' as u2 WHERE u2.url = :u2_url) THEN true ELSE false END ) AS urlExiste');
    $qb->join('u.typeAlias', 'eta');
    $qb->andWhere('eta.entityClass = :eta_entityClass');
    $qb->setParameter('eta_entityClass', $class);
    $qb->setMaxResults(1);
    $qb->setParameter('u_entityId', $entityId);
    $qb->setParameter('u2_url', $new_url);
    $result = $qb->getQuery()->getOneOrNullResult();
    if (isset($result['entity_url']) && $result['entity_url'] !== '') {
      /**
       *
       * @var EntityUrlAlias $entityUrlAlias
       */
      $entityUrlAlias = $result['entity_url'];
      $old_url = $entityUrlAlias->getUrl();
      // si cest la meme URL, on passe.
      if ($old_url === $new_url) {
        return $result['entity_url'];
      }
      if (isset($result['urlExiste'])) {
        $typeAlias = $entityUrlAlias->getTypeAlias();
        if (null === $typeAlias) {
          throw new \LogicException('EntityUrlAlias typeAlias must be defined.');
        }
        $new_url = $this->generateUniqueUrl($new_url, $typeAlias, $entity);
        // on definie false, pour eviter de faire une boucle infinit.
        $entity->setUrlAlias($new_url, false);
      }
      $entityUrlAlias->setUrl($new_url);
      return $entityUrlAlias;
    }

    $typeAlias = $this->em->createQueryBuilder()
      ->select('eta')
      ->from(EntityTypeAlias::class, 'eta')
      ->where('eta.entityClass = :eta_entityClass')
      ->setParameter('eta_entityClass', $class)
      ->andWhere('eta.isDefault = :isDefault')
      ->setParameter('isDefault', true)
      ->getQuery()
      ->getOneOrNullResult();
    if ($typeAlias === null) {
      throw new \LogicException("No default EntityTypeAlias for $class");
    }
    if (isset($result) && isset($result['urlExiste'])) {
      $new_url = $this->generateUniqueUrl($new_url, $typeAlias, $entity);
      // on definie false, pour eviter de faire une boucle infinit.
      $entity->setUrlAlias($new_url, false);
    }
    $alias = new EntityUrlAlias();
    $alias->setEntityId($entityId);
    $alias->setTypeAlias($typeAlias);
    $alias->setUrl($new_url);
    $this->em->persist($alias);
    return $alias;
  }

  /**
   * Permet de verifier qu'une URL est unique et sinon genere de nouvelle jusqu'à
   * trouver une URL unique.
   *
   * @param string $baseUrl
   * @param EntityTypeAlias $typeAlias
   * @param AliasEntitiesBase $currentEntity
   * @return string
   */
  private function generateUniqueUrl(string $baseUrl, EntityTypeAlias $typeAlias, AliasEntitiesBase $currentEntity): string {
    $url = $baseUrl;
    $counter = 1;
    while (true) {
      $existing = $this->em->getRepository(EntityUrlAlias::class)->findOneBy([
        'url' => $url,
        'typeAlias' => $typeAlias
      ]);
      if ($existing === null || $existing->getEntityId() === $currentEntity->getId()) {
        return $url;
      }
      if ($counter > self::MAX_UNIQUE_ATTEMPTS) {
        throw new \RuntimeException(sprintf("Impossible de générer une URL unique après %d tentatives.", self::MAX_UNIQUE_ATTEMPTS));
      }
      $url = $baseUrl . '-' . $counter;
      $counter ++;
    }
  }
}
