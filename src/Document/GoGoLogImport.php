<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Special log for Import.
 *
 * @MongoDB\Document
 */
class GoGoLogImport extends GoGoLog
{
    function trans($msg)  # TODO translation use TranslatorInterface
    {
        return $msg;
    }

    public function displayMessage()
    {
        $result = $this->getMessage().' ! <strong>'.$this->trans('importService.total', ['%count%', $this->getDataProp('elementsCount')], 'admin').'</strong> ';

        if ($this->getDataProp('elementsCreatedCount') > 0) {
            $result .= $this->trans('importService.elementsCreatedCount', ['%count%', $this->getDataProp('elementsCreatedCount')], 'admin');
        }
        if ($this->getDataProp('elementsUpdatedCount') > 0) {
            $result .= $this->trans('importService.elementsUpdatedCount', ['%count%', $this->getDataProp('elementsUpdatedCount')], 'admin');
        }
        if ($this->getDataProp('elementsNothingToDoCount') > 0) {
            $result .= ' - '.$this->trans('importService.elementsNothingToDoCount', ['%count%', $this->getDataProp('elementsNothingToDoCount')], 'admin');
        }
        if ($this->getDataProp('elementsMissingGeoCount') > 0) {
            $result .= ' - '.$this->trans('importService.elementsMissingGeoCount', ['%count%', $this->getDataProp('elementsMissingGeoCount')], 'admin');
        }
        if ($this->getDataProp('elementsMissingTaxoCount') > 0) {
            $result .= ' - '.$this->trans('importService.elementsMissingTaxoCount', ['%count%', $this->getDataProp('elementsMissingTaxoCount')], 'admin');
        }
        if ($this->getDataProp('elementsPreventImportedNoTaxo') > 0) {
            $result .= ' - '.$this->trans('importService.elementsPreventImportedNoTaxo', ['%count%', $this->getDataProp('elementsPreventImportedNoTaxo')], 'admin');
        }
        if ($this->getDataProp('elementsDeletedCount') > 0) {
            $result .= ' - '.$this->trans('importService.elementsDeletedCount', ['%count%', $this->getDataProp('elementsDeletedCount')], 'admin');
        }
        if ($this->getDataProp('elementsErrorsCount') > 0) {
            $result .= ' - '.$this->trans('importService.elementsErrorsCount', ['%count%', $this->getDataProp('elementsErrorsCount')], 'admin');
        }
        if ($this->getDataProp('automaticMergesCount') > 0) {
            $result .= ' - '.$this->trans('importService.automaticMergesCount', ['%count%', $this->getDataProp('automaticMergesCount')], 'admin');
        }
        if ($this->getDataProp('potentialDuplicatesCount') > 0) {
            $result .= ' - '.$this->trans('importService.potentialDuplicatesCount', ['%count%', $this->getDataProp('potentialDuplicatesCount')], 'admin');
        }

        if ($this->getDataProp('errorMessages')) {
            $result .= '</br></br>'.implode('</br>', $this->getDataProp('errorMessages'));
        }

        return $result;
    }
}
