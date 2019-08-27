<?php


namespace Frootbox\Db\Rows;


class NestedSet extends \Frootbox\Db\Row {

    /**
     * Insert new child
     */
    public function appendChild ( \Frootbox\Db\Row $child ): \Frootbox\Db\Row {

        $this->db->transactionStart();


        $query = 'UPDATE
			' . $this->getTable() . '
		SET 
			lft      =  lft + 2
		WHERE
			rootId	=	' . $this->getRootId() . '	AND
			lft		>	' . $this->getRgt() . '		AND
			rgt		>=	' . $this->getRgt();

        $this->db->query($query);


        $query = 'UPDATE
			' . $this->getTable() . '
		SET
			rgt		=	rgt + 2
		WHERE
			rootId	=	' . $this->getRootId() . '	AND
			rgt		>=	' . $this->getRgt();

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
     *
     */
    public function delete ( ) {

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
     * 
     */
    public function getChildren ( ) {
        
            
        $result = $this->db->fetch([
            'table' => $this->getTable(),
            'where' => [
                'parentId' => $this->getId()
            ],
            'order' => [
                'lft ASC'
            ]
        ]);
        
        $result->setClassName(get_class($this));
        
        return $result;
    }


    /**
     *
     */
    public function getParent ( ): NestedSet {

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
     *
     */
    public function getSiblings ( )
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
     * Get trace to root node
     */
    public function getTrace ( ) {

        $result = $this->db->fetch([
            'table' => $this->getTable(),
            'where' => [
                new \Frootbox\Db\Conditions\LessOrEqual('lft', $this->getLft()),
                new \Frootbox\Db\Conditions\GreaterOrEqual('rgt', $this->getRgt()),
                'rootId' => $this->getRootId()
            ],
            'order' => [
                'lft ASC'
            ]
        ]);

        $result->setClassName(get_class($this));

        return $result;
    }


    /**
     *
     */
    public function isChildOf ( NestedSet $row ): bool
    {
        return ($this->getLft() > $row->getLft() and $this->getRgt() < $row->getRgt());
    }
}