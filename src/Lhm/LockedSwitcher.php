<?php


namespace Lhm;


use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table as PhinxTable;
use RuntimeException;


class LockedSwitcher extends Command
{
    /** @var AdapterInterface */
    protected $adapter;

    /** @var PhinxTable */
    protected $origin;

    /** @var Table */
    protected $destination;
    
    /**
     * @var SqlHelper
     */
    protected $sqlHelper;

    /**
     * @param AdapterInterface $adapter
     * @param PhinxTable       $origin
     * @param Table            $destination
     * @param array            $options Table options:
     *                                  - `retry_sleep_time`
     *                                  - `max_retries`
     *                                  - `archive_name`
     */
    public function __construct(
        AdapterInterface $adapter,
        PhinxTable $origin,
        Table $destination,
        array $options = []
    ) {
        $this->options = $options + [
                'retry_sleep_time' => 10,
                'max_retries'      => 600,
                'archive_name'     => 'lhma_' . gmdate('Y_m_d_H_i_s') . "_{$origin->getName()}",
            ];

        $this->adapter     = $adapter;
        $this->origin      = $origin;
        $this->destination = $destination;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @throws RuntimeException
     */
    protected function validate()
    {
        if ($this->adapter->hasTable($this->origin->getName()) && $this->adapter->hasTable(
                $this->destination->getName()
            )) {
            return;
        }

        throw new RuntimeException(
            "Table `{$this->origin->getName()}` and `{$this->destination->getName()}` must exist."
        );
    }

    /**
     * Revert the switch
     */
    protected function revert()
    {
        $this->adapter->query($this->getSqlHelper()->tagged('unlock tables'));
    }

    /**
     * @return SqlHelper
     */
    public function getSqlHelper()
    {
        if ( ! $this->sqlHelper) {
            $this->sqlHelper = new SqlHelper($this->adapter);
        }

        return $this->sqlHelper;
    }

    /**
     * @param SqlHelper $sqlHelper
     */
    public function setSqlHelper($sqlHelper)
    {
        $this->sqlHelper = $sqlHelper;
    }

    /**
     * Execute the switch
     */
    protected function execute()
    {
        $sqlHelper = $this->getSqlHelper();
        foreach ($this->statements() as $statement) {
            $this->adapter->query($sqlHelper->tagged($statement));
        }
    }

    /**
     * @return array
     */
    protected function statements()
    {
        return $this->uncommitted($this->switchStatements());
    }

    /**
     * @param array $switchStatements
     *
     * @return array
     */
    protected function uncommitted($switchStatements)
    {
        $statements = [
            'set @lhm_auto_commit = @@session.autocommit',
            'set session autocommit = 0',
        ];

        $statements = array_merge($statements, $switchStatements);

        $statements[] = 'set session autocommit = @lhm_auto_commit';
        return $statements;
    }

    /**
     * @return array
     */
    protected function switchStatements()
    {
        $originTableName      = $this->adapter->quoteTableName($this->origin->getName());
        $destinationTableName = $this->adapter->quoteTableName($this->destination->getName());

        $archiveName = $this->adapter->quoteTableName($this->options['archive_name']);

        return [
            "LOCK TABLE {$originTableName} write, {$destinationTableName} write",
            "ALTER TABLE  {$originTableName} rename {$archiveName}",
            "ALTER TABLE {$destinationTableName} rename {$originTableName}",
            'COMMIT',
            'UNLOCK TABLES',
        ];
    }


}
