<?php

namespace Drupal\video_bg\Plugin\Field\FieldWidget;

use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Plugin implementation of the 'video_bg' widget.
 *
 * @FieldWidget(
 *   id = "video_bg",
 *   label = @Translation("Video BG"),
 *   field_types = {
 *     "video_bg"
 *   }
 * )
 */
class VideoBgWidget extends FileWidget {

  /**
   * Overrides \Drupal\Core\Field\WidgetBase::formMultipleElements().
   *
   * Special handling for draggable multiple widgets and 'add more' button.
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];

    // Load the items for form rebuilds from the field state as they might not
    // be in $form_state->getValues() because of validation limitations. Also,
    // they are only passed in as $items when editing existing entities.
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    if (isset($field_state['items'])) {
      $items->setValue($field_state['items']);
    }

    // Determine the number of widgets to display.
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $max = $cardinality - 1;
    $is_multiple = ($cardinality > 1);

    $title = $this->fieldDefinition->getLabel();
    $description = $this->getFilteredDescription();

    $elements = array();
    $titles = ['Ogg', 'WebM', 'Mp4'];

    // Add an element for every existing item.
    for ($delta = 0; $delta < $cardinality; $delta++) {
      $element = array(
        '#title' => $titles[$delta],
        '#description' => $description,
      );
      if (empty($items[$delta])) {
        $items->appendItem();
      }
      // dsm($items->get($delta));
      $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);

      $item = $items[$delta];
      if ($element) {
        $element['#required'] = $delta == 0 ? $element['#required'] : $elements[0]['#required'];
        $elements[$delta] = $element;
      }
    }

    $empty_single_allowed = ($cardinality == 1 && $delta == 0);
    $empty_multiple_allowed = ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED || $delta < $cardinality) && !$form_state->isProgrammed();

    if ($is_multiple) {
      // The group of elements all-together need some extra functionality after
      // building up the full list (like draggable table rows).
      $elements['#file_upload_delta'] = $delta;
      $elements['#type'] = 'details';
      $elements['#open'] = TRUE;
      // $elements['#theme'] = 'file_widget_multiple';
      $elements['#theme_wrappers'] = array('details');
      $elements['#process'] = array(array(get_class($this), 'processMultiple'));
      $elements['#title'] = $title;

      $elements['#description'] = $description;
      $elements['#field_name'] = $field_name;
      $elements['#language'] = $items->getLangcode();
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_settings = $this->getFieldSettings();

    // The field settings include defaults for the field type. However, this
    // widget is a base class for other widgets (e.g., ImageWidget) that may act
    // on field types without these expected settings.
    $field_settings += array(
      'display_default' => NULL,
      'display_field' => NULL,
      'description_field' => NULL,
    );

    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $defaults = array(
      'fids' => array(),
      'display' => (bool) $field_settings['display_default'],
      'description' => '',
    );

    // Essentially we use the managed_file type, extended with some
    // enhancements.
    $element_info = $this->elementInfo->getInfo('managed_file');
    $element += array(
      '#type' => 'managed_file',
      '#upload_location' => $items[$delta]->getUploadLocation(),
      '#upload_validators' => $items[$delta]->getVideoUploadValidators($delta),
      '#value_callback' => array(get_class($this), 'value'),
      '#process' => array_merge($element_info['#process'], array(array(get_class($this), 'process'))),
      '#progress_indicator' => $this->getSetting('progress_indicator'),
      // Allows this field to return an array instead of a single value.
      '#extended' => TRUE,
      // Add properties needed by value() and process() methods.
      '#field_name' => $this->fieldDefinition->getName(),
      '#entity_type' => $items->getEntity()->getEntityTypeId(),
      '#display_field' => (bool) $field_settings['display_field'],
      '#display_default' => $field_settings['display_default'],
      '#description_field' => $field_settings['description_field'],
      '#cardinality' => $cardinality,
    );

    $element['#weight'] = $delta;

    // Field stores FID value in a single mode, so we need to transform it for
    // form element to recognize it correctly.
    if (!isset($items[$delta]->fids) && isset($items[$delta]->target_id)) {
      $items[$delta]->fids = array($items[$delta]->target_id);
    }
    $element['#default_value'] = $items[$delta]->getValue() + $defaults;

    $default_fids = $element['#extended'] ? $element['#default_value']['fids'] : $element['#default_value'];
    if (empty($default_fids)) {

      $upload_validators = $element['#upload_validators'];
      $description = [];
      if (isset($upload_validators['file_validate_size'])) {
        $descriptions[] = t('@size limit.', array('@size' => format_size($upload_validators['file_validate_size'][0])));
      }
      if (isset($upload_validators['file_validate_extensions'])) {
        $descriptions[] = t('Allowed types: @extensions.', array('@extensions' => $upload_validators['file_validate_extensions'][0]));
      }

      $file_upload_help = [
        '#markup' => implode('<br>', $descriptions),
      ];

      // $file_upload_help = array(
      //   '#theme' => 'file_upload_help',
      //   '#description' => $element['#description'],
      //   '#upload_validators' => $element['#upload_validators'],
      //   '#cardinality' => $cardinality,
      // );
      $element['#description'] = \Drupal::service('renderer')->renderPlain($file_upload_help);
      $element['#multiple'] = $cardinality != 1 ? TRUE : FALSE;
      if ($cardinality != 1 && $cardinality != -1) {
        $element['#element_validate'] = array(array(get_class($this), 'validateMultipleCount'));
      }
    }

    return $element;
  }

  /**
   * Form API callback: Processes a group of file_generic field elements.
   *
   * Adds the weight field to each row so it can be ordered and adds a new Ajax
   * wrapper around the entire group so it can be replaced all at once.
   *
   * This method on is assigned as a #process callback in formMultipleElements()
   * method.
   */
  public static function processMultiple($element, FormStateInterface $form_state, $form) {
    $element = parent::processMultiple($element, $form_state, $form);
    $element_children = Element::children($element, TRUE);

    foreach ($element_children as $delta => $key) {
      unset($element[$key]['_weight']);
    }

    return $element;
  }
}
