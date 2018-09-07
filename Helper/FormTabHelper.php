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

use Doctrine\ORM\EntityManager;
use Joomla\Http\Http;
use Mautic\CoreBundle\Helper\TemplatingHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\FormBundle\Model\FormModel;
use Mautic\FormBundle\Model\SubmissionModel;
use Mautic\LeadBundle\Entity\Lead;
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
     * @var EntityManager
     */
    private $entityManager;

    /**
     * FormTabHelper constructor.
     *
     * @param TemplatingHelper                     $templatingHelper
     * @param FormModel                            $formModel
     * @param UserHelper                           $userHelper
     * @param CorePermissions                      $security
     * @param SubmissionModel                      $submissionModel
     * @param IntegrationHelper|IntegrationHeZlper $integrationHelper
     * @param LeadModel                            $leadModel
     * @param EntityManager                        $entityManager
     */
    public function __construct(
        TemplatingHelper $templatingHelper,
        FormModel $formModel,
        UserHelper $userHelper,
        CorePermissions $security,
        SubmissionModel $submissionModel,
        IntegrationHelper $integrationHelper,
        LeadModel $leadModel,
        EntityManager $entityManager
    ) {

        $this->formModel         = $formModel;
        $this->templatingHelper  = $templatingHelper;
        $this->userHelper        = $userHelper;
        $this->security          = $security;
        $this->submissionModel   = $submissionModel;
        $this->integrationHelper = $integrationHelper;
        $this->leadModel         = $leadModel;
        $this->entityManager = $entityManager;
    }

    /**
     * @param null $formId
     * @param bool $populate
     *
     * @param null $replaceAttrName
     *
     * @return string
     */
    public function getFormContentFromId($formId, $populate = false, $replaceAttrName = null)
    {
        return $this->getFormContent($this->formModel->getEntity($formId), $populate, $replaceAttrName);

    }

    /**
     * @param Form $form
     * @param bool $populate
     *
     * @param null $replaceAttrName
     *
     * @return mixed|string
     */
    public function getFormContent(Form $form, $populate = false, $replaceAttrName = null)
    {
        $html = $this->formModel->getContent($form, false, false);

        if ($replaceAttrName) {
            $html = str_replace('name="mauticform', $replaceAttrName, $html);

        }

        if (true === $populate) {
            $this->formModel->populateValuesWithGetParameters($form, $html);
        }

        $html = preg_replace('/<form(.*)>/', '', $html, 1);
        $html = preg_replace('/<button type="submit"(.*)<\/button>/', '', $html, 1);
        $html = str_replace('</form>', '', $html);

        return $html;
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

    /**
     * @param Form $form
     * @param      $contactId
     *
     * @param bool $withoutContent
     *
     * @return array
     */
    public function getFormWithResult(Form $form, $contactId, $withoutContent = false)
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
        $return             = [];
        $return['results']  = $submissionEntities;
        if ($withoutContent !== true) {
            $return['content'] = $this->templatingHelper->getTemplating()->render(
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
            );
        }
        return $return;
    }

    /**
     * Get all form entities with permission
     *
     * @return array|\Doctrine\ORM\Tools\Pagination\Paginator
     */
    public function getFormsEntities()
    {
        $filters     = [];

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
        return $this->formModel->getEntities(
            [
                'filter' => ['force' => $filters],
            ]
        );
    }

    /**
     * Get all date fields
     *
     * @return array
     */
    public function getDateFields()
    {
        $forms = $this->getFormsEntities();
        foreach ($forms as $entity) {
            /** @var Form $form */
            $form = $entity[0];
            $fields =  $form->getFields();
            /** @var Field $field */
            foreach ($fields as $field) {
                if (in_array($field->getType(), ['date'])) {
                    $dateFields[$form->getId()][$field->getAlias()] = $field;
                }
            }
        }
        return $dateFields;
    }

    /**
     * Compare a form result value with defined date value for defined lead.
     *
     * @param Form                   $form
     * @param CampaignExecutionEvent $event
     * @param string                 $value to compare with
     *
     * @return bool
     */
    public function compareDateValue($form, Lead $lead, $field, $value)
    {
        $formAlias = $form->getAlias();
        $formId = $form->getId();

        //use DBAL to get entity fields
        $q = $this->entityManager->getConnection()->createQueryBuilder();
        $q->select('s.id')
            ->from($this->submissionModel->getRepository()->getResultsTableName($formId, $formAlias), 'r')
            ->leftJoin('r', MAUTIC_TABLE_PREFIX.'form_submissions', 's', 's.id = r.submission_id')
            ->where(
                $q->expr()->andX(
                    $q->expr()->eq('s.lead_id', ':lead'),
                    $q->expr()->eq('s.form_id', ':form'),
                    $q->expr()->eq('r.'.$field, ':value')
                )
            )
            ->setParameter('lead', $lead->getId())
            ->setParameter('form', $formId)
            ->setParameter('value', $value);

        $result = $q->execute()->fetch();
        return !empty($result['id']);
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
        $formEntities = $this->getFormsEntities();

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
     * @return FormModel
     */
    public function getFormModel()
    {
        return $this->formModel;
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
