<?php
/* 
 * Manages assorted router maintenance tasks
 * @category Entity
 * @package Entity\Controller
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright(c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Controller;

use Application\Controller\AbstractConsole;
use Magelink\Exception\MagelinkException;


class Console extends AbstractConsole
{

    /** @var array $_tasks */
    protected $_tasks = array(
        'import',
        'export',
    );


    /**
     * @param $id
     * @throws MagelinkException
     */
    protected function exportTask($id)
    {
        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');
        /** @var \Entity\Service\EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        $entityType = $entityConfigService->parseEntityType($id);
        if (!$entityType) {
            throw new MagelinkException('Invalid Entity Type ' . $id);
        }
        $entity_type_str = $entityConfigService->parseEntityTypeReverse($entityType);

        $fname = './data/import/'.$entity_type_str.'.csv';
        if (!file_exists($fname) || !is_readable($fname)) {
            throw new MagelinkException('Failed to load CSV file ' . $fname);
        }

        $fh = fopen($fname, 'w');
        $cols = $entityConfigService->getAttributesCode($entityType);
        array_unshift($cols, 'updated_at');
        array_unshift($cols, 'parent_id');
        array_unshift($cols, 'store_id');
        array_unshift($cols, 'unique_id');
        array_unshift($cols, 'entity_id');
        fputcsv($fh, array_values($cols));

        $results = $entityService->locateEntity(0, $entityType, false, array(), array(), array(), array_values($cols));
        foreach ($results as $res) {
            $arr = array($res->getId(), $res->getUniqueId(), $res->getStoreId(), $res->getParentId(), $res->getUpdatedAt());
            foreach ($cols as $aid=>$acode) {
                $arr[] = $res->getData($acode, '');
            }
            fputcsv($fh, $arr);
        }
        fclose($fh);

    }

    /**
     * @param $id
     * @throws MagelinkException
     */
    protected function importTask($id)
    {
        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');
        /** @var \Entity\Service\EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        $entityType = $entityConfigService->parseEntityType($id);
        if (!$entityType) {
            throw new MagelinkException('Invalid Entity Type ' . $id);
        }
        $entity_type_str = $entityConfigService->parseEntityTypeReverse($entityType);

        $fname = './data/import/'.$entity_type_str.'.csv';
        if (!file_exists($fname) || !is_readable($fname)) {
            throw new MagelinkException('Failed to load CSV file ' . $fname);
        }

        $fh = fopen($fname, 'r');

        $fields = fgetcsv($fh);
        if (!count($fields) || (count($fields) == 1 && $fields[0] === null)) {
            throw new MagelinkException('Invalid header line!');
        }
        $fieldCount = count($fields);

        $staticFields = array();

        foreach ($fields as $k=>$f) {
            if (strtoupper($f) === $f) {
                $staticFields[strtolower($f)] = $k;
                unset($fields[$k]);
                continue; // Static field, fine.
            }
            if (!$entityConfigService->checkAttribute($entityType, $f)) {
                throw new MagelinkException('Invalid attribute code ' . $f . ' for ' . $entity_type_str);
            }
        }

        if (!isset($staticFields['unique_id'])) {
            throw new MagelinkException('Unique ID must always be passed!');
        }
        $forceStoreId = false;
        if (!isset($staticFields['store_id'])) {
            $forceStoreId = true;
        }
        $forceParentId = false;
        if (!isset($staticFields['parent_id'])) {
            $forceParentId = true;
        }

        $line = 1;
        while ($line = fgetcsv($fh)) {
            $line++; // First line will then be 2, as 1 was headers
            if (count($line) != $fieldCount) {
                throw new MagelinkException('Invalid line ' . $line . ', wrong number of values');
            }

            $unique_id = $line[$staticFields['unique_id']];
            unset($line[$staticFields['unique_id']]);
            if ($forceStoreId) {
                $store_id = 0;
            }else{
                $store_id = $line[$staticFields['store_id']];
                unset($line[$staticFields['store_id']]);
            }
            if ($forceParentId) {
                $parent_id = null;
            }else{
                $parent_id = $line[$staticFields['parent_id']];
                unset($line[$staticFields['parent_id']]);
            }

            $data = array_combine($fields, $line);

            try{
                $entityService->createEntity(0, $entityType, $store_id, $unique_id, $data, $parent_id);
            }catch(\Exception $e) {
                echo $e.PHP_EOL;
            }
        }
    }

}