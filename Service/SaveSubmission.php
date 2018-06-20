<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\Service;

use Doctrine\ORM\EntityManager;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\Exception\FileUploadException;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\FormBundle\Model\FormModel;
use Mautic\FormBundle\Crate\UploadFileCrate;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\FormBundle\Event\ValidationEvent;
use Mautic\FormBundle\Exception\FileValidationException;
use Mautic\FormBundle\Exception\NoFileGivenException;
use Mautic\FormBundle\Helper\FormFieldHelper;
use Mautic\FormBundle\Helper\FormUploader;
use Mautic\FormBundle\Model\SubmissionModel;
use Mautic\FormBundle\Validator\UploadFieldValidator;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class SaveSubmission.
 */
class SaveSubmission
{

    /**
     * @var FormModel
     */
    private $formModel;

    /**
     * @var FormFieldHelper
     */
    private $fieldHelper;

    /**
     * @var UploadFieldValidator
     */
    private $fieldValidator;

    /**
     * @var FormUploader
     */
    private $formUploader;

    /**
     * @var CampaignModel
     */
    private $campaignModel;

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var SubmissionModel
     */
    private $submissionModel;

    /**
     * @var IpLookupHelper
     */
    private $ipLookupHelper;

    /**
     * @var EntityManager
     */
    private $entity;

    /**
     * SaveSubmission constructor.
     *
     * @param FormModel                                $formModel
     * @param FormFieldHelper                          $fieldHelper
     * @param UploadFieldValidator                     $fieldValidator
     * @param FormUploader                             $formUploader
     * @param CampaignModel                            $campaignModel
     * @param EventDispatcher|EventDispatcherInterface $dispatcher
     * @param TranslatorInterface                      $translator
     * @param SubmissionModel                          $submissionModel
     * @param IpLookupHelper                           $ipLookupHelper
     * @param EntityManager                            $entity
     */
    public function __construct(FormModel $formModel, FormFieldHelper $fieldHelper,UploadFieldValidator $fieldValidator, FormUploader $formUploader, CampaignModel $campaignModel, EventDispatcherInterface $dispatcher, TranslatorInterface $translator, SubmissionModel $submissionModel, IpLookupHelper $ipLookupHelper, EntityManager $entity)
    {

        $this->formModel = $formModel;
        $this->fieldHelper = $fieldHelper;
        $this->fieldValidator = $fieldValidator;
        $this->formUploader = $formUploader;
        $this->campaignModel = $campaignModel;
        $this->dispatcher = $dispatcher;
        $this->translator = $translator;
        $this->submissionModel = $submissionModel;
        $this->ipLookupHelper = $ipLookupHelper;
        $this->entity = $entity;
    }

