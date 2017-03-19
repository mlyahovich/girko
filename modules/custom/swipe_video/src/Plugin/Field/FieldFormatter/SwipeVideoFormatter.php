<?php

/**
 * @file
 * Contains \Drupal\swipe_video\Plugin\Field\FieldFormatter\SwipeVideoFormatter.
 */

namespace Drupal\swipe_video\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'photoswipe_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "swipe_video_formatter",
 *   label = @Translation("Swipe Video"),
 *   field_types = {
 *     "video_embed_field"
 *   }
 * )
 */
class SwipeVideoFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    if (!empty($items)) {
      $elements = array(
        '#attributes' => array('class' => array('photoswipe-gallery')),
      );
      $elements['#attached']['library'][] = 'swipe_video/swipe_video.init';
    }

    foreach ($items as $delta => $item) {
      $id = self::getVideoData($item->value);
      $elements[$delta] = array(
        '#theme' => 'photoswipe_video_formatter',
        '#item' => $id['id'],
        '#delta' => $delta,
        '#img' => $id['img'],
        '#host' => $id['host'],
        '#display_settings' => [],
      );
    }
    return $elements;
  }

  /**
   * @param $url
   *  Video link
   *
   * @return array
   *  Return host, id and url image for vimeo.com.
   */
  public function getVideoData($url) {
    $id = 0;
    $data_url = '';
    $parse = parse_url($url);
    if ($parse['host'] == 'www.youtube.com') {
      $id = explode('=', $parse['query'])[1];
    }
    elseif ($parse['host'] == 'vimeo.com') {
      $id = str_replace('/', '', $parse['path']);
      $data = file_get_contents("http://vimeo.com/api/v2/video/" . $id . ".json");
      $data = json_decode($data);
      $data_url = $data[0]->thumbnail_large;
    }
    return ['host' => $parse['host'], 'id' => $id, 'img' => $data_url];
  }

}
