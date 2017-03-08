<?php

namespace Drupal\import;

use Drupal\field_collection\Entity\FieldCollectionItem;
use Drupal\node\Entity\Node;

/**
 * Provides a import form.
 */
class importBatchProcess {

  /**
   * @param $success
   * @param $results
   * @param $operations
   */
  public static function createNodeFinishedCallback($success, $results, $operations) {
    if ($success) {
      $message = \Drupal::translation()
        ->formatPlural(count($results), 'One post processed.', '@count posts processed.');
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }

  /**
   * @param $entity_type_id
   * @param $item
   * @param $params
   * @param $content_type
   */
  public static function paramCreate($entity_type_id, $item, &$params, $content_type) {
    switch ($item['field_type']) {
      case 'text_with_summary':
        $params[$item['field']] = [
          'value' => html_entity_decode($item['data'], HTML_ENTITIES),
          'format' => 'full_html',
        ];
        break;
      case 'comment':
        $params[$item['field']] = $item['data'];
        break;
      case 'boolean':
        $params[$item['field']] = ['value' => $item['data']];
        break;
      case 'video_embed_field':
        $video_list = [];
        $videos = explode(',', $item['data']);
        if (count($videos) > 1) {
          foreach ($videos as $video) {
            $video_list[] = ['value' => $video];
          }
        }
        else {
          $video_list = ['value' => $item['data']];
        }
        $params[$item['field']] = $video_list;
        break;
      case 'link':
        $data_list = [];
        $datas = explode(',', $item['data']);
        if (count($datas) > 1) {
          foreach ($datas as $data) {
            $title = $data;
            $uri = $data;
            if ($item['separator'] != '') {
              if (count(explode($item['separator'], $data)) > 1) {
                $title = explode($item['separator'], $data)[0];
                $uri = explode($item['separator'], $data)[1];
              }
            }
            $data_list[] = [
              'uri' => $uri,
              'title' => $title,
            ];
          }
        }
        else {
          $title = $item['data'];
          $uri = $item['data'];
          if ($item['separator'] != 'no') {
            if (count(explode($item['separator'], $item['data'])) > 1) {
              $title = explode($item['separator'], $item['data'])[0];
              $uri = explode($item['separator'], $item['data'])[1];
            }
          }
          $data_list[] = [
            'uri' => $uri,
            'title' => $title,
          ];
        }
        $params[$item['field']] = $data_list;
        break;
      case 'email':
        $data_list = [];
        $datas = explode(',', $item['data']);
        if (count($datas) > 1) {
          foreach ($datas as $data) {
            $data_list[] = ['value' => $data];
          }
        }
        else {
          $data_list = ['value' => $item['data']];
        }
        $params[$item['field']] = $data_list;
        break;
      case 'datetime':
        $format_date = 'medium';
        $datetime_type = self::getNodeFieldSetting($entity_type_id, $content_type, $item['field'], 'datetime_type');
        if ($datetime_type == 'datetime') {
          $format_date = DATETIME_DATETIME_STORAGE_FORMAT;
        }
        elseif ($datetime_type == 'date') {
          $format_date = DATETIME_DATE_STORAGE_FORMAT;
        }

        $data_list = [];
        $dates = explode(',', $item['data']);
        if (count($dates) > 1) {
          foreach ($dates as $date) {
            $data_list[] = ['value' => self::datetimeStorageFormat($date, $format_date)];
          }
        }
        else {
          $data_list = ['value' => self::datetimeStorageFormat($item['data'], $format_date)];
        }
        $params[$item['field']] = $data_list;
        break;
      case 'string':
        $params[$item['field']] = html_entity_decode($item['data'], HTML_ENTITIES);
        break;
      case 'uuid':
        $params[$item['field']] = $item['data'];
        break;
      case 'integer':
        $params[$item['field']] = $item['data'];
        break;
      case 'file':
        $data_list = [];
        $datas = explode(',', $item['data']);
        if (count($datas) > 1) {
          foreach ($datas as $data) {
            $url = $data;
            if (@fopen($url, 'r')) {
              $arr = explode('/', $url);
              $file_name = end($arr);
              $data = file_get_contents($url);
              $file = file_save_data($data, 'public://' . $file_name);
              $data_list[] = ['target_id' => $file->id()];
            }
          }
        }
        else {
          $url = $item['data'];
          if (@fopen($url, 'r')) {
            $arr = explode('/', $url);
            $file_name = end($arr);
            $data = file_get_contents($url);
            $file = file_save_data($data, 'public://' . $file_name);
            $images_list['target_id'] = $file->id();
          }
        }
        $params[$item['field']] = $data_list;
        break;
      case 'image':
        $images_list = [];
        $images = explode(',', $item['data']);
        if (count($images) > 1) {
          foreach ($images as $image) {
            $url = $image;
            if (@fopen($url, 'r')) {
              $arr = explode('/', $url);
              $file_name = end($arr);
              $data = file_get_contents($url);
              $file = file_save_data($data, 'public://' . $file_name);
              $images_list[] = ['target_id' => $file->id()];
            }
          }
        }
        else {
          $url = $item['data'];
          if (@fopen($url, 'r')) {
            $arr = explode('/', $url);
            $file_name = end($arr);
            $data = file_get_contents($url);
            $file = file_save_data($data, 'public://' . $file_name);
            $images_list['target_id'] = $file->id();
          }
        }
        $params[$item['field']] = $images_list;
        break;
      case 'entity_reference':
        $target_type = self::getNodeFieldSettingTargetType($entity_type_id, $content_type, $item['field']);
        if ( $target_type == 'taxonomy_term') {
          $entity_list = [];
          $entitys = explode(',', $item['data']);
          if (count($entitys) > 1) {
            foreach ($entitys as $entity) {
              $entity_list[] = self::getTidByName($entity);
            }
          }
          else {
            $entity_list = [self::getTidByName($item['data'])];
          }
          $params[$item['field']] = $entity_list;
        }
        elseif ($target_type == 'user') {
          $data_list = [];
          $datas = explode(',', $item['data']);
          if (count($datas) > 1) {
            foreach ($datas as $data) {
              if (!empty($item['user_id']) && $item['user_id'] == '1') {
                /** @var \Drupal\user\Entity\User $user */
                $user = user_load_by_name($data);
                $data_list[] = ['target_id' => $user->id()];
              }
              else {
                $data_list[] = ['target_id' => $data];
              }
            }
          }
          else {
            if (!empty($item['user_id']) && $item['user_id'] == '1') {
              /** @var \Drupal\user\Entity\User $user */
              $user = user_load_by_name($item['data']);
              $data_list[] = ['target_id' => $user->id()];
            }
            else {
              $data_list = ['target_id' => $item['data']];
            }
          }
          $params[$item['field']] = $data_list;
        }
        break;
      case 'list_integer':
        $field_list = [];
        $lists = explode(',', $item['data']);
        if (count($lists) > 1) {
          foreach ($lists as $list) {
            $field_list[] = ['value' => array_search($list, self::getNodeFieldSetting($entity_type_id, $content_type, $item['field'], 'allowed_values'))];
          }
        }
        else {
          $field_list[] = ['value' => array_search($item['data'], self::getNodeFieldSetting($entity_type_id, $content_type, $item['field'], 'allowed_values'))];
        }
        $params[$item['field']] = $field_list;
        break;
    }
  }

  /**
   * Field collection create.
   * @param array $fields
   * @param $context
   */
  public function fieldCollectionCreate(array $fields, &$context) {
    $content_type = '';
    $fc = '';
    foreach ($fields as $key => $field) {
      $content_type = $fields['content'];
      $fc = $fields['fc'];
      if (!$content_type && !$fc) {
        return;
      }
      $params = [];
      $params['type'] = $content_type;
      if (is_array($field)) {
        foreach ($field as $item) {
          self::paramCreate('field_collection_item', $item, $params, $fc);
        }

        /** @var \Drupal\node\Entity\Node $node */
        $node = \Drupal::service('entity.manager')->loadEntityByUuid('node', $params['uuid']);
        unset($params['uuid']);

        /** @var \Drupal\field_collection\Entity\FieldCollectionItem $field_collection_item */
        $field_collection_item = FieldCollectionItem::create(['field_name' => $fc]);
        $field_collection_item->setHostEntity($node);

        foreach ($params as $field => $value) {
          if ($field != 'type' && $field != 'uuid') {
            $field_collection_item->set($field, $value);
          }
        }
        $field_collection_item->save();

        $message = 'Creating Field colection for node - ' . $node->getTitle();
        $context['results'][] = $node->id();
        $context['message'] = $message;
      }
    }
  }

  /**
   * Nodes create.
   *
   * @param array $fields
   * @param $context
   */
  public function nodeCreate(array $fields, &$context) {
    $content_type = '';
    foreach ($fields as $key => $field) {
      $content_type = $fields['content'];
      if (!$content_type) {
        return;
      }
      $params = [];
      $params['type'] = $content_type;
      if (is_array($field)) {
        foreach ($field as $item) {
          self::paramCreate('node', $item, $params, $content_type);
        }
        $node = Node::create($params);
        $node->save();
        $message = 'Creating Node ... ' . $node->getTitle();
        $context['results'][] = $node->id();
        $context['message'] = $message;
      }
    }
  }

  /**
   * @param array $fields
   * @param $context
   */
  public function nodeUpdate(array $fields, &$context) {
    $content_type = '';
    foreach ($fields as $key => $field) {
      $content_type = $fields['content'];
      $node_id = $fields['node'];
      if (!$content_type) {
        return;
      }
      $params = [];
      $params['type'] = $content_type;
      if (is_array($field)) {
        foreach ($field as $item) {
          self::paramCreate('node', $item, $params, $content_type);
        }
        $node = Node::load($node_id);
        foreach ($params as $field => $value) {
          if ($field != 'type' && $field != 'uuid') {
            $node->set($field, $value);
          }
        }
        $node->save();
        $message = 'Updating Node ... ' . $node->getTitle();
        $context['results'][] = $node->id();
        $context['message'] = $message;
      }
    }
  }

  /**
   * Utility: find term by name and vid.
   *
   * @param null $name
   *  Term name
   * @param null $vid
   *  Term vid
   *
   * @return int
   *  Term id or 0 if none.
   */
  protected static function getTidByName($name = NULL, $vid = NULL) {
    $properties = [];
    if (!empty($name)) {
      $properties['name'] = $name;
    }
    if (!empty($vid)) {
      $properties['vid'] = $vid;
    }
    $terms = \Drupal::service('entity.manager')
      ->getStorage('taxonomy_term')
      ->loadByProperties($properties);
    $term = reset($terms);

    return !empty($term) ? $term->id() : 0;
  }

  /**
   * Get field settings from content type.
   *
   * @param $entity_type_id
   * @param $bundle
   * @param $field_name
   * @param $setting
   *
   * @return mixed
   */
  public static function getNodeFieldSetting($entity_type_id, $bundle, $field_name, $setting) {
    $bundle_fields = \Drupal::getContainer()
      ->get('entity_field.manager')
      ->getFieldDefinitions($entity_type_id, $bundle);
    /** @var \Drupal\field\Entity\FieldConfig $field_definition */
    $field_definition = $bundle_fields[$field_name];
    return $field_definition->getSetting($setting);
  }

  /**
   * @param $bundle
   * @param $field_name
   *
   * @return mixed|null
   */
  public static function getNodeFieldSettingTargetType($entity_type_id, $bundle, $field_name) {
    $bundle_fields = \Drupal::getContainer()
      ->get('entity_field.manager')
      ->getFieldDefinitions($entity_type_id, $bundle);
    /** @var \Drupal\field\Entity\FieldConfig $field_definition */
    $field_definition = $bundle_fields[$field_name];
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type');
  }

  /**
   * Check whether the string is a unix timestamp.
   *
   * @param $timestamp
   *
   * @return bool
   */
  public static function isValidTimestamp($timestamp) {
    return ((string) (int) $timestamp === $timestamp) && ($timestamp <= PHP_INT_MAX) && ($timestamp >= ~PHP_INT_MAX);
  }

  /**
   * @param $customdate
   * @param $dateformat
   *
   * @return mixed
   */
  public static function datetimeStorageFormat($customdate, $dateformat) {
    $timestamp = strtotime($customdate);
    return \Drupal::service('date.formatter')
      ->format($timestamp, 'custom', $dateformat, DATETIME_STORAGE_TIMEZONE);
  }

}