    /**
     * @param              $post
     * @param              $server
     * @param Form         $form
     * @param Submission   $submission
     * @param Request|null $request
     * @param bool         $returnEvent
     *
     * @return array|bool
     */
    public function saveSubmission($post, $server, Form $form, Request $request = null, $returnEvent = false, Lead $lead)
    {
        if (!empty($post['submissionId'])) {
            $submissionId = (int) $post['submissionId'];
            $submission   = $this->submissionModel->getEntity($submissionId);
        }
        // new results
        if (empty($submission) || !$submission instanceof Submission) {
            //everything matches up so let's save the results
            $submission = new Submission();
            $submission->setDateSubmitted(new \DateTime());
            $submission->setForm($form);
            $submission->setLead($lead);
            //$submission->setPage($page);
            $ipAddress = $this->ipLookupHelper->getIpAddress();
            $submission->setIpAddress($ipAddress);
        }



        if (!empty($post['return'])) {
            $referer = $post['return'];
        } elseif (!empty($server['HTTP_REFERER'])) {
            $referer = $server['HTTP_REFERER'];
        } else {
            $referer = '';
        }

        //clean the referer by removing mauticError and mauticMessage
        $referer = InputHelper::url($referer, null, null, ['mauticError', 'mauticMessage']);
        $submission->setReferer($referer);

        // Get a list of components to build custom fields from
        $components = $this->formModel->getCustomComponents();

        $fields           = $form->getFields();
        $fieldArray       = [];
        $results          = [];
        $tokens           = [];
        $leadFieldMatches = [];
        $validationErrors = [];
        $filesToUpload    = new UploadFileCrate();

        /** @var Field $f */
        foreach ($fields as $f) {
            $id    = $f->getId();
            $type  = $f->getType();
            $alias = $f->getAlias();
            $value = (isset($post[$alias])) ? $post[$alias] : '';

            $fieldArray[$id] = [
                'id'    => $id,
                'type'  => $type,
                'alias' => $alias,
            ];

            if ($f->isCaptchaType()) {
                $captcha = $this->fieldHelper->validateFieldValue($type, $value, $f);
                if (!empty($captcha)) {
                    $props = $f->getProperties();
                    //check for a custom message
                    $validationErrors[$alias] = (!empty($props['errorMessage'])) ? $props['errorMessage'] : implode('<br />', $captcha);
                }
                continue;
            } elseif ($f->isFileType()) {
                try {
                    $file  = $this->uploadFieldValidator->processFileValidation($f, $request);
                    $value = $file->getClientOriginalName();
                    $filesToUpload->addFile($file, $f);
                } catch (NoFileGivenException $e) { //No error here, we just move to another validation, eg. if a field is required
                } catch (FileValidationException $e) {
                    $validationErrors[$alias] = $e->getMessage();
                }
            }

            if ($value === '' && $f->isRequired()) {
                //field is required, but hidden from form because of 'ShowWhenValueExists'
                if ($f->getShowWhenValueExists() === false && !isset($post[$alias])) {
                    continue;
                }

                //somehow the user got passed the JS validation
                $msg = $f->getValidationMessage();
                if (empty($msg)) {
                    $msg = $this->translator->trans(
                        'mautic.form.field.generic.validationfailed',
                        [
                            '%label%' => $f->getLabel(),
                        ],
                        'validators'
                    );
                }

                $validationErrors[$alias] = $msg;

                continue;
            }

            if (isset($components['viewOnlyFields']) && in_array($type, $components['viewOnlyFields'])) {
                //don't save items that don't have a value associated with it
                continue;
            }

            //clean and validate the input
            if ($f->isCustom()) {
                if (!isset($components['fields'][$f->getType()])) {
                    continue;
                }

                $params = $components['fields'][$f->getType()];
                if (!empty($value)) {
                    if (isset($params['valueFilter'])) {
                        if (is_string($params['valueFilter']) && is_callable(['\Mautic\CoreBundle\Helper\InputHelper', $params['valueFilter']])) {
                            $value = InputHelper::_($value, $params['valueFilter']);
                        } elseif (is_callable($params['valueFilter'])) {
                            $value = call_user_func_array($params['valueFilter'], [$f, $value]);
                        } else {
                            $value = InputHelper::_($value, 'clean');
                        }
                    } else {
                        $value = InputHelper::_($value, 'clean');
                    }
                }

                // @deprecated - BC support; to be removed in 3.0 - be sure to remove support in FormBuilderEvent as well
                if (isset($params['valueConstraints']) && is_callable($params['valueConstraints'])) {
                    $customErrors = call_user_func_array($params['valueConstraints'], [$f, $value]);
                    if (!empty($customErrors)) {
                        $validationErrors[$alias] = is_array($customErrors) ? implode('<br />', $customErrors) : $customErrors;
                    }
                }
            } elseif (!empty($value)) {
                $filter = $this->fieldHelper->getFieldFilter($type);
                $value  = InputHelper::_($value, $filter);

                $isValid = $this->validateFieldValue($f, $value);
                if (true !== $isValid) {
                    $validationErrors[$alias] = is_array($isValid) ? implode('<br />', $isValid) : $isValid;
                }
            }

            // Check for custom validators
            $isValid = $this->validateFieldValue($f, $value);
            if (true !== $isValid) {
                $validationErrors[$alias] = $isValid;
            }

            $leadField = $f->getLeadField();
            if (!empty($leadField)) {
                $leadValue = $value;

                $leadFieldMatches[$leadField] = $leadValue;
            }

            //convert array from checkbox groups and multiple selects
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $tokens["{formfield={$alias}}"] = $value;

            //save the result
            if ($f->getSaveResult() !== false) {
                $results[$alias] = $value;
            }
        }

        // Set the results
        $submission->setResults($results);
        $lead = null;
        // Remove validation errors if the field is not visible
        if ($lead && $form->usesProgressiveProfiling()) {
            $leadSubmissions = $this->formModel->getLeadSubmissions($form, $lead->getId());

            foreach ($fields as $field) {
                if (isset($validationErrors[$field->getAlias()]) && !$field->showForContact($leadSubmissions, $lead, $form)) {
                    unset($validationErrors[$field->getAlias()]);
                }
            }
        }

        //return errors if there any
        if (!empty($validationErrors)) {
            return ['errors' => $validationErrors];
        }

        /*
         * Process File upload and save the result to the entity
         * Upload is here to minimize a need for deleting file if there is a validation error
         * The action can still be invalidated below - deleteEntity takes care for File deletion
         *
         * @todo Refactor form validation to execute this code only if Submission is valid
         */
        try {
            $this->formUploader->uploadFiles($filesToUpload, $submission);
        } catch (FileUploadException $e) {
            $msg                                = $this->translator->trans('mautic.form.submission.error.file.uploadFailed', [], 'validators');
            $validationErrors[$e->getMessage()] = $msg;

            return ['errors' => $validationErrors];
        }

        // Save the submission
        $this->saveUpdateEntity($submission);

       /* if (!$form->isStandalone()) {
            // Find and add the lead to the associated campaigns
            $campaigns = $this->campaignModel->getCampaignsByForm($form);
            if (!empty($campaigns)) {
                foreach ($campaigns as $campaign) {
                    $this->campaignModel->addLead($campaign, $lead);
                }
            }
        }*/

        return $submission;
    }

