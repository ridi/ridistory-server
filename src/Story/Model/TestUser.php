<?php
namespace Story\Model;

class TestUser
{
    public static function add($values)
    {
        global $app;
        return $app['db']->insert('test_user', $values);
    }

    public static function get($u_id)
    {
        $sql = <<<EOT
select bu.google_id, tu.* from test_user tu
 left join buyer_user bu on tu.u_id = bu.id
where u_id = ?
EOT;
        global $app;
        return $app['db']->fetchAssoc($sql, array($u_id));
    }

    public static function getWholeList()
    {
        $sql = <<<EOT
sselect bu.google_id, tu.* from test_user tu
 left join buyer_user bu on tu.u_id = bu.id
EOT;
        global $app;
        return $app['db']->fetchAll($sql);
    }

    public static function getConcatUidList($exclude_inactive)
    {
        $sql = <<<EOT
select u_id from test_user
EOT;
        if ($exclude_inactive) {
            $sql .= ' where is_active = 1';
        }

        global $app;
        $results = $app['db']->fetchAll($sql);

        $test_u_ids = array();
        foreach ($results as $result) {
            array_push($test_u_ids, $result['u_id']);
        }
        return implode(',', $test_u_ids);
    }

    public static function update($u_id, $values)
    {
        global $app;
        return $app['db']->update('test_user', $values, array('u_id' => $u_id));
    }

    public static function delete($u_id)
    {
        global $app;
        return $app['db']->delete('test_user', array('u_id' => $u_id));
    }
}