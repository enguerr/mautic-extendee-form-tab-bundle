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

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Model\FormModel;
use MauticPlugin\MauticExtendeeFormTabBundle\Service\SaveSubmission;

/**
 * Class SubmissionController.
 */
class SubmissionController extends CommonController
{
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
        /** @var FormModel $model */
        $formId   = (empty($formId)) ? InputHelper::int($this->request->get('formId')) : $formId;
        $objectId = (empty($objectId)) ? InputHelper::int($this->request->get('objectId')) : $objectId;
        /** @var FormModel $model */
        $model    = $this->getModel('form.form');
        $form     = $model->getEntity($formId);
        $template = null;
        $router   = $this->get('router');

        if ($form === null || !$form->isPublished()) {
            return $this->notFound();
        } else {
            $html = $model->getContent($form, true, false);

            $action = $router->generate(
                'mautic_formtab_postresults',
                [
                    'formId'       => $form->getId(),
                    'submissionId' => $objectId,
                ]
            );
            $html   = preg_replace('/action="([^"]+)/', 'action="'.$action, $html, 1);
        }

        return $this->delegateView(
            [
                'viewParameters'  => [
                    'content' => $html,
                    'name'    => $form->getName(),
                ],
                'contentTemplate' => 'MauticExtendeeFormTabBundle::form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_contact_index',
                    'mauticContent' => 'lead',
                ],
            ]
        );
    }

    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function submitAction()
    {
        if ($this->request->getMethod() !== 'POST') {
            return $this->accessDenied();
        }
        $form = null;
        $post = $this->request->request->get('mauticform');
        if ($this->request->get('submissionId', '')) {
            $post['submissionId'] = $this->request->get('submissionId', '');
        }
        $server = $this->request->server->all();
        $return = (isset($server['HTTP_REFERER'])) ? $server['HTTP_REFERER'] : false;

        if (!empty($return)) {
            //remove mauticError and mauticMessage from the referer so it doesn't get sent back
            $return = InputHelper::url($return, null, null, null, ['mauticError', 'mauticMessage'], true);
            $query  = (strpos($return, '?') === false) ? '?' : '&';
        }

        $translator = $this->get('translator');

        if (!isset($post['formId']) && isset($post['formid'])) {
            $post['formId'] = $post['formid'];
        } elseif (isset($post['formId']) && !isset($post['formid'])) {
            $post['formid'] = $post['formId'];
        }

        //check to ensure there is a formId
        if (!isset($post['formId'])) {
            $error = $translator->trans('mautic.form.submit.error.unavailable', [], 'flashes');
        } else {
            $formModel = $this->getModel('form.form');
            /** @var Form $form */
            $form = $formModel->getEntity($post['formId']);

            //check to see that the form was found
            if ($form === null) {
                $error = $translator->trans('mautic.form.submit.error.unavailable', [], 'flashes');
            } else {
                //get what to do immediately after successful post
                //check to ensure the form is published
                $status             = $form->getPublishStatus();
                $dateTemplateHelper = $this->get('mautic.helper.template.date');
                if ($status == 'pending') {
                    $error = $translator->trans(
                        'mautic.form.submit.error.pending',
                        [
                            '%date%' => $dateTemplateHelper->toFull($form->getPublishUp()),
                        ],
                        'flashes'
                    );
                } elseif ($status == 'expired') {
                    $error = $translator->trans(
                        'mautic.form.submit.error.expired',
                        [
                            '%date%' => $dateTemplateHelper->toFull($form->getPublishDown()),
                        ],
                        'flashes'
                    );
                } elseif ($status != 'published') {
                    $error = $translator->trans('mautic.form.submit.error.unavailable', [], 'flashes');
                } else {

                    // remove action
                    $actions = $form->getActions();;
                    foreach ($actions as $action) {
                        $form->removeAction($action);
                    }
                    // remove matching field
                    foreach ($form->getFields() as &$field) {
                        $field->setLeadField('');
                    }
                    /** @var SaveSubmission $saveSubmission */
                    $saveSubmission = $this->get('mautic.extendee.form.tab.service.save_submission');
                    $result         = $saveSubmission->saveSubmission($post, $server, $form, $this->request, true);
                    if (!empty($result['errors'])) {
                        $error = ($result['errors']) ?
                            $this->get('translator')->trans('mautic.form.submission.errors').'<br /><ol><li>'.
                            implode('</li><li>', $result['errors']).'</li></ol>' : false;
                    }
                }
            }

            /* $viewParameters = [
                 'objectId'     => 1,
                 'objectAction' => 'view',
             ];

             return $this->postActionRedirect(
                 [
                     'returnUrl'       => $this->generateUrl('mautic_contact_action', $viewParameters),
                     'viewParameters'  => $viewParameters,
                     'contentTemplate' => 'MauticLeadBundle:Lead:view',
                     'passthroughVars' => [
                         'activeLink'    => '#mautic_contact_index',
                         'mauticContent' => 'lead',
                         'closeModal'    => 1, //just in case in quick form
                     ],
                 ]
             );*/

            if (!empty($error)) {
                $data['errorMessage'] = implode('',$error);
            } else {
                $data['successMessage'] = $this->get('translator')->trans('mautic.core.success');
            }
            $response = json_encode($data);

            return $this->render('MauticFormBundle::messenger.html.php', ['response' => $response]);
        }
    }
}
