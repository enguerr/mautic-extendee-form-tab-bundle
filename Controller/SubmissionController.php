<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\FormBundle\Model\FormModel;
use Mautic\FormBundle\Model\SubmissionModel;
use MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper;
use MauticPlugin\MauticExtendeeFormTabBundle\Service\SaveSubmission;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class SubmissionController.
 */
class SubmissionController extends FormController
{

    private function getPostActionVars($formId)
    {
        $session  = $this->get('session');
        $viewParameters = [
            'objectId' => $formId,
            'page'     => $session->get('mautic.formresult.page', 1)
        ];
        return [
            'returnUrl'       => $this->generateUrl('mautic_form_results', $viewParameters),
            'contentTemplate' => 'MauticFormBundle:Result:index',
            'viewParameters'  => $viewParameters,
            'passthroughVars' => [
                'closeModal' => 1,
            ],
        ];
    }

    /**
     * Gives a preview of the form.
     *
     * @param     $formId
     * @param int $objectId
     *
     * @return Response
     *
     */
    public function editAction($formId, $objectId = 0)
    {
        /** @var FormTabHelper $formTabHelper */
        $formTabHelper = $this->get('mautic.extendee.form.tab.helper');
        $formTabHelper->setResultCache('');

        $formId        = (empty($formId)) ? InputHelper::int($this->request->get('formId')) : $formId;
        $isContactPage = $contactId = InputHelper::int($this->request->get('contactId'));

        /** @var SubmissionModel $submissionModel */
        $submissionModel = $this->getModel('form.submission');
        $objectId        = (empty($objectId)) ? InputHelper::int($this->request->get('objectId')) : $objectId;

        /** @var FormModel $model */
        $model    = $this->getModel('form.form');
        $form     = $model->getEntity($formId);
        $template = null;
        $router   = $this->get('router');

        $submission      = '';
        if ($objectId) {
            /** @var Submission $submission */
            $submission = $submissionModel->getEntity($objectId);
            if (!$formTabHelper->canEdit($submission->getForm())) {
                return $this->accessDenied();
            }
        }else{
            if (!$formTabHelper->canCreate($form)) {
                return $this->accessDenied();
            }
        };

        if ($form === null) {
            return $this->postActionRedirect(
                array_merge(
                    $this->getPostActionVars($formId),
                    [
                        'flashes' => [
                            [
                                'type' => 'error',
                                'msg'  => 'mautic.form.error.notfound',
                            ],
                        ],
                    ]
                )
            );
        }

        $html = $formTabHelper->getFormContent($form);
        $form     = $model->getEntity($formId);
        $this->getModel('form.form')->getRepository()->clear();
        $form = $this->getModel('form.form')->getEntity($formId);
        $formTabHelper->populateValuesWithGetParameters($form, $html);

        $action = $router->generate(
            'mautic_formtabsubmission_edit',
            [
                'formId'    => $form->getId(),
                'objectId'  => $objectId,
                'contactId' => $contactId,
            ]
        );

        $formView   = $this->get('form.factory')->create(
            'form_tab_submission',
            [],
            [
                'action' => $action,
            ]
        );

        $flashes    = [];
        $closeModal = false;
        $new        = false;
        $error = '';
        if ($submission && !$contactId && $submission->getLead()) {
            $contactId = $submission->getLead()->getId();
        }
        if ($this->request->getMethod() == 'POST') {
            $post                 = $this->request->request->get('mauticform');
            $post['submissionId'] = $objectId;
            if (!$objectId) {
                $new = true;
            }

            $server = $this->request->server->all();
            $return = (isset($server['HTTP_REFERER'])) ? $server['HTTP_REFERER'] : false;

            if (!empty($return)) {
                //remove mauticError and mauticMessage from the referer so it doesn't get sent back
                $return = InputHelper::url($return, null, null, null, ['mauticError', 'mauticMessage'], true);
            }

            $valid = true;
            if (!$this->isFormCancelled($formView)) {
                /** @var SaveSubmission $saveSubmission */
                $saveSubmission = $this->get('mautic.extendee.form.tab.service.save_submission');
                $result         = $saveSubmission->saveSubmission(
                    $post,
                    $server,
                    $form,
                    $this->request,
                    true,
                    $this->getModel('lead.lead')->getEntity($contactId)
                );
                if (!$result instanceof Submission && !empty($result['errors'])) {
                    $error = $result['errors'];
                } else {
                    $closeModal = true;

                }
            } else {
                return new JsonResponse(['closeModal' => 1]);;
            }
        }


        // hide fomr start/end and button, because we wanna use controller view

        // prepopulate
        if (!empty($submission)) {
            $this->populateValuesWithLead($submission, $html);
        }

        if ($closeModal) {
            if (!$isContactPage) {
                return $this->postActionRedirect(
                    $this->getPostActionVars($formId)
                );
            } else {
                return $this->delegateView(
                    [
                        'passthroughVars' => [
                            'target'     => '#form-results-'.$form->getId(),
                            'newContent' => $formTabHelper->getFormWithResult($form, $contactId)['content'],
                            'closeModal' => 1,
                        ],
                    ]
                );
            }
        }

        return $this->delegateView(
            [
                'viewParameters'  => [
                    'content' => $html,
                    'new' => $new,
                    'name'    => $form->getName(),
                    'form'    => $formView->createView(),
                    'error'=>$error
                ],
                'contentTemplate' => 'MauticExtendeeFormTabBundle::form.html.php',
            ]
        );
    }

    /**
     * @param $objectId
     *
     * @return array|JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction($objectId)
    {
        $flashes = [];

        /** @var FormTabHelper $formTabHelper */
        $formTabHelper = $this->get('mautic.extendee.form.tab.helper');
        $formTabHelper->setResultCache('');

        if ($this->request->getMethod() == 'POST') {
            $model = $this->getModel('form.submission');

            // Find the result
            /** @var Submission $entity */
            $entity = $model->getEntity($objectId);

            if ($entity === null) {
                return $this->accessDenied();
            } elseif (!$formTabHelper->canDelete($entity->getForm())) {
                return $this->accessDenied();
            } else {
                $id        = $entity->getId();
                $form      = $entity->getForm();
                $contactId = $entity->getLead()->getId();
                $model->deleteEntity($entity);

                $flashes[] = [
                    'type'    => 'notice',
                    'msg'     => 'mautic.core.notice.deleted',
                    'msgVars' => [
                        '%name%' => '#'.$id,
                    ],
                ];
            }
        } //else don't do anything

        return $this->delegateView(
            [
                'passthroughVars' => [
                    'target'     => '#form-results-'.$form->getId(),
                    'newContent' => $formTabHelper->getFormWithResult($form, $contactId)['content'],
                    'closeModal' => 1,
                ],
            ]
        );
    }



}
