<?php

namespace App\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Contracts\Translation\TranslatorInterface;

class TwigHelperService
{
  public function __construct(DocumentManager $dm, TranslatorInterface $t)
  {
    $this->dm = $dm;
    $this->t = $t;
  }

  public function config()
  {
    return $this->dm->get('Configuration')->findConfiguration();
  }

  public function translator()
  {
    return $this->t;
  }

  public function listAbouts()
  {
    return $this->dm->get('About')->findAllOrderedByPosition();
  }

  public function countPartners()
  {
    return count($this->dm->get('Partner')->findAll());
  }
}