<?php
declare(strict_types = 1);

namespace Habeuk\UrlAliasBundle\Event;

use Habeuk\UrlAliasBundle\Shared\Doctrine\AliasEntitiesBase;

/**
 * Evenement emis lorsqu'un alias d'URL doit etre (re)genere pour une entite.
 *
 * @author stephane
 */
class UrlAliasUpdatedEvent {

  public function __construct(public readonly AliasEntitiesBase $entity) {}

  public function getEntity(): AliasEntitiesBase {
    return $this->entity;
  }
}