    /**
     * Validates a field value.
     *
     * @param Field $field
     * @param       $value
     *
     * @return bool|string True if valid; otherwise string with invalid reason
     */
    protected function validateFieldValue(Field $field, $value)
    {
        $standardValidation = $this->fieldHelper->validateFieldValue($field->getType(), $value);
        if (!empty($standardValidation)) {
            return $standardValidation;
        }

        $components = $this->formModel->getCustomComponents();
        foreach ([$field->getType(), 'form'] as $type) {
            if (isset($components['validators'][$type])) {
                if (!is_array($components['validators'][$type])) {
                    $components['validators'][$type] = [$components['validators'][$type]];
                }
                foreach ($components['validators'][$type] as $validator) {
                    if (!is_array($validator)) {
                        $validator = ['eventName' => $validator];
                    }
                    $event = $this->dispatcher->dispatch($validator['eventName'], new ValidationEvent($field, $value));
                    if (!$event->isValid()) {
                        return $event->getInvalidReason();
                    }
                }
            }
        }

        return true;
    }


    /**
     * @param Submission $entity
     */
    public function saveUpdateEntity(Submission $entity)
    {
        //add the results
        $results                  = $entity->getResults();
        if (!empty($results)) {
            if( $entity->getId()){
                $this->entity->getConnection()->update($this->submissionModel->getRepository()->getResultsTableName($entity->getForm()->getId(), $entity->getForm()->getAlias()), $results, [
                    'submission_id' => (int) $entity->getId()
                ]);
            }else{
                $this->submissionModel->saveEntity($entity);
            }
        }
    }
}
