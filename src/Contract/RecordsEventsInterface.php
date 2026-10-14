<?php
namespace Habeuk\UrlAliasBundle\Contract;

interface RecordsEventsInterface {

  public function recordEvent(object $event): void;

  /**
   * Récupère et vide les événements en attente.
   *
   * @return array<object>
   */
  public function pullDomainEvents(): array;

  public function clearDomainEvents(): void;
}
