<?php

namespace Biopen\GeoDirectoryBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Biopen\GeoDirectoryBundle\Document\Element;
use Biopen\GeoDirectoryBundle\Document\ElementStatus;
use Biopen\GeoDirectoryBundle\Document\ModerationState;
use Biopen\GeoDirectoryBundle\Document\Coordinates;
use Biopen\GeoDirectoryBundle\Document\Option;
use Biopen\GeoDirectoryBundle\Document\OptionValue;
use Biopen\GeoDirectoryBundle\Document\UserInteractionContribution;
use Biopen\GeoDirectoryBundle\Document\PostalAddress;
use Biopen\GeoDirectoryBundle\Document\ElementUrl;
use Biopen\GeoDirectoryBundle\Document\ElementImage;
use Biopen\GeoDirectoryBundle\Document\ImportState;

class ElementImportMappingService
{
  protected $import;
  protected $createMissingOptions;
  protected $parentCategoryIdToCreateMissingOptions;
  protected $em;
  protected $ontologyMapping;
  protected $allNewFields;
  protected $existingProps;
  protected $coreFields = ['id', 'name', 'categories', 'streetAddress', 'addressLocality', 'postalCode', 'addressCountry', 'latitude', 'longitude', 'images', 'owner', 'source', 'openHours', 'email'];
  protected $mappedCoreFields = [
    'title' => 'name', 'nom' => 'name',
    'taxonomy' => 'categories',
    'address' => 'streetAddress',
    'city' => 'addressLocality',
    'postcode' => 'postalCode',
    'country' => 'addressCountry',
    'lat' => 'latitude',
    'long' => 'longitude', 'lng' => 'longitude', 'lon' => 'longitude'
  ];

  public function __construct(DocumentManager $documentManager)
  {
    $this->em = $documentManager;
  }

  public function transform($data, $import)
  {
    $this->import = $import;
    $this->createMissingOptions = $import->getCreateMissingOptions();
    $parent = $import->getParentCategoryToCreateOptions() ?: $this->em->getRepository('BiopenGeoDirectoryBundle:Category')->findOneByIsRootCategory(true);
    $this->parentCategoryIdToCreateMissingOptions = $parent->getId();

    // Execute custom code (the <?php is used to have proper code highliting in text editor, we remove it before executing)
    eval(str_replace('<?php', '', $import->getCustomCode()));
    if ($data == null || !is_array($data)) return [];

    // elements is ofently stored nested in some attribute
    if (array_key_exists('data', $data) && is_array($data['data'])) $data = $data['data'];
    if (array_key_exists('elements', $data) && is_array($data['elements'])) $data = $data['elements'];
    if (array_key_exists('features', $data) && is_array($data['features'])) $data = $data['features'];

    // Fixs gogocarto ontology when importing, to simplify import/export from gogocarto to gogocarto
    foreach ($data as $key => $row) {
      if (is_array($row)) {
        // GOGOCARTO ONTOLGY
        if (array_key_exists('geo', $row))
        {
          $data[$key]['latitude']  = $row['geo']['latitude'];
          $data[$key]['longitude'] = $row['geo']['longitude'];
          unset($data[$key]['geo']);
        }
        if (array_key_exists('address', $row))
        {
          $address = $row['address'];

          if (gettype($address) == "string") $data[$key]['streetAddress'] = $address;
          else if ($address) {
            if (array_key_exists('streetNumber', $address))    $data[$key]['streetNumber']   =  $address['streetNumber'];
            if (array_key_exists('streetAddress', $address))   $data[$key]['streetAddress']   = $address['streetAddress'];
            if (array_key_exists('addressLocality', $address)) $data[$key]['addressLocality'] = $address['addressLocality'];
            if (array_key_exists('postalCode', $address))      $data[$key]['postalCode']      = $address['postalCode'];
            if (array_key_exists('addressCountry', $address))  $data[$key]['addressCountry']  = $address['addressCountry'];
          }
          unset($data[$key]['address']);
        }
        if (array_key_exists('categories', $row) && array_key_exists('categoriesFull', $row)) {
          $data[$key]['categories'] = $data[$key]['categoriesFull'];
          unset($data[$key]['categoriesFull']);
        }

        // GEOJSON
        if (isset($row['geometry']) && isset($row['geometry']['coordinates'])) {
          $data[$key]['latitude']  = $row['geometry']['coordinates'][1];
          $data[$key]['longitude'] = $row['geometry']['coordinates'][0];
          unset($data[$key]['geometry']);

          if (isset($row['properties']) && $this->isAssociativeArray($row['properties'])) {
            foreach ($row['properties'] as $field => $value) {
              $data[$key][$field] = $value;
            }
            unset($data[$key]['properties']);
          }
        }
      } else {
        // the $row is not an array, probably a string so we ignore it
        unset($data[$key]);
      }
    }

    // Ontology
    $this->collectOntology($data, $import);
    $data = $this->mapOntology($data);

    if ($data == null || !is_array($data)) return [];
    // remove empty row, i.e. without name
    $data = array_filter($data, function($row) { return array_key_exists('name', $row); });

    // Taxonomy
    if ($import->isCategoriesFieldMapped())
    {
      $this->collectTaxonomy($data, $import);
      $data = $this->mapTaxonomy($data);
    }

    $this->em->persist($import);
    $this->em->flush();
    return $data;
  }

