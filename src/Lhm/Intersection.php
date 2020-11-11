<?php


namespace Lhm;


use Phinx\Db\Table as PhinxTable;

class Intersection
{

    /**
     * @var Table
     */
    protected $origin;
    /**
     * @var Table
     */
    protected $destination;

    /**
     * @var array
     */
    protected $renames;

    /**
     * @param PhinxTable $origin
     * @param Table      $destination
     */
    public function __construct(PhinxTable $origin, Table $destination)
    {
        $this->origin      = $origin;
        $this->destination = $destination;
    }

    /**
     * @return array
     */
    public function origin()
    {
        return array_merge($this->common(), array_keys($this->destination->getRenamedColumns()));
    }

    /**
     * @return array
     */
    public function common()
    {
        $origin = [];
        foreach ($this->origin->getColumns() as $column) {
            $origin[] = $column->getName();
        }

        $destination = [];
        foreach ($this->destination->getColumns() as $column) {
            $destination[] = $column->getName();
        }

        $intersection = array_intersect($origin, $destination);

        sort($intersection);

        return $intersection;
    }

    /**
     * @return array
     */
    public function destination()
    {
        return array_merge($this->common(), array_values($this->destination->getRenamedColumns()));
    }
}
