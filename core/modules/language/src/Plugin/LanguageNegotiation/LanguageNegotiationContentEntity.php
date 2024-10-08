<?php

namespace Drupal\language\Plugin\LanguageNegotiation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\language\Attribute\LanguageNegotiation;
use Drupal\language\LanguageNegotiationMethodBase;
use Drupal\language\LanguageSwitcherInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Class for identifying the content translation language.
 */
#[LanguageNegotiation(
  id: LanguageNegotiationContentEntity::METHOD_ID,
  name: new TranslatableMarkup('Content language'),
  types: [LanguageInterface::TYPE_CONTENT],
  weight: -9,
  description: new TranslatableMarkup("Determines the content language from the request parameter named 'language_content_entity'.")
)]
class LanguageNegotiationContentEntity extends LanguageNegotiationMethodBase implements OutboundPathProcessorInterface, LanguageSwitcherInterface, ContainerFactoryPluginInterface {

  /**
   * The language negotiation method ID.
   */
  const METHOD_ID = 'language-content-entity';

  /**
   * The query string parameter.
   */
  const QUERY_PARAMETER = 'language_content_entity';

  /**
   * A list of all the link paths of enabled content entities.
   *
   * @var array
   */
  protected $contentEntityPaths;

  /**
   * Static cache for the language negotiation order check.
   *
   * @var bool
   *
   * @see \Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationContentEntity::hasLowerLanguageNegotiationWeight()
   */
  protected $hasLowerLanguageNegotiationWeightResult;

