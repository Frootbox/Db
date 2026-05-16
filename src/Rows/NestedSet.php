<?php
/**
 *
 */

namespace Frootbox\Db\Rows;

class NestedSet extends \Frootbox\Db\Row
{
    /**
     * Insert a new child node below this node.
     *
     * @param \Frootbox\Db\Row $child Child row to insert.
     * @return \Frootbox\Db\Row Inserted child row.
     */
    public function appendChild(\Frootbox\Db\Row $child): \Frootbox\Db\Row
    {
        // Start database transaction
        $this->db->transactionStart();

        $query = 'UPDATE
			' . $this->getTable() . '
		SET 
			lft = lft + 2
		WHERE
			rootId = ' . $this->getRootId() . ' AND
			lft > ' . $this->getRgt() . ' AND
			rgt >= ' . $this->getRgt();

        $this->db->query($query);

        $query = 'UPDATE
			' . $this->getTable() . '
		SET
			rgt = rgt + 2
		WHERE
			rootId = ' . $this->getRootId() . '	AND
			rgt >= ' . $this->getRgt();

        $this->db->query($query);

        // Insert new child
        $child->setData([
            'parentId' => $this->getId(),
            'rootId' => $this->getRootId(),
            'lft' => $this->getRgt(),
            'rgt' => ($this->getRgt() + 1)
        ]);

        $model = $this->getModel();
        $row = $model->insert($child);

        // Update own keys
        $this->setData([
            'rgt' => ($this->getRgt() + 2)
        ]);

        $this->db->transactionCommit();

        return $row;
    }

    /**
     * Delete this node if it has no children and close the nested-set gap.
     *
     * @return bool
     */
    public function delete()
    {
        $model = $this->getModel();

        // Check for existing children
        $result = $model->fetch([
            'where' => [ 'parentId' => $this->getId() ]
        ]);

        if ($result->getCount() > 0) {
            throw new \Frootbox\Exceptions\PermissionDenied('Row is not deletable because it has children.');
        }

        $this->db->transactionStart();

        parent::delete();

        // Update left ids
        $query = 'UPDATE ' . $this->getTable() . ' SET
			lft		=	lft - 2
		WHERE
			lft		>	' . $this->getLft() . '		AND
			rootId	=	' . $this->getRootId() . '';

        $this->db->query($query);

        // Update right ids
        $query = 'UPDATE ' . $this->getTable() . ' SET
			rgt		=	rgt - 2
		WHERE
			rgt		>	' . $this->getRgt() . '	AND
			rootId	=	' . $this->getRootId() . '';

        $this->db->query($query);

        $this->db->transactionCommit();

        return true;
    }

    /**
     * Fetch direct children of this node.
     *
     * @param array|null $params Optional fetch parameters, including where.
     * @return \Frootbox\Db\Result
     */
    public function getChildren(array $params = null)
    {
        $where = $params['where'] ?? [];
        $where['parentId'] = $this->getId();

        $result = $this->db->fetch([
            'table' => $this->getTable(),
            'where' => $where,
            'order' => [
                'lft ASC'
            ]
        ]);
        
        $result->setClassName(get_class($this));
        
        return $result;
    }

    /**
     * Fetch the first direct child of this node.
     *
     * @return \Frootbox\Persistence\Page|null
     */
    public function getFirstChild(): \Frootbox\Persistence\Page
    {
        $result = $this->db->fetch([
            'table' => $this->getTable(),
            'where' => [
                'parentId' => $this->getId(),
            ],
            'order' => [ 'lft ASC' ],
            'limit' => 1,
        ]);

        $result->setClassName(get_class($this));

        return $result->current();
    }

    /**
     * Fetch this node and all descendants.
     *
     * @param array|null $params Optional fetch parameters.
     * @return \Frootbox\Db\Result
     */
    public function getOffspring(array $params = null)
    {
        if (empty($params['order'])) {
            $params['order'] = [ 'lft ASC' ];
        }

        $result = $this->db->fetch([
            'table' => $this->getTable(),
            'where' => [
                'rootId' => $this->getRootId(),
                new \Frootbox\Db\Conditions\GreaterOrEqual('lft', $this->getLft()),
                new \Frootbox\Db\Conditions\LessOrEqual('rgt', $this->getRgt()),
            ],
            'order' => $params['order']
        ]);

        $result->setClassName(get_class($this));

        return $result;
    }

    /**
     * Fetch the parent node.
     *
     * @return NestedSet
     */
    public function getParent(): NestedSet
    {
        $result = $this->db->fetch([
            'table' => $this->getTable(),
            'where' => [
                'id' => $this->getParentId()
            ],
            'limit' => 1
        ]);

        $result->setClassName(get_class($this));

        return $result->current();
    }

    /**
     * Fetch the previous sibling node.
     *
     * @return \Frootbox\Db\Row|null
     */
    public function getPreviousSibling(): ?\Frootbox\Db\Row
    {
        $result = $this->db->fetch([
            'table' => $this->getTable(),
            'where' => [
                new \Frootbox\Db\Conditions\Less('lft', $this->getLft()),
                'parentId' => $this->getParentId()
            ],
            'order' => [
                'lft DESC'
            ],
            'limit' => 1
        ]);

        $result->setClassName(get_class($this));

        if ($result->getCount() == 0) {
            return null;
        }

        return $result->current();
    }

    /**
     * Fetch all sibling nodes with the same root and parent.
     *
     * @return \Frootbox\Db\Result
     */
    public function getSiblings()
    {
        $result = $this->db->fetch([
            'table' => $this->getTable(),
            'where' => [
                'rootId' => $this->getRootId(),
                'parentId' => $this->getParentId()
            ],
            'order' => [
                'lft ASC'
            ]
        ]);

        $result->setClassName(get_class($this));

        return $result;
    }

    /**
     * Fetch the trace from this node to the root node.
     *
     * @return \Frootbox\Db\Result
     */
    public function getTrace()
    {
        $result = $this->db->fetch([
            'table' => $this->getTable(),
            'where' => [
                new \Frootbox\Db\Conditions\LessOrEqual('lft', $this->getLft()),
                new \Frootbox\Db\Conditions\GreaterOrEqual('rgt', $this->getRgt()),
                'rootId' => $this->getRootId(),
            ],
            'order' => [
                'lft ASC',
            ],
        ]);

        $result->setClassName(get_class($this));

        return $result;
    }

    /**
     * Check whether this node has descendants.
     *
     * @return bool
     */
    public function hasChildren(): bool
    {
        return ($this->getRgt() - $this->getLft()) > 1;
    }

    /**
     * Fetch the next sibling node.
     *
     * @return \Frootbox\Db\Row|null
     */
    public function getNextSibling(): ?\Frootbox\Db\Row
    {
        $result = $this->db->fetch([
            'table' => $this->getTable(),
            'where' => [
                new \Frootbox\Db\Conditions\Greater('lft', $this->getLft()),
                'parentId' => $this->getParentId(),
            ],
            'order' => [
                'lft ASC',
            ],
            'limit' => 1,
        ]);

        $result->setClassName(get_class($this));

        if ($result->getCount() == 0) {
            return null;
        }

        return $result->current();
    }

    /**
     * Check whether this node is a descendant of another node.
     *
     * @param NestedSet $row Potential ancestor node.
     * @return bool
     */
    public function isChildOf(NestedSet $row): bool
    {
        return ($this->getLft() > $row->getLft() and $this->getRgt() < $row->getRgt());
    }
}
