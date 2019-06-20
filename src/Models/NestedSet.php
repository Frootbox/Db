<?php
/**
 *
 */

namespace Frootbox\Db\Models;

abstract class NestedSet extends \Frootbox\Db\Model {


    /**
     *
     */
    public function getTree ( $rootId, array $params = null ) : \Frootbox\Db\Result {

        $where = ' AND p.rootId = ' . $rootId . ' AND n.rootId = ' . $rootId;


        // Parse where
        if (!empty($params['where'])) {

            foreach ($params['where'] AS $column => $value) {
                $where .= ' AND n.' . $column . ' = "' . $value . '"';
            }
        }

        if (!empty($params['circle'])) {
            $where = ' AND n.lft BETWEEN ' . $params['circle']['left'] . ' AND ' . $params['circle']['right'];
        }

        $query	=	'SELECT
			n.*,
			COUNT(*) AS level,
			ROUND((n.rgt - n.lft - 1) / 2) AS offspring
		FROM
			' . $this->getTable() . ' AS n,
			' . $this->getTable() . ' AS p
		WHERE
			n.lft BETWEEN p.lft AND p.rgt
			' .$where . ' 
		GROUP
			BY n.lft, n.id
		ORDER
			BY n.lft';


        $stmt = $this->db->query($query);
        $list = $stmt->fetchAll();

        return new \Frootbox\Db\Result($list, $this->db, [
            'className' => $this->class
        ]);
    }
    
    
    /**
     * 
     */
    public function insertRoot ( \Frootbox\Db\Rows\NestedSet $rootNode ): \Frootbox\Db\Row {
        
        $rootNode->setData([
            'lft' => 1,
            'rgt' => 2,
            'parentId' => 0
        ]);
        
        $row = $this->insert($rootNode);
        
        $row->setData([
            'rootId' => $row->getId()
        ]);
        
        $row->save();
        
        return $row;
    }
}