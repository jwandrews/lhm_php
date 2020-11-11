<?php


namespace Lhm;

use Phinx\Db\Table as PhinxTable;

/**
 * {@inheritdoc}
 */
class Table extends PhinxTable
{
    /**
     * @var array
     */
    protected $renames = [];

    /**
     * Return a list of columns that are being renamed.
     *
     * @return array
     */
    public function getRenamedColumns()
    {
        return $this->renames;
    }

    /**
     * {@inheritdoc}
     */
    public function renameColumn($oldName, $newName)
    {
        $this->renames[$oldName] = $newName;

        return parent::renameColumn($oldName, $newName);
    }

    /**
     * {@inheritdoc}
     */
    public function removeColumn($columnName)
    {
        if (isset($this->renames[$columnName])) {
            unset($this->renames[$columnName]);
        }

        return parent::removeColumn($columnName);
    }


}
