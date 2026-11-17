<?php

/**
 * What happened to each part of a table during the move, in plain words for the summary.
 * Not a model of the factory: the converter makes one per table.
 */
class SupsysticTables_Pricingimport_Model_Report
{
  /** @var RscDtgs_Environment */
  private $environment;
  private $items = [];

  public function __construct($environment)
  {
    $this->environment = $environment;
  }

  /** Moved as it was. */
  public function moved($label, $count = 0, $note = '')
  {
    $this->add('moved', $label, $count, $note);
  }

  /** Works, in the way of Data Tables. */
  public function replaced($label, $value = '', $note = '')
  {
    $this->add('replaced', $label, 0, trim($value . ($value !== '' && $note !== '' ? ' — ' : '') . $this->environment->translate($note)));
  }

  /** Moves with Data Tables PRO only. */
  public function pro($label, $note = '')
  {
    $this->add('pro', $label, 0, $note);
  }

  /** Not moved. */
  public function skipped($label, $note = '')
  {
    $this->add('skipped', $label, 0, $note);
  }

  private function add($kind, $label, $count, $note)
  {
    $this->items[] = [
      'kind' => $kind,
      'label' => $this->environment->translate($label),
      'count' => (int) $count,
      'note' => (string) $note,
    ];
  }

  public function getItems()
  {
    return $this->items;
  }
}
