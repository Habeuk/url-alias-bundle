<?php
declare(strict_types = 1);
namespace Habeuk\UrlAliasBundle\Shared\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Habeuk\HbkSymfony\Contract\ {
  AliasableEntityInterface,
  TimestampableInterface
};
use Habeuk\HbkSymfony\Shared\Doctrine\AbstractBaseEntity;
use Habeuk\UrlAliasBundle\Event\UrlAliasUpdatedEvent;
use Habeuk\UrlAliasBundle\Contract\RecordsEventsInterface;

/**
 * Classe de base des entites portant un alias d'URL.
 *
 * Elle porte deux valeurs qui doivent rester synchronisees avec le gestionnaire
 * d'alias (EntityUrlAlias) :
 * - $urlAlias : la derniere URL connue, stockee sur l'entite afin d'eviter une
 * requete supplementaire lors de l'affichage ;
 * - $autoGenerateAlias : qui decide de l'URL (le gestionnaire ou le contenu).
 *
 * L'entite finale doit porter #[ORM\HasLifecycleCallbacks] (les callbacks de
 * TimestampableTrait ne sont pas herites depuis un MappedSuperclass).
 */
#[ORM\MappedSuperclass]
abstract class AliasEntitiesBase extends AbstractBaseEntity implements RecordsEventsInterface, TimestampableInterface, AliasableEntityInterface {

  /**
   * Cette valeur doit etre synchronisee avec le gestionnaire d'alias.
   * Le but est d'avoir un systeme qui necessite le moins de requetes en bd.
   */
  #[ORM\Column(type: 'string', length: 255, nullable: true)]
  private ?string $urlAlias = null;

  /**
   * Permet de determiner si l'url est geree par l'entite ou par le gestionnaire
   * d'url.
   */
  #[ORM\Column(options: [
    'default' => true
  ])]
  private bool $autoGenerateAlias = true;

  public function getUrlAlias(): ?string {
    return $this->urlAlias;
  }

  /**
   * On a un risque de boucle a ce niveau.
   * Utiliser $dispatch pour ne pas emettre d'evenement.
   *
   * {@inheritdoc}
   * @see \Habeuk\HbkSymfony\Contract\AliasableEntityInterface::setUrlAlias()
   */
  public function setUrlAlias(?string $alias, bool $dispatch = true): static {
    if ($this->urlAlias !== $alias) {
      $this->urlAlias = $alias;
      // On stocke les entites donc on doit mettre a jour les URLs.
      if ($dispatch && ! $this->isAutoGenerateAlias())
        $this->recordEvent(new UrlAliasUpdatedEvent($this));
    }
    return $this;
  }

  public function isAutoGenerateAlias(): bool {
    return $this->autoGenerateAlias;
  }

  public function setAutoGenerateAlias(bool $generate): static {
    $this->autoGenerateAlias = $generate;
    return $this;
  }
}
