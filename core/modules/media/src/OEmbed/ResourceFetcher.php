<?php

namespace Drupal\media\OEmbed;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;

// cspell:ignore nocdata

/**
 * Fetches and caches oEmbed resources.
 */
class ResourceFetcher implements ResourceFetcherInterface {

  /**
   * Constructs a ResourceFetcher object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\media\OEmbed\ProviderRepositoryInterface $providers
   *   The oEmbed provider repository service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   * @param int $timeout
   *   The length of time to wait for the request before the request
   *   should time out.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    protected ProviderRepositoryInterface $providers,
    protected CacheBackendInterface $cacheBackend,
    protected int $timeout = 5,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function fetchResource($url) {
    $cache_id = "media:oembed_resource:$url";

    $cached = $this->cacheBackend->get($cache_id);
    if ($cached) {
      return $this->createResource($cached->data, $url);
    }

    try {
      $response = $this->httpClient->request('GET', $url, [
        RequestOptions::TIMEOUT => $this->timeout,
      ]);
    }
    catch (ClientExceptionInterface $e) {
      throw new ResourceException('Could not retrieve the oEmbed resource.', $url, [], $e);
    }

    [$format] = $response->getHeader('Content-Type');
    $content = (string) $response->getBody();

    if (strstr($format, 'text/xml') || strstr($format, 'application/xml')) {
      $data = $this->parseResourceXml($content, $url);
    }
    // By default, try to parse the resource data as JSON.
    else {
      $data = Json::decode($content);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new ResourceException('Error decoding oEmbed resource: ' . json_last_error_msg(), $url);
      }
    }
    if (empty($data) || !is_array($data)) {
      throw new ResourceException('The oEmbed resource could not be decoded.', $url);
    }

    $this->cacheBackend->set($cache_id, $data);

    return $this->createResource($data, $url);
  }

  /**
   * Creates a Resource object from raw resource data.
   *
   * @param array $data
   *   The resource data returned by the provider.
   * @param string $url
   *   The URL of the resource.
   *
   * @return \Drupal\media\OEmbed\Resource
   *   A value object representing the resource.
   *
   * @throws \Drupal\media\OEmbed\ResourceException
   *   If the resource cannot be created.
   */
  protected function createResource(array $data, $url) {
    $data += [
      'title' => NULL,
      'author_name' => NULL,
      'author_url' => NULL,
      'provider_name' => NULL,
      'cache_age' => NULL,
      'thumbnail_url' => NULL,
      'thumbnail_width' => NULL,
      'thumbnail_height' => NULL,
      'width' => NULL,
      'height' => NULL,
      'url' => NULL,
      'html' => NULL,
      'version' => NULL,
    ];

    if ($data['version'] !== '1.0') {
      throw new ResourceException("Resource version must be '1.0'", $url, $data);
    }

    // Prepare the arguments to pass to the factory method.
    $provider = $data['provider_name'] ? $this->providers->get($data['provider_name']) : NULL;

    // The Resource object will validate the data we create it with and throw an
    // exception if anything looks wrong. For better debugging, catch those
    // exceptions and wrap them in a more specific and useful exception.
    try {
      switch ($data['type']) {
        case Resource::TYPE_LINK:
          return Resource::link(
            $data['url'],
            $provider,
            $data['title'],
            $data['author_name'],
            $data['author_url'],
            $data['cache_age'],
            $data['thumbnail_url'],
            $data['thumbnail_width'],
            $data['thumbnail_height']
          );

        case Resource::TYPE_PHOTO:
          return Resource::photo(
            $data['url'],
            $data['width'],
            $data['height'],
            $provider,
            $data['title'],
            $data['author_name'],
            $data['author_url'],
            $data['cache_age'],
            $data['thumbnail_url'],
            $data['thumbnail_width'],
            $data['thumbnail_height']
          );

        case Resource::TYPE_RICH:
          return Resource::rich(
            $data['html'],
            $data['width'],
            $data['height'],
            $provider,
            $data['title'],
            $data['author_name'],
            $data['author_url'],
            $data['cache_age'],
            $data['thumbnail_url'],
            $data['thumbnail_width'],
            $data['thumbnail_height']
          );

        case Resource::TYPE_VIDEO:
          return Resource::video(
            $data['html'],
            $data['width'],
            $data['height'],
            $provider,
            $data['title'],
            $data['author_name'],
            $data['author_url'],
            $data['cache_age'],
            $data['thumbnail_url'],
            $data['thumbnail_width'],
            $data['thumbnail_height']
          );

        default:
          throw new ResourceException('Unknown resource type: ' . $data['type'], $url, $data);
      }
    }
    catch (\InvalidArgumentException $e) {
      throw new ResourceException($e->getMessage(), $url, $data, $e);
    }
  }

  /**
   * Parses XML resource data.
   *
   * @param string $data
   *   The raw XML for the resource.
   * @param string $url
   *   The resource URL.
   *
   * @return array
   *   The parsed resource data.
   *
   * @throws \Drupal\media\OEmbed\ResourceException
   *   If the resource data could not be parsed.
   */
  protected function parseResourceXml($data, $url) {
    // Enable userspace error handling.
    $was_using_internal_errors = libxml_use_internal_errors(TRUE);
    libxml_clear_errors();

    $content = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
    // Restore the previous error handling behavior.
    libxml_use_internal_errors($was_using_internal_errors);

    $error = libxml_get_last_error();
    if ($error) {
      libxml_clear_errors();
      throw new ResourceException($error->message, $url);
    }
    elseif ($content === FALSE) {
      throw new ResourceException('The fetched resource could not be parsed.', $url);
    }

    // Convert XML to JSON so that the parsed resource has a consistent array
    // structure, regardless of any XML attributes or quirks of the XML parser.
    $data = Json::encode($content);
    return Json::decode($data);
  }

}
