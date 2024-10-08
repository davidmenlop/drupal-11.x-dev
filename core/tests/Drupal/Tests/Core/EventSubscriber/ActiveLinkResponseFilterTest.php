<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\Core\EventSubscriber\ActiveLinkResponseFilter;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Template\Attribute;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

// cspell:ignore svenska

/**
 * @coversDefaultClass \Drupal\Core\EventSubscriber\ActiveLinkResponseFilter
 * @group EventSubscriber
 * @group #slow
 */
class ActiveLinkResponseFilterTest extends UnitTestCase {

  /**
   * Provides test data for testSetLinkActiveClass().
   *
   * @see \Drupal\Core\EventSubscriber\ActiveLinkResponseFilter::setLinkActiveClass()
   */
  public static function providerTestSetLinkActiveClass() {
    // Define all the variations that *don't* affect whether or not an
    // "is-active" class is set, but that should remain unchanged:
    // - surrounding HTML
    // - tags for which to test the setting of the "is-active" class
    // - content of said tags
    $edge_case_html5 = '<audio src="foo.ogg">
  <track kind="captions" src="foo.en.vtt" srclang="en" label="English">
  <track kind="captions" src="foo.sv.vtt" srclang="sv" label="Svenska">
</audio>';
    $html = [
      // Simple HTML.
      0 => ['prefix' => '<div><p>', 'suffix' => '</p></div>'],
      // Tricky HTML5 example that's unsupported by PHP <=5.4's DOMDocument:
      // https://www.drupal.org/comment/7938201#comment-7938201.
      1 => ['prefix' => '<div><p>', 'suffix' => '</p>' . $edge_case_html5 . '</div>'],
      // Multi-byte content *before* the HTML that needs the "is-active" class.
      // cSpell:disable-next-line
      2 => ['prefix' => '<div><p>αβγδεζηθικλμνξοσὠ</p><p>', 'suffix' => '</p></div>'],
    ];
    $tags = [
      // Of course, it must work on anchors.
      'a',
      // Unfortunately, it must also work on list items.
      'li',
      // … and therefore, on *any* tag, really.
      'foo',
    ];
    $contents = [
      // Regular content.
      'test',
      // Mix of UTF-8 and HTML entities, both must be retained.
      '☆ 3 × 4 = €12 and 4 &times; 3 = &euro;12 &#9734',
      // Multi-byte content.
      // cSpell:disable-next-line
      'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΣὨ',
      // Text that closely approximates an important attribute, but should be
      // ignored.
      'data-drupal-link-system-path=&quot;&lt;front&gt;&quot;',
    ];

    // Define all variations that *do* affect whether or not an "is-active"
    // class is set: all possible situations that can be encountered.
    $situations = [];

    // Situations with context: front page, English, no query.
    $context = [
      'path' => 'my-front-page',
      'front' => TRUE,
      'language' => 'en',
      'query' => [],
    ];
    // Nothing to do.
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => []];
    // Matching path, plus all matching variations.
    $attributes = [
      'data-drupal-link-system-path' => 'my-front-page',
    ];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['aria-current' => 'page']];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['hreflang' => 'en', 'aria-current' => 'page']];
    // Matching path, plus all non-matching variations.
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => '{"foo":"bar"}']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => TRUE]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => '{"foo":"bar"}']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => TRUE]];
    // Special matching path, plus all variations.
    $attributes = [
      'data-drupal-link-system-path' => '<front>',
    ];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['aria-current' => 'page']];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['hreflang' => 'en', 'aria-current' => 'page']];
    // Special matching path, plus all non-matching variations.
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => '{"foo":"bar"}']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => TRUE]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => '{"foo":"bar"}']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => TRUE]];

    // Situations with context: non-front page, Dutch, no query.
    $context = [
      'path' => 'llama',
      'front' => FALSE,
      'language' => 'nl',
      'query' => [],
    ];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => []];
    // Matching path, plus all matching variations.
    $attributes = [
      'data-drupal-link-system-path' => 'llama',
    ];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['aria-current' => 'page']];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['hreflang' => 'nl', 'aria-current' => 'page']];
    // Matching path, plus all non-matching variations.
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => '{"foo":"bar"}']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => TRUE]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => '{"foo":"bar"}']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => TRUE]];
    // Special non-matching path, plus all variations.
    $attributes = [
      'data-drupal-link-system-path' => '<front>',
    ];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => '{"foo":"bar"}']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => TRUE]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => '{"foo":"bar"}']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => TRUE]];

    // Situations with context: non-front page, Dutch, with query.
    $context = [
      'path' => 'llama',
      'front' => FALSE,
      'language' => 'nl',
      'query' => ['foo' => 'bar'],
    ];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => []];
    // Matching path, plus all matching variations.
    $attributes = [
      'data-drupal-link-system-path' => 'llama',
    ];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['aria-current' => 'page', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['hreflang' => 'nl', 'aria-current' => 'page', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    // Matching path, plus all non-matching variations.
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => TRUE]];
    // Special non-matching path, plus all variations.
    $attributes = [
      'data-drupal-link-system-path' => '<front>',
    ];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => TRUE]];

    // Situations with context: non-front page, Dutch, with query.
    $context = [
      'path' => 'llama',
      'front' => FALSE,
      'language' => 'nl',
      'query' => ['foo' => 'bar'],
    ];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => []];
    // Matching path, plus all matching variations.
    $attributes = [
      'data-drupal-link-system-path' => 'llama',
    ];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['aria-current' => 'page', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['hreflang' => 'nl', 'aria-current' => 'page', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    // Matching path, plus all non-matching variations.
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => TRUE]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => TRUE]];
    // Special non-matching path, plus all variations.
    $attributes = [
      'data-drupal-link-system-path' => '<front>',
    ];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en']];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => TRUE]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => TRUE]];

    // Situations with context: front page, English, query.
    $context = [
      'path' => 'my-front-page',
      'front' => TRUE,
      'language' => 'en',
      'query' => ['foo' => 'bar'],
    ];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => []];
    // Matching path, plus all matching variations.
    $attributes = [
      'data-drupal-link-system-path' => 'my-front-page',
    ];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['aria-current' => 'page', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['hreflang' => 'en', 'aria-current' => 'page', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    // Matching path, plus all non-matching variations.
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => TRUE]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => TRUE]];
    // Special matching path, plus all variations.
    $attributes = [
      'data-drupal-link-system-path' => '<front>',
    ];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['aria-current' => 'page', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['hreflang' => 'en', 'aria-current' => 'page', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    // Special matching path, plus all non-matching variations.
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'nl', 'data-drupal-link-query' => Json::encode(['foo' => 'bar'])]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['data-drupal-link-query' => TRUE]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => ""]];
    $situations[] = ['context' => $context, 'is active' => FALSE, 'attributes' => $attributes + ['hreflang' => 'en', 'data-drupal-link-query' => TRUE]];

    // Query with unsorted keys must match when the attribute is in sorted form.
    $context = [
      'path' => 'my-front-page',
      'front' => TRUE,
      'language' => 'en',
      'query' => ['foo' => 'bar', 'baz' => 'qux'],
    ];
    $attributes = [
      'data-drupal-link-system-path' => 'my-front-page',
      'data-drupal-link-query' => Json::encode(['baz' => 'qux', 'foo' => 'bar']),
    ];
    $situations[] = ['context' => $context, 'is active' => TRUE, 'attributes' => $attributes + ['aria-current' => 'page']];

    // Loop over the surrounding HTML variations.
    $data = [];
    for ($h = 0; $h < count($html); $h++) {
      $html_prefix = $html[$h]['prefix'];
      $html_suffix = $html[$h]['suffix'];
      // Loop over the tag variations.
      for ($t = 0; $t < count($tags); $t++) {
        $tag = $tags[$t];
        // Loop over the tag contents variations.
        for ($c = 0; $c < count($contents); $c++) {
          $tag_content = $contents[$c];

          $create_markup = function (Attribute $attributes) use ($html_prefix, $html_suffix, $tag, $tag_content) {
            return $html_prefix . '<' . $tag . $attributes . '>' . $tag_content . '</' . $tag . '>' . $html_suffix;
          };

          // Loop over the situations.
          for ($s = 0; $s < count($situations); $s++) {
            $situation = $situations[$s];

            // Build the source markup.
            $source_markup = $create_markup(new Attribute($situation['attributes']));

            // Build the target markup. If no "is-active" class should be set,
            // the resulting HTML should be identical. Otherwise, it should get
            // an "is-active" class, either by extending an existing "class"
            // attribute or by adding a "class" attribute.
            $target_markup = NULL;
            if (!$situation['is active']) {
              $target_markup = $source_markup;
            }
            else {
              $active_attributes = $situation['attributes'];
              if (!isset($active_attributes['class'])) {
                $active_attributes['class'] = [];
              }
              $active_attributes['class'][] = 'is-active';
              $target_markup = $create_markup(new Attribute($active_attributes));
            }

            $data[] = [$source_markup, $situation['context']['path'], $situation['context']['front'], $situation['context']['language'], $situation['context']['query'], $target_markup];
          }
        }
      }
    }

    // Test case to verify that the 'is-active' class is not added multiple
    // times.
    $data[] = [
      0 => '<a data-drupal-link-system-path="&lt;front&gt;">Once</a> <a data-drupal-link-system-path="&lt;front&gt;">Twice</a>',
      1 => '',
      2 => TRUE,
      3 => 'en',
      4 => [],
      5 => '<a data-drupal-link-system-path="&lt;front&gt;" class="is-active" aria-current="page">Once</a> <a data-drupal-link-system-path="&lt;front&gt;" class="is-active" aria-current="page">Twice</a>',
    ];

    // Test cases to verify that the 'is-active' class is added when on the
    // front page, and there are two different kinds of matching links on the
    // page:
    // - the matching path (the resolved front page path)
    // - the special matching path ('<front>')
    $front_special_link = '<a data-drupal-link-system-path="&lt;front&gt;">Front</a>';
    $front_special_link_active = '<a data-drupal-link-system-path="&lt;front&gt;" class="is-active" aria-current="page">Front</a>';
    $front_path_link = '<a data-drupal-link-system-path="my-front-page">Front Path</a>';
    $front_path_link_active = '<a data-drupal-link-system-path="my-front-page" class="is-active" aria-current="page">Front Path</a>';
    $data[] = [
      0 => $front_path_link . ' ' . $front_special_link,
      1 => 'my-front-page',
      2 => TRUE,
      3 => 'en',
      4 => [],
      5 => $front_path_link_active . ' ' . $front_special_link_active,
    ];
    $data[] = [
      0 => $front_special_link . ' ' . $front_path_link,
      1 => 'my-front-page',
      2 => TRUE,
      3 => 'en',
      4 => [],
      5 => $front_special_link_active . ' ' . $front_path_link_active,
    ];

    // Test cases to verify that links to the front page do not get the
    // 'is-active' class when not on the front page.
    $other_link = '<a data-drupal-link-system-path="other-page">Other page</a>';
    $other_link_active = '<a data-drupal-link-system-path="other-page" class="is-active" aria-current="page">Other page</a>';
    $data['<front>-and-other-link-on-other-path'] = [
      0 => $front_special_link . ' ' . $other_link,
      1 => 'other-page',
      2 => FALSE,
      3 => 'en',
      4 => [],
      5 => $front_special_link . ' ' . $other_link_active,
    ];
    $data['front-and-other-link-on-other-path'] = [
      0 => $front_path_link . ' ' . $other_link,
      1 => 'other-page',
      2 => FALSE,
      3 => 'en',
      4 => [],
      5 => $front_path_link . ' ' . $other_link_active,
    ];
    $data['other-and-<front>-link-on-other-path'] = [
      0 => $other_link . ' ' . $front_special_link,
      1 => 'other-page',
      2 => FALSE,
      3 => 'en',
      4 => [],
      5 => $other_link_active . ' ' . $front_special_link,
    ];
    $data['other-and-front-link-on-other-path'] = [
      0 => $other_link . ' ' . $front_path_link,
      1 => 'other-page',
      2 => FALSE,
      3 => 'en',
      4 => [],
      5 => $other_link_active . ' ' . $front_path_link,
    ];
    return $data;
  }

  /**
   * Tests setLinkActiveClass().
   *
   * @param string $html_markup
   *   The original HTML markup.
   * @param string $current_path
   *   The system path of the currently active page.
   * @param bool $is_front
   *   Whether the current page is the front page (which implies the current
   *   path might also be <front>).
   * @param string $url_language
   *   The language code of the current URL.
   * @param array $query
   *   The query string for the current URL.
   * @param string $expected_html_markup
   *   The expected updated HTML markup.
   *
   * @dataProvider providerTestSetLinkActiveClass
   * @covers ::setLinkActiveClass
   */
  public function testSetLinkActiveClass($html_markup, $current_path, $is_front, $url_language, array $query, $expected_html_markup): void {
    $this->assertSame($expected_html_markup, ActiveLinkResponseFilter::setLinkActiveClass($html_markup, $current_path, $is_front, $url_language, $query));
  }

  /**
   * Tests ActiveLinkResponseFilter only affects HTML responses.
   *
   * @covers ::onResponse
   */
  public function testOnlyHtml(): void {
    $session = new AnonymousUserSession();
    $language_manager = new LanguageManager(new LanguageDefault([]));
    $request_stack = new RequestStack();
    $request_stack->push(new Request());
    $current_path_stack = new CurrentPathStack($request_stack);

    // Make sure path matcher isn't called and we didn't get to the link logic.
    $path_matcher = $this->prophesize(PathMatcherInterface::class);
    $path_matcher->isFrontPage()->shouldNotBeCalled();

    $subscriber = new ActiveLinkResponseFilter(
      $session,
      $current_path_stack,
      $path_matcher->reveal(),
      $language_manager
    );

    // A link that might otherwise be set 'active'.
    $content = '<a data-drupal-link-system-path="other-page">Other page</a>';

    // Assert response with non-html content type gets ignored.
    $response = new Response();
    $response->setContent($content);
    $response->headers->get('Content-Type', 'application/json');
    $subscriber->onResponse(new ResponseEvent(
      $this->prophesize(KernelInterface::class)->reveal(),
      $request_stack->getCurrentRequest(),
      HttpKernelInterface::MAIN_REQUEST,
      $response
    ));
    $this->assertSame($response->getContent(), $content);
  }

  /**
   * Tests certain response types ignored by the ActiveLinkResponseFilter.
   *
   * @covers ::onResponse
   */
  public function testSkipCertainResponseTypes(): void {
    $session = new AnonymousUserSession();
    $language_manager = new LanguageManager(new LanguageDefault([]));
    $request_stack = new RequestStack();
    $request_stack->push(new Request());
    $current_path_stack = new CurrentPathStack($request_stack);

    // Ensure path matcher is not called. This also tests that the
    // ActiveLinkResponseFilter ignores the response.
    $path_matcher = $this->prophesize(PathMatcherInterface::class);
    $path_matcher->isFrontPage()->shouldNotBeCalled();

    $subscriber = new ActiveLinkResponseFilter(
      $session,
      $current_path_stack,
      $path_matcher->reveal(),
      $language_manager
    );

    // Test BinaryFileResponse is ignored. Calling setContent() would throw a
    // logic exception.
    $response = new BinaryFileResponse(__FILE__, 200, ['Content-Type' => 'text/html']);
    $subscriber->onResponse(new ResponseEvent(
      $this->prophesize(KernelInterface::class)->reveal(),
      $request_stack->getCurrentRequest(),
      HttpKernelInterface::MAIN_REQUEST,
      $response
    ));

    // Test StreamedResponse is ignored. Calling setContent() would throw a
    // logic exception.
    $response = new StreamedResponse(function () {
      echo 'Success!';
    }, 200, ['Content-Type' => 'text/html']);
    $subscriber->onResponse(new ResponseEvent(
      $this->prophesize(KernelInterface::class)->reveal(),
      $request_stack->getCurrentRequest(),
      HttpKernelInterface::MAIN_REQUEST,
      $response
    ));
  }

}
