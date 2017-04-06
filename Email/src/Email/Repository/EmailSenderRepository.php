<?php
/**
 * @category Email
 * @package Repository
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2016 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Email\Repository;

use Doctrine\ORM\EntityRepository;


class EmailSenderRepository extends EntityRepository
{
    /**
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getSender()
    {
        $queryBuilder = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('es')
            ->from('\Email\Entity\EmailSender', 'es');

        $query = $queryBuilder->getQuery();

        return  $query->setMaxResults(1)->getOneOrNullResult();
    }

}
