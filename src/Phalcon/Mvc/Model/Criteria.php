<?php
/**
 * Criteria class with support for array parameters.
 *
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     BSD-3-clause
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Phalcon\Mvc\Model;

use Phalcon\Db\Column;
use Phalcon\DiInterface;

/**
 * Criteria class with support for array parameters.
 */
class Criteria extends \Phalcon\Mvc\Model\Criteria
{

    /**
     * Builds a Criteria object based on an input array like _POST
     *
     * @param DiInterface $dependencyInjector
     * @param string      $modelName
     * @param array       $data
     * @param string      $operator
     *
     * @return Criteria
     */
    public static function fromInput(DiInterface $dependencyInjector, $modelName, array $data, $operator = "AND")
    {
        $conditions = [];
        $bind = [];

        if (count($data)) {
            $metaData = $dependencyInjector->getShared('modelsMetadata');
            $model = new $modelName();
            $dataTypes = $metaData->getDataTypes($model);
            $columnMap = $metaData->getReverseColumnMap($model);

            foreach ($data as $field => $value) {
                if (is_array($columnMap) && count($columnMap)) {
                    $attribute = $columnMap[$field];
                } else {
                    $attribute = $field;
                }

                if (isset($dataTypes[$attribute])) {
                    if ($value !== null && $value !== '') {
                        if (is_array($value)) {
                            $conditions[] = "[$field] IN ({" . $field . ":array})";
                            $bind[$field] = $value;
                            continue;
                        }

                        if ($value === 'NULL' || $value === '!NULL') {
                            if (strpos($value, '!') === 0) {
                                $conditions[] = "[$field] IS NOT NULL";
                            } else {
                                $conditions[] = "[$field] IS NULL";
                            }

                            continue;
                        }

                        if ($dataTypes[$attribute] == Column::TYPE_VARCHAR) {
                            /**
                             * For varchar types we use LIKE operator
                             */
                            $conditions[] = "[$field] LIKE :$field:";
                            $bind[$field] = "%$value%";
                            continue;
                        }

                        /**
                         * For the rest of data types we use a plain = operator
                         */
                        $conditions[] = "[$field] = :$field:";
                        $bind[$field] = $value;
                    }
                }
            }
        }

        /**
         * Create an object instance and pass the paramaters to it
         */
        $criteria = new self();
        if (count($conditions)) {
            $criteria->where(join(" $operator ", $conditions));
            $criteria->bind($bind);
        }

        $criteria->setModelName($modelName);
        return $criteria;
    }
}
