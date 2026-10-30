<?php

declare(strict_types=1);

namespace Drupal\ghostwriter\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghostwriter\Data\CharacterRepository;
use Drupal\ghostwriter\Sync\DataSync;

/**
 * Shows when the official data was fetched and refreshes it on demand.
 */
final class RefreshForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected readonly DataSync $dataSync,
    protected readonly CharacterRepository $characters,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ghostwriter_refresh';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $status = $this->characters->status();
    $form['source'] = [
      '#type' => 'item',
      '#title' => $this->t('Source'),
      '#plain_text' => $this->config('ghostwriter.settings')->get('base_url'),
    ];
    $form['fetched_at'] = [
      '#type' => 'item',
      '#title' => $this->t('Last fetched'),
      '#plain_text' => $status['fetched_at'] ?? $this->t('Never'),
    ];
    $form['stale'] = [
      '#type' => 'item',
      '#title' => $this->t('Status'),
      '#markup' => $status['stale'] ? $this->t('Stale') : $this->t('Current'),
    ];
    $form['force'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Download every file, even if unchanged'),
      '#description' => $this->t('Skips the ETag check against the source.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh data'),
    ];
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $result = $this->dataSync->sync((bool) $form_state->getValue('force'));
    if (isset($result['error'])) {
      $this->messenger()->addError($result['error']['message']);
      return;
    }
    $this->messenger()->addStatus($this->t('Changed: @changed. Unchanged: @unchanged.', [
      '@changed' => implode(', ', $result['changed']) ?: $this->t('none'),
      '@unchanged' => implode(', ', $result['unchanged']) ?: $this->t('none'),
    ]));
  }

}