  public function collectOntology($data, $import)
  {
    $this->ontologyMapping = $import->getOntologyMapping();
    $this->allNewFields = [];
    $props = $this->em->getRepository('BiopenGeoDirectoryBundle:Element')->findAllCustomProperties();
    $props = array_merge($this->coreFields, $props);
    $this->existingProps = [];
    foreach ($props as $prop) {
      $this->existingProps[str_replace('_', '', strtolower($prop))] = $prop;
    }

    foreach($data as $row)
    {
      foreach ($row as $key => $value) {
        $this->collectKey($key, null, $import);
        if ($this->isAssociativeArray($value) && !in_array($key, ['openHours', 'modifiedElement'])) {
          foreach ($value as $subkey => $subvalue) { $this->collectKey($subkey, $key, $import); }
        }
      }
    }
    // delete no more used fields
    foreach($this->ontologyMapping as $field => $mappedField) {
      if (!in_array($field, $this->allNewFields)) unset($this->ontologyMapping[$field]);
    }

    $import->setOntologyMapping($this->ontologyMapping);
  }

  private function collectKey($key, $parentKey = null, $import) {
    if (in_array($key, ['__initializer__', '__cloner__', '__isInitialized__'])) return;
    $keyName = $parentKey ? $parentKey . '/' . $key : $key;
    if (!$keyName || strlen($keyName) == 0) return;

    if (!in_array($keyName, $this->allNewFields)) $this->allNewFields[] = $keyName;
    if (!array_key_exists($keyName, $this->ontologyMapping)) {
      $keyLower = str_replace('_', '', strtolower($key));
      $value = array_key_exists($keyLower, $this->existingProps) ? $this->existingProps[$keyLower] : "";
      // use alternative name, like lat instead of latitude
      if (!$value && array_key_exists($keyLower, $this->mappedCoreFields))
        $value = $this->mappedCoreFields[$keyLower];
      // Asign mapping
      if (!$value || !in_array($value, array_values($this->ontologyMapping)))
      {
        $this->ontologyMapping[$keyName] = $value;
        $import->setNewOntologyToMap(true);
      }
    }
  }

  private function isAssociativeArray($a) {
    if (!is_array($a)) return false;
    foreach(array_keys($a) as $key)
      if (!is_int($key)) return true;
    return false;
  }

  public function collectTaxonomy($data, $import)
  {
    $taxonomyMapping = $import->getTaxonomyMapping();
    $allNewCategories = [];
    $this->createOptionsMappingTable();

    foreach($data as $row)
    {
      $categories = $row['categories'];
      $categories = is_array($categories) ? $categories : explode(',', $categories);
      foreach($categories as $category) {
        if (is_array($category)) $category = $category['name'];
        $category = ltrim(rtrim($category));
        $category = str_replace('.', '_', $category);
        if (!in_array($category, $allNewCategories)) $allNewCategories[] = $category;
        if ($category && !array_key_exists($category, $taxonomyMapping))
        {
          $categorySlug = $this->slugify($category);
          $value = array_key_exists($categorySlug, $this->mappingTableIds) ? $this->mappingTableIds[$categorySlug]['id'] : '';

          // create option if does not exist
          if ($value == '' && $this->createMissingOptions) $value = $this->createOption($category);

          $taxonomyMapping[$category] = [$value];
          $import->setNewTaxonomyToMap(true);
        }
        // create options for previously imported non mapped options
        if (array_key_exists($category, $taxonomyMapping)
            && (!$taxonomyMapping[$category] || $taxonomyMapping[$category] == '/')
            && $this->createMissingOptions) {
          $taxonomyMapping[$category] = [$this->createOption($category)];
        }
      }
    }
    // delete no more used categories
    foreach($taxonomyMapping as $category => $mappedCategory) {
      if (!in_array($category, $allNewCategories)) unset($taxonomyMapping[$category]);
    }
    $import->setTaxonomyMapping($taxonomyMapping);
  }

