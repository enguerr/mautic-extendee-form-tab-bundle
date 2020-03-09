<?php

/*
 * @copyright   2020 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticExtendeeFormTabBundle\Compare;


use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\ArrayHelper;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Model\FormModel;
use Mautic\FormBundle\Model\SubmissionModel;
use MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO\CampaignEventDTO;
use MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO\CampaignEvents;
use MauticPlugin\MauticExtendeeFormTabBundle\Compare\DTO\CompareEvent;
use MauticPlugin\MauticExtendeeFormTabBundle\Helper\FormTabHelper;
use MauticPlugin\MauticRecommenderBundle\Helper\SqlQuery;

class CompareQueryBuilder
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var FormModel
     */
    private $formModel;

    /**
     * @var SubmissionModel
     */
    private $submissionModel;

    /**
     * @var CampaignEventDTO
     */
    private $campaignEvent;

    /**
     * @var QueryBuilder[]
     */
    private $subQueries;

    /**
     * @var FormTabHelper
     */
    private $formTabHelper;

    /**
     * @var array
     */
    private $subQueriesConditions;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $tableAlias;

    /**
     * @var Form
     */
    private $form;

    /**
     * CompareQueryBuilder constructor.
     *
     * @param EntityManager   $entityManager
     * @param FormModel       $formModel
     * @param SubmissionModel $submissionModel
     */
    public function __construct(
        EntityManager $entityManager,
        FormModel $formModel,
        SubmissionModel $submissionModel,
        FormTabHelper $formTabHelper
    ) {
        $this->entityManager   = $entityManager;
        $this->formModel       = $formModel;
        $this->submissionModel = $submissionModel;
        $this->formTabHelper   = $formTabHelper;
    }

    public function compareValue(CampaignEvents $campaignEvents)
    {
        //use DBAL to get entity fields
        $q = $this->entityManager->getConnection()->createQueryBuilder();
        $q->select('s.id')
            ->from(MAUTIC_TABLE_PREFIX.'form_submissions', 's')
            ->where(
                $q->expr()->andX(
                    $q->expr()->eq('s.lead_id', ':leadId')
                )
            )
            ->setParameter('leadId', $campaignEvents->first()->getContact()->getId());

        foreach ($campaignEvents->getCampaignEvents() as $campaignEvent) {
            $this->campaignEvent = $campaignEvent;
            foreach ($campaignEvent->getCompareEvents() as $compareEvent) {
                $this->addSubQueriesLogic($this->campaignEvent->getConditionsType(), $compareEvent);
            }
        }

        $conditionEvent  = $campaignEvents->first()->getCampaignEvent();
        $addConditions = [];
        if ($sum = ArrayHelper::getValue('sum', $conditionEvent->getEvent()['properties'])) {
            if (!empty($sum['field']) && !empty($sum['value'])) {
                list($formId, $fieldAlias) = explode('|',$sum['field']);
                $subQuery = $this->getSubQuery($formId);
                $expr = $sum['expr'];
                $addConditions[$this->table] = $subQuery->expr()->$expr($this->tableAlias.'.'.$fieldAlias, ':sumvalue');
                $q->setParameter('sumvalue', $sum['value']);
            }
        }


        if (!empty($this->subQueries)) {
            foreach ($this->subQueries as $table => $subQuery) {
                $orX = $subQuery->expr()->orX();
                foreach ($this->subQueriesConditions[$table] as $subQueriesConditions) {
                    $andX = $subQuery->expr()->andX();
                    foreach ($subQueriesConditions as $subQueriesCondition) {
                        $andX->add($subQueriesCondition);
                    }
                    if (isset($addConditions[$table])) {
                        $andX->add($addConditions[$table]);
                    }
                    $orX->add($andX);
                }

                $subQuery->andWhere($orX);

                $q->andWhere(sprintf("EXISTS (%s)", $subQuery->getSQL()));
                foreach ($subQuery->getParameters() as $key => $parameter) {
                    $q->setParameter($key, $parameter);
                }
            }
        }

        $this->formTabHelper->log(
            sprintf("Complex query: %s with parameters %s", $q->getSQL(), print_r($q->getParameters(), true))
        );
        die(print_r(SqlQuery::getQuery($q)));
        return $q->execute()->fetchAll();
    }

    /**
     * @param string $table
     * @param string $tableAlias
     * @param int $formId
     *
     * @return QueryBuilder
     */
    private function getSubQuery(int $formId)
    {
        /** @var Form $form */
        $form = $this->form = $this->formModel->getEntity($formId);
        if (!$form) {
            return;
        }
        $this->table      = $this->submissionModel->getRepository()->getResultsTableName($formId, $form->getAlias());
        $this->tableAlias = 'alias'.md5($this->table);

        if (!isset($this->subQueries[$this->table])) {
            $subQuery = $this->entityManager->getConnection()->createQueryBuilder();

            $subQuery->select('NULL')
                ->from($this->table, $this->tableAlias);
            $subQuery->where(
                $subQuery->expr()->andX(
                    $subQuery->expr()->eq($this->tableAlias.'.submission_id', 's.id'),
                    $subQuery->expr()->eq($this->tableAlias.'.form_id', ':formId')
                )
            )
                ->setParameter('formId', $formId);
            $this->subQueries[$this->table] = $subQuery;
        } else {
            $subQuery = $this->subQueries[$this->table];
        }

        return $subQuery;
    }

    /**
     * @param string       $conditionsType
     * @param CompareEvent $compareEvent
     */
    private function addSubQueriesLogic($conditionsType, CompareEvent $compareEvent)
    {
        $formId = $compareEvent->getProperties()->getFormId();
        $config = $compareEvent->getProperties()->getProperties();

        $subQuery = $this->getSubQuery($formId);

        $table = $this->table;
        $tableAlias = $this->tableAlias;
        $form = $this->form;

        $subQueryConditions = $subQuery->expr()->andX();

        $operatorExpr   = $compareEvent->getProperties()->getExpr($this->formModel->getFilterExpressionFunctions());
        $field          = $compareEvent->getProperties()->getFieldAlias();
        $value          = $compareEvent->getProperties()->getValue();
        $valueParameter = 'value'.md5($field.$tableAlias.$value);
        if ($compareEvent->getProperties()->isCustomDateCondition()) {
            $value = $this->formTabHelper->getDate($compareEvent->getProperties()->getProperties());
        }

        // Modify operator
        switch ($operatorExpr) {
            case 'like':
            case 'notLike':
                $value = strpos($value, '%') === false ? '%'.$value.'%' : $value;
                break;
            case 'startsWith':
                $operatorExpr = 'like';
                $value        = $value.'%';
                break;
            case 'endsWith':
                $operatorExpr = 'like';
                $value        = '%'.$value;
                break;
            case 'contains':
                $operatorExpr = 'like';
                $value        = '%'.$value.'%';
                break;
        }

        if ($operatorExpr === 'anniversary') {
            $subQueryConditions->add(
                $subQuery->expr()->andX(
                    $subQuery->expr()->eq("MONTH($tableAlias.$field)", ':month'),
                    $subQuery->expr()->eq("DAY($tableAlias.$field)", ':day')
                )
            );
            $subQuery->setParameter('month', $value->format('m'));
            $subQuery->setParameter('day', $value->format('d'));
        } elseif ($operatorExpr === 'date') {
            $expr = 'eq';
            if (!empty($config['expr'])) {
                $expr = $config['expr'];
            }
            $subQueryConditions->add($subQuery->expr()->$expr($tableAlias.'.'.$field, ':'.$valueParameter));
            $subQuery->setParameter($valueParameter, $value->format('Y-m-d'));
        } else {
            switch ($this->formTabHelper->getFieldTypeFromFormByAlias($form, $field)) {
                case 'boolean':
                case 'number':
                    $subQueryConditions->add($subQuery->expr()->$operatorExpr($tableAlias.'.'.$field, $value));
                    break;
                default:
                    $subQueryConditions->add(
                        $subQuery->expr()->$operatorExpr($tableAlias.'.'.$field, ':'.$valueParameter)
                    );
                    $subQuery->setParameter($valueParameter, $value);
                    break;
            }
        }
        $this->subQueriesConditions[$table][$conditionsType][] = $subQueryConditions;
        $this->subQueries[$table] = $subQuery;
    }
}