  /**
   * Static cache of outbound route paths per request.
   *
   * @var \SplObjectStorage
   */
  protected $paths;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LanguageNegotiationContentEntity instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->paths = new \SplObjectStorage();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(?Request $request = NULL) {
    if ($request === NULL || $this->languageManager === NULL) {
      return NULL;
    }

    $langcode = $request->query->get(static::QUERY_PARAMETER);

    $language_enabled = array_key_exists($langcode, $this->languageManager->getLanguages());
    return $language_enabled ? $langcode : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    // If appropriate, process outbound to add a query parameter to the URL and
    // remove the language option, so that URL negotiator does not rewrite the
    // URL.

    // First, check if processing conditions are met.
    if (!($request && !empty($options['route']) && $this->hasLowerLanguageNegotiationWeight() && $this->meetsContentEntityRoutesCondition($options['route'], $request))) {
      return $path;
    }

    if (isset($options['language']) || $langcode = $this->getLangcode($request)) {
      // If the language option is set, unset it, so that the URL language
      // negotiator does not rewrite the URL.
      if (isset($options['language'])) {
        $langcode = $options['language']->getId();
        unset($options['language']);
      }

      if (!isset($options['query'][static::QUERY_PARAMETER])) {
        $options['query'][static::QUERY_PARAMETER] = $langcode;
      }

      if ($bubbleable_metadata) {
        // Cached URLs that have been processed by this outbound path
        // processor must be:
        $bubbleable_metadata
          // - varied by the content language query parameter.
          ->addCacheContexts(['url.query_args:' . static::QUERY_PARAMETER]);
      }
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageSwitchLinks(Request $request, $type, Url $url) {
    $links = [];
    $query = [];
    parse_str($request->getQueryString() ?? '', $query);

    foreach ($this->languageManager->getNativeLanguages() as $language) {
      $langcode = $language->getId();
      $query[static::QUERY_PARAMETER] = $langcode;
      $links[$langcode] = [
        'url' => $url,
        'title' => $language->getName(),
        'attributes' => ['class' => ['language-link']],
        'query' => $query,
      ];
    }

    return $links;
  }

  /**
   * Determines if content entity language negotiator has higher priority.
   *
   * The content entity language negotiator having higher priority than the URL
   * language negotiator, is a criteria in
   * \Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationContentEntity::processOutbound().
   *
   * @return bool
   *   TRUE if the content entity language negotiator has higher priority than
   *   the URL language negotiator, FALSE otherwise.
   */
  protected function hasLowerLanguageNegotiationWeight() {
    if (!isset($this->hasLowerLanguageNegotiationWeightResult)) {
      // Only run if the LanguageNegotiationContentEntity outbound function is
      // being executed before the outbound function of LanguageNegotiationUrl.
      $content_method_weights = $this->config->get('language.types')->get('negotiation.language_content.enabled') ?: [];

      // Check if the content language is configured to be dependent on the
      // URL negotiator directly or indirectly over the interface negotiator.
      if (isset($content_method_weights[LanguageNegotiationUrl::METHOD_ID]) && ($content_method_weights[static::METHOD_ID] > $content_method_weights[LanguageNegotiationUrl::METHOD_ID])) {
        $this->hasLowerLanguageNegotiationWeightResult = FALSE;
      }
      else {
        $check_interface_method = FALSE;
        if (isset($content_method_weights[LanguageNegotiationUI::METHOD_ID])) {
          $interface_method_weights = $this->config->get('language.types')->get('negotiation.language_interface.enabled') ?: [];
          $check_interface_method = isset($interface_method_weights[LanguageNegotiationUrl::METHOD_ID]);
        }
        if ($check_interface_method) {
          $max_weight = $content_method_weights[LanguageNegotiationUI::METHOD_ID];
          $max_weight = isset($content_method_weights[LanguageNegotiationUrl::METHOD_ID]) ? max($max_weight, $content_method_weights[LanguageNegotiationUrl::METHOD_ID]) : $max_weight;
        }
        else {
          $max_weight = $content_method_weights[LanguageNegotiationUrl::METHOD_ID] ?? PHP_INT_MAX;
        }

        $this->hasLowerLanguageNegotiationWeightResult = $content_method_weights[static::METHOD_ID] < $max_weight;
      }
    }

    return $this->hasLowerLanguageNegotiationWeightResult;
  }

  /**
   * Determines if content entity route condition is met.
   *
   * Requirements: currently being on a content entity route and processing
   * outbound URL pointing to the same content entity.
   *
   * @param \Symfony\Component\Routing\Route $outbound_route
   *   The route object for the current outbound URL being processed.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HttpRequest object representing the current request.
   *
   * @return bool
   *   TRUE if the content entity route condition is met, FALSE otherwise.
   */
  protected function meetsContentEntityRoutesCondition(Route $outbound_route, Request $request) {
    $outbound_path_pattern = $outbound_route->getPath();
    $storage = $this->paths[$request] ?? [];
    if (!isset($storage[$outbound_path_pattern])) {
      $storage[$outbound_path_pattern] = FALSE;

      // Check if the outbound route points to the current entity.
      if ($content_entity_type_id_for_current_route = $this->getContentEntityTypeIdForCurrentRequest($request)) {
        if (!empty($this->getContentEntityPaths()[$outbound_path_pattern]) && $content_entity_type_id_for_current_route == $this->getContentEntityPaths()[$outbound_path_pattern]) {
          $storage[$outbound_path_pattern] = TRUE;
        }
      }

      $this->paths[$request] = $storage;
    }

    return $storage[$outbound_path_pattern];
  }

  /**
   * Returns the content entity type ID from the current request for the route.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HttpRequest object representing the current request.
   *
   * @return string
   *   The entity type ID for the route from the request.
   */
  protected function getContentEntityTypeIdForCurrentRequest(Request $request) {
    $content_entity_type_id_for_current_route = '';

    if ($current_route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT)) {
      $current_route_path = $current_route->getPath();
      $content_entity_type_id_for_current_route = $this->getContentEntityPaths()[$current_route_path] ?? '';
    }

    return $content_entity_type_id_for_current_route;
  }

  /**
   * Returns the paths for the link templates of all content entities.
   *
   * @return array
   *   An array of all content entity type IDs, keyed by the corresponding link
   *   template paths.
   */
  protected function getContentEntityPaths() {
    if (!isset($this->contentEntityPaths)) {
      $this->contentEntityPaths = [];
      $entity_types = $this->entityTypeManager->getDefinitions();
      foreach ($entity_types as $entity_type_id => $entity_type) {
        if ($entity_type->entityClassImplements(ContentEntityInterface::class)) {
          $entity_paths = array_fill_keys($entity_type->getLinkTemplates(), $entity_type_id);
          $this->contentEntityPaths = array_merge($this->contentEntityPaths, $entity_paths);
        }
      }
    }

    return $this->contentEntityPaths;
  }

}
