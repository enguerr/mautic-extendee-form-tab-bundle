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
use Mautic\LeadBundle\Model\LeadModel;

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
        $model             = $this->getModel('form.form');
        $form              = $model->getEntity($formId);
        $template          = null;
        $router = $this->get('router');

        if ($form === null || !$form->isPublished()) {
            return $this->notFound();
        } else {
            $html = $model->getContent($form, true, false);
            $action = $router->generate(
                'mautic_formtab_postresults',
                ['formId' => $form->getId()]
            );
            $html = preg_replace('/action="([^"]+)/', 'action="'.$action, $html, 1);
            $viewParams = [
                'content'     => $html,
                'name'        => $form->getName(),
            ];
        }

        return $this->render('MauticExtendeeFormTabBundle::form.html.php', $viewParams);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function submitAction()
    {
        if ($this->request->getMethod() !== 'POST') {
            return $this->accessDenied();
        }
        $isAjax        = $this->request->query->get('ajax', false);
        $form          = null;
        $post          = $this->request->request->get('mauticform');
        $messengerMode = (!empty($post['messenger']));
        $server        = $this->request->server->all();
        $return        = (isset($post['return'])) ? $post['return'] : false;

        if (empty($return)) {
            //try to get it from the HTTP_REFERER
            $return = (isset($server['HTTP_REFERER'])) ? $server['HTTP_REFERER'] : false;
        }

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
            $form      = $formModel->getEntity($post['formId']);

            //check to see that the form was found
            if ($form === null) {
                $error = $translator->trans('mautic.form.submit.error.unavailable', [], 'flashes');
            } else {
                //get what to do immediately after successful post
                $postAction         = 'return';
                $postActionProperty = $this->get('translator')->trans('mautic.core.success');

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
                    // rmeove acction
                    $actions = $form->getActions();;
                    foreach ($actions as $action) {
                        $form->removeAction($action);
                    }
                    // remove lead field
                    foreach ($form->getFields() as &$field) {
                        $field->setLeadField('');
                    }

                    $result = $this->getModel('form.submission')->saveSubmission($post, $server, $form, $this->request, true);
                    if (!empty($result['errors'])) {
                        if ($messengerMode || $isAjax) {
                            $error = $result['errors'];
                        } else {
                            $error = ($result['errors']) ?
                                $this->get('translator')->trans('mautic.form.submission.errors').'<br /><ol><li>'.
                                implode('</li><li>', $result['errors']).'</li></ol>' : false;
                        }
                    } elseif (!empty($result['callback'])) {
                        /** @var SubmissionEvent $submissionEvent */
                        $submissionEvent = $result['callback'];

                        // Return the first Response object if one is defined
                        $firstResponseObject = false;
                        if ($callbackResponses = $submissionEvent->getPostSubmitCallbackResponse()) {
                            // Some submit actions already injected it's responses
                            foreach ($callbackResponses as $key => $response) {
                                if ($response instanceof Response) {
                                    $firstResponseObject = $key;
                                    break;
                                }
                            }
                        }

                        // These submit actions have requested a callback after all is said and done
                        $callbacksRequested = $submissionEvent->getPostSubmitCallback();
                        foreach ($callbacksRequested as $key => $callbackRequested) {
                            $callbackRequested['messengerMode'] = $messengerMode;
                            $callbackRequested['ajaxMode']      = $isAjax;

                            if (isset($callbackRequested['eventName'])) {
                                $submissionEvent->setPostSubmitCallback($key, $callbackRequested);

                                $this->get('event_dispatcher')->dispatch($callbackRequested['eventName'], $submissionEvent);
                            } elseif (isset($callbackRequested['callback'])) {
                                // @deprecated - to be removed in 3.0; use eventName instead - be sure to remove callback key support from SubmissionEvent::setPostSubmitCallback
                                $callback = $callbackRequested['callback'];
                                if (is_callable($callback)) {
                                    if (is_array($callback)) {
                                        $reflection = new \ReflectionMethod($callback[0], $callback[1]);
                                    } elseif (strpos($callback, '::') !== false) {
                                        $parts      = explode('::', $callback);
                                        $reflection = new \ReflectionMethod($parts[0], $parts[1]);
                                    } else {
                                        $reflection = new \ReflectionMethod(null, $callback);
                                    }

                                    //add the factory to the arguments
                                    $callbackRequested['factory'] = $this->factory;

                                    $pass = [];
                                    foreach ($reflection->getParameters() as $param) {
                                        if (isset($callbackRequested[$param->getName()])) {
                                            $pass[] = $callbackRequested[$param->getName()];
                                        } else {
                                            $pass[] = null;
                                        }
                                    }

                                    $callbackResponses[$key] = $reflection->invokeArgs($this, $pass);
                                }
                            }

                            if (!$firstResponseObject && $callbackResponses[$key] instanceof Response) {
                                $firstResponseObject = $key;
                            }
                        }

                        if ($firstResponseObject && !$messengerMode && !$isAjax) {
                            // Return the response given by the sbumit action

                            return $callbackResponses[$firstResponseObject];
                        }
                    } elseif (isset($result['submission'])) {
                        /** @var SubmissionEvent $submissionEvent */
                        $submissionEvent = $result['submission'];
                    }
                }
            }
        }

        if ($messengerMode || $isAjax) {
            // Return the call via postMessage API
            $data = ['success' => 1];
            if (!empty($error)) {
                if (is_array($error)) {
                    $data['validationErrors'] = $error;
                } else {
                    $data['errorMessage'] = $error;
                }
                $data['success'] = 0;
            } else {
                // Include results in ajax response for JS callback use
                if (isset($submissionEvent)) {
                    $data['results'] = $submissionEvent->getResults();
                }

                if (!empty($callbackResponses)) {
                    foreach ($callbackResponses as $key => $response) {
                        // Convert the responses to something useful for a JS response
                        if ($response instanceof RedirectResponse && !isset($data['redirect'])) {
                            $data['redirect'] = $response->getTargetUrl();
                        } elseif ($response instanceof Response) {
                            if (!isset($data['successMessage'])) {
                                $data['successMessage'] = [];
                            }
                            $data['successMessage'][] = $response->getContent();
                        } elseif (is_array($response)) {
                            $data = array_merge($data, $response);
                        } elseif (is_string($response)) {
                            if (!isset($data['successMessage'])) {
                                $data['successMessage'] = [];
                            }
                            $data['successMessage'][] = $response;
                        } // ignore anything else
                    }
                }

                // Combine all messages into one
                if (isset($data['successMessage'])) {
                    $data['successMessage'] = implode('<br /><br />', $data['successMessage']);
                }
            }

            if (isset($post['formName'])) {
                $data['formName'] = $post['formName'];
            }

            if ($isAjax) {
                // Post via ajax so return a json response

                return new JsonResponse($data);
            } else {
                $response = json_encode($data);

                return $this->render('MauticFormBundle::messenger.html.php', ['response' => $response]);
            }
        } else {
            if (!empty($error)) {
                if ($return) {
                    $hash = ($form !== null) ? '#'.strtolower($form->getAlias()) : '';

                    return $this->redirect($return.$query.'mauticError='.rawurlencode($error).$hash);
                } else {
                    $msg     = $error;
                    $msgType = 'error';
                }
            } else {
                $msg = $postActionProperty;
            }

            $session = $this->get('session');
            $session->set(
                'mautic.emailbundle.message',
                [
                    'message' => $msg,
                    'type'    => (empty($msgType)) ? 'notice' : $msgType,
                ]
            );

            return $this->redirect($this->generateUrl('mautic_form_postmessage'));
        }
    }
}
