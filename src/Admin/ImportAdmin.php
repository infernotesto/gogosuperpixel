<?php

namespace App\Admin;

use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use App\Helper\GoGoHelper;

class ImportAdmin extends GoGoAbstractAdmin
{
    public $config;
    
    public function getTemplate($name)
    {
        $isDynamic = "App\Document\ImportDynamic" == $this->getClass();
        switch ($name) {
            case 'edit': return 'admin/edit/edit_import.html.twig';
            break;
            case 'list': return $isDynamic ? 'admin/list/list_import_dynamic.html.twig' : 'admin/list/list_import.html.twig';
            break;
            default: return parent::getTemplate($name);
            break;
        }
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        $dm = GoGoHelper::getDmFromAdmin($this);
        $repo = $dm->get('Element');
        $formProperties = json_encode($repo->findFormProperties());
        $elementProperties = json_encode($repo->findDataCustomProperties());
        $this->config = $dm->get('Configuration')->findConfiguration();
        $taxonomy = $dm->get('Taxonomy')->findTaxonomy();
        $optionsList = $taxonomy->getTaxonomyJson();

        $isDynamic = $this->getSubject()->isDynamicImport();
        $title = $this->trans($isDynamic ? 'imports.dynamic' : 'imports.static');
        $isPersisted = $this->getSubject()->getId();

        $usersQuery = $dm->query('User');
        $usersQuery->addOr($usersQuery->expr()->field('roles')->exists(true))
                   ->addOr($usersQuery->expr()->field('groups')->exists(true));
        $formMapper
            ->tab('general')
                ->panel($title, ['class' => 'col-md-12'])
                    ->add('sourceName', null, ['required' => true])
                    ->add('file', FileType::class);
        if ($isDynamic) {
            $formMapper
                    // Every attribute that will be update need to be mapped here. Following attributes are manually inserted in element-import.html.twig, but we still need them here as hidden input
                    ->add('osmQueriesJson', HiddenType::class)
                    ->add('url', HiddenType::class)
                    ->add('sourceType', null, ['attr' => ['class' => 
                            'gogo-element-import',
                            'data-title-layer' => $this->config->getDefaultTileLayer()->getUrl(),
                            'data-default-bounds' => json_encode($this->config->getDefaultBounds()),
                        ], 'required' => true])
                ->end()
                ->panel('parameters')
                    ->add('refreshFrequencyInDays', null, ['required' => false, 'label' => 'Fréquence de mise à jours des données en jours (laisser vide pour ne jamais mettre à jour automatiquement']) // TODO translate
                    ->add('usersToNotify', ModelType::class, [
                        'class' => 'App\Document\User',
                        'required' => false,
                        'multiple' => true,
                        'query' => $usersQuery,
                        'btn_add' => false,
                        'label' => "Utilisateurs à notifier en cas d'erreur, ou lorsque de nouveaux champs/catégories sont à faire correspondre", ], ['admin_code' => 'admin.option_hidden']) // TODO translate
                    ->add('isSynchronized', null, [
                        'disabled' => !$this->config->getOsm()->isConfigured(),
                        'required' => false,
                        'attr' => ['class' => 'input-is-synched'],
                        'label_attr' => ['title' => "Chaque modification sera envoyée à OpenStreetMap"], // TODO translate
                        'label' => "Autoriser l'édition des données" . ($this->config->getOsm()->isConfigured() ? '' : ' (Vous devez préalablement renseigner des identifiants dans Autre configuration -> OpenStreetMap)') // TODO translate
                    ])
                    ->add('moderateElements')
                    ->add('idsToIgnore', TextType::class, ['mapped' => false, 'required' => false, 
                        'attr' => ['class' => 'gogo-display-array', 
                        'value' => $this->getSubject()->getIdsToIgnore()], 
                        'label' => "Liste des IDs qui seront ignorées lors de l'import",  // TODO translate
                        'label_attr' => ['title' => "Pour ignorer un élément, supprimer le (définitivement) et il ne sera plus jamais importé. Si vous supprimez un élément dynamiquement importé juste en changeant son status (soft delete), l'élément sera quand meme importé mais conservera son status supprimé. Vous pourrez donc à tout moment restaurer cet élement pour le voir apparaitre de nouveau"]]); // TODO translate
        } else {
            $formMapper                    
                    ->add('url', UrlType::class)
                    ->add('moderateElements');
        }
        $formMapper->end();                
        if ($isPersisted) {
            $formMapper->panel('historic')
                        ->add('currState', null, ['attr' => ['class' => 'gogo-display-logs'], 'label_attr' => ['style' => 'display: none'], 'mapped' => false])
                    ->end();
        }
        $formMapper->end();

        // TAB - Custom Code
        $formMapper->tab('customCode')
            ->panel('code')
                ->add('customCode', null, ['attr' => ['class' => 'gogo-code-editor', 'format' => 'php', 'height' => '500']])
            ->end()
        ->end();

        
        if ($isPersisted) {
            // TAB - Ontology Mapping
            $title = $this->trans('imports.form.groups.ontologyMappingTab');
            if ($this->getSubject()->getNewOntologyToMap()) {
                $title .= ' <label class="label label-info">'.$this->trans('imports.form.groups.newFields').'</label>';
            }
            $formMapper
                ->tab($title)                    
                    ->panel('ontologyMappingPanel')
                        ->add('ontologyMapping', null, [
                            'label_attr' => ['style' => 'display:none'], 
                            'attr' => ['class' => 'gogo-mapping-ontology', 
                            'data-form-props' => $formProperties, 
                            'data-props' => $elementProperties]])
                    ->end();
                
                if ($this->getSubject()->getSourceType() != 'osm') {
                    $formMapper
                    ->panel('otherOptions', ['box_class' => 'box box-default'])
                        ->add('geocodeIfNecessary')
                        ->add('fieldToCheckElementHaveBeenUpdated')
                    ->end();
                }
                $formMapper->end();

            // TAB - Taxonomy Mapping
            if (count($this->getSubject()->getOntologyMapping()) > 0) {     
                $title = $this->trans('imports.form.groups.taxonomyMapping');
                if ($this->getSubject()->getNewTaxonomyToMap()) {
                    $title .= ' <label class="label label-info">'.$this->trans('imports.form.groups.newCategories').'</label>';
                }
                $formMapper->tab($title)          
                    ->panel('taxonomyMapping2')
                        ->add('taxonomyMapping', null, ['label_attr' => ['style' => 'display:none'], 'attr' => ['class' => 'gogo-mapping-taxonomy', 'data-options' => $optionsList]])
                    ->end()

                    ->panel('otherOptions', ['box_class' => 'box box-default'])
                        ->add('optionsToAddToEachElement', ModelType::class, [
                            'class' => 'App\Document\Option',
                            'multiple' => true,
                            'btn_add' => false],
                            ['admin_code' => 'admin.option_hidden'])
                        ->add('needToHaveOptionsOtherThanTheOnesAddedToEachElements')
                        ->add('preventImportIfNoCategories')
                    ->end()
                ->end();
            }

            if ($this->getSubject()->isDynamicImport() && $this->getSubject()->getIsSynchronized()) {
                // TAB - Custom Code For Export
                $formMapper->tab('customCodeForExportTab')
                    ->panel('customCodeForExportPanel')
                        ->add('customCodeForExport', null, [
                            'attr' => ['class' => 'gogo-code-editor', 'format' => 'php', 'height' => '500']])
                    ->end()
                ->end();
            }
        }
    }

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('refresh', $this->getRouterIdParameter().'/refresh');
        $collection->add('collect', $this->getRouterIdParameter().'/collect');
        $collection->add('showData', $this->getRouterIdParameter().'/show-data');
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('sourceName')
        ;
    }

    public function createQuery($context = 'list')
    {
        $isDynamic = "App\Document\ImportDynamic" == $this->getClass();
        $query = parent::createQuery($context);
        if (!$isDynamic) {
            $query->field('type')->equals('normal');
        }
        $query->sort('updatedAt', 'DESC');

        return $query;
    }

    public function configureBatchActions($actions)
    {
        return [];
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $dm = GoGoHelper::getDmFromAdmin($this);
        $deletedElementsCount = $dm->get('Element')->findDeletedElementsByImportIdCount();
        $isDynamic = "App\Document\ImportDynamic" == $this->getClass();

        $listMapper
            ->addIdentifier('sourceName', null, ['label' => 'Nom de la source']) // TODO translate
            ->add('logs', null, ['label' => "Nombre d'éléments", 'template' => 'admin/partials/import/list_total_count.html.twig']); // TODO translate
        if ($isDynamic) {
            $listMapper
            ->add('idsToIgnore', null, ['label' => 'Infos', 'template' => 'admin/partials/import/list_non_visibles_count.html.twig', 'choices' => $deletedElementsCount]) // TODO translate
            ->add('refreshFrequencyInDays', null, ['label' => 'Mise à jour', 'template' => 'admin/partials/import/list_refresh_frequency.html.twig']); // TODO translate
        }

        $listMapper
            ->add('lastRefresh', null, ['label' => 'Dernier import', 'template' => 'admin/partials/import/list_last_refresh.html.twig']) // TODO translate
            ->add('_action', 'actions', [
                'actions' => [
                    'edit' => [],
                    'delete' => [],
                    'refresh' => ['template' => 'admin/partials/list__action_refresh.html.twig'],
                ],
            ])
        ;
    }
}
