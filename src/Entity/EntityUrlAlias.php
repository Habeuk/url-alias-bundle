<?php

namespace Habeuk\UrlAliasBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Habeuk\UrlAliasBundle\Repository\EntityUrlAliasRepository;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * URL generee et stockee pour une entite donnee.
 *
 * La correspondance chemin -> entite se fait par (entityId, typeAlias) : il n'y
 * a pas d'association Doctrine directe vers l'entite cible, celle-ci etant
 * designee dynamiquement par EntityTypeAlias::$entityClass.
 */
#[ORM\Entity(repositoryClass: EntityUrlAliasRepository::class)]
#[ORM\UniqueConstraint(name: 'url_unique', columns: [
  'url'
])]
#[ORM\Index(name: 'idx_eua_entity_id', columns: [
  'entity_id'
])]
#[ORM\Index(name: 'idx_eua_entity_type', columns: [
  'entity_id',
  'type_alias_id'
])]
class EntityUrlAlias {

  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  /**
   * Contient l'id de l'entite.
   */
  #[ORM\Column(nullable: true)]
  #[Assert\NotBlank]
  private ?int $entityId = null;

  /**
   * Relation avec le type.
   */
  #[ORM\ManyToOne(targetEntity: EntityTypeAlias::class)]
  #[ORM\JoinColumn(nullable: true)]
  #[Assert\NotNull(message: "Le typeAlias est requis")]
  private ?EntityTypeAlias $typeAlias = null;

  /**
   * Url
   */
  #[ORM\Column(length: 255, unique: true, nullable: true)]
  #[Assert\NotBlank]
  private ?string $url = null;

  #[ORM\Column(type: 'datetime_immutable')]
  private \DateTimeImmutable $generatedAt;

  public function __construct() {
    $this->generatedAt = new \DateTimeImmutable();
  }

  public function getId(): ?int {
    return $this->id;
  }

  public function getEntityId(): ?int {
    return $this->entityId;
  }

  public function setEntityId(int $i): self {
    $this->entityId = $i;
    return $this;
  }

  public function getTypeAlias(): ?EntityTypeAlias {
    return $this->typeAlias;
  }

  public function setTypeAlias(EntityTypeAlias $ta): self {
    $this->typeAlias = $ta;
    return $this;
  }

  public function getUrl(): ?string {
    return $this->url;
  }

  public function setUrl(string $url): self {
    $this->url = $url;
    return $this;
  }

  public function getGeneratedAt(): \DateTimeImmutable {
    return $this->generatedAt;
  }
}
