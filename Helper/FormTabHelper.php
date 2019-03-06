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
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\TemplatingHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\FormBundle\Model\FormModel;
use Mautic\FormBundle\Model\SubmissionModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticExtendeeFormTabBundle\Integration\FormTabIntegration;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class FormTabHelper.
 */
class FormTabHelper
{
    CONST ALLOWED_FORM_TAB_DECISIONS =  ['email.open'];

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
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * @var RequestStack
     */
    private $request;

    /**
     * @var EmailModel
     */
    private $emailModel;

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
     * @param CoreParametersHelper                 $coreParametersHelper
     * @param RequestStack                         $requestStack
     * @param EmailModel                           $emailModel
     */
    public function __construct(
        TemplatingHelper $templatingHelper,
        FormModel $formModel,
        UserHelper $userHelper,
        CorePermissions $security,
        SubmissionModel $submissionModel,
        IntegrationHelper $integrationHelper,
        LeadModel $leadModel,
        EntityManager $entityManager,
        CoreParametersHelper $coreParametersHelper,
        RequestStack $requestStack,
        EmailModel $emailModel
    ) {

        $this->formModel            = $formModel;
        $this->templatingHelper     = $templatingHelper;
        $this->userHelper           = $userHelper;
        $this->security             = $security;
        $this->submissionModel      = $submissionModel;
        $this->integrationHelper    = $integrationHelper;
        $this->leadModel            = $leadModel;
        $this->entityManager        = $entityManager;
        $this->coreParametersHelper = $coreParametersHelper;
        $this->request              = $requestStack;
        $this->emailModel = $emailModel;
    }

    /**
     * @param null $formId
     * @param bool $populate
     * @param null $replaceAttrName
     * @param bool $removeFileUploadFields
     *
     * @return string
     */
    public function getFormContentFromId($formId, $populate = false, $replaceAttrName = null, $removeFileUploadFields = false)
    {
        return $this->getFormContent($this->formModel->getEntity($formId), $populate, $replaceAttrName, $removeFileUploadFields);

    }

