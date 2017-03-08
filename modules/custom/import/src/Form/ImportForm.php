<?php

namespace Drupal\import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\field\FieldConfigInterface;
use Drupal\import\importBatchProcess;

/**
 * Provides a import form.
 */
class ImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'import_form';
  }

  /**
   * @param $callback
   *    callback method
   * @param $wrapper
   *    id for selector in dom structure
   *
   * @return array
   */
  protected function ajaxElement($callback, $wrapper, $num = NULL) {
    if (empty($callback)) {
      return [];
    }

    return [
      'callback' => '::' . $callback,
      'wrapper' => $wrapper,
      'method' => 'replace',
      'num' => $num,
    ];
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function formFieldGenerate(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Retrieves the upload validators for a file field.
   *
   * @return array
   *   An array suitable for passing to file_save_upload() or the file field
   *   element's '#upload_validators' property.
   */
  public function getUploadValidators() {
    $validators['file_validate_extensions'] = array('csv');
    return $validators;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
       
    $fid = '';
    $query = \Drupal::database()
      ->select('file_managed', 'f')
      ->fields('f', ['fid'])
      ->orderBy('f.created', 'DESC')
      ->range(0, 1)
      ->condition('f.uri', '%public://import%', 'LIKE')
      ->execute();
    $result = $query->fetchAll();

    if (count($result) > 0) {
      $fid = $result[0]->fid;
    }

    $input = $form_state->getUserInput();
    $contentTypes = \Drupal::service('entity.manager')
      ->getStorage('node_type')
      ->loadMultiple();

    $contentTypesList = ['all' => t('All')];
    foreach ($contentTypes as $contentType) {
      $contentTypesList[$contentType->id()] = $contentType->label();
    }

    $form = [
      '#prefix' => '<div id="import-config-form">',
      '#suffix' => '</div>',
    ];
    $form['code']['content_type'] = [
      '#type' => 'details',
      '#title' => t('Details'),
      '#open' => TRUE,
    ];
    $form['code']['content_type']['upload'] = [
      '#type' => 'managed_file',
      '#upload_validators' => $this->getUploadValidators(),
      '#title' => 'Managed file',
      '#required' => TRUE,
      '#upload_location' => 'public://import',
      '#default_value' => [$fid],
      '#description' => t("Upload <b>*.csv</b> file."),
      '#weight' => -3,
    ];
    $form['code']['content_type']['file_separator'] = [
      '#description' => t("Write separator for cell in file. Example - (| ,)"),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => t('Separator'),
      '#default_value' => '',
      '#weight' => -2,
    ];
    if (!isset($input['field_collection']) || $input['field_collection'] != '1') {
      $form['code']['content_type']['update'] = [
        '#type' => 'checkbox',
        '#description' => t('If check - will be find existing node and update field value. (Need set UUID in csv file.)'),
        '#title' => t('Update'),
        '#weight' => -1,
      ];
    }
    $form['code']['content_type']['field_collection'] = [
      '#type' => 'checkbox',
      '#description' => t('If check - will import only for field_collection. (Need set UUID in csv file.)'),
      '#title' => t('Import field_collection'),
      '#ajax' => $this->ajaxElement('formFieldGenerate', 'import-config-form'),
      '#weight' => -1,
    ];
    $form['code']['content_type']['type'] = [
      '#type' => 'select',
      '#title' => t('Content types'),
      '#description' => t("Select content type"),
      '#options' => $contentTypesList,
      '#default_value' => 1,
      '#ajax' => $this->ajaxElement('formFieldGenerate', 'import-config-form'),
      '#weight' => 0,
    ];

    if (!empty($input['type']) && $input['type'] != 'all' && isset($input['field_collection']) && $input['field_collection'] == '1') {
      $field_list = $this->getFieldCollectionFields($input['type']);

      if ($field_list['fc_count'] > 1) {
        $form['code']['content_type']['fc_list'] = [
          '#type' => 'select',
          '#title' => t('Field colection list in content type.'),
          '#description' => t("Select Field colection"),
          '#options' => $field_list['fc_list'],
          '#ajax' => $this->ajaxElement('formFieldGenerate', 'import-config-form'),
          '#weight' => 0,
        ];
        if (isset($input['fc_list']) && $input['fc_list'] != 'no') {
          $field_list = $this->getFieldCollectionFields($input['type'], $input['fc_list']);
        }
      }

      if ($field_list['fc_count'] == 0) {
        $form['code']['content_type']['no_collection'] = [
          '#type' => 'details',
          '#title' => t('Fields list'),
          '#description' => t("<b>No collection fields for this content type.</b>"),
          '#open' => TRUE,
          '#tree' => TRUE,
          '#weight' => 3,
        ];
      }
      if (!isset($field_list['fc_count']) && !isset($field_list['fc_list'])) {
        $form['code']['content_type']['select'] = [
          '#type' => 'details',
          '#title' => t('Fields list'),
          '#description' => t("If <b>Column number = 0</b> - field is ignored. (<b>The default value = 0</b>)"),
          '#open' => TRUE,
          '#tree' => TRUE,
          '#weight' => 3,
        ];

        $form['code']['content_type']['fc_field'] = [
          '#type' => 'hidden',
          '#value' => $field_list['uuid'][3],
        ];

        $count = 0;
        $open = TRUE;
        foreach ($field_list as $name => $field) {
          if ($count != 0) {
            $open = FALSE;
          }
          $required = $separator = $user = FALSE;
          $default_value = 0;
          $description = '';
          switch ($field[1]) {
            case 'string':
              if ($name == 'title') {
                $required = TRUE;
                $default_value = '';
              }
              break;
            case 'uuid':
              $required = TRUE;
              $open = TRUE;
              $default_value = '';
              break;
            case 'file':
              $file_extensions = importBatchProcess::getNodeFieldSetting('field_collection_item', $field[3], $field[2], 'file_extensions');
              $description = "Allowed types - <b>$file_extensions</b>";
              break;
            case 'entity_reference':
              $target_type = importBatchProcess::getNodeFieldSettingTargetType('field_collection_item', $field[3], $field[2]);
              if ($target_type == 'user') {
                $user = TRUE;
              }
              break;
            case 'link':
              $separator = TRUE;
              break;
            case 'datetime':
              $description = "Use any English textual datetime description. Example -- 10 September 2000 -- 2017-02-27 21:45:00 -- 11-12-10 -- 11/12/10 -- 2009-01-31 +1 month and other. <a href='http://php.net/manual/en/function.strtotime.php'>Manual</a>";
              break;
          }

          $form['code']['content_type']['select']['fieldset'][$count] = [
            '#type' => 'details',
            '#description' => $description,
            '#title' => t('Settings for field  - @field', ['@field' => $field[0]]),
            '#open' => $open,
            '#prefix' => "<div id='import-config-form-$count'>",
            '#suffix' => '</div>',
          ];
          $form['code']['content_type']['select']['fieldset'][$count]['field'] = [
            '#type' => 'hidden',
            '#value' => $name,
          ];
          $form['code']['content_type']['select']['fieldset'][$count]['field_type'] = [
            '#type' => 'hidden',
            '#value' => $field[1],
          ];
          $form['code']['content_type']['select']['fieldset'][$count]['fc_field'] = [
            '#type' => 'hidden',
            '#value' => $field[3],
          ];

          if ($separator) {
            $form['code']['content_type']['select']['fieldset'][$count]['separator'] = [
              '#description' => t("Write separator for title_link and url. Or write only url. Example <b>|</b> - title|http://example.com or http://example.com."),
              '#type' => 'textfield',
              '#title' => t('Separator'),
              '#default_value' => '',
            ];
          }
          if ($user) {
            $form['code']['content_type']['select']['fieldset'][$count]['user_id'] = [
              '#type' => 'checkbox',
              '#description' => t('If checked -  will be used username (user_id (uid) is used by Default )'),
              '#title' => t('Use user name.'),
            ];
          }

          $form['code']['content_type']['select']['fieldset'][$count]['num_row'] = [
            '#title' => t('Column number'),
            '#default_value' => $default_value,
            '#description' => t('Enter num column this field in import file'),
            '#type' => 'number',
            '#required' => $required,
            '#min' => 0,
            '#max' => 100,
          ];
          $count++;
        }
      }
    }
    elseif (!empty($input['type']) && $input['type'] != 'all') {
      $field_list = $this->getFields($input['type']);

      $form['code']['content_type']['select'] = [
        '#type' => 'details',
        '#title' => t('Fields list'),
        '#description' => t("If <b>Column number = 0</b> - field is ignored. (<b>The default value = 0</b>)"),
        '#open' => TRUE,
        '#tree' => TRUE,
        '#weight' => 3,
      ];

      $count = 0;
      $open = TRUE;
      foreach ($field_list as $name => $field) {
        if ($count != 0) {
          $open = FALSE;
        }
        $required = $separator = $user = FALSE;
        $default_value = 0;
        $description = '';
        switch ($field[1]) {
          case 'string':
            if ($name == 'title') {
              $required = TRUE;
              $default_value = '';
            }
            break;
          case 'file':
            $file_extensions = importBatchProcess::getNodeFieldSetting('node', $input['type'],$field[2],'file_extensions');
            $description = t("Allowed types - <b>@file_extensions</b>", ['@file_extensions' => $file_extensions]);
            break;
          case 'entity_reference':
            $target_type = importBatchProcess::getNodeFieldSettingTargetType('node', $input['type'],$field[2]);
            if ($target_type == 'user') {
              $user = TRUE;
            }
            break;
          case 'link':
            $separator = TRUE;
            break;
          case 'datetime':
            $description = "Use any English textual datetime description. Example -- 10 September 2000 -- 2017-02-27 21:45:00 -- 11-12-10 -- 11/12/10 -- 2009-01-31 +1 month and other. <a href='http://php.net/manual/en/function.strtotime.php'>Manual</a>";
            break;
        }

        $form['code']['content_type']['select']['fieldset'][$count] = [
          '#type' => 'details',
          '#description' => $description,
          '#title' => t('Settings for field  - @field', ['@field' => $field[0]]),
          '#open' => $open,
          '#prefix' => "<div id='import-config-form-$count'>",
          '#suffix' => '</div>',
        ];
        $form['code']['content_type']['select']['fieldset'][$count]['field'] = [
          '#type' => 'hidden',
          '#value' => $name,
        ];
        $form['code']['content_type']['select']['fieldset'][$count]['field_type'] = [
          '#type' => 'hidden',
          '#value' => $field[1],
        ];

        if ($separator) {
          $form['code']['content_type']['select']['fieldset'][$count]['separator'] = [
            '#description' => t("Write separator for title_link | url. Example <b>|</b> - title|http://example.com"),
            '#type' => 'textfield',
            '#title' => t('Separator'),
            '#default_value' => '',
          ];
        }
        if ($user) {
          $form['code']['content_type']['select']['fieldset'][$count]['user_id'] = [
            '#type' => 'checkbox',
            '#description' => t('If checked -  will be used username (user_id (uid) is used by Default )'),
            '#title' => t('Use user name.'),
          ];
        }

        $form['code']['content_type']['select']['fieldset'][$count]['num_row'] = [
          '#title' => t('Column number'),
          '#default_value' => $default_value,
          '#description' => t('Enter num column this field in import file'),
          '#type' => 'number',
          '#required' => $required,
          '#min' => 0,
          '#max' => 100,
        ];
        $count++;
      }
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Import'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * Get field list for CT.
   *
   * @param $contentType
   *
   * @return array
   */
  public function getFields($contentType) {
    /** @var \Drupal\Core\Entity\EntityFieldManager $entityManager */
    $entityManager = \Drupal::service('entity_field.manager');
    $all_field = $entityManager->getFieldDefinitions('node', $contentType);

    $fields = $result = [];
    if (!empty($contentType)) {
      $fields = array_filter($all_field, function ($field_definition) {
        /** @var \Drupal\Core\Field\BaseFieldDefinition $field_definition */
        return $field_definition instanceof FieldConfigInterface ||
        $field_definition->getName() == 'title' ||
        $field_definition->getName() == 'uuid';
      });

      /** @var \Drupal\field\Entity\FieldConfig $field */
      foreach ($fields as $field) {
        if ($field->getType() == 'uuid') {
          $result[$field->getName()] = [
            'UUID',
            $field->getType(),
            $field->getName()
          ];
        }
        elseif ($field->getType() == 'field_collection') {
          continue;
        }
        else {
          $label = '';
          if (is_object($field->getLabel())) {
            $label = $field->getLabel()->render();
          }
          else {
            $label = $field->getLabel();
          }
          $result[$field->getName()] = [
            $label,
            $field->getType(),
            $field->getName()
          ];
        }
      }
      $uuid = $result['uuid'];
      unset($result['uuid']);
      $result['uuid'] = $uuid;
    }
    $ff =$result;
    return $result;
  }

  /**
   * Get field_collection fields list for CT.
   *
   * @param $contentType
   * @param $field_name
   *
   * @return array
   */
  public function getFieldCollectionFields($contentType, $field_name = NULL) {
    /** @var \Drupal\Core\Entity\EntityFieldManager $entityManager */
    $entityManager = \Drupal::service('entity_field.manager');
    $all_field = $entityManager->getFieldDefinitions('node', $contentType);

    $fields = $result = $fc_fields = [];
    if (!empty($contentType)) {
      $fields = array_filter($all_field, function ($field_definition) {
        /** @var \Drupal\Core\Field\BaseFieldDefinition $field_definition */
        return $field_definition instanceof FieldConfigInterface;
      });

      if (empty($field_name)) {
        $fc_count = 0;
        /** @var \Drupal\field\Entity\FieldConfig $field */
        foreach ($fields as $field) {
          if ($field->getType() == 'field_collection') {
            $result['fc_list']['no'] = t('No selected');
            $fields_colection = $entityManager->getFieldDefinitions('field_collection_item', $field->getName());
            $fc_fields = array_filter($fields_colection, function ($fc_field_definition) {
              /** @var \Drupal\Core\Field\BaseFieldDefinition $fc_field_definition */
              return $fc_field_definition instanceof FieldConfigInterface || $fc_field_definition->getName() == 'uuid';
            });

            /** @var \Drupal\field\Entity\FieldConfig $fc_field */
            foreach ($fc_fields as $fc_field) {
              if ($fc_field->getType() == 'uuid') {
                $result[$fc_field->getName()] = [
                  'UUID',
                  $fc_field->getType(),
                  $fc_field->getName(),
                  $field->getName(),
                ];
              }
              elseif ($fc_field->getType() == 'field_collection') {
                $result['fc_list'][$fc_field->getName()] = $field->getName() . ' → ' . $fc_field->getName();
              }
              else {
                $label = '';
                if (is_object($fc_field->getLabel())) {
                  $label = $fc_field->getLabel()->render();
                }
                else {
                  $label = $fc_field->getLabel();
                }
                $result[$fc_field->getName()] = [
                  $label,
                  $fc_field->getType(),
                  $fc_field->getName(),
                  $field->getName(),
                ];
              }
            }
            $uuid = $result['uuid'];
            unset($result['uuid']);
            $result['uuid'] = $uuid;
            $fc_count++;

            $result['fc_list'][$field->getName()] = $field->getName();

          }
          $result['fc_count'] = $fc_count;
        }
      }
      else {
        $fields_colection = $entityManager->getFieldDefinitions('field_collection_item', $field_name);
        $fc_fields = array_filter($fields_colection, function ($fc_field_definition) {
          /** @var \Drupal\Core\Field\BaseFieldDefinition $fc_field_definition */
          return $fc_field_definition instanceof FieldConfigInterface || $fc_field_definition->getName() == 'uuid';
        });

        /** @var \Drupal\field\Entity\FieldConfig $fc_field */
        foreach ($fc_fields as $fc_field) {
          if ($fc_field->getType() == 'uuid') {
            $result[$fc_field->getName()] = [
              'UUID',
              $fc_field->getType(),
              $fc_field->getName(),
              $field_name,
            ];
          }
          elseif ($fc_field->getType() == 'field_collection') {
            continue;
          }
          else {
            $label = '';
            if (is_object($fc_field->getLabel())) {
              $label = $fc_field->getLabel()->render();
            }
            else {
              $label = $fc_field->getLabel();
            }
            $result[$fc_field->getName()] = [
              $label,
              $fc_field->getType(),
              $fc_field->getName(),
              $field_name,
            ];
          }
        }
        $uuid = $result['uuid'];
        unset($result['uuid']);
        $result['uuid'] = $uuid;
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();

    $load_file = '';
    if (!empty($input['upload']['fids'])) {
      $load_file = $input['upload']['fids'];
    }

    if (empty($input['select']['fieldset'])) {
      return;
    }

    $fields = $input['select']['fieldset'];

    /** @var \Drupal\file\Entity\File $csv */
    $csv = File::load($load_file);
    $file_uri = file_create_url($csv->getFileUri());

    $row = 0;
    //$lines = sizeof(file($file_uri));
    $result = [];
    $result['content'] = $input['type'];
    if (isset($input['select']['fc_field'])) {
      $result['fc'] = $input['select']['fc_field'];
    }

    if (($handle = fopen($file_uri, "r")) !== FALSE) {
      while (($data = fgetcsv($handle, 10000, "|")) !== FALSE) {
        $row++;
        $save_field = [];
        foreach ($fields as $field) {
          if ($field['num_row'] != 0) {
            $field['data'] = $data[$field['num_row'] - 1];
            $save_field[] = $field;
          }
        }
        $result[$row] = $save_field;
      }
      fclose($handle);
    }

    $operations = [];

    if ((!isset($input['update']) || $input['update'] != '1') && (!isset($input['field_collection']) || $input['field_collection'] != '1')) {
      foreach ($result as $key => $value) {
        $fake_result = [];
        $fake_result['content'] = $input['type'];
        if (is_array($value)) {
          $fake_result[] = $value;
          $operations[] = [
            '\Drupal\import\importBatchProcess::nodeCreate',
            [$fake_result]
          ];
        }
      }

      $batch = [
        'title' => t('Importing'),
        'operations' => $operations,
        'finished' => '\Drupal\import\importBatchProcess::createNodeFinishedCallback',
      ];
      batch_set($batch);
    }
    elseif(isset($input['field_collection']) && $input['field_collection'] == '1') {
      foreach ($result as $key => $value) {
        $fake_result = [];
        $fake_result['content'] = $input['type'];
        $fake_result['fc'] = $input['fc_field'];
        if (is_array($value)) {
          $fake_result[] = $value;
          $operations[] = [
            '\Drupal\import\importBatchProcess::fieldCollectionCreate',
            [$fake_result]
          ];
        }
      }

      $batch = [
        'title' => t('Importing'),
        'operations' => $operations,
        'finished' => '\Drupal\import\importBatchProcess::createNodeFinishedCallback',
      ];
      batch_set($batch);
    }
    elseif($input['update'] == '1') {
      foreach ($result as $key => $value) {
        $fake_result = [];
        $method = 'nodeCreate';
        $fake_result['node'] = '';
        $fake_result['content'] = $input['type'];
        if (is_array($value)) {
          foreach ($value as $item) {
            if ($item['field_type'] == 'uuid') {
              /** @var \Drupal\node\Entity\Node $node */
              $node = \Drupal::service('entity.manager')->loadEntityByUuid('node', $item['data']);
              if (!empty($node)) {
                $method = 'nodeUpdate';
                $fake_result['node'] =  $node->id();
              }
            }
          }
          $fake_result[] = $value;
          $operations[] = [
            '\Drupal\import\importBatchProcess::' . $method,
            [$fake_result]
          ];
        }
      }
      $batch = [
        'title' => t('Importing'),
        'operations' => $operations,
        'finished' => '\Drupal\import\importBatchProcess::createNodeFinishedCallback',
      ];
      batch_set($batch);
    }
  }

  /**
   * {@inheritdoc}
   *
   *
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    $done = 0;
    if (!isset($input['select']['fieldset'])) {
      $form_state->setErrorByName('use_uuid', t('Please select content type for import.'));
    }
    if (isset($input['update']) && $input['update'] == '1') {
      foreach ($input['select']['fieldset'] as $field) {
        if ($field['field'] == 'uuid' && $field['num_row'] != '0' && $field['num_row'] != '') {
          $done++;
        }
      }
      if ($done == 0) {
        $form_state->setErrorByName('use_uuid', t('Please set uuid num column.'));
      }
    }
  }
}
