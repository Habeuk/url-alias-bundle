<?php
namespace Habeuk\UrlAliasBundle\Shared\Doctrine\Traits;

trait RecordsEventsTrait {

  /**
   *
   * @var array<object>
   */
  private array $domainEvents = [];

  public function recordEvent(object $event): void {
    $this->domainEvents[] = $event;
  }

  /**
   *
   * {@inheritdoc}
   * @see \Habeuk\UrlAliasBundle\Contract\RecordsEventsInterface::pullDomainEvents()
   * @return array<object>
   */
  public function pullDomainEvents(): array {
    $events = $this->domainEvents;
    $this->domainEvents = [];
    return $events;
  }

  public function clearDomainEvents(): void {
    $this->domainEvents = [];
  }
}
