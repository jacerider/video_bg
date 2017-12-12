<?php

namespace Drupal\video_bg\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\media\MediaInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\video_embed_field\ProviderManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the video field formatter for media.
 *
 * @FieldFormatter(
 *   id = "video_embed_bg_media",
 *   label = @Translation("Video Background"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class VideoEmbedBgMedia extends VideoEmbedBg {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ProviderManagerInterface $provider_manager, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $provider_manager, $current_user);
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns the entity URI.
   */
  protected function getProvider(FieldItemInterface $item) {
    $url = $this->getVideoUrl($item);
    return $this->providerManager->loadProviderFromInput($url);
  }

  /**
   * Return the Video URL.
   */
  protected function getVideoUrl(FieldItemInterface $item) {
    $media = $item->get('entity')->getTarget()->getValue();
    return $this->getVideoUrlFromMedia($media);
  }

  /**
   * Get the video URL from a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return string|bool
   *   A video URL or FALSE on failure.
   */
  protected function getVideoUrlFromMedia(MediaInterface $media) {
    $media_type = $this->entityTypeManager
      ->getStorage('media_type')
      ->load($media->bundle());
    $source = $media->getSource();
    $source_field = $source->getSourceFieldDefinition($media_type);
    $field_name = $source_field->getName();
    $video_url = $media->{$field_name}->value;
    return !empty($video_url) ? $video_url : FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * This has to be overriden because FileFormatterBase expects $item to be
   * of type \Drupal\file\Plugin\Field\FieldType\FileItem and calls
   * isDisplayed() which is not in FieldItemInterface.
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    if ($target_type !== 'media') {
      return FALSE;
    }

    $storage = \Drupal::service('entity_type.manager')->getStorage('media_type');
    $target_bundles = $field_definition->getSetting('handler_settings')['target_bundles'];
    foreach ($target_bundles as $bundle) {
      if ($storage->load($bundle)->getSource()->getPluginId() !== 'video_embed_field') {
        return FALSE;
      }
    }
    return parent::isApplicable($field_definition);
  }

}
