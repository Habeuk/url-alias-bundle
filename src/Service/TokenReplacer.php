<?php
declare(strict_types = 1);
namespace Habeuk\UrlAliasBundle\Service;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Remplace les tokens d'un modele d'URL par les valeurs d'une entite.
 *
 * @author stephane
 */
class TokenReplacer {

  /**
   * Accesseur utilise pour lire les proprietes de l'entite.
   */
  private PropertyAccessorInterface $accessor;

  public function __construct(private SluggerInterface $slugger) {
    $this->accessor = PropertyAccess::createPropertyAccessor();
  }

  /**
   * Remplace les tokens dans le modele par les valeurs de l'entite.
   * Exemple : 'services/{entity.name}' => 'services/informatique'
   */
  public function replaceTokens(string $model, object $entity): string {
    $matches = [];
    // Expression regex pour trouver tous les tokens {entity.xxx}
    preg_match_all('/\{entity(?:\.([a-zA-Z0-9_\.]+))\}/', $model, $matches);

    foreach ($matches[1] as $propertyPath) {
      $replacement = '';

      if ($this->accessor->isReadable($entity, $propertyPath)) {
        $value = $this->accessor->getValue($entity, $propertyPath);
        $replacement = $value !== null ? $this->slugger->slug((string) $value)
          ->lower()
          ->toString() : '';
      }

      $model = str_replace('{entity.' . $propertyPath . '}', $replacement, $model);
    }

    return trim($model, '/');
  }
}
