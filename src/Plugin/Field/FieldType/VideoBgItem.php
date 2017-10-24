<?php

namespace Drupal\video_bg\Plugin\Field\FieldType;

use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Component\Utility\Bytes;

/**
 * Plugin implementation of the 'video_bg' field type.
 *
 * @FieldType(
 *   id = "video_bg",
 *   label = @Translation("Video BG"),
 *   description = @Translation("This field stores the ID of a video_bg as an integer value."),
 *   category = @Translation("Reference"),
 *   default_widget = "video_bg",
 *   default_formatter = "video_bg",
 *   list_class = "\Drupal\file\Plugin\Field\FieldType\FileFieldItemList",
 * )
 */
class VideoBgItem extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return array(
      'file_extensions' => 'ogg webm mp4',
      'file_directory' => '[date:custom:Y]-[date:custom:m]',
      'max_filesize' => '',
      'description_field' => 0,
    ) + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    // Force cardinality.
    $field_definition->setCardinality(3);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];
    $element['#attached']['library'][] = 'file/drupal.file';
    $scheme_options = \Drupal::service('stream_wrapper_manager')->getNames(StreamWrapperInterface::WRITE_VISIBLE);
    $element['uri_scheme'] = array(
      '#type' => 'radios',
      '#title' => t('Upload destination'),
      '#options' => $scheme_options,
      '#default_value' => $this->getSetting('uri_scheme'),
      '#description' => t('Select where the final files should be stored. Private file storage has significantly more overhead than public files, but allows restricted access to files within this field.'),
      '#disabled' => $has_data,
    );
    $form['#after_build'][] = [get_class($this), 'storageSettingsFormAfterBuild'];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function storageSettingsFormAfterBuild(array $element, FormStateInterface $form_state) {
    // Hide the cardinality container. There must be a better way to do this.
    $element['cardinality_container']['#access'] = FALSE;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);
    $element['file_extensions']['#access'] = FALSE;
    $element['description_field']['#access'] = FALSE;
    return $element;
  }

  /**
   * Retrieves the upload validators for a file field.
   *
   * @return array
   *   An array suitable for passing to file_save_upload() or the file field
   *   element's '#upload_validators' property.
   */
  public function getVideoUploadValidators($delta) {
    $validators = array();
    $settings = $this->getSettings();

    // Cap the upload size according to the PHP limit.
    $max_filesize = Bytes::toInt(file_upload_max_size());
    if (!empty($settings['max_filesize'])) {
      $max_filesize = min($max_filesize, Bytes::toInt($settings['max_filesize']));
    }

    // There is always a file size limit due to the PHP server limit.
    $validators['file_validate_size'] = array($max_filesize);

    // Add the extension check if necessary.
    if (!empty($settings['file_extensions'])) {
      $file_extentions = explode(' ', $settings['file_extensions']);
      $validators['file_validate_extensions'] = array($file_extentions[$delta]);
    }

    return $validators;
  }

}
