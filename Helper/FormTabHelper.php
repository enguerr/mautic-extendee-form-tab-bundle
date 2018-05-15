<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\Helper;

use Joomla\Http\Http;
use Mautic\CoreBundle\Helper\TemplatingHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Model\FormModel;
use Mautic\FormBundle\Model\SubmissionModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticExtendeeFormTabBundle\Integration\FormTabIntegration;

/**
 * Class FormTabHelper.
 */
class FormTabHelper
{

    /**
     * @var FormModel
     */
    private $formModel;

    /**
     * @var TemplatingHelper
     */
    private $templatingHelper;

    /**
     * @var UserHelper
     */
    private $userHelper;

    /**
     * @var CorePermissions
     */
    private $security;

    /**
     * @var SubmissionModel
     */
    private $submissionModel;

    /**
     * @var IntegrationHelper
     */
    private $integrationHelper;

    private $resultCache;

    /**
     * FormTabHelper constructor.
     *
     * @param TemplatingHelper  $templatingHelper
     * @param FormModel         $formModel
     * @param UserHelper        $userHelper
     * @param CorePermissions   $security
     * @param SubmissionModel   $submissionModel
     * @param IntegrationHelper $integrationHelper
     */
    public function __construct(
        TemplatingHelper $templatingHelper,
        FormModel $formModel,
        UserHelper $userHelper,
        CorePermissions $security,
        SubmissionModel $submissionModel,
        IntegrationHelper $integrationHelper
    ) {

        $this->formModel         = $formModel;
        $this->templatingHelper  = $templatingHelper;
        $this->userHelper        = $userHelper;
        $this->security          = $security;
        $this->submissionModel   = $submissionModel;
        $this->integrationHelper = $integrationHelper;
    }

    /**
     * @param array $leadForms
     */
    public function getTabHeaderWithResult(array $leadForms)
    {
        return $this->templatingHelper->getTemplating()->render(
            'MauticExtendeeFormTabBundle:Result:tab-header.html.php',
            [
                'leadForms' => $leadForms,
            ]
        );
    }

    /**
     * @param array $leadForms
     */
    public function getTabContentWithResult(array $leadForms)
    {
        return $this->templatingHelper->getTemplating()->render(
            'MauticExtendeeFormTabBundle:Result:tab-content.html.php',
            [
                'leadForms' => $leadForms,
            ]
        );
    }

    /**
     * Return Forms with results to contacts tab.
     *
     * @param int $leadId
     *
     * @return array
     */
    public function getFormsWithResults($leadId)
    {
        if (!empty($this->resultCache)) {
            return $this->resultCache;
        }


        /** @var FormTabIntegration $formTab */
        $formTab = $this->integrationHelper->getIntegrationObject('FormTab');

        if (false === $formTab || !$formTab->getIntegrationSettings()->getIsPublished()) {
            return [];
        }

        $keys = $formTab->getKeys();
        if (empty($keys['forms'])) {
            return [];
        }

        $formResults    = [];
        $viewOnlyFields = $this->formModel->getCustomComponents()['viewOnlyFields'];
        $filters        = [];
        //  $filters[]      = ['column' => 'f.inContactTab', 'expr' => 'eq', 'value' => 1];


        $permissions = $this->security->isGranted(
            ['form:forms:viewown', 'form:forms:viewother'],
            'RETURN_ARRAY'
        );
        if ($permissions['form:forms:viewown'] && !$permissions['form:forms:viewother']) {
            $filters[] = ['column' => 'f.createdBy', 'expr' => 'eq', 'value' => $this->userHelper->getUser()->getId()];
        }
        $formEntities = $this->formModel->getEntities(
            [
                'filter' => ['force' => $filters],
            ]
        );
        foreach ($formEntities as $key => $entity) {
            /** @var Form $form */
            $form = $entity[0];
            if (!in_array($form->getId(), $keys['forms'])) {
                continue;
            }

            $formResults[$key]['entity'] = $entity[0];

            $start      = 0;
            $limit      = 9999;
            $orderBy    = 's.date_submitted';
            $orderByDir = 'DESC';
            $filters    = [];
            $filters[]  = ['column' => 's.form_id', 'expr' => 'eq', 'value' => $form->getId()];
            $filters[]  = ['column' => 's.lead_id', 'expr' => 'eq', 'value' => $leadId];
            //get the results
            $submissionEntities = $this->submissionModel->getEntities(
                [
                    'start'          => $start,
                    'limit'          => $limit,
                    'filter'         => ['force' => $filters],
                    'orderBy'        => $orderBy,
                    'orderByDir'     => $orderByDir,
                    'form'           => $form,
                    'withTotalCount' => true,
                ]
            );
            if (empty($submissionEntities['count'])) {
                unset($formResults[$key]);
                continue;
            }
            $formResults[$key]['results'] = $submissionEntities;
            $formResults[$key]['content'] = $this->templatingHelper->getTemplating()->render(
                'MauticExtendeeFormTabBundle:Result:list-condensed.html.php',
                [
                    'items'          => $submissionEntities['results'],
                    'filters'        => $filters,
                    'form'           => $form,
                    'page'           => 1,
                    'totalCount'     => $submissionEntities['count'],
                    'limit'          => $limit,
                    'tmpl'           => '',
                    'canDelete'      => false,
                    'viewOnlyFields' => $viewOnlyFields,
                ]
            );
        }
        $this->resultCache = array_values($formResults);
        return $this->resultCache;
    }


}
