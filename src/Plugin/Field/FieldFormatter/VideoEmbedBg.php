<?php

namespace Drupal\video_bg\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\video_embed_field\ProviderManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;
use Drupal\image\Entity\ImageStyle;

/**
 * Plugin implementation of the video field formatter.
 *
 * @FieldFormatter(
 *   id = "video_embed_bg",
 *   label = @Translation("Video Background"),
 *   field_types = {
 *     "video_embed_field"
 *   }
 * )
 */
class VideoEmbedBg extends FormatterBase implements ContainerFactoryPluginInterface {

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
    foreach ($items as $delta => $item) {
      $keys = [
        $entity_type,
        $entity->bundle(),
        $entity->id(),
        $this->viewMode,
        $field_name,
        $delta,
      ];
      $provider = $this->providerManager->loadProviderFromInput($item->value);

      if (!$provider) {
        $element[$delta] = ['#theme' => 'video_embed_field_missing_provider'];
      }
      else {
        if ($provider->getPluginId() == 'youtube') {

          $id = Html::cleanCssIdentifier(implode('-', $keys));
          $element[$delta] = [
            '#type' => 'container',
            '#attached' => [
              'library' => ['video_bg/video_bg'],
            ],
            '#attributes' => [
              'id' => $id,
              'class' => ['video-bg-wrapper'],
            ],
          ];

          $image = FALSE;
          if ($this->getSetting('image_enable')) {
            $image = $provider->getLocalThumbnailUri();
            if ($image_style = $this->getSetting('image_style')) {
              $image = ImageStyle::load($image_style)->buildUrl($image);
            }
            else {
              // $image = file_create_url($image);
              $image = \Drupal::service('file_url_generator')->generateAbsoluteString($image);
            }
          }

          $provider->downloadThumbnail();
          $element[$delta]['#attached']['drupalSettings']['videoBg']['items'][$id] = [
            'position' => 'absolute',
            'video_ratio' => $this->getSetting('width') / $this->getSetting('height'),
            'loop' => $this->getSetting('loop'),
            'autoplay' => $this->getSetting('autoplay'),
            'mute' => $this->getSetting('mute'),
            'youtube' => $provider->getIdFromInput($item->value),
            'image' => $image,
            'sizing' => 'adjust',
          ];
        }
      }
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
      'image_enable' => TRUE,
      'image_style' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();

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
    $form['image_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use Image'),
      '#description' => $this->t('An image will be displayed while the video is loading.'),
      '#default_value' => $this->getSetting('image_enable'),
      '#required' => FALSE,
    ];
    $form['image_style'] = [
      '#title' => $this->t('Image Style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#required' => FALSE,
      '#options' => image_style_options(),
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $field_name . '][settings_edit_form][settings][image_enable]"]' => ['checked' => TRUE],
        ],
      ],
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
    if ($this->getSetting('image_enable')) {
      $summary[] = $this->t('Thumbnail (@style).', [
        '@style' => $this->getSetting('image_style') ? $this->getSetting('image_style') : $this->t('no image style'),
      ]);
    }
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
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, $settings, $label, $view_mode, $third_party_settings, ProviderManagerInterface $provider_manager, AccountInterface $current_user) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->providerManager = $provider_manager;
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
      $container->get('video_embed_field.provider_manager'),
      $container->get('current_user')
    );
  }

}
