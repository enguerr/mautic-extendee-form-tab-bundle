<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Model\FormModel;
use MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AjaxController.
 */
class AjaxController extends CommonAjaxController
{

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    protected function generateFieldsFormTabAction(Request $request)
    {
        /** @var FormModel $formModel */
        $formModel = $this->get('mautic.form.model.form') ;
        /** @var FormTabHelper $formTabHelper */
        $formTabHelper = $this->get('mautic.extendee.form.tab.helper') ;
        $formId     = InputHelper::int($request->request->get('formId'));
        $form = $formModel->getEntity($formId);
        $dataArray['success'] = 0;
        if($formId && $form instanceof Form) {
            $dataArray['content'] = $formTabHelper->getFormContent($form, false, 'name="campaignevent[properties][content]');
        }

        return $this->sendJsonResponse($dataArray);
    }

    /**
     * Ajax submit for forms.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function submitAction()
    {
        $response     = $this->forwardWithPost('MauticFormBundle:Public:submit', $this->request->request->all(), [], ['ajax' => true]);
        $responseData = json_decode($response->getContent(), true);
        $success      = (!in_array($response->getStatusCode(), [404, 500]) && empty($responseData['errorMessage'])
            && empty($responseData['validationErrors']));

        $message = '';
        $type    = '';
        if (isset($responseData['successMessage'])) {
            $message = $responseData['successMessage'];
            $type    = 'notice';
        } elseif (isset($responseData['errorMessage'])) {
            $message = $responseData['errorMessage'];
            $type    = 'error';
        }

        $data = array_merge($responseData, ['message' => $message, 'type' => $type, 'success' => $success]);

        return $this->sendJsonResponse($data);
    }
}
