<?php

namespace Habeuk\UrlAliasBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Habeuk\UrlAliasBundle\Repository\EntityTypeAliasRepository;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Modele d'URL associe a un type d'entite.
 *
 * $model contient le gabarit a tokens, par exemple :
 *   "produit/{entity.slug}-{entity.id}"
 * Les tokens disponibles sont remplaces par TokenReplacer.
 */
#[ORM\Entity(repositoryClass: EntityTypeAliasRepository::class)]
class EntityTypeAlias {

  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\Column(length: 255, unique: true, nullable: true)]
  #[Assert\NotBlank]
  private ?string $entityClass = null;

  #[ORM\Column(length: 255, nullable: true)]
  #[Assert\NotBlank]
  private ?string $model = null;

  #[ORM\Column]
  private bool $isDefault = false;

  public function getId(): ?int {
    return $this->id;
  }

  public function getEntityClass(): ?string {
    return $this->entityClass;
  }

  public function setEntityClass(string $c): self {
    $this->entityClass = $c;
    return $this;
  }

  public function getModel(): ?string {
    return $this->model;
  }

  public function setModel(string $m): self {
    $this->model = $m;
    return $this;
  }

  public function isDefault(): bool {
    return $this->isDefault;
  }

  public function setIsDefault(bool $b): self {
    $this->isDefault = $b;
    return $this;
  }
}
