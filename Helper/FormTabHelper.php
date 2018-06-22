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
use Mautic\FormBundle\Entity\Submission;
use Mautic\FormBundle\Model\FormModel;
use Mautic\FormBundle\Model\SubmissionModel;
use Mautic\LeadBundle\Model\LeadModel;
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
     * @var LeadModel
     */
    private $leadModel;

    /**
     * FormTabHelper constructor.
     *
     * @param TemplatingHelper  $templatingHelper
     * @param FormModel         $formModel
     * @param UserHelper        $userHelper
     * @param CorePermissions   $security
     * @param SubmissionModel   $submissionModel
     * @param IntegrationHelper $integrationHelper
     * @param LeadModel         $leadModel
     */
    public function __construct(
        TemplatingHelper $templatingHelper,
        FormModel $formModel,
        UserHelper $userHelper,
        CorePermissions $security,
        SubmissionModel $submissionModel,
        IntegrationHelper $integrationHelper,
        LeadModel $leadModel
    ) {

        $this->formModel         = $formModel;
        $this->templatingHelper  = $templatingHelper;
        $this->userHelper        = $userHelper;
        $this->security          = $security;
        $this->submissionModel   = $submissionModel;
        $this->integrationHelper = $integrationHelper;
        $this->leadModel         = $leadModel;
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
     * @param Submission $submission
     * @param            $contactId
     *
     * @return string
     */
    public function getItemContentBySubmission(Submission $submission, $contactId, $new = false)
    {
        $result = $this->getFormsWithResults($contactId, $submission->getId());

        return $this->templatingHelper->getTemplating()->render(
            'MauticExtendeeFormTabBundle:Result:item.html.php',
            [
                'item'      => $result[0]['results']['results'][0],
                'form'      => $submission->getForm(),
                'lead'      => $this->leadModel->getLead($contactId),
                'canDelete' => $this->canDelete($submission->getForm()),
                'skip'      => true,
                'new'       => $new,
            ]
        );
    }

    public function getFormWithResult(Form $form, $contactId)
    {
        $viewOnlyFields = $this->formModel->getCustomComponents()['viewOnlyFields'];

        $start      = 0;
        $limit      = 9999;
        $orderBy    = 's.date_submitted';
        $orderByDir = 'DESC';
        $filters    = [];
        $filters[]  = ['column' => 's.form_id', 'expr' => 'eq', 'value' => $form->getId()];
        $filters[]  = ['column' => 's.lead_id', 'expr' => 'eq', 'value' => $contactId];

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

        return [
            'results' => $submissionEntities,
            'content' => $this->templatingHelper->getTemplating()->render(
                'MauticExtendeeFormTabBundle:Result:list-condensed.html.php',
                [
                    'lead'           => $this->leadModel->getLead($contactId),
                    'items'          => $submissionEntities['results'],
                    'form'           => $form,
                    'totalCount'     => $submissionEntities['count'],
                    'canCreate'      => $this->canCreate($form),
                    'canEdit'        => $this->canEdit($form),
                    'canDelete'      => $this->canDelete($form),
                    'viewOnlyFields' => $viewOnlyFields,
                ]
            ),
        ];

    }

    /**
     * Return Forms with results to contacts tab.
     *
     * @param int $leadId
     *
     * @return array
     */
    public function getFormsWithResults($leadId, $submissionId = null)
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
        if (empty($keys['forms']) && empty($keys['forms_forced'])) {
            return [];
        }

        $lead = $this->leadModel->getLead($leadId);

        $formResults = [];
        $filters     = [];
        //  $filters[]      = ['column' => 'f.inContactTab', 'expr' => 'eq', 'value' => 1];

        $permissions = $this->security->isGranted(
            [
                'form:forms:viewown',
                'form:forms:viewother',
            ],
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

            if (!in_array($form->getId(), array_merge($keys['forms'], $keys['forms_forced']))) {
                continue;
            }

            $submissions = $this->getFormWithResult($form, $leadId);

            if (empty($submissions['results']['count']) && !in_array($form->getId(), $keys['forms_forced'])) {
                continue;
            }

            $formResults[$key]           = $submissions;
            $formResults[$key]['entity'] = $entity[0];
        }
        $this->resultCache = array_values($formResults);

        return $this->resultCache;
    }

    /**
     * @param Form $form
     *
     * @return bool
     */
    public function canDelete(Form $form)
    {
        return $this->security->hasEntityAccess(
            'form:forms:deleteown',
            'form:forms:deleteother',
            $form->getCreatedBy()
        );
    }

    /**
     * @param Form $form
     *
     * @return bool
     */
    public function canEdit(Form $form)
    {
        return $this->security->hasEntityAccess(
            'form:forms:editown',
            'form:forms:editother',
            $form->getCreatedBy()
        );
    }

    /**
     * @param Form $form
     *
     * @return bool
     */
    public function canView(Form $form)
    {
        return $this->security->hasEntityAccess(
            'form:forms:viewown',
            'form:forms:viewother',
            $form->getCreatedBy()
        );
    }

    /**
     * @param Form $form
     *
     * @return bool
     */
    public function canCreate(Form $form)
    {
        return $this->security->hasEntityAccess(
            'form:forms:create',
            'form:forms:create',
            $form->getCreatedBy()
        );
    }

    /**
     * @return mixed
     */
    public function getResultCache()
    {
        return $this->resultCache;
    }

    /**
     * @param mixed $resultCache
     */
    public function setResultCache($resultCache)
    {
        $this->resultCache = $resultCache;
    }


}
