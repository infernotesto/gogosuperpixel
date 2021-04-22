<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * Special log for Import.
 *
 * @MongoDB\Document
 */
class GoGoLogImport extends GoGoLog
{
    public function displayMessage()
    {
        $result = $this->getMessage().' ! <strong>Total: '.$this->getDataProp('elementsCount').'</strong> ';

        if ($this->getDataProp('elementsCreatedCount') > 0) {
            $result .= ' - '.$this->getDataProp('elementsCreatedCount').' importé.s'; // TODO translate
        }
        if ($this->getDataProp('elementsUpdatedCount') > 0) {
            $result .= ' - '.$this->getDataProp('elementsUpdatedCount').' mis à jour'; // TODO translate
        }
        if ($this->getDataProp('elementsNothingToDoCount') > 0) {
            $result .= ' - '.$this->getDataProp('elementsNothingToDoCount').' laissé.s tel.s quel.s (rien à mettre à jour)'; // TODO translate
        }
        if ($this->getDataProp('elementsMissingGeoCount') > 0) {
            $result .= ' - '.$this->getDataProp('elementsMissingGeoCount').' sans geoloc'; // TODO translate
        }
        if ($this->getDataProp('elementsMissingTaxoCount') > 0) {
            $result .= ' - '.$this->getDataProp('elementsMissingTaxoCount').' sans categories'; // TODO translate
        }
        if ($this->getDataProp('elementsPreventImportedNoTaxo') > 0) {
            $result .= ' - '.$this->getDataProp('elementsPreventImportedNoTaxo').' non importés car sans catégories'; // TODO translate
        }
        if ($this->getDataProp('elementsDeletedCount') > 0) {
            $result .= ' - '.$this->getDataProp('elementsDeletedCount').' supprimé.s'; // TODO translate
        }
        if ($this->getDataProp('elementsErrorsCount') > 0) {
            $result .= ' - '.$this->getDataProp('elementsErrorsCount')." erreur.s pendant l'import"; // TODO translate
        }
        if ($this->getDataProp('automaticMergesCount') > 0) {
            $result .= ' - '.$this->getDataProp('automaticMergesCount')." fusionnés avec un élément déjà existant"; // TODO translate
        }
        if ($this->getDataProp('potentialDuplicatesCount') > 0) {
            $result .= ' - '.$this->getDataProp('potentialDuplicatesCount')." doublons potentiels détectés"; // TODO translate
        }

        if ($this->getDataProp('errorMessages')) {
            $result .= '</br></br>'.implode('</br>', $this->getDataProp('errorMessages'));
        }

        return $result;
    }
}