  private function mapOntology($data)
  {
    $mapping = $this->import->getOntologyMapping();
    foreach ($data as $key => $row) {

      // First map nested fields
      foreach ($mapping as $search => $replace) {
        $searchKeys = explode('/', $search);
        if (count($searchKeys) == 2) {
          $searchkey = $searchKeys[0]; $subkey = $searchKeys[1];

          if (!in_array($replace, ['/', '']) && isset($data[$key][$searchkey]) && isset($row[$searchkey][$subkey]) && !isset($data[$key][$replace])) {
            $data[$key][$replace] = $row[$searchkey][$subkey];
            unset($data[$key][$searchkey][$subkey]);
          }
        }
      }

      // Then remove non mapped fields
      foreach ($mapping as $search => $replace) {
        if (in_array($replace, ['/', ''])) unset($data[$key][$search]);
      }

      // Finally map non nested fields
      foreach ($mapping as $search => $replace) {
        $searchKeys = explode('/', $search);
        if (count($searchKeys) == 1) {
          $searchkey = $search;

          if (!in_array($replace, ['/', '']) && isset($data[$key][$searchkey]) && !isset($data[$key][$replace])) {
            $data[$key][$replace] = $row[$searchkey];
            unset($data[$key][$searchkey]);
          }
        }
      }

      // add streetNumber into streetAddress
      if (isset($data[$key]['streetNumber']) && isset($data[$key]['streetAddress'])) {
        $data[$key]['streetAddress'] = $data[$key]['streetNumber'] . ' ' . $data[$key]['streetAddress'];
        unset($data[$key]['streetNumber']);
      }
      if (isset($data[$key]['fullAddress'])) {
        $data[$key]['streetAddress'] = $data[$key]['fullAddress'];
        unset($data[$key]['fullAddress']);
      }
    }

    return $data;
  }

  private function mapTaxonomy($data)
  {
    $mapping = $this->import->getTaxonomyMapping();
    foreach ($data as $key => $row)
    {
      if (isset($data[$key]['categories'])) {
        if (is_string($row['categories'])) $row['categories'] = explode(',', $row['categories']);
        $categories = []; $categoriesIds = [];
        foreach ($row['categories'] as $category)
        {
          $val = is_array($category) ? $category['name'] : $category;
          if (isset($mapping[$val]) && $mapping[$val])
          {
            foreach ($mapping[$val] as $mappedCategory) {
              if (array_key_exists($mappedCategory, $this->mappingTableIds))
              {
                $newcat['originalValue'] = $val;
                $newcat['mappedName'] = $this->mappingTableIds[$mappedCategory]['name'];
                $newcat['mappedId'] = $this->mappingTableIds[$mappedCategory]['id'];
                if (!in_array($newcat['mappedId'], $categoriesIds))
                {
                  if (isset($category['index'])) $newcat['index'] = $category['index'];
                  if (isset($category['description'])) $newcat['description'] = $category['description'];
                  $categories[] = $newcat;
                  $categoriesIds[] = $newcat['mappedId'];
                  $parentIds = $this->mappingTableIds[$mappedCategory]['idAndParentsId'];
                  foreach ($parentIds as $id) {
                    if (!in_array($id, $categoriesIds)) {
                      $categories[] = ['mappedId' => $id, 'info' => "Automatiquement ajoutée (category parente d'une category importée)"];
                      $categoriesIds[] = $id;
                    }
                  }
                }
              }
            }
          }
        }
        $data[$key]['categories'] = $categories;
      }
    }
    return $data;
  }

  private function array_flatten(array $array) {
    $return = array();
    array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
    return $return;
  }

  private function createOptionsMappingTable($options = null)
  {
    if ($options === null) $options = $this->em->getRepository('BiopenGeoDirectoryBundle:Option')->findAll();

    foreach($options as $option)
    {
      $ids = [
        'id' => $option->getId(),
        'name' => $option->getName(),
        'idAndParentsId' => $option->getIdAndParentOptionIds()
      ];
      $this->mappingTableIds[$this->slugify($option->getNameWithParent())] = $ids;
      $this->mappingTableIds[$this->slugify($option->getName())] = $ids;
      $this->mappingTableIds[strval($option->getId())] = $ids;
      if ($option->getCustomId()) $this->mappingTableIds[$this->slugify($option->getCustomId())] = $ids;
    }
  }

  private function createOption($name)
  {
    $option = new Option();
    $option->setName($name);
    $parent = $this->em->getRepository('BiopenGeoDirectoryBundle:Category')->find($this->parentCategoryIdToCreateMissingOptions);
    $option->setParent($parent);
    $option->setUseIconForMarker(false);
    $option->setUseColorForMarker(false);
    $this->em->persist($option);
    $this->createOptionsMappingTable([$option]);
    return $option->getId();
  }

  private function slugify($text)
  {
    if (!is_string($text)) return;
    // replace non letter or digits by -
    $text = str_replace('é', 'e', $text);
    $text = str_replace('è', 'e', $text);
    $text = str_replace('ê', 'e', $text);
    $text = str_replace('ô', 'o', $text);
    $text = str_replace('ç', 'c', $text);
    $text = str_replace('à', 'a', $text);
    $text = str_replace('â', 'a', $text);
    $text = str_replace('î', 'i', $text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); // transliterate
    $text = preg_replace('~[^-\w]+~', '', $text); // remove unwanted characters
    $text = trim($text, '-'); // trim
    $text = rtrim($text, 's'); // remove final "s" for plural
    $text = preg_replace('~-+~', '-', $text); // remove duplicate -
    $text = strtolower($text); // lowercase

    if (empty($text)) return '';
    return $text;
  }
}