    /**
     * @param Form $form
     * @param bool $populate
     * @param null $replaceAttrName
     * @param bool $removeFileUploadFields
     *
     * @return mixed|string
     */
    public function getFormContent(Form $form, $populate = false, $replaceAttrName = null, $removeFileUploadFields = false)
    {
        $formId = $form->getId();
        $html   = $this->formModel->getContent($form, false, false);
        if ($replaceAttrName) {
            $html = str_replace('name="mauticform', $replaceAttrName, $html);

        }
        if (true === $populate) {
            $this->formModel->getRepository()->clear();
            $form = $this->formModel->getEntity($formId);
            $this->populateValuesWithGetParameters($form, $html);
        }

        $html = preg_replace('/<form(.*)>/', '', $html, 1);
        $html = preg_replace('/<button type="submit"(.*)<\/button>/', '', $html, 1);
        $html = str_replace('</form>', '', $html);

        if ($removeFileUploadFields) {
            $html = preg_replace('/<div(?:(?!(<div|<\/div>)).)*?type="file".*?<\/div>/s', '', $html);
        }

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
        $filters = [];

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
            $form   = $entity[0];
            $fields = $form->getFields();
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
     * Compare a form result value with defined value for lead.
     *
     * @param        $form
     * @param Lead   $lead
     * @param        $field
     * @param        $value
     * @param string $operatorExpr
     *
     * @return bool
     */
    public function compareValue($form, Lead $lead, $field, $value, $operatorExpr = 'eq')
    {

        $formAlias = $form->getAlias();
        $formId    = $form->getId();


        // Modify operator
        switch ($operatorExpr) {
            case 'like':
            case 'notLike':
                $value = strpos($value, '%') === false ? '%'.$value.'%' : $value;
                break;
            case 'startsWith':
                $operatorExpr    = 'like';
                $value           = $value.'%';
                break;
            case 'endsWith':
                $operatorExpr   = 'like';
                $value          = '%'.$value;
                break;
            case 'contains':
                $operatorExpr   = 'like';
                $value          = '%'.$value.'%';
                break;
        }

        //use DBAL to get entity fields
        $q = $this->entityManager->getConnection()->createQueryBuilder();
        $q->select('s.id')
            ->from($this->submissionModel->getRepository()->getResultsTableName($formId, $formAlias), 'r')
            ->leftJoin('r', MAUTIC_TABLE_PREFIX.'form_submissions', 's', 's.id = r.submission_id')
            ->where(
                $q->expr()->andX(
                    $q->expr()->eq('s.lead_id', ':lead'),
                    $q->expr()->eq('s.form_id', ':form')
                )
            )
            ->setParameter('lead', $lead->getId())
            ->setParameter('form', $formId);

        if ($operatorExpr === 'anniversary') {
            $q->andWhere(
                $q->expr()->andX(
                    $q->expr()->eq("MONTH(r. $field)", ':month'),
                    $q->expr()->eq("DAY(r. $field)", ':day')
                )
            )
                ->setParameter('month', $value->format('m'))
                ->setParameter('day', $value->format('d'));
        } elseif($operatorExpr === 'date') {
            $q->andWhere($q->expr()->eq('r.'.$field, ':value'))
                ->setParameter('value', $value->format('Y-m-d'));
        }else{
            $q->andWhere($q->expr()->$operatorExpr('r.'.$field, ':value'))
                ->setParameter('value', $value);
        }

        $results = $q->execute()->fetchAll();
        return $results;
    }


    /**
     * @param array $eventParents
     * @param int $relatedFormId
     *
     * @return array
     */
    public function getRelatedFormIdsFromEvents($eventParents, $relatedFormId)
    {
        $ids =$this->getFormIdFromEvents($eventParents);
        foreach ($ids as $key=>$id) {
            if ($relatedFormId !== $id[0]) {
                unset($ids[$key]);
            }
        }
        return $ids;
    }

    /**
     * @param $eventParents
     *
     * @return array
     */
    public function getFormIdFromEvents($eventParents)
    {
        $eventIds = [];
        foreach ($eventParents as $eventParent) {
            $eventIds[] = $this->getFormIdFromEvent($eventParent);
        }
        return $eventIds;
    }

    /**
     * @param array $eventParent
     *
     * @return array
     */
    public function getFormIdFromEvent($eventParent)
    {
        $fieldAlias = null;
        // If form value condition
        if ($eventParent->getType() === 'form.field_value') {
            $formId     = $eventParent->getProperties()['form'];
            $fieldAlias = $eventParent->getProperties()['field'];
        } else {
            // If form date value condition
            list($formId, $fieldAlias) = explode('|', $eventParent->getProperties()['field']);
        }

        return [$formId, $fieldAlias];
    }

    /**
     * @param $config
     *
     * @return \DateTime
     */
    public function getDate($config)
    {
        $triggerDate = new \DateTime(
            'now',
            new \DateTimeZone($this->coreParametersHelper->getParameter('default_timezone'))
        );

        if ($config['unit'] === 'anniversary') {
            return $triggerDate;
        }elseif(strpos($config['interval'], '-') === false && strpos($config['interval'], '+') === false && strpos($config['unit'], '-') === false && strpos($config['unit'], '+') === false)
        {
            $config['interval'] = '+'.$config['interval'];
        }
        $interval    = substr($config['interval'], 1); // remove 1st character + or -
        $unit        = strtoupper($config['unit']);


        switch ($unit) {
            case 'H':
            case 'I':
            case 'S':
            $interval = 'T'.$interval;
        }
        $trigger = '';
        $type = '';

        if (strpos($config['unit'], '+') !== false) {
            $trigger = substr($config['unit'], 1);
            $type = 'add';
        }elseif (strpos($config['unit'], '-') !== false) {
            $trigger = substr($config['unit'], 1);
            $type = 'sub';
        }elseif (strpos($config['interval'], '+') !== false) {
            $trigger = 'P'.$interval.$unit;
            $type = 'add';
        } elseif (strpos($config['interval'], '-') !== false) {
            $trigger = 'P'.$interval.$unit;
            $type = 'sub';
        }
        if ($trigger) {
            $triggerDate->$type(new \DateInterval($trigger)); //subtract the today date with interval
        }

        return $triggerDate;
    }

    /**
     * @param      $eventParents
     * @param Lead $lead
     *
     * @return array
     */
    public function formResultsFromFromEvents($eventParents, Lead $lead)
    {
        $submissionIds = [];
        if ($this->continueAfterDecision($eventParents)) {
            return $this->submissionIdsFromDecision($eventParents, $lead);
        }else {
            foreach ($eventParents as $eventParent) {
                $results    = $this->formResultsFromFromEvent($eventParent, $lead);
                $resultsIds = array_column($results, 'id');
                if (!empty($submissionIds)) {
                    $resultsIds = array_intersect($submissionIds, $resultsIds);
                }
                $submissionIds = $resultsIds;
            }
        }
        return $submissionIds;
    }

    /**
     * @param array $eventParents
     *
     * @return bool
     */
    public function continueAfterDecision($eventParents)
    {
        /** @var Event $eventParent */
        $eventParent = current($eventParents);

        if (in_array($eventParent->getType(), self::ALLOWED_FORM_TAB_DECISIONS)) {
            return true;
        }

        return false;
    }

    /**
     * @param array $eventParents
     *
     * @param Lead  $lead
     *
     * @return bool
     */
    public function submissionIdsFromDecision($eventParents, Lead $lead)
    {
        /** @var Event $eventParent */
        $eventParent = current($eventParents);
        switch ($eventParent->getType()) {
            case 'email.open':
                //$eventParent->getLogEn
                //use DBAL to get entity fields
                $q = $this->entityManager->getConnection()->createQueryBuilder();
                $q->select('l.channel, l.channel_id')
                    ->from(MAUTIC_TABLE_PREFIX.'campaign_lead_event_log', 'l')
                    ->where(
                        $q->expr()->andX(
                            $q->expr()->eq('l.lead_id', ':lead'),
                            $q->expr()->eq('l.event_id', ':event'),
                            $q->expr()->eq('l.campaign_id', ':campaign')
                        )
                    )
                    ->setParameter('lead', $lead->getId())
                    ->setParameter('event', $eventParent->getId())
                    ->setParameter('campaign', $eventParent->getCampaign()->getId())
                    ->orderBy('l.id', 'DESC');
                $lastLog = $q->execute()->fetch();
                if ($lastLog['channel'] == 'form.result') {
                    return [$lastLog['channel_id']];
                }
                break;
        }
        return [];
    }


    /**
     * @param      $event
     * @param Lead $contact
     *
     * @return bool
     */
    public function formResultsFromFromEvent($event, Lead $lead)
    {
        list($formId, $fieldAlias) = $this->getFormIdFromEvent($event);
        $operators  = $this->formModel->getFilterExpressionFunctions();
        $form       = $this->formModel->getRepository()->findOneById($formId);
        $operator   = 'eq';
        $properties = $event->getProperties();
        if (!empty($properties['operator'])) {
            $operator = $operators[$properties['operator']]['expr'];
            $value    = $properties['value'];
        } elseif(!empty($properties['unit'])) {
            $operator = 'date';
            if ($properties['unit'] === 'anniversary') {
                $operator = 'anniversary';
            }
            $value = $this->getDate($properties);
        }
        return $this->compareValue($form, $lead, $fieldAlias, $value, $operator);
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

        $formResults  = [];
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

    /**
     * Writes in form values from get parameters.
     *
     * @param $form
     * @param $formHtml
     */
    public function populateValuesWithGetParameters(Form $form, &$formHtml)
    {
        $formName = $form->generateFormName();
        $fields = $form->getFields()->toArray();
        /** @var \Mautic\FormBundle\Entity\Field $f */
        foreach ($fields as $f) {
            $alias = $f->getAlias();
            if ($this->request->getCurrentRequest()->query->has($alias)) {
                $value = $this->request->getCurrentRequest()->query->get($alias);
                $this->populateField($f, $value, $formName, $formHtml);
            }
        }
    }

    /**
     * @param      $formHtml
     */
    public function populateValuesWithLead(Submission $submission, &$formHtml)
    {

        $form     = $submission->getForm();
        $form = $this->formModel->getEntity($form->getId());
        $formName = $form->generateFormName();
        $fields  =  $form->getFields();
        /** @var \Mautic\FormBundle\Entity\Field $f */
        foreach ($fields as $f) {
            if (!empty($submission->getResults()[$f->getAlias()])) {
                $value = $submission->getResults()[$f->getAlias()];
                $this->populateField($f, $value, $formName, $formHtml);
            }
        }
    }

    /**
     * @param $field
     * @param $value
     * @param $formName
     * @param $formHtml
     */
    public function populateField(Field $field, $value, $formName, &$formHtml)
    {
        $alias = $field->getAlias();
        switch ($field->getType()) {
            case 'text':
            case 'email':
            case 'hidden':
            case 'number':
            case 'date':
            case 'datetime':
            case 'url':
                if (preg_match(
                    '/<input(.*?)id="mauticform_input_'.$formName.'_'.$alias.'"(.*?)value="(.*?)"(.*?)>/i',
                    $formHtml,
                    $match
                )) {
                    $replace  = '<input'.$match[1].'id="mauticform_input_'.$formName.'_'.$alias.'"'.$match[2].'value="'.$this->sanitizeValue(
                            $value
                        ).'"'
                        .$match[4].'/>';
                    $formHtml = str_replace($match[0], $replace, $formHtml);
                }
                break;
            case 'textarea':
                if (preg_match(
                    '/<textarea(.*?)id="mauticform_input_'.$formName.'_'.$alias.'"(.*?)>(.*?)<\/textarea>/i',
                    $formHtml,
                    $match
                )) {
                    $replace  = '<textarea'.$match[1].'id="mauticform_input_'.$formName.'_'.$alias.'"'.$match[2].'>'.$this->sanitizeValue(
                            $value
                        ).'</textarea>';
                    $formHtml = str_replace($match[0], $replace, $formHtml);
                }
                break;
            case 'checkboxgrp':
                if (is_string($value) && strrpos($value, '|') > 0) {
                    $value = explode('|', $value);
                }elseif (is_string($value) && strrpos($value, ',') > 0) {
                    $value = explode(',', $value);
                } elseif (!is_array($value)) {
                    $value = [$value];
                }

                foreach ($value as $val) {
                    $val = $this->sanitizeValue(trim($val));
                    if (preg_match(
                        '/<input(.*?)id="mauticform_checkboxgrp_checkbox(.*?)"(.*?)value="'.$val.'"(.*?)>/i',
                        $formHtml,
                        $match
                    )) {
                        $replace  = '<input'.$match[1].'id="mauticform_checkboxgrp_checkbox'.$match[2].'"'.$match[3].'value="'.$val.'"'
                            .$match[4].' checked />';
                        $formHtml = str_replace($match[0], $replace, $formHtml);
                    }
                }
                break;
            case 'radiogrp':
                $value = $this->sanitizeValue($value);
                if (preg_match(
                    '/<input(.*?)id="mauticform_radiogrp_radio(.*?)"(.*?)value="'.$value.'"(.*?)>/i',
                    $formHtml,
                    $match
                )) {
                    $replace  = '<input'.$match[1].'id="mauticform_radiogrp_radio'.$match[2].'"'.$match[3].'value="'.$value.'"'.$match[4]
                        .' checked />';
                    $formHtml = str_replace($match[0], $replace, $formHtml);
                }
                break;
            case 'select':
            case 'country':
                $regex = '/<select\s*id="mauticform_input_'.$formName.'_'.$alias.'"(.*?)<\/select>/is';
                if (preg_match($regex, $formHtml, $match)) {
                    $valuesArray = explode(',', $value);
                    $origText = $match[0];
                    $replace = [];
                    foreach ($valuesArray as $value) {
                        $value = trim($value);
                        $replace['<option value="'.$this->sanitizeValue($value).'">'] = '<option value="'.$this->sanitizeValue($value).'" selected="selected">';
                    }

                    $formHtml = str_replace($origText, str_replace(array_keys($replace), $replace, $origText), $formHtml);
                }

                break;
        }
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function sanitizeValue($value)
    {
        return str_replace(['"', '>', '<'], ['&quot;', '&gt;', '&lt;'], strip_tags(rawurldecode($value)));
    }
}
