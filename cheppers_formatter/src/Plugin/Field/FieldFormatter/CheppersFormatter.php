<?php

/**
 * @file
 * Contains \Drupal\cheppers_formatter\Plugin\Field\FieldFormatter.
 */

namespace Drupal\cheppers_formatter\Plugin\Field\FieldFormatter;


use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldFormatter\DateTimeFormatterBase;

/**
 * Plugin implementation of the 'cheppers_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "cheppers_formatter",
 *   label = @Translation("Cheppers Date formatter"),
 *   field_types = {
 *     "datetime"
 *   }
 * )
 */
class CheppersFormatter extends DateTimeFormatterBase  {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'past_date_color' => 'blue',
      'future_date_color' => 'orange',
      'date_format' => DATETIME_DATETIME_STORAGE_FORMAT,
      'future_date_format' => '@interval hence',
      'past_date_format' => '@interval ago',
      'granularity' => 2,
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $summary[] = $this->t('A table containing the field value and the time difference.');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    // Select list for the past date color
    $form['past_date_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Past date color'),
      '#description' => $this->t('Select which color would you like the <strong>past</strong> date to be presented in.'),
      '#options' => [
        'orange' => $this->t('Orange'),
        'blue' => $this->t('Blue'),
        'green' => $this->t('Green'),
      ],
      '#default_value' => $this->getSetting('past_date_color'),
      '#required' => TRUE,
    ];

    // Select list for the future date color
    $form['future_date_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Future date color'),
      '#description' => $this->t('Select which color would you like the <strong>future</strong> date to be presented in.'),
      '#options' => [
        'orange' => $this->t('Orange'),
        'blue' => $this->t('Blue'),
        'green' => $this->t('Green'),
      ],
      '#default_value' => $this->getSetting('future_date_color'),
      '#required' => TRUE,
    ];

    // Date format for the input value
    $form['date_format'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Date/time format'),
      '#description' => $this->t('See <a href="http://php.net/manual/function.date.php" target="_blank">the documentation for PHP date formats</a>.'),
      '#default_value' => $this->getSetting('date_format'),
    );

    // Format for the future date differences
    $form['future_date_format'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Future format'),
      '#default_value' => $this->getSetting('future_date_format'),
      '#description' => $this->t('Use <em>@interval</em> where you want the formatted interval text to appear.'),
    );

    // Format for the past date differences
    $form['past_date_format'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Past format'),
      '#default_value' => $this->getSetting('past_date_format'),
      '#description' => $this->t('Use <em>@interval</em> where you want the formatted interval text to appear.'),
    );

    // Set the granularity of the date difference
    $form['granularity'] = array(
      '#type' => 'number',
      '#title' => $this->t('Granularity'),
      '#default_value' => $this->getSetting('granularity'),
      '#description' => $this->t('How many time units should be shown in the formatted output.'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Prepare elements for the table
    $headers = [
      $this->t('Original'),
      $this->t('Difference'),
    ];
    $rows = [];

    // Get the field settings
    $settings = [
      'past_date_color' => $this->getSetting('past_date_color'),
      'future_date_color' => $this->getSetting('future_date_color'),
      'date_format' => $this->getSetting('date_format'),
      'future_date_format' => $this->getSetting('future_date_format'),
      'past_date_format' => $this->getSetting('past_date_format'),
      'granularity' => $this->getSetting('granularity'),
    ];

    foreach ($items as $delta => $item) {
      if (!empty($item->date)) {
        $date = $item->date;

        if ($this->getFieldSetting('datetime_type') == 'date') {
          // A date without time will pick up the current time, use the default.
          datetime_date_default_time($date);
        }
        $this->setTimeZone($date);

        // Get the date difference
        $difference = $this->dateDifference($date, $settings);

        // Setup the rows, first one with the field value
        // second one with the time difference
        $rows[] = [
          'data' => [
            $this->formatDate($date),
            $difference['string'],
          ],
          'class' => ($difference['future']) ? 'cheppers-formatter-table-' . $settings['future_date_color'] : 'cheppers-formatter-table-' . $settings['past_date_color'],
        ];
      }
    }

    // Setup the table for the output if we have any rows
    // return empty array if none
    if (!empty($rows)) {
      return [
        '#type' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
        '#attached' => [
          'library' => [
            'cheppers_formatter/cheppers-table-formatter',
          ],
        ],
        '#cache' => [
          'contexts' => [
            'timezone',
          ],
        ],
      ];
    } else {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function formatDate($date) {
    $format = $this->getSetting('date_format');
    $timezone = $this->getSetting('timezone_override');
    return $this->dateFormatter->format($date->getTimestamp(), 'custom', $format, $timezone != '' ? $timezone : NULL);
  }

  /**
   * Calculates the time difference between the given date
   * and determines if the date is in the past or in the future.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The date on which to calculate the difference.
   * @param $settings
   *   Array containing the field display settings.
   *
   * @return array
   *   Array containing the formatted difference string
   *   and the past/future indicator.
   */
  private function dateDifference($date, $settings) {
    // We need the timestamp from the date
    $timestamp = $date->getTimestamp();

    // Get the time interval
    $interval = $this->dateFormatter->formatTimeDiffSince($timestamp, array('strict' => FALSE, 'granularity' => $settings['granularity']));

    // Get the time difference between the value and current time
    $difference = REQUEST_TIME - $timestamp;

    // Return the correct string based on the difference
    $string = ($difference < 0) ? $this->t($settings['future_date_format'], array('@interval' => $interval)) : $this->t($settings['past_date_format'], array('@interval' => $interval));

    return [
      'string' => $string,
      'future' => ($difference < 0) ? TRUE : FALSE,
    ];
  }

}
