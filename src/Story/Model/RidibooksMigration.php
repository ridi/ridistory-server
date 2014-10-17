<?php
namespace Story\Model;

use Story\Entity\TestUserFactory;

class RidibooksMigration
{
    public static function add($u_id, $ridibooks_id)
    {
        $bind = array(
            'u_id' => $u_id,
            'ridibooks_id' => $ridibooks_id
        );
        global $app;
        $app['db']->insert('ridibooks_migration_history', $bind);
        $r = $app['db']->lastInsertId();
        return ($r > 0) ? true : false;
    }

    public static function isMigrated($u_id)
    {
        $sql = <<<EOT
SELECT COUNT(*) FROM ridibooks_migration_history
WHERE u_id = ?
EOT;
        global $app;
        $r = $app['db']->fetchColumn($sql, array($u_id));
        return ($r > 0) ? true : false;
    }

    public static function getMigrateRequestCount($exclude_done = true)
    {
        $sql = <<<EOT
SELECT COUNT(*) FROM buyer_user
WHERE ridibooks_id != ''
EOT;
        if ($exclude_done) {
            $sql .= ' AND id NOT IN (SELECT u_id FROM ridibooks_migration_history)';
        }

        global $app;
        return $app['db']->fetchColumn($sql);
    }

    public static function getMigrateRequestList($end_date, $include_test_users = false)
    {
        $sql = <<<EOT
SELECT bu.id, bu.ridibooks_id, bu.ridibooks_reg_date, IFNULL(SUM(ch.amount), 0) coin, IFNULL(ph.id, 0) purchased FROM buyer_user bu
 LEFT JOIN coin_history ch ON ch.u_id = bu.id
 LEFT JOIN purchase_history ph ON ph.u_id = bu.id
WHERE bu.ridibooks_id != '' AND bu.id NOT IN (SELECT u_id FROM ridibooks_migration_history) AND bu.ridibooks_reg_date < ? AND ph.is_paid = 1
EOT;
        if (!$include_test_users) {
            $test_users = TestUserFactory::getConcatUidList(true);
            if ($test_users) {
                $sql .= ' AND bu.id NOT IN (' . $test_users . ')';
            }
        }
        $sql .= ' GROUP BY bu.id HAVING (coin > 0 OR purchased != 0) ORDER BY ridibooks_reg_date';

        global $app;
        return $app['db']->fetchAll($sql, array($end_date));
    }

    public static function getMigratedCount()
    {
        $sql = <<<EOT
SELECT COUNT(*) FROM ridibooks_migration_history
EOT;
        global $app;
        return $app['db']->fetchColumn($sql);
    }

    public static function getWholeUidList()
    {
        $sql = <<<EOT
SELECT u_id from ridibooks_migration_history
EOT;
        global $app;
        return $app['db']->fetchAll($sql);
    }

    public static function getListByOffsetAndSize($offset, $size)
    {
        $sql = <<<EOT
SELECT * FROM ridibooks_migration_history
ORDER BY migration_time DESC LIMIT ?, ?
EOT;
        global $app;
        $stmt = $app['db']->executeQuery($sql,
            array($offset, $size),
            array(\PDO::PARAM_INT, \PDO::PARAM_INT)
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getListBySearchTypeAndKeyword($search_type, $search_keyword)
    {
        $sql = <<<EOT
SELECT * FROM ridibooks_migration_history
EOT;
        if ($search_type == 'uid') {
            $sql .= ' WHERE u_id = ? ORDER BY migration_time DESC';
            $bind = array($search_keyword);
        } else if ($search_type == 'ridibooks_id') {
            $sql .= ' WHERE ridibooks_id like ? ORDER BY migration_time DESC';
            $bind = array('%' . $search_keyword . '%');
        } else {
            $sql = null;
            $bind = null;
        }

        global $app;
        return $app['db']->fetchAll($sql, $bind);
    }

    public static function update($u_id, $values)
    {
        global $app;
        return $app['db']->update('ridibooks_migration_history', $values, array('u_id' => $u_id));
    }

    public static function delete($u_id)
    {
        global $app;
        return $app['db']->delete('ridibooks_migration_history', array('u_id' => $u_id));
    }
}
