<?php
declare(strict_types = 1);

namespace Habeuk\UrlAliasBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Bundle de gestion des alias d'URL.
 *
 * Il isole le mecanisme d'alias du socle Habeuk\HbkSymfony : un projet qui n'a
 * pas besoin d'URL lisibles n'installe pas ce bundle et ne recupere donc ni les
 * entites EntityTypeAlias / EntityUrlAlias, ni leurs tables.
 */
final class UrlAliasBundle extends AbstractBundle {

  /**
   * Charge les services du bundle.
   *
   * {@inheritdoc}
   * @param array<mixed> $config
   * @see \Symfony\Component\HttpKernel\Bundle\AbstractBundle::loadExtension()
   */
  public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void {
    $container->import('../config/services.yaml');
  }

  /**
   * Declare le mapping Doctrine de AliasEntitiesBase.
   *
   * Cette classe est un #[ORM\MappedSuperclass] generique : il doit etre declare
   * cote bundle afin que l'application puisse l'etendre sans le redeclarer.
   * Les entites de ce bundle (src/Entity) sont, elles, mappees automatiquement
   * par l'auto_mapping de DoctrineBundle.
   *
   * {@inheritdoc}
   * @see \Symfony\Component\HttpKernel\Bundle\AbstractBundle::prependExtension()
   */
  public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void {
    $builder->prependExtensionConfig('doctrine', [
      'orm' => [
        'mappings' => [
          'HabeukUrlAliasBundle' => [
            'type' => 'attribute',
            'dir' => \dirname(__DIR__) . '/src/Shared/Doctrine',
            'prefix' => 'Habeuk\UrlAliasBundle\Shared\Doctrine',
            'is_bundle' => false
          ]
        ]
      ]
    ]);
  }

  /**
   * Configuration surchargeable par l'application.
   *
   * {@inheritdoc}
   * @see \Symfony\Component\HttpKernel\Bundle\AbstractBundle::configure()
   */
  public function configure(DefinitionConfigurator $definition): void {
    $definition->rootNode()
      ->children()
      ->integerNode('alias_max_attempts')
      ->defaultValue(100)
      ->min(1)
      ->info("Nombre maximum de tentatives lors de la generation d'une URL unique.")
      ->end()
      ->scalarNode('alias_controller')
      ->defaultNull()
      ->info("Controleur de resolution d'un chemin vers une entite (route catch-all).")
      ->end()
      ->end();
  }
}
