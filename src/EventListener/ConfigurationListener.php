<?php

namespace App\EventListener;

use App\Document\Configuration\ConfigurationApi;
use App\Document\Configuration;
use App\Document\Configuration\ConfigurationMarker;
use App\Services\AsyncService;
use Symfony\Component\Filesystem\Filesystem;
use App\Helper\GoGoHelper;

class ConfigurationListener
{
    protected $asyncService;

    public function __construct(AsyncService $asyncService, $baseUrl, $contactEmail, $projectDir)
    {
        $this->asyncService = $asyncService;
        $this->baseUrl = $baseUrl;
        $this->contactEmail = $contactEmail;
        $this->projectDir = $projectDir;
    }

    public function preUpdate(\Doctrine\ODM\MongoDB\Event\LifecycleEventArgs $args)
    {
        $document = $args->getDocument();
        $dm = $args->getDocumentManager();
        $changeset = $dm->getChangeSet($document);
        // Update Json representation to fit the private property config
        if ($document instanceof ConfigurationApi) {            
            if (array_key_exists('publicApiPrivateProperties', $changeset)) {
                $this->asyncService->callCommand('app:elements:updateJson', ['ids' => 'all']);
            }
        }
        if ($document instanceof ConfigurationMarker) {
            if (array_key_exists('fieldsUsedByTemplate', $changeset)) {
                $this->asyncService->callCommand('app:elements:updateJson', ['ids' => 'all']);
            }
        }

        if ($document instanceof Configuration) {
            if (array_key_exists('elementFormFieldsJson', $changeset)) {
                $formFieldsChanged = $changeset['elementFormFieldsJson'];
                $oldFormFields = $formFieldsChanged[0];
                $newFormFields = $formFieldsChanged[1];
                $this->updateSearchIndex($dm, $oldFormFields, $newFormFields);
            }
            if (array_key_exists('customDomain', $changeset)) {
                $customDomainChanged = $changeset['customDomain'];
                $oldCustomDomain = rtrim(preg_replace('/https?:\/\//', '', $customDomainChanged[0]), '/');
                $newCustomDomain = rtrim(preg_replace('/https?:\/\//', '', $customDomainChanged[1]), '/');
                $filesystem = new Filesystem();
                // Those files will be consumed by bin/execute_custom_domain.sh called by a cron tab
                $removePath = "$this->projectDir/var/file_queues/custom_domain_to_remove";
                $addPath = "$this->projectDir/var/file_queues/custom_domain_to_configure";
                if ($oldCustomDomain) {
                    $filesystem->dumpFile("$removePath/{$document->getDbName()}", $oldCustomDomain);
                }
                if  ($newCustomDomain) {
                    $gogo_url = $document->getDbName() . '.' . $this->baseUrl;
                    $filesystem->dumpFile("$addPath/{$document->getDbName()}", "$newCustomDomain $gogo_url $this->contactEmail");
                }
            }
        }
    }

    private function updateSearchIndex($dm, $oldFormFields, $newFormFields) {
        if ($oldFormFields == null || $newFormFields == null) return;
        $oldSearchIndex = $this->calculateSearchIndexConfig($oldFormFields);
        $newSearchIndex = $this->calculateSearchIndexConfig($newFormFields);

        if ($oldSearchIndex != $newSearchIndex) {
            $db = $dm->getDB();
            $db->command(["deleteIndexes" => 'Element',"index" => "name_text"]);
            $db->command(["deleteIndexes" => 'Element',"index" => "search_index"]);
            $db->selectCollection('Element')->createIndex($newSearchIndex["fields"], [
                'name' => "search_index", 
                "default_language" => "french", 
                "weights" => $newSearchIndex["weights"]
            ]);
            return ;
        }
    }

    private function calculateSearchIndexConfig($formFieldsJson) {
        $indexConf = [];
        $indexWeight = [];
        $formFields = json_decode($formFieldsJson);
        foreach ($formFields as $key => $field) {
            if (property_exists($field, 'search') && $field->search) {
                $path = "data.{$field->name}";
                if ($field->name == 'name') $path = 'name';
                $indexConf[$path] = "text";
                $indexWeight[$path] = (int) $field->searchWeight;
            }
        }
        // default index on name
        if (count($indexConf) == 0) {
            $indexConf = ['name' => 'text'];
            $indexWeight = ['name' => 1];
        }
        return ['fields' => $indexConf, 'weights' => $indexWeight];
    }
}
