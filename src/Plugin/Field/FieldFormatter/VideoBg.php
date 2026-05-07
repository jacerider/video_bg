<?php

namespace Drupal\video_bg\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;

/**
 * Plugin implementation of the video field formatter.
 *
 * @FieldFormatter(
 *   id = "video_bg",
 *   label = @Translation("Video Background"),
 *   field_types = {
 *     "video_bg"
 *   }
 * )
 */
class VideoBg extends FileFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The embed provider plugin manager.
   *
   * @var \Drupal\video_embed_field\ProviderManagerInterface
   */
  protected $providerManager;

  /**
   * The logged in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $entity = $items->getEntity();
    $entity_type = $entity->getEntityTypeId();
    $field_name = $this->fieldDefinition->getName();
    $config = [];

    if ($files = $this->getEntitiesToView($items, $langcode)) {
      $config = [
        'position' => 'absolute',
        'video_ratio' => $this->getSetting('width') / $this->getSetting('height'),
        'loop' => $this->getSetting('loop'),
        'autoplay' => $this->getSetting('autoplay'),
        'mute' => $this->getSetting('mute'),
        'sizing' => 'adjust',
      ];
      $extentions = explode(' ', $this->fieldDefinition->getSetting('file_extensions'));
      foreach ($files as $delta => $file) {
        /** @var \Drupal\file\FileInterface $file */
        $config[$extentions[$delta]] = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()); 
      }
    }

    if (!empty($config)) {
      $keys = [
        $entity_type,
        $entity->bundle(),
        $entity->id(),
        $this->viewMode,
        $field_name,
      ];
      $id = Html::cleanCssIdentifier(implode('-', $keys));
      $element[] = [
        '#type' => 'container',
        '#attached' => [
          'library' => ['video_bg/video_bg'],
          'drupalSettings' => ['videoBg' => ['items' => [$id => $config]]],
        ],
        '#attributes' => [
          'id' => $id,
          'class' => ['video-bg-wrapper'],
        ],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'autoplay' => TRUE,
      'loop' => TRUE,
      'mute' => TRUE,
      'width' => '854',
      'height' => '480',
      'image_style' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['autoplay'] = [
      '#title' => $this->t('Autoplay'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('autoplay'),
    ];
    $form['loop'] = [
      '#title' => $this->t('Loop'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('loop'),
    ];
    $form['mute'] = [
      '#title' => $this->t('Mute'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('mute'),
    ];
    $form['width'] = [
      '#title' => $this->t('Width'),
      '#type' => 'number',
      '#field_suffix' => 'px',
      '#default_value' => $this->getSetting('width'),
      '#required' => TRUE,
      '#size' => 20,
    ];
    $form['height'] = [
      '#title' => $this->t('Height'),
      '#type' => 'number',
      '#field_suffix' => 'px',
      '#default_value' => $this->getSetting('height'),
      '#required' => TRUE,
      '#size' => 20,
    ];
    $form['image_style'] = [
      '#title' => $this->t('Image Style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#required' => FALSE,
      '#options' => image_style_options(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $dimensions = $this->t('@widthx@height', ['@width' => $this->getSetting('width'), '@height' => $this->getSetting('height')]);
    $summary[] = $this->t('Video (@dimensions@autoplay@loop@mute).', [
      '@dimensions' => $dimensions,
      '@autoplay' => $this->getSetting('autoplay') ? $this->t(', autoplaying') : '',
      '@loop' => $this->getSetting('loop') ? $this->t(', loop') : '',
      '@mute' => $this->getSetting('mute') ? $this->t(', mute') : '',
    ]);
    $summary[] = $this->t('Thumbnail (@style).', [
      '@style' => $this->getSetting('image_style') ? $this->getSetting('image_style') : $this->t('no image style'),
    ]);
    return $summary;
  }

  /**
   * Constructs a new instance of the plugin.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\video_embed_field\ProviderManagerInterface $provider_manager
   *   The video embed provider manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The logged in user.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, $settings, $label, $view_mode, $third_party_settings, AccountInterface $current_user) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user')
    );
  }

}
