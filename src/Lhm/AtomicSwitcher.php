<?php

namespace Lhm;

use DateTime;
use DateTimeZone;
use Exception;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table as PhinxTable;
use RuntimeException;

/**
 * Switched the origin table with the destination using an atomic rename.
 */
class AtomicSwitcher extends Command
{

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var PhinxTable
     */
    protected $origin;

    /**
     * @var Table
     */
    protected $destination;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param AdapterInterface $adapter
     * @param PhinxTable       $origin
     * @param Table            $destination
     * @param array            $options Table options:
     *                                  - `retry_sleep_time`
     *                                  - `max_retries`
     *                                  - `archive_name`
     *
     * @throws Exception
     */
    public function __construct(
        AdapterInterface $adapter,
        PhinxTable $origin,
        Table $destination,
        array $options = []
    ) {
        // 0.234234234 234234234 -> [0.2323232 => 232322323]
        $micro = explode(' ', microtime());
        // 0.232323232 -> [0, 23232323232]
        $micro = explode('.', $micro[0]);
        $start = new DateTime('now', new DateTimeZone('UTC'));

        $this->options = $options + [
                'retry_sleep_time' => 10,
                'max_retries'      => 600,
                'archive_name'     => 'lhma_' . $start->format('Y_m_d_H_i_s') . '_' . sprintf(
                        '%03d',
                        $micro[1] / 1000
                    ) . "_{$origin->getName()}",
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
     * Set an option
     *
     * @param string $name
     * @param mixed  $value
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
    }

    /**
     * @return array
     */
    protected function statements()
    {
        $originTableName      = $this->adapter->quoteTableName($this->origin->getName());
        $destinationTableName = $this->adapter->quoteTableName($this->destination->getName());

        $archiveName = $this->adapter->quoteTableName($this->options['archive_name']);

        return [
            "RENAME TABLE {$originTableName} TO {$archiveName}, {$destinationTableName} TO {$originTableName}",
        ];
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
     * Execute the atomic rename.
     */
    protected function execute()
    {
        $retries = 0;

        while ($retries < $this->options['max_retries']) {
            $retries++;

            try {
                foreach ($this->statements() as $statement) {
                    $this->getLogger()->debug("Executing statement `{$statement}`");

                    $this->adapter->query($statement);
                }

                return;
            } catch (Exception $e) {
                if ($this->shouldRetryException($e)) {
                    $this->getLogger()->warning($e->getMessage());

                    sleep($this->options['retry_sleep_time']);

                    //TODO log the retry
                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * Determine if the operation should be retried.
     *
     * @param Exception $e
     *
     * @return false|int
     */
    protected function shouldRetryException(Exception $e)
    {
        return preg_match('/Lock wait timeout exceeded/', $e->getMessage());
    }
}